<?php
/**
 * GET /api/directory.php?kind=specialist|facility — published directory entries (managed in Admin › Directory).
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('Cache-Control: public, max-age=300');
$kind = ($_GET['kind'] ?? '') === 'specialist' ? 'specialist' : 'facility';

try {
    $st = db()->prepare('SELECT id, name, category, location, description, phone, hours, services FROM directory WHERE kind = ? AND published = 1 ORDER BY sort, name');
    $st->execute([$kind]);
    json_response(['ok' => true, 'kind' => $kind, 'items' => $st->fetchAll()]);
} catch (Throwable $ex) {
    error_log('Imara directory: ' . $ex->getMessage());
    json_response(['ok' => false], 500);
}
