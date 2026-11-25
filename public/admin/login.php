<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

start_secure_session();
admin_headers();

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
// only allow redirects to pages inside /admin/ (no open redirects)
if (!preg_match('/^[a-z\-]+\.php(\?[A-Za-z0-9=&%_\-.]*)?$/', $next)) {
    $next = 'index.php';
}

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $email = str_clean($_POST['email'] ?? '', 160);
    $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if (is_array($result)) {
        header('Location: ' . $next);
        exit;
    }
    $error = $result;
}

ob_start(); ?>
<div class="auth-card">
    <div class="brand"><span class="brand-word">IMARA</span><span class="brand-tag">Health Bridge &middot; Gulu</span></div>
    <form method="post" action="login.php" autocomplete="on">
        <h1>Staff sign in</h1>
        <p class="lead">Authorised Imara team members only.</p>
        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label class="f" for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label class="f" for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn" style="width:100%">Sign in</button>
    </form>
</div>
<p class="auth-note">Patient information is confidential. Every view and change is recorded in the audit log.</p>
<?php
render_admin_page('Sign in', (string) ob_get_clean());
