<?php
/** CSV of patient requests (contains personal data — admin/coordinator only, always audited). */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('requests.export');
$filters = [
    'status' => (string) ($_GET['status'] ?? ''),
    'urgent' => !empty($_GET['urgent']),
    'service' => str_clean($_GET['service'] ?? '', 80),
];
[$rows, $total] = search_requests($filters, 100000, 0);
audit('requests_export', 'request', null, $total . ' rows' . ($filters['status'] ? ', status ' . $filters['status'] : ''));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="imara-requests-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // so Excel shows the dashes correctly
fputcsv($out, ['Reference', 'Received', 'Status', 'Urgent', 'Name', 'Phone', 'Age', 'Child', 'Gender', 'Location', 'Service', 'Urgency', 'Preferred facility', 'Recommended facility', 'Receiving institution', 'Referral date', 'Approved by', 'Got care', 'Closed reason', 'Heard about Imara', 'Assigned to']);
foreach ($rows as $r) {
    $anon = (bool) $r['anonymised_at'];
    fputcsv($out, [
        $r['reference'], local_time($r['created_at'], 'Y-m-d H:i'), status_label($r['status']), $r['is_urgent'] ? 'Yes' : 'No',
        $anon ? '' : decrypt_field($r['name_enc']), $anon ? '' : format_phone(decrypt_field($r['phone_enc'])),
        $r['age'], $r['is_minor'] ? 'Yes' : 'No', $r['gender'], $r['location'] . ($r['location_other'] ? ' — ' . $r['location_other'] : ''),
        $r['service'] . ($r['service_other'] ? ' — ' . $r['service_other'] : ''), $r['urgency'], $r['preferred_facility'], $r['recommended_facility'],
        $r['receiving_institution'], $r['referral_date'], $r['reviewed_by'], ACCESSED_CARE[$r['accessed_care']] ?? '', $r['closed_reason'], $r['heard'], $r['assigned_name'] ?? '',
    ]);
}
fclose($out);
