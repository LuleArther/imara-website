<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('audit.view');
$entity = (string) ($_GET['entity'] ?? '');
$entityId = (int) ($_GET['id'] ?? 0);
$where = [];
$args = [];
if (in_array($entity, ['request', 'submission', 'user', 'directory'], true)) {
    $where[] = 'entity = ?';
    $args[] = $entity;
    if ($entityId) {
        $where[] = 'entity_id = ?';
        $args[] = $entityId;
    }
}
$st = db()->prepare('SELECT * FROM audit_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 500');
$st->execute($args);
$rows = $st->fetchAll();
$refs = db()->query('SELECT id, reference FROM requests')->fetchAll(PDO::FETCH_KEY_PAIR);

ob_start(); ?>
<div class="card">
    <div class="card-head"><h2>Audit log</h2><span class="muted">Who viewed or changed what (latest 500)</span></div>
    <div class="tabs">
        <a href="audit.php" class="<?= $entity === '' ? 'on' : '' ?>">Everything</a>
        <a href="audit.php?entity=request" class="<?= $entity === 'request' ? 'on' : '' ?>">Patient requests</a>
        <a href="audit.php?entity=submission" class="<?= $entity === 'submission' ? 'on' : '' ?>">Forms</a>
        <a href="audit.php?entity=user" class="<?= $entity === 'user' ? 'on' : '' ?>">Sign-ins &amp; users</a>
    </div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="muted" style="white-space:nowrap"><?= e(local_time($r['created_at'], 'j M Y, H:i:s')) ?></td>
                <td><?= e($r['user_name'] ?? '—') ?></td>
                <td><?= e(str_replace('_', ' ', $r['action'])) ?></td>
                <td><?php if ($r['entity'] === 'request' && $r['entity_id']): ?><a href="request.php?id=<?= (int) $r['entity_id'] ?>"><?= e($refs[$r['entity_id']] ?? '#' . $r['entity_id']) ?></a> · <a class="muted" href="audit.php?entity=request&id=<?= (int) $r['entity_id'] ?>">history</a><?php elseif ($r['entity']): ?><?= e($r['entity']) ?> #<?= (int) $r['entity_id'] ?><?php endif; ?></td>
                <td class="muted"><?= e($r['details']) ?></td>
                <td class="muted"><?= e($r['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php
render_admin_page('Audit log', (string) ob_get_clean(), ['active' => 'audit']);
