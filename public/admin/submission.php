<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('submissions.view');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM submissions WHERE id = ?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    render_admin_page('Not found', '<div class="card"><h2>Not found</h2><p><a href="submissions.php">Back</a></p></div>', ['active' => 'submissions']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && can('submissions.update', $user)) {
    verify_csrf();
    $newStatus = isset(SUBMISSION_STATUSES[$_POST['status'] ?? '']) ? (string) $_POST['status'] : $s['status'];
    $notes = str_clean($_POST['notes'] ?? '', 3000);
    db()->prepare('UPDATE submissions SET status = ?, notes_enc = ?, updated_at = ? WHERE id = ?')
        ->execute([$newStatus, encrypt_field($notes), now_utc(), $id]);
    audit('submission_update', 'submission', $id, 'status ' . $newStatus);
    flash('ok', $s['reference'] . ' saved.');
    header('Location: submission.php?id=' . $id);
    exit;
}

audit('submission_view', 'submission', $id);
$schema = submission_types()[$s['type']] ?? null;
$data = json_decode(decrypt_field($s['payload_enc']) ?: '{}', true) ?: [];
$contact = json_decode(decrypt_field($s['contact_enc']) ?: '{}', true) ?: [];

ob_start(); ?>
<p><a href="submissions.php?type=<?= e($s['type']) ?>">← <?= e(submission_label($s['type'])) ?>s</a></p>
<div class="grid grid-3">
    <div class="card">
        <div class="card-head"><h2><?= e($s['reference']) ?> <span class="badge b-<?= e($s['status']) ?>"><?= e(SUBMISSION_STATUSES[$s['status']]) ?></span></h2><span class="muted"><?= e(local_time($s['created_at'])) ?></span></div>
        <dl class="kv">
            <dt>Form</dt><dd><?= e(submission_label($s['type'])) ?></dd>
            <?php foreach (($schema['fields'] ?? []) as $name => $rule):
                $v = $data[$name] ?? '';
                if ($v === '' || $v === [] || $v === 0 && ($rule['type'] ?? '') !== 'bool') continue;
                if (($rule['type'] ?? '') === 'bool') $v = $v ? 'Yes' : 'No';
                if (($rule['type'] ?? '') === 'phone') $v = format_phone((string) $v);
                if ($name === 'amount') $v = 'UGX ' . number_format((int) $v);
                if (is_array($v)) $v = implode(', ', $v); ?>
                <dt><?= e($rule['label']) ?></dt><dd style="white-space:pre-wrap"><?= e((string) $v) ?></dd>
            <?php endforeach; ?>
        </dl>
        <div class="contact-actions">
            <?php if (!empty($contact['phone'])): ?><a class="btn btn-dark btn-sm" href="tel:+<?= e($contact['phone']) ?>">Call</a><a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($contact['phone']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
            <?php if (!empty($contact['email'])): ?><a class="btn btn-ghost btn-sm" href="mailto:<?= e($contact['email']) ?>?subject=<?= e(rawurlencode('Imara — ' . $s['reference'])) ?>">Email</a><?php endif; ?>
        </div>
        <?php if ($s['type'] === 'donation'): ?>
            <p class="pii-warning">Pledge only — no money has been received through the website. Send the donor verified payment details and record when the funds arrive and are reconciled.</p>
        <?php endif; ?>
    </div>
    <form class="card" method="post">
        <?= csrf_field() ?>
        <h3>Follow up</h3>
        <div class="field"><label class="f" for="status">Status</label>
            <select id="status" name="status" <?= can('submissions.update', $user) ? '' : 'disabled' ?>>
                <?php foreach (SUBMISSION_STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label class="f" for="notes">Internal notes</label><textarea id="notes" name="notes" rows="8"><?= e(decrypt_field($s['notes_enc'])) ?></textarea></div>
        <?php if (can('submissions.update', $user)): ?><button class="btn" type="submit" style="width:100%">Save</button><?php endif; ?>
    </form>
</div>
<?php
render_admin_page($s['reference'], (string) ob_get_clean(), ['active' => 'submissions']);
