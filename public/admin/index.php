<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();
if (!can('requests.view', $user)) {
    header('Location: reports.php');
    exit;
}

$filters = [
    'q' => str_clean($_GET['q'] ?? '', 80),
    'status' => (string) ($_GET['status'] ?? ''),
    'urgent' => !empty($_GET['urgent']),
    'service' => str_clean($_GET['service'] ?? '', 80),
    'mine' => !empty($_GET['mine']),
    'user_id' => $user['id'],
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 25;
[$rows, $total] = search_requests($filters, $per, ($page - 1) * $per);
if ($filters['q'] !== '') {
    audit('requests_search', 'request', null, 'searched (' . $total . ' results)');
}

$counts = request_counts();
$statusCounts = db()->query('SELECT status, COUNT(*) n FROM requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$urgentOpen = db()->query("SELECT id, reference, created_at FROM requests WHERE is_urgent = 1 AND status IN ('new','under_review') ORDER BY id ASC LIMIT 10")->fetchAll();

$qs = function (array $change) use ($filters): string {
    $p = array_filter(array_merge(['q' => $filters['q'], 'status' => $filters['status'], 'urgent' => $filters['urgent'] ? 1 : null, 'service' => $filters['service'], 'mine' => $filters['mine'] ? 1 : null], $change), fn ($v) => $v !== null && $v !== '' && $v !== false);
    return 'index.php' . ($p ? '?' . http_build_query($p) : '');
};

ob_start(); ?>
<p style="margin:-6px 0 18px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:.8rem;color:#DD5903">Imara Health Bridge — Gulu</p>

<?php if ($urgentOpen): ?>
<div class="urgent-banner" role="alert">
    <span style="font-size:1.6rem">⚠</span>
    <div>
        <strong><?= count($urgentOpen) ?> urgent request<?= count($urgentOpen) > 1 ? 's' : '' ?> waiting for a human.</strong>
        Call these patients first. If symptoms are severe, tell them to go to the nearest health facility now.
        <ul>
            <?php foreach ($urgentOpen as $u): ?>
                <li><a href="request.php?id=<?= (int) $u['id'] ?>"><strong><?= e($u['reference']) ?></strong></a> — received <?= e(local_time($u['created_at'])) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="stat-cards">
    <a class="stat primary" href="index.php"><div class="label">Total patients reached</div><div class="value"><?= number_format($counts['total'] ?? 0) ?></div><div class="sub">All requests received online</div></a>
    <a class="stat" href="<?= e($qs(['status' => 'new', 'page' => null])) ?>"><div class="label">New requests</div><div class="value"><?= number_format($counts['new_count'] ?? 0) ?></div><div class="sub">Not yet reviewed</div></a>
    <a class="stat" href="<?= e($qs(['status' => 'linked', 'page' => null])) ?>"><div class="label">Successfully linked</div><div class="value"><?= number_format($counts['linked'] ?? 0) ?></div><div class="sub">Patient confirmed they got care</div></a>
    <a class="stat" href="<?= e($qs(['status' => 'open', 'page' => null])) ?>"><div class="label">Pending requests</div><div class="value"><?= number_format($counts['pending'] ?? 0) ?></div><div class="sub">Still being handled</div></a>
</div>

<div class="card">
    <div class="card-head">
        <h2>Request tracking</h2>
        <?php if (can('requests.export', $user)): ?>
            <a class="btn btn-ghost btn-sm" href="export.php?<?= e(http_build_query(['status' => $filters['status'], 'urgent' => $filters['urgent'] ? 1 : '', 'service' => $filters['service']])) ?>">Export CSV</a>
        <?php endif; ?>
    </div>

    <form class="toolbar" method="get" action="index.php" role="search">
        <div class="field grow">
            <label class="f" for="q">Search by reference, name or phone</label>
            <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. IMARA-0012, Akello, 0772123456">
        </div>
        <div class="field">
            <label class="f" for="service">Service</label>
            <select id="service" name="service" data-autosubmit>
                <option value="">All services</option>
                <?php foreach (option_values('services') as $s): ?><option <?= $filters['service'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <label class="check" style="margin-bottom:10px"><input type="checkbox" name="urgent" value="1" <?= $filters['urgent'] ? 'checked' : '' ?> data-autosubmit> Urgent only</label>
        <label class="check" style="margin-bottom:10px"><input type="checkbox" name="mine" value="1" <?= $filters['mine'] ? 'checked' : '' ?> data-autosubmit> Assigned to me</label>
        <button class="btn" type="submit">Search</button>
        <?php if ($filters['q'] || $filters['status'] || $filters['urgent'] || $filters['service'] || $filters['mine']): ?><a class="btn btn-ghost" href="index.php">Clear</a><?php endif; ?>
    </form>

    <div class="tabs" aria-label="Filter by status">
        <a href="<?= e($qs(['status' => null, 'page' => null])) ?>" class="<?= $filters['status'] === '' ? 'on' : '' ?>">All<span class="n"><?= (int) ($counts['total'] ?? 0) ?></span></a>
        <a href="<?= e($qs(['status' => 'open', 'page' => null])) ?>" class="<?= $filters['status'] === 'open' ? 'on' : '' ?>">Open<span class="n"><?= (int) ($counts['pending'] ?? 0) ?></span></a>
        <?php foreach (REQUEST_STATUSES as $k => $label): ?>
            <a href="<?= e($qs(['status' => $k, 'page' => null])) ?>" class="<?= $filters['status'] === $k ? 'on' : '' ?>"><?= e($label) ?><span class="n"><?= (int) ($statusCounts[$k] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Received</th><th>Patient</th><th>Phone</th><th>Service</th><th>Location</th><th>Urgency</th><th>Status</th><th>Assigned</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="empty">No requests found<?= $filters['q'] ? ' for “' . e($filters['q']) . '”' : '' ?>.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $name = $r['anonymised_at'] ? '(anonymised)' : decrypt_field($r['name_enc']);
                $phone = $r['anonymised_at'] ? '' : decrypt_field($r['phone_enc']); ?>
                <tr class="<?= (int) $r['is_urgent'] === 1 && in_array($r['status'], ['new', 'under_review'], true) ? 'is-urgent' : '' ?>">
                    <td><a class="ref" href="request.php?id=<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td>
                    <td class="muted"><?= e(local_time($r['created_at'], 'j M, H:i')) ?></td>
                    <td><?= e($name) ?><div class="muted"><?= (int) $r['age'] ?> yrs<?= (int) $r['is_minor'] === 1 ? ' · child' : '' ?></div></td>
                    <td><?= $phone ? e(format_phone($phone)) : '' ?></td>
                    <td><?= e($r['service'] === 'Other' && $r['service_other'] ? 'Other: ' . $r['service_other'] : $r['service']) ?></td>
                    <td><?= e($r['location'] === 'Other' && $r['location_other'] ? $r['location_other'] : $r['location']) ?></td>
                    <td><?php if ((int) $r['is_urgent'] === 1): ?><span class="badge b-urgent">Urgent</span><?php else: ?><span class="muted"><?= e($r['urgency']) ?></span><?php endif; ?></td>
                    <td><span class="badge b-<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td>
                    <td class="muted"><?= e($r['assigned_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
        <div class="pager">
            <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($qs(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?> (<?= $total ?> requests)</span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($qs(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_admin_page('Patient requests', (string) ob_get_clean(), ['active' => 'dashboard']);
