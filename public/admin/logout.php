<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

start_secure_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    logout();
    start_secure_session();
    flash('ok', 'You have signed out.');
}
header('Location: login.php');
