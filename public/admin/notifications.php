<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('notifications.view');
$rows = db()->query('SELECT n.*, r.reference AS req_ref, s.reference AS sub_ref FROM notifications n
    LEFT JOIN requests r ON n.entity = \'request\' AND r.id = n.entity_id
    LEFT JOIN submissions s ON n.entity = \'submission\' AND s.id = n.entity_id
    ORDER BY n.id DESC LIMIT 300')->fetchAll();
$failed = db()->query("SELECT COUNT(*) FROM notifications WHERE status = 'failed' AND created_at > strftime('%Y-%m-%dT%H:%M:%SZ', 'now', '-7 days')")->fetchColumn();

ob_start(); ?>
<?php if ((int) $failed > 0): ?><div class="alert alert-error"><?= (int) $failed ?> alert(s) failed in the last 7 days. Check the email/WhatsApp settings, and make sure no request was missed.</div><?php endif; ?>
<?php if (config('mail_transport') === 'log'): ?><div class="alert alert-info">Email is in <strong>test mode</strong>: messages are written to <code>data/logs/mail.log</code> instead of being sent. Switch to SMTP or mail() in config.local.php before launch.</div><?php endif; ?>
<?php if (!whatsapp_enabled()): ?><div class="alert alert-info">WhatsApp Business is <strong>not connected</strong> yet, so WhatsApp alerts are recorded as “skipped”. See docs/WHATSAPP-TEMPLATES.md.</div><?php endif; ?>
<div class="card">
    <div class="card-head"><h2>Notifications</h2><span class="muted">Email and WhatsApp alerts (latest 300)</span></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>When</th><th>Channel</th><th>To</th><th>Subject</th><th>Record</th><th>Result</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No alerts yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $n): ?>
            <tr>
                <td class="muted" style="white-space:nowrap"><?= e(local_time($n['created_at'], 'j M, H:i')) ?></td>
                <td><?= e(ucfirst($n['channel'])) ?></td>
                <td><?= e($n['recipient']) ?></td>
                <td><?= e($n['subject']) ?></td>
                <td><?php if ($n['req_ref']): ?><a href="request.php?id=<?= (int) $n['entity_id'] ?>"><?= e($n['req_ref']) ?></a><?php elseif ($n['sub_ref']): ?><a href="submission.php?id=<?= (int) $n['entity_id'] ?>"><?= e($n['sub_ref']) ?></a><?php endif; ?></td>
                <td><span class="badge b-<?= e($n['status']) ?>"><?= e($n['status']) ?></span><?= $n['error'] ? '<div class="muted">' . e($n['error']) . '</div>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php
render_admin_page('Notifications', (string) ob_get_clean(), ['active' => 'notifications']);
