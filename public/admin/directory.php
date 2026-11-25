<?php
/** Manage the public "Find a Specialist" and "Find a Hospital" lists. */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('directory.manage');
$kind = ($_GET['kind'] ?? $_POST['kind'] ?? 'facility') === 'specialist' ? 'specialist' : 'facility';
$editId = (int) ($_GET['edit'] ?? 0);
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM directory WHERE id = ?')->execute([$id]);
        audit('directory_delete', 'directory', $id);
        flash('ok', 'Entry removed.');
        header('Location: directory.php?kind=' . $kind);
        exit;
    }
    $f = [
        'name' => str_clean($_POST['name'] ?? '', 150),
        'category' => str_clean($_POST['category'] ?? '', 120),
        'location' => str_clean($_POST['location'] ?? '', 120),
        'description' => str_clean($_POST['description'] ?? '', 600),
        'phone' => str_clean($_POST['phone'] ?? '', 40),
        'hours' => str_clean($_POST['hours'] ?? '', 120),
        'services' => str_clean($_POST['services'] ?? '', 400),
        'published' => !empty($_POST['published']) ? 1 : 0,
        'sort' => (int) ($_POST['sort'] ?? 100),
    ];
    if ($f['name'] === '') {
        $error = 'Please enter a name.';
    } else {
        if ($id) {
            db()->prepare('UPDATE directory SET name=?, category=?, location=?, description=?, phone=?, hours=?, services=?, published=?, sort=?, updated_at=? WHERE id=?')
                ->execute([...array_values($f), now_utc(), $id]);
            audit('directory_update', 'directory', $id, $f['name']);
        } else {
            db()->prepare('INSERT INTO directory (kind, name, category, location, description, phone, hours, services, published, sort, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$kind, ...array_values($f), now_utc(), now_utc()]);
            audit('directory_create', 'directory', (int) db()->lastInsertId(), $f['name']);
        }
        flash('ok', 'Saved. The public page updates straight away.');
        header('Location: directory.php?kind=' . $kind);
        exit;
    }
}

$st = db()->prepare('SELECT * FROM directory WHERE kind = ? ORDER BY sort, name');
$st->execute([$kind]);
$rows = $st->fetchAll();
$edit = ['id' => 0, 'name' => '', 'category' => '', 'location' => '', 'description' => '', 'phone' => '', 'hours' => '', 'services' => '', 'published' => 1, 'sort' => 100];
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
    }
}
$noun = $kind === 'specialist' ? 'specialist' : 'hospital or clinic';

ob_start(); ?>
<div class="tabs">
    <a href="directory.php?kind=facility" class="<?= $kind === 'facility' ? 'on' : '' ?>">Hospitals &amp; clinics</a>
    <a href="directory.php?kind=specialist" class="<?= $kind === 'specialist' ? 'on' : '' ?>">Specialists</a>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="grid grid-3">
    <div class="card">
        <h2><?= $kind === 'specialist' ? 'Specialists' : 'Hospitals & clinics' ?></h2>
        <p class="muted">Only add people and places that have agreed to be listed. Shown on the public <?= $kind === 'specialist' ? '“Find a Specialist”' : '“Find a Hospital”' ?> page.</p>
        <div class="table-wrap"><table class="data">
            <thead><tr><th>Name</th><th><?= $kind === 'specialist' ? 'Specialty' : 'Type' ?></th><th>Location</th><th>Public</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty">No entries yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong></td>
                    <td><?= e($r['category']) ?></td>
                    <td><?= e($r['location']) ?></td>
                    <td><?= (int) $r['published'] ? '<span class="badge b-done">Yes</span>' : '<span class="badge b-archived">Hidden</span>' ?></td>
                    <td style="white-space:nowrap">
                        <a class="btn btn-ghost btn-sm" href="directory.php?kind=<?= e($kind) ?>&edit=<?= (int) $r['id'] ?>">Edit</a>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" data-confirm="Remove <?= e($r['name']) ?>?">Remove</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <form class="card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
        <h3><?= $edit['id'] ? 'Edit ' . e($edit['name']) : 'Add a ' . $noun ?></h3>
        <div class="field"><label class="f" for="name">Name</label><input type="text" id="name" name="name" value="<?= e($edit['name']) ?>" required></div>
        <div class="field"><label class="f" for="category"><?= $kind === 'specialist' ? 'Specialty' : 'Type' ?></label><input type="text" id="category" name="category" value="<?= e($edit['category']) ?>" placeholder="<?= $kind === 'specialist' ? 'e.g. Paediatrician' : 'e.g. Public referral hospital' ?>"></div>
        <div class="field"><label class="f" for="location">Location</label><input type="text" id="location" name="location" value="<?= e($edit['location']) ?>" placeholder="e.g. Gulu City"></div>
        <div class="field"><label class="f" for="description">Short description</label><textarea id="description" name="description"><?= e($edit['description']) ?></textarea></div>
        <div class="field"><label class="f" for="services">Services / where they work</label><input type="text" id="services" name="services" value="<?= e($edit['services']) ?>"></div>
        <div class="row-fields">
            <div class="field"><label class="f" for="phone">Public phone <small>(optional)</small></label><input type="text" id="phone" name="phone" value="<?= e($edit['phone']) ?>"></div>
            <div class="field"><label class="f" for="hours">Days / hours</label><input type="text" id="hours" name="hours" value="<?= e($edit['hours']) ?>"></div>
        </div>
        <div class="row-fields">
            <div class="field"><label class="f" for="sort">Order</label><input type="number" id="sort" name="sort" value="<?= (int) $edit['sort'] ?>"></div>
            <div class="field" style="padding-top:28px"><label class="check"><input type="checkbox" name="published" value="1" <?= (int) $edit['published'] ? 'checked' : '' ?>> Show on website</label></div>
        </div>
        <button class="btn" type="submit" style="width:100%">Save</button>
        <?php if ($edit['id']): ?><p style="margin-top:10px"><a href="directory.php?kind=<?= e($kind) ?>">Cancel</a></p><?php endif; ?>
    </form>
</div>
<?php
render_admin_page('Directory', (string) ob_get_clean(), ['active' => 'directory']);
