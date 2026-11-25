<?php
/**
 * GET /api/impact.php — public, anonymous numbers for the Impact page.
 * "verified" figures are entered by Imara staff in Admin › Settings;
 * "live" figures are counted from the Health Bridge system (no personal data).
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

try {
    $pdo = db();
    $live = $pdo->query("SELECT
            COUNT(*) AS requests,
            SUM(status = 'linked' OR accessed_care = 'yes') AS linked,
            SUM(status = 'referred' OR referral_date IS NOT NULL AND referral_date <> '') AS referred,
            COUNT(DISTINCT location) AS places
        FROM requests")->fetch() ?: [];
    $camps = (int) $pdo->query("SELECT COUNT(*) FROM submissions WHERE type = 'camp'")->fetchColumn();
    $volunteers = (int) $pdo->query("SELECT COUNT(*) FROM submissions WHERE type = 'volunteer'")->fetchColumn();

    $verified = [];
    foreach (['patients', 'professionals', 'institutions', 'policy', 'youth'] as $k) {
        $verified[$k] = (int) setting('impact_' . $k, '0');
    }

    json_response([
        'ok' => true,
        'verified' => $verified,
        'verified_note' => setting('impact_note', ''),
        'live' => [
            'requests' => (int) ($live['requests'] ?? 0),
            'linked' => (int) ($live['linked'] ?? 0),
            'referred' => (int) ($live['referred'] ?? 0),
            'places' => (int) ($live['places'] ?? 0),
            'camp_requests' => $camps,
            'volunteers' => $volunteers,
        ],
    ]);
} catch (Throwable $ex) {
    error_log('Imara impact: ' . $ex->getMessage());
    json_response(['ok' => false], 500);
}
