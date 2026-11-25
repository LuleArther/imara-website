<?php
/**
 * Make a consistent copy of the database into data/backups/ and keep the last 30.
 * Schedule daily (Hostinger hPanel › Advanced › Cron Jobs):
 *     php /home/USER/imara/tools/backup.php
 * Then copy the backups folder somewhere safe off the server (e.g. weekly download).
 * Personal fields inside the backup stay encrypted with app_key.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$dir = rtrim((string) config('backup_dir'), '/\\');
$file = $dir . '/imara-' . date('Ymd-His') . '.sqlite';
db()->exec('VACUUM INTO ' . db()->quote($file));
@chmod($file, 0600);
audit('backup_created', null, null, basename($file));
echo "Backup written: $file\n";

$all = glob($dir . '/imara-*.sqlite') ?: [];
rsort($all);
foreach (array_slice($all, 30) as $old) {
    unlink($old);
    echo "Removed old backup: " . basename($old) . "\n";
}
