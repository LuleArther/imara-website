<?php
/** Weekly and monthly impact reports — anonymous counts only. */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('reports.view');
$tz = new DateTimeZone((string) config('timezone'));
$today = new DateTimeImmutable('today', $tz);

$presets = [
    'this_week'  => ['This week', $today->modify('monday this week'), $today],
    'last_week'  => ['Last week', $today->modify('monday last week'), $today->modify('sunday last week')],
    'this_month' => ['This month', $today->modify('first day of this month'), $today],
    'last_month' => ['Last month', $today->modify('first day of last month'), $today->modify('last day of last month')],
    'last_90'    => ['Last 90 days', $today->modify('-89 days'), $today],
    'all'        => ['All time', new DateTimeImmutable('2020-01-01', $tz), $today],
];
$preset = (string) ($_GET['period'] ?? 'this_month');
if (isset($_GET['from'], $_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to'])) {
    $preset = 'custom';
    $from = new DateTimeImmutable((string) $_GET['from'], $tz);
    $to = new DateTimeImmutable((string) $_GET['to'], $tz);
    $label = 'Custom range';
} else {
    $preset = isset($presets[$preset]) ? $preset : 'this_month';
    [$label, $from, $to] = $presets[$preset];
}
$fromUtc = $from->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
$toUtc = $to->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');

$pdo = db();
$one = function (string $sql) use ($pdo, $fromUtc, $toUtc) {
    $st = $pdo->prepare($sql);
    $st->execute([$fromUtc, $toUtc]);
    return $st;
};
$k = $one("SELECT COUNT(*) total, SUM(is_urgent) urgent, SUM(is_minor) children,
        SUM(status = 'linked' OR accessed_care = 'yes') linked,
        SUM(referral_date IS NOT NULL AND referral_date <> '') referred,
        SUM(status NOT IN ('linked','closed')) open_now
    FROM requests WHERE created_at BETWEEN ? AND ?")->fetch() ?: [];
$k = array_map('intval', $k);

$hours = $one("SELECT (julianday(first_contact_at) - julianday(created_at)) * 24 h FROM requests WHERE first_contact_at IS NOT NULL AND created_at BETWEEN ? AND ? ORDER BY h")->fetchAll(PDO::FETCH_COLUMN);
$median = $hours ? (float) $hours[intdiv(count($hours), 2)] : null;
$within24 = $hours ? round(100 * count(array_filter($hours, fn ($h) => $h <= 24)) / count($hours)) : null;

$group = fn (string $col) => $one("SELECT COALESCE(NULLIF($col, ''), 'Not answered') label, COUNT(*) n FROM requests WHERE created_at BETWEEN ? AND ? GROUP BY label ORDER BY n DESC")->fetchAll();
$byStatus = $one("SELECT status label, COUNT(*) n FROM requests WHERE created_at BETWEEN ? AND ? GROUP BY status ORDER BY n DESC")->fetchAll();
foreach ($byStatus as &$row) { $row['label'] = status_label($row['label']); } unset($row);
$sections = [
    'Status' => $byStatus,
    'Service requested' => $group('service'),
    'Division / district' => $group('location'),
    'How they heard about Imara' => $group('heard'),
    'Preferred way to get help' => $group('assistance'),
    'Receiving institution' => $one("SELECT receiving_institution label, COUNT(*) n FROM requests WHERE receiving_institution IS NOT NULL AND receiving_institution <> '' AND created_at BETWEEN ? AND ? GROUP BY label ORDER BY n DESC")->fetchAll(),
];
$subs = $one("SELECT type, COUNT(*) n, SUM(amount) amt FROM submissions WHERE created_at BETWEEN ? AND ? GROUP BY type")->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
    audit('report_export', null, null, $label);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="imara-report-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Imara Health Bridge Gulu — impact report', $from->format('j M Y') . ' to ' . $to->format('j M Y')]);
    fputcsv($out, []);
    foreach (['Requests received' => $k['total'] ?? 0, 'Urgent' => $k['urgent'] ?? 0, 'Children' => $k['children'] ?? 0, 'Referred' => $k['referred'] ?? 0, 'Linked to care (confirmed)' => $k['linked'] ?? 0, 'Still open' => $k['open_now'] ?? 0, 'Median hours to first contact' => $median !== null ? round($median, 1) : '', '% contacted within 24h' => $within24 ?? ''] as $l => $v) {
        fputcsv($out, [$l, $v]);
    }
    foreach ($sections as $title => $rows) {
        fputcsv($out, []);
        fputcsv($out, [$title, 'Requests']);
        foreach ($rows as $r) { fputcsv($out, [$r['label'], $r['n']]); }
    }
    fputcsv($out, []);
    fputcsv($out, ['Other forms', 'Count', 'Pledged (UGX)']);
    foreach ($subs as $s) { fputcsv($out, [submission_label($s['type']), $s['n'], $s['type'] === 'donation' ? $s['amt'] : '']); }
    exit;
}

$bars = function (array $rows): string {
    if (!$rows) return '<p class="muted">No data for this period.</p>';
    $max = max(array_column($rows, 'n')) ?: 1;
    $h = '<ul class="bars">';
    foreach ($rows as $r) {
        $h .= '<li><span>' . e($r['label']) . '</span><span class="bar"><span style="width:' . round(100 * $r['n'] / $max) . '%"></span></span><span class="n">' . (int) $r['n'] . '</span></li>';
    }
    return $h . '</ul>';
};
$rate = ($k['total'] ?? 0) ? round(100 * ($k['linked'] ?? 0) / $k['total']) : 0;

ob_start(); ?>
<div class="card no-print">
    <div class="tabs" style="margin-bottom:12px">
        <?php foreach ($presets as $key => [$l]): ?><a href="reports.php?period=<?= e($key) ?>" class="<?= $preset === $key ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
    </div>
    <form class="toolbar" method="get" style="margin:0">
        <div class="field"><label class="f" for="from">From</label><input type="date" id="from" name="from" value="<?= e($from->format('Y-m-d')) ?>"></div>
        <div class="field"><label class="f" for="to">To</label><input type="date" id="to" name="to" value="<?= e($to->format('Y-m-d')) ?>"></div>
        <button class="btn btn-ghost" type="submit">Show</button>
        <span style="flex:1"></span>
        <a class="btn btn-ghost" href="reports.php?<?= e(http_build_query(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'format' => 'csv'])) ?>">Download CSV</a>
        <button class="btn btn-dark" type="button" data-print>Print / PDF</button>
    </form>
</div>

<div class="card">
    <h2>Impact report · <?= e($label) ?></h2>
    <p class="muted"><?= e($from->format('j M Y')) ?> – <?= e($to->format('j M Y')) ?> · Imara Health Bridge Gulu · Anonymous figures</p>
    <div class="kpis">
        <div class="kpi"><div class="v"><?= (int) ($k['total'] ?? 0) ?></div><div class="l">Requests received</div></div>
        <div class="kpi"><div class="v"><?= (int) ($k['referred'] ?? 0) ?></div><div class="l">Referred</div></div>
        <div class="kpi"><div class="v"><?= (int) ($k['linked'] ?? 0) ?> <small style="font-size:1rem;color:#1E7B4C"><?= $rate ?>%</small></div><div class="l">Linked to care</div></div>
        <div class="kpi"><div class="v"><?= (int) ($k['urgent'] ?? 0) ?></div><div class="l">Marked urgent</div></div>
        <div class="kpi"><div class="v"><?= $median !== null ? e(number_format($median, 1)) . 'h' : '—' ?></div><div class="l">Median time to first contact<?= $within24 !== null ? ' · ' . $within24 . '% ≤ 24h' : '' ?></div></div>
    </div>
    <div class="grid grid-2">
        <?php foreach ($sections as $title => $rows): ?>
            <div><h3><?= e($title) ?></h3><?= $bars($rows) ?></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3>Other forms in this period</h3>
    <?php if (!$subs): ?><p class="muted">None.</p><?php else: ?>
    <table class="data"><thead><tr><th>Form</th><th>Received</th><th>Pledged (UGX)</th></tr></thead><tbody>
        <?php foreach ($subs as $s): ?><tr><td><?= e(submission_label($s['type'])) ?></td><td><?= (int) $s['n'] ?></td><td><?= $s['type'] === 'donation' ? number_format((int) $s['amt']) : '' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <p class="pii-warning">Donation figures are pledges made on the website, not money received. Reconcile against mobile money and bank statements before reporting income.</p>
    <?php endif; ?>
</div>
<?php
render_admin_page('Reports', (string) ob_get_clean(), ['active' => 'reports']);
