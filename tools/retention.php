<?php
/**
 * Data retention: remove personal details from requests that were closed or linked
 * more than N months ago (Admin › Settings › Retention). Numbers for reports are kept.
 * Schedule monthly:  php /home/USER/imara/tools/retention.php
 *   --dry-run   only show how many would be anonymised
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$months = max(6, (int) setting('retention_months', (string) config('retention_months_default', 24)));
$cutoff = gmdate('Y-m-d\TH:i:s\Z', strtotime("-$months months"));
$st = db()->prepare("SELECT id FROM requests WHERE anonymised_at IS NULL AND status IN ('closed','linked') AND updated_at < ?");
$st->execute([$cutoff]);
$ids = $st->fetchAll(PDO::FETCH_COLUMN);

echo count($ids) . " request(s) older than $months months are due for anonymisation.\n";
if (in_array('--dry-run', $argv, true)) {
    exit(0);
}
foreach ($ids as $id) {
    anonymise_request((int) $id, "retention policy ($months months)");
}
echo "Done.\n";
