<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();
$first = isset($_GET['first']) || (int) $user['must_change_password'] === 1;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    if (!password_verify($current, (string) $st->fetchColumn())) {
        $error = 'Your current password is not correct.';
    } elseif ($new !== $confirm) {
        $error = 'The new passwords do not match.';
    } elseif ($p = password_problem($new)) {
        $error = $p;
    } elseif ($new === $current) {
        $error = 'Choose a password different from the current one.';
    } else {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        audit('password_change', 'user', (int) $user['id']);
        flash('ok', 'Your password has been changed.');
        header('Location: index.php');
        exit;
    }
}

ob_start(); ?>
<div class="card" style="max-width:560px">
    <h2>My account</h2>
    <?php if ($first): ?><div class="alert alert-warn">Please choose your own password before you continue.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <dl class="kv" style="margin-bottom:20px">
        <dt>Name</dt><dd><?= e($user['name']) ?></dd>
        <dt>Email</dt><dd><?= e($user['email']) ?></dd>
        <dt>Role</dt><dd><?= e(ROLES[$user['role']] ?? $user['role']) ?></dd>
    </dl>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label class="f" for="current">Current password</label><input type="password" id="current" name="current" autocomplete="current-password" required></div>
        <div class="field"><label class="f" for="new">New password</label><input type="password" id="new" name="new" autocomplete="new-password" required minlength="10"><p class="help">At least 10 characters, with letters and a number.</p></div>
        <div class="field"><label class="f" for="confirm">Repeat new password</label><input type="password" id="confirm" name="confirm" autocomplete="new-password" required></div>
        <button class="btn" type="submit">Change password</button>
    </form>
</div>
<?php
render_admin_page('My account', (string) ob_get_clean());
