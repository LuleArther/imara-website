<?php
declare(strict_types=1);

/** Every request carries exactly one of these statuses (from the brief). */
const REQUEST_STATUSES = [
    'new'          => 'New request',
    'under_review' => 'Under review',
    'contacted'    => 'Contacted',
    'referred'     => 'Referred',
    'linked'       => 'Linked to care',
    'follow_up'    => 'Follow-up required',
    'closed'       => 'Closed',
];

const STATUS_HELP = [
    'new'          => 'Received, not yet reviewed',
    'under_review' => 'Team assessing it',
    'contacted'    => 'Patient reached',
    'referred'     => 'Connected to a healthcare provider',
    'linked'       => 'Patient confirmed they got the service',
    'follow_up'    => 'More contact needed',
    'closed'       => 'Completed, or closed with a recorded reason',
];

const ACCESSED_CARE = [
    'unknown' => 'Not yet known',
    'yes'     => 'Yes — patient received care',
    'no'      => 'No — patient did not receive care',
];

function status_label(string $s): string
{
    return REQUEST_STATUSES[$s] ?? $s;
}

/**
 * Validate a patient request. Returns [array $clean, array $errors].
 * Mirrors the browser checks in assets/js/forms.js + get-care.js.
 */
function validate_patient_request(array $in): array
{
    $err = [];
    $s = fn (string $k, int $max = 200) => str_clean($in[$k] ?? '', $max);

    $d = [];
    $d['full_name'] = $s('full_name', 120);
    if (mb_strlen($d['full_name']) < 2 || !preg_match('/\p{L}/u', $d['full_name'])) {
        $err['full_name'] = 'Please enter the patient\'s full name.';
    }

    $ageRaw = $s('age', 5);
    if ($ageRaw === '' || !ctype_digit($ageRaw) || (int) $ageRaw > 120) {
        $err['age'] = 'Please enter an age between 0 and 120.';
    }
    $d['age'] = (int) $ageRaw;
    $d['is_minor'] = (!isset($err['age']) && $d['age'] < 18) ? 1 : 0;

    $d['gender'] = pick_option('gender', $s('gender'));

    $d['location'] = $s('location');
    if (!is_option('locations', $d['location'])) {
        $err['location'] = 'Please choose where you are.';
    }
    $d['location_other'] = '';
    if ($d['location'] === 'Other') {
        $d['location_other'] = $s('location_other', 80);
        if ($d['location_other'] === '') {
            $err['location_other'] = 'Please tell us where you are.';
        }
    }

    $d['phone'] = normalise_phone($s('phone', 30), $s('phone_code', 6) ?: '+256');
    if ($d['phone'] === null) {
        $err['phone'] = 'Please enter a valid phone number, for example 0772 123 456.';
    }

    $d['insurance'] = pick_option('insurance', $s('insurance'));

    // Children: parent / guardian contact and consent
    $d['guardian'] = null;
    $d['guardian_consent'] = 0;
    if ($d['is_minor']) {
        $g = [
            'name' => $s('guardian_name', 120),
            'relationship' => $s('guardian_relationship', 40),
            'phone' => normalise_phone($s('guardian_phone', 30), $s('guardian_phone_code', 6) ?: '+256'),
        ];
        if (mb_strlen($g['name']) < 2) {
            $err['guardian_name'] = 'Please enter the parent or guardian\'s name.';
        }
        if (!is_option('guardian_relationship', $g['relationship'])) {
            $err['guardian_relationship'] = 'Please choose the relationship.';
        }
        if ($g['phone'] === null) {
            $err['guardian_phone'] = 'Please enter a valid phone number for the parent or guardian.';
        }
        if (($in['guardian_consent'] ?? '') !== '1') {
            $err['guardian_consent'] = 'A parent or guardian must agree before we can help a child.';
        }
        $d['guardian'] = $g;
        $d['guardian_consent'] = 1;
    }

    $d['service'] = $s('service', 80);
    if (!is_option('services', $d['service'])) {
        $err['service'] = 'Please choose a service.';
    }
    $d['service_other'] = '';
    if ($d['service'] === 'Other') {
        $d['service_other'] = $s('service_other', 80);
        if ($d['service_other'] === '') {
            $err['service_other'] = 'Please tell us the service you need.';
        }
    }

    $d['concern'] = str_clean($in['concern'] ?? '', 600);
    if (mb_strlen($d['concern']) < 3) {
        $err['concern'] = 'Please describe your concern in a few words.';
    }

    $d['duration'] = pick_option('duration', $s('duration'));

    $d['urgency'] = $s('urgency', 60);
    if (!is_option('urgency', $d['urgency'])) {
        $err['urgency'] = 'Please choose how soon you need help.';
    }
    $d['is_urgent'] = str_starts_with($d['urgency'], 'Urgent') ? 1 : 0;

    $d['assistance'] = pick_option('assistance', $s('assistance'));
    $d['facility'] = pick_option('facilities', $s('facility'));
    $prev = $s('previous_care', 5);
    $d['previous_care'] = in_array($prev, ['Yes', 'No'], true) ? $prev : '';
    $d['previous_details'] = $d['previous_care'] === 'Yes' ? str_clean($in['previous_details'] ?? '', 400) : '';
    $d['heard'] = pick_option('heard', $s('heard'));

    if (($in['consent_care'] ?? '') !== '1') {
        $err['consent_care'] = 'We need your consent before we can link you to care.';
    }
    $d['consent_whatsapp'] = ($in['consent_whatsapp'] ?? '') === '1' ? 1 : 0;

    $token = $s('client_token', 64);
    $d['client_token'] = preg_match('/^[a-f0-9-]{16,64}$/i', $token) ? strtolower($token) : null;

    return [$d, $err];
}

/** Look for the same request already in the system. */
function find_duplicate_request(array $d): ?array
{
    $pdo = db();
    if ($d['client_token']) {
        $st = $pdo->prepare('SELECT * FROM requests WHERE client_token = ?');
        $st->execute([$d['client_token']]);
        if ($row = $st->fetch()) {
            return $row;
        }
    }
    $since = gmdate('Y-m-d\TH:i:s\Z', time() - 60 * (int) config('duplicate_window_minutes', 30));
    $st = $pdo->prepare("SELECT * FROM requests WHERE phone_hash = ? AND service = ? AND created_at >= ? AND status <> 'closed' ORDER BY id DESC LIMIT 1");
    $st->execute([blind_index('phone|' . $d['phone']), $d['service'], $since]);
    return $st->fetch() ?: null;
}

/** Store a validated request. Returns [array $row, bool $duplicate]. */
function create_request(array $d): array
{
    $pdo = db();
    if ($dup = find_duplicate_request($d)) {
        return [$dup, true];
    }
    $now = now_utc();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO requests (
                reference, created_at, updated_at, status, is_urgent, urgency,
                name_enc, phone_enc, concern_enc, guardian_enc, previous_details_enc, phone_hash,
                age, is_minor, guardian_consent, gender, location, location_other, insurance,
                service, service_other, duration, assistance, preferred_facility, previous_care, heard,
                consent_care, consent_whatsapp, consent_at, privacy_version, client_token, ip_hash
            ) VALUES (?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,?,?,?,?,?)')
            ->execute([
                'PENDING-' . bin2hex(random_bytes(8)), $now, $now, 'new', $d['is_urgent'], $d['urgency'],
                encrypt_field($d['full_name']), encrypt_field($d['phone']), encrypt_field($d['concern']),
                $d['guardian'] ? encrypt_field(json_encode($d['guardian'], JSON_UNESCAPED_UNICODE)) : null,
                encrypt_field($d['previous_details']), blind_index('phone|' . $d['phone']),
                $d['age'], $d['is_minor'], $d['guardian_consent'], $d['gender'], $d['location'], $d['location_other'], $d['insurance'],
                $d['service'], $d['service_other'], $d['duration'], $d['assistance'], $d['facility'], $d['previous_care'], $d['heard'],
                1, $d['consent_whatsapp'], $now, (string) config('privacy_version'), $d['client_token'], ip_hash(),
            ]);
        $id = (int) $pdo->lastInsertId();
        $reference = sprintf('IMARA-%04d', $id);
        $pdo->prepare('UPDATE requests SET reference = ? WHERE id = ?')->execute([$reference, $id]);
        add_request_event($id, 'created', null, 'new', $d['is_urgent'] ? 'Submitted online — marked URGENT by patient' : 'Submitted online');
        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        // A double-click can race past the first check: the UNIQUE token catches it.
        if ($d['client_token'] && str_contains($ex->getMessage(), 'UNIQUE')) {
            if ($dup = find_duplicate_request($d)) {
                return [$dup, true];
            }
        }
        throw $ex;
    }
    return [get_request($id), false];
}

function get_request(int $id): ?array
{
    $st = db()->prepare('SELECT r.*, u.name AS assigned_name FROM requests r LEFT JOIN users u ON u.id = r.assigned_to WHERE r.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Decrypt the personal fields of a request row for staff views. */
function request_pii(array $r): array
{
    $g = json_decode(decrypt_field($r['guardian_enc'] ?? null) ?: 'null', true);
    return [
        'name' => decrypt_field($r['name_enc'] ?? null),
        'phone' => decrypt_field($r['phone_enc'] ?? null),
        'concern' => decrypt_field($r['concern_enc'] ?? null),
        'previous_details' => decrypt_field($r['previous_details_enc'] ?? null),
        'outcome' => decrypt_field($r['outcome_enc'] ?? null),
        'guardian' => is_array($g) ? $g : null,
    ];
}

function add_request_event(int $requestId, string $type, ?string $from, ?string $to, string $note = ''): void
{
    $user = function_exists('current_user') ? current_user() : null;
    db()->prepare('INSERT INTO request_events (request_id, created_at, user_id, type, from_status, to_status, note_enc) VALUES (?,?,?,?,?,?,?)')
        ->execute([$requestId, now_utc(), $user['id'] ?? null, $type, $from, $to, encrypt_field($note)]);
}

/**
 * Search requests by reference, name or phone, with optional filters.
 * Names are encrypted, so name search decrypts candidate rows in PHP
 * (fine for the volumes a regional NGO handles).
 */
function search_requests(array $f, int $limit = 25, int $offset = 0): array
{
    $where = [];
    $args = [];
    if (!empty($f['status']) && isset(REQUEST_STATUSES[$f['status']])) {
        $where[] = 'r.status = ?';
        $args[] = $f['status'];
    } elseif (($f['status'] ?? '') === 'open') {
        $where[] = "r.status NOT IN ('linked','closed')";
    }
    if (!empty($f['urgent'])) {
        $where[] = 'r.is_urgent = 1';
    }
    if (!empty($f['service']) && is_option('services', $f['service'])) {
        $where[] = 'r.service = ?';
        $args[] = $f['service'];
    }
    if (!empty($f['mine']) && !empty($f['user_id'])) {
        $where[] = 'r.assigned_to = ?';
        $args[] = (int) $f['user_id'];
    }

    $q = trim((string) ($f['q'] ?? ''));
    $nameFilter = null;
    if ($q !== '') {
        if (preg_match('/^(imara-?)?\d{1,6}$/i', $q)) {
            $num = (int) preg_replace('/\D/', '', $q);
            $where[] = 'r.reference = ?';
            $args[] = sprintf('IMARA-%04d', $num);
        } elseif (($p = normalise_phone($q)) !== null || ($p = normalise_phone($q, '+254')) !== null) {
            $where[] = 'r.phone_hash = ?';
            $args[] = blind_index('phone|' . $p);
        } else {
            $nameFilter = mb_strtolower($q);
        }
    }

    $sql = 'SELECT r.*, u.name AS assigned_name FROM requests r LEFT JOIN users u ON u.id = r.assigned_to'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY (r.is_urgent = 1 AND r.status IN (\'new\',\'under_review\')) DESC, r.id DESC';

    if ($nameFilter === null) {
        $count = db()->prepare('SELECT COUNT(*) FROM requests r' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $st = db()->prepare($sql . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset);
        $st->execute($args);
        return [$st->fetchAll(), $total];
    }

    $st = db()->prepare($sql . ' LIMIT 5000');
    $st->execute($args);
    $matches = [];
    foreach ($st as $row) {
        if (str_contains(mb_strtolower(decrypt_field($row['name_enc'])), $nameFilter)) {
            $matches[] = $row;
        }
    }
    return [array_slice($matches, $offset, $limit), count($matches)];
}

function request_counts(): array
{
    $row = db()->query("SELECT
            COUNT(*) AS total,
            SUM(status = 'new') AS new_count,
            SUM(status = 'linked' OR (status = 'closed' AND accessed_care = 'yes')) AS linked,
            SUM(status NOT IN ('linked','closed')) AS pending,
            SUM(is_urgent = 1 AND status IN ('new','under_review')) AS urgent_open
        FROM requests")->fetch();
    return array_map('intval', $row ?: []);
}

/**
 * Apply a staff update. Returns an error message or null.
 * Rules: Referred needs a receiving institution and a named health professional who approved it;
 * Closed needs a reason; Linked records that the patient got care.
 */
function update_request(array $r, array $in, array $user): ?string
{
    $to = (string) ($in['status'] ?? $r['status']);
    if (!isset(REQUEST_STATUSES[$to])) {
        return 'Unknown status.';
    }
    $institution = str_clean($in['receiving_institution'] ?? $r['receiving_institution'] ?? '', 120);
    $reviewedBy = str_clean($in['reviewed_by'] ?? $r['reviewed_by'] ?? '', 120);
    $closedReason = str_clean($in['closed_reason'] ?? $r['closed_reason'] ?? '', 200);
    $referralDate = str_clean($in['referral_date'] ?? $r['referral_date'] ?? '', 10);
    $followUp = str_clean($in['follow_up_date'] ?? $r['follow_up_date'] ?? '', 10);
    $accessed = (string) ($in['accessed_care'] ?? $r['accessed_care']);
    $recommended = str_clean($in['recommended_facility'] ?? $r['recommended_facility'] ?? '', 120);
    $assigned = isset($in['assigned_to']) && $in['assigned_to'] !== '' ? (int) $in['assigned_to'] : null;
    $note = str_clean($in['note'] ?? '', 2000);
    $outcome = array_key_exists('outcome', $in) ? str_clean($in['outcome'], 1000) : null;

    foreach ([$referralDate, $followUp] as $date) {
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Dates must look like 2026-11-30.';
        }
    }
    if (!isset(ACCESSED_CARE[$accessed])) {
        $accessed = 'unknown';
    }
    if ($to === 'referred' && ($institution === '' || $reviewedBy === '')) {
        return 'To mark as Referred, record the receiving institution and the qualified health professional who approved the referral.';
    }
    if ($to === 'referred' && $referralDate === '') {
        $referralDate = date('Y-m-d');
    }
    if ($to === 'linked') {
        $accessed = 'yes';
    }
    if ($to === 'closed' && $closedReason === '') {
        return 'Please record why this request is being closed.';
    }
    if ($user['role'] === 'viewer') {
        return 'Your role cannot change requests.';
    }

    $now = now_utc();
    $from = (string) $r['status'];
    $fields = [
        'status' => $to,
        'updated_at' => $now,
        'receiving_institution' => $institution,
        'reviewed_by' => $reviewedBy,
        'closed_reason' => $closedReason,
        'referral_date' => $referralDate,
        'follow_up_date' => $followUp,
        'accessed_care' => $accessed,
        'recommended_facility' => $recommended,
        'assigned_to' => $assigned,
    ];
    if ($outcome !== null) {
        $fields['outcome_enc'] = encrypt_field($outcome);
    }
    if (!$r['first_contact_at'] && in_array($to, ['contacted', 'referred', 'linked', 'follow_up'], true)) {
        $fields['first_contact_at'] = $now;
    }
    if ($to === 'linked' && !$r['linked_at']) {
        $fields['linked_at'] = $now;
    }
    if ($to === 'closed' && !$r['closed_at']) {
        $fields['closed_at'] = $now;
    }
    if ($to !== 'closed') {
        $fields['closed_at'] = null;
    }

    $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($fields)));
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE requests SET $set WHERE id = ?")->execute([...array_values($fields), (int) $r['id']]);
    if ($from !== $to) {
        add_request_event((int) $r['id'], 'status', $from, $to, $note);
    } elseif ($note !== '') {
        add_request_event((int) $r['id'], 'note', null, null, $note);
    } else {
        add_request_event((int) $r['id'], 'update', null, null, '');
    }
    $pdo->commit();

    audit('request_update', 'request', (int) $r['id'], $from !== $to ? "status $from → $to" : 'details updated');
    return null;
}

/** Remove personal data but keep the anonymous numbers for reports. */
function anonymise_request(int $id, string $why): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE requests SET name_enc = NULL, phone_enc = NULL, concern_enc = NULL, guardian_enc = NULL,
        previous_details_enc = NULL, outcome_enc = NULL, phone_hash = NULL, location_other = NULL, service_other = NULL,
        client_token = NULL, ip_hash = NULL, anonymised_at = ?, updated_at = ? WHERE id = ?")
        ->execute([now_utc(), now_utc(), $id]);
    $pdo->prepare('UPDATE request_events SET note_enc = NULL WHERE request_id = ?')->execute([$id]);
    $pdo->commit();
    audit('request_anonymise', 'request', $id, $why);
}
