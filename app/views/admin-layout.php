<?php
/** @var string $title @var string $content @var array|null $user @var array $opts */
$active = $opts['active'] ?? '';
$flash = take_flash();
$nav = [
    ['index.php', 'dashboard', 'Requests', 'requests.view', '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>'],
    ['submissions.php', 'submissions', 'Forms inbox', 'submissions.view', '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 12h-4a3 3 0 0 1-6 0H5V5h14v10z"/>'],
    ['reports.php', 'reports', 'Reports', 'reports.view', '<path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>'],
    ['directory.php', 'directory', 'Directory', 'directory.manage', '<path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>'],
    ['notifications.php', 'notifications', 'Notifications', 'notifications.view', '<path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6V11c0-3.1-1.6-5.6-4.5-6.3V4a1.5 1.5 0 0 0-3 0v.7C7.6 5.4 6 7.9 6 11v5l-2 2v1h16v-1l-2-2z"/>'],
    ['users.php', 'users', 'Staff users', 'users.manage', '<path d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13zm8 0c-.3 0-.6 0-1 .1 1.2.8 2 2 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5z"/>'],
    ['settings.php', 'settings', 'Settings & data', 'settings.manage', '<path d="M19.4 13a7.5 7.5 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 0 0-1.7-1L15 3h-4l-.4 2.9a7.4 7.4 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11a7.5 7.5 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1c.5.4 1.1.7 1.7 1L11 21h4l.4-2.9c.6-.3 1.2-.6 1.7-1l2.5 1 2-3.5-2.2-1.6zM13 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z"/>'],
    ['audit.php', 'audit', 'Audit log', 'audit.view', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — Imara Health Bridge Admin</title>
<link rel="icon" type="image/svg+xml" href="../assets/images/ui/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arapey&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) . '/assets/admin.css') ?>">
</head>
<body class="<?= $user ? 'has-sidebar' : 'auth-page' ?>">
<?php if ($user): ?>
<aside class="sidebar" id="sidebar">
    <a href="index.php" class="brand">
        <span class="brand-word">IMARA</span>
        <span class="brand-tag">Health Bridge &middot; Gulu</span>
    </a>
    <nav>
        <?php foreach ($nav as [$href, $key, $label, $perm, $icon]): if (!can($perm, $user)) continue; ?>
            <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <a href="../index.html" target="_blank" rel="noopener">View website ↗</a>
    </div>
</aside>
<div class="main">
    <header class="topbar">
        <button class="menu-toggle" id="menu-toggle" aria-label="Menu" aria-controls="sidebar">☰</button>
        <h1><?= e($title) ?></h1>
        <div class="who">
            <a href="account.php" class="who-name"><?= e($user['name']) ?> <small><?= e(ROLES[$user['role']] ?? $user['role']) ?></small></a>
            <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm">Sign out</button></form>
        </div>
    </header>
    <main class="content">
        <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="admin-foot">Imara Health Initiative AFRIKA &middot; Strong Communities. Strong Health. Strong Afrika. &middot; Confidential — authorised staff only.</footer>
</div>
<?php else: ?>
<main class="auth-wrap">
    <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<?php endif; ?>
<script src="assets/admin.js?v=<?= (int) @filemtime(dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) . '/assets/admin.js') ?>"></script>
</body>
</html>
