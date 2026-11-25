<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('submissions.view');
$types = submission_types();
$type = isset($types[$_GET['type'] ?? '']) ? (string) $_GET['type'] : '';
$status = isset(SUBMISSION_STATUSES[$_GET['status'] ?? '']) ? (string) $_GET['status'] : '';

$where = [];
$args = [];
if ($type) { $where[] = 'type = ?'; $args[] = $type; }
if ($status) { $where[] = 'status = ?'; $args[] = $status; } else { $where[] = "status <> 'archived'"; }
$st = db()->prepare('SELECT * FROM submissions' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300');
$st->execute($args);
$rows = $st->fetchAll();
$counts = db()->query("SELECT type, COUNT(*) FROM submissions WHERE status = 'new' GROUP BY type")->fetchAll(PDO::FETCH_KEY_PAIR);

$link = fn (array $c) => 'submissions.php?' . http_build_query(array_filter(array_merge(['type' => $type, 'status' => $status], $c)));

ob_start(); ?>
<div class="card">
    <div class="card-head"><h2>Forms inbox</h2><span class="muted">Volunteers, partners, medical camp requests, messages and donation pledges</span></div>
    <div class="tabs">
        <a href="<?= e($link(['type' => null])) ?>" class="<?= $type === '' ? 'on' : '' ?>">All<span class="n"><?= array_sum($counts) ?: '' ?></span></a>
        <?php foreach ($types as $k => $t): ?>
            <a href="<?= e($link(['type' => $k])) ?>" class="<?= $type === $k ? 'on' : '' ?>"><?= e($t['label']) ?><span class="n"><?= (int) ($counts[$k] ?? 0) ?: '' ?></span></a>
        <?php endforeach; ?>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="field"><label class="f" for="status">Status</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">Open (not archived)</option>
                <?php foreach (SUBMISSION_STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Received</th><th>Type</th><th>From</th><th>Summary</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">Nothing here yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a class="ref" href="submission.php?id=<?= (int) $r['id'] ?>"><?= e($r['reference']) ?></a></td>
                    <td class="muted"><?= e(local_time($r['created_at'], 'j M, H:i')) ?></td>
                    <td><?= e(submission_label($r['type'])) ?></td>
                    <td><?= e(decrypt_field($r['name_enc'])) ?></td>
                    <td><?= e($r['summary']) ?></td>
                    <td><span class="badge b-<?= e($r['status']) ?>"><?= e(SUBMISSION_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_admin_page('Forms inbox', (string) ob_get_clean(), ['active' => 'submissions']);
