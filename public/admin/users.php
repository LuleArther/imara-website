<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('users.manage');
$error = null;
$temp = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $name = str_clean($_POST['name'] ?? '', 120);
        $email = str_clean($_POST['email'] ?? '', 160);
        $role = (string) ($_POST['role'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(ROLES[$role])) {
            $error = 'Enter a name, a valid email and a role.';
        } else {
            $temp = 'Imara-' . substr(strtr(base64_encode(random_bytes(9)), '+/', 'xy'), 0, 10) . random_int(10, 99);
            try {
                $newId = create_user($name, $email, $temp, $role, true);
                audit('user_create', 'user', $newId, $role);
            } catch (PDOException) {
                $error = 'A user with that email already exists.';
                $temp = null;
            }
        }
    } elseif ($id && $id !== (int) $user['id']) {
        if ($action === 'role' && isset(ROLES[$_POST['role'] ?? ''])) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$_POST['role'], $id]);
            audit('user_role', 'user', $id, (string) $_POST['role']);
            flash('ok', 'Role updated.');
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$id]);
            audit('user_toggle', 'user', $id);
            flash('ok', 'Account updated.');
        } elseif ($action === 'reset') {
            $temp = 'Imara-' . substr(strtr(base64_encode(random_bytes(9)), '+/', 'xy'), 0, 10) . random_int(10, 99);
            db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
            audit('user_reset_password', 'user', $id);
        }
        if (!$temp) {
            header('Location: users.php');
            exit;
        }
    } elseif ($id === (int) $user['id']) {
        $error = 'You cannot change your own role or switch off your own account here.';
    }
}

$rows = db()->query('SELECT * FROM users ORDER BY active DESC, name')->fetchAll();

ob_start(); ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($temp): ?>
    <div class="alert alert-warn"><strong>Temporary password: <code style="font-size:1.1rem"><?= e($temp) ?></code></strong><br>Give it to the person privately (in person or by phone — not by WhatsApp group or email). They must change it when they first sign in. It will not be shown again.</div>
<?php endif; ?>
<div class="grid grid-3">
    <div class="card">
        <h2>Staff users</h2>
        <div class="table-wrap"><table class="data">
            <thead><tr><th>Name</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $self = (int) $r['id'] === (int) $user['id']; ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong><div class="muted"><?= e($r['email']) ?></div></td>
                    <td>
                        <?php if ($self): ?><?= e(ROLES[$r['role']]) ?> (you)<?php else: ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="role">
                            <select name="role" data-autosubmit><?php foreach (ROLES as $k => $l): ?><option value="<?= e($k) ?>" <?= $r['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                        </form><?php endif; ?>
                    </td>
                    <td class="muted"><?= e(local_time($r['last_login_at'], 'j M Y, H:i')) ?></td>
                    <td><?= (int) $r['active'] ? '<span class="badge b-done">Active</span>' : '<span class="badge b-archived">Off</span>' ?></td>
                    <td style="white-space:nowrap"><?php if (!$self): ?>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-ghost btn-sm" data-confirm="Create a new temporary password for <?= e($r['name']) ?>?">Reset password</button></form>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-danger btn-sm"><?= (int) $r['active'] ? 'Switch off' : 'Switch on' ?></button></form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <h3 style="margin-top:24px">What each role can do</h3>
        <dl class="kv">
            <dt>Administrator</dt><dd>Everything, including staff accounts, settings, backups, deleting personal data and the audit log.</dd>
            <dt>Patient coordinator</dt><dd>Handles patient requests, the forms inbox, the directory, exports and reports.</dd>
            <dt>Health professional</dt><dd>Reviews and updates patient requests, approves referrals, sees reports.</dd>
            <dt>Viewer</dt><dd>Sees anonymous reports only — no patient names or phone numbers.</dd>
        </dl>
    </div>
    <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <h3>Add a staff member</h3>
        <div class="field"><label class="f" for="name">Full name</label><input type="text" id="name" name="name" required></div>
        <div class="field"><label class="f" for="email">Email</label><input type="email" id="email" name="email" required></div>
        <div class="field"><label class="f" for="role">Role</label><select id="role" name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'coordinator' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <button class="btn" type="submit" style="width:100%">Create account</button>
        <p class="help" style="font-size:.82rem;color:#777;margin-top:10px">A temporary password is shown once. Give only the access each person needs.</p>
    </form>
</div>
<?php
render_admin_page('Staff users', (string) ob_get_clean(), ['active' => 'users']);
