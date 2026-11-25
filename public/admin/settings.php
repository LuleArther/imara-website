<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('settings.manage');
$impactKeys = [
    'impact_patients' => 'Patients reached (screened & connected to care)',
    'impact_professionals' => 'Healthcare professionals',
    'impact_institutions' => 'Institutions connected',
    'impact_policy' => 'Policy briefs & podcast episodes',
    'impact_youth' => 'Young people engaged',
];
$months = (int) setting('retention_months', (string) config('retention_months_default', 24));
$cutoff = gmdate('Y-m-d\TH:i:s\Z', strtotime("-$months months"));
$dueSt = db()->prepare("SELECT COUNT(*) FROM requests WHERE anonymised_at IS NULL AND status IN ('closed','linked') AND updated_at < ?");
$dueSt->execute([$cutoff]);
$due = (int) $dueSt->fetchColumn();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'impact') {
        foreach ($impactKeys as $k => $label) {
            set_setting($k, (string) max(0, (int) ($_POST[$k] ?? 0)));
        }
        set_setting('impact_note', str_clean($_POST['impact_note'] ?? '', 300));
        audit('settings_impact');
        flash('ok', 'Impact figures saved. The public Impact page now shows them.');
    } elseif ($action === 'retention') {
        $m = max(6, min(120, (int) ($_POST['retention_months'] ?? 24)));
        set_setting('retention_months', (string) $m);
        audit('settings_retention', null, null, "$m months");
        flash('ok', "Retention period set to $m months.");
    } elseif ($action === 'run_retention') {
        $st = db()->prepare("SELECT id FROM requests WHERE anonymised_at IS NULL AND status IN ('closed','linked') AND updated_at < ?");
        $st->execute([$cutoff]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            anonymise_request((int) $id, "retention policy ($months months)");
        }
        flash('ok', count($ids) . ' request(s) anonymised.');
    } elseif ($action === 'backup') {
        $tmp = rtrim((string) config('backup_dir'), '/\\') . '/download-' . bin2hex(random_bytes(6)) . '.sqlite';
        db()->exec('VACUUM INTO ' . db()->quote($tmp));
        audit('backup_download');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="imara-backup-' . date('Y-m-d-His') . '.sqlite"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    } elseif ($action === 'test_email') {
        $to = (string) ($user['email']);
        [$ok, $err] = send_email($to, 'Imara test email', "This is a test from the Imara Health Bridge dashboard.\nIf you can read this, email alerts are working.");
        log_notification('email', $to, 'Test email', $ok, $err, 'test', 0);
        flash($ok ? 'ok' : 'error', $ok ? 'Test email sent to ' . $to . (config('mail_transport') === 'log' ? ' (written to data/logs/mail.log because email is in test mode)' : '') : 'Test email failed: ' . $err);
    }
    header('Location: settings.php');
    exit;
}

$waOk = whatsapp_enabled();
ob_start(); ?>
<div class="grid grid-2">
    <form class="card" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="impact">
        <h2>Public impact figures</h2>
        <p class="muted">Verified numbers shown on the website's Impact page and home page. Only enter figures you can back up with camp records.</p>
        <?php foreach ($impactKeys as $k => $label): ?>
            <div class="field"><label class="f" for="<?= e($k) ?>"><?= e($label) ?></label><input type="number" min="0" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e(setting($k, '0')) ?>"></div>
        <?php endforeach; ?>
        <div class="field"><label class="f" for="impact_note">Note shown under the figures</label><input type="text" id="impact_note" name="impact_note" value="<?= e(setting('impact_note', '')) ?>"></div>
        <button class="btn" type="submit">Save figures</button>
    </form>

    <div>
        <div class="card">
            <h2>Alerts &amp; integrations</h2>
            <dl class="kv">
                <dt>Email delivery</dt><dd><?= e(config('mail_transport')) ?><?= config('mail_transport') === 'log' ? ' <span class="badge b-skipped">test mode</span>' : ' <span class="badge b-done">live</span>' ?></dd>
                <dt>Alerts go to</dt><dd><?= e(implode(', ', (array) config('notify_emails'))) ?></dd>
                <dt>WhatsApp Business</dt><dd><?= $waOk ? '<span class="badge b-done">connected</span>' : '<span class="badge b-skipped">not connected</span>' ?> <span class="muted"><?= e((string) count((array) config('whatsapp.admin_numbers'))) ?> admin number(s)</span></dd>
            </dl>
            <p class="muted" style="font-size:.85rem;margin-top:12px">These are set in <code>app/config.local.php</code> on the server (see docs/HANDOVER.md). Patient alerts never include health details.</p>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_email"><button class="btn btn-ghost btn-sm" type="submit">Send me a test email</button></form>
        </div>

        <div class="card">
            <h2>Data retention</h2>
            <p class="muted">Personal details are removed from requests that were closed or linked longer ago than this. Anonymous numbers are kept for reports.</p>
            <form method="post" class="toolbar"><?= csrf_field() ?><input type="hidden" name="action" value="retention">
                <div class="field"><label class="f" for="retention_months">Keep personal details for (months)</label><input type="number" min="6" max="120" id="retention_months" name="retention_months" value="<?= $months ?>"></div>
                <button class="btn btn-ghost" type="submit">Save</button>
            </form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_retention">
                <p><strong><?= $due ?></strong> request(s) are older than <?= $months ?> months and due for anonymisation.</p>
                <button class="btn btn-danger" type="submit" <?= $due ? '' : 'disabled' ?> data-confirm="Remove personal details from <?= $due ?> old request(s)? This cannot be undone.">Anonymise now</button>
            </form>
        </div>

        <div class="card">
            <h2>Backup</h2>
            <p class="muted">Download a full copy of the database. Names, phone numbers and health notes inside stay encrypted. Store it somewhere safe and private; a daily automatic backup can also be scheduled (docs/HANDOVER.md).</p>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-dark" type="submit">Download backup</button></form>
        </div>
    </div>
</div>
<?php
render_admin_page('Settings & data', (string) ob_get_clean(), ['active' => 'settings']);
