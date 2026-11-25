<?php
declare(strict_types=1);

/**
 * Volunteer, partnership, medical-camp, contact and donation forms.
 * Each type is described by a small schema; everything personal is encrypted.
 *
 * Field rule keys: label, required, max, type (text|email|phone|int|option|options|bool), option (form-options key)
 */
const SUBMISSION_STATUSES = [
    'new'         => 'New',
    'in_progress' => 'In progress',
    'done'        => 'Done',
    'archived'    => 'Archived',
];

function submission_types(): array
{
    return [
        'volunteer' => [
            'label' => 'Volunteer registration',
            'prefix' => 'VOL',
            'name_field' => 'full_name',
            'contact_fields' => ['phone', 'email'],
            'summary' => fn ($d) => $d['role'] . ($d['district'] ? ' · ' . $d['district'] : ''),
            'fields' => [
                'full_name'    => ['label' => 'Full name', 'required' => true, 'max' => 120],
                'phone'        => ['label' => 'Phone / WhatsApp', 'required' => true, 'type' => 'phone'],
                'email'        => ['label' => 'Email', 'type' => 'email'],
                'role'         => ['label' => 'I am a', 'required' => true, 'type' => 'option', 'option' => 'volunteer_roles'],
                'role_other'   => ['label' => 'Other role', 'max' => 80, 'required_if' => ['role', 'Other']],
                'institution'  => ['label' => 'School, university or workplace', 'max' => 120],
                'district'     => ['label' => 'Where you live', 'required' => true, 'max' => 80],
                'availability' => ['label' => 'Availability', 'type' => 'options', 'allowed' => ['Weekdays', 'Weekends', 'Medical camps only', 'Remote / online']],
                'message'      => ['label' => 'Skills and why you want to volunteer', 'max' => 1000],
                'consent'      => ['label' => 'Consent to be contacted', 'required' => true, 'type' => 'bool'],
            ],
        ],
        'partner' => [
            'label' => 'Partnership application',
            'prefix' => 'PTN',
            'name_field' => 'contact_name',
            'contact_fields' => ['email', 'phone'],
            'summary' => fn ($d) => $d['org_name'] . ' · ' . $d['partner_type'],
            'fields' => [
                'org_name'     => ['label' => 'Organisation', 'required' => true, 'max' => 150],
                'contact_name' => ['label' => 'Contact person', 'required' => true, 'max' => 120],
                'role_title'   => ['label' => 'Job title', 'max' => 100],
                'partner_type' => ['label' => 'Type of partnership', 'required' => true, 'type' => 'option', 'option' => 'partner_types'],
                'email'        => ['label' => 'Email', 'required' => true, 'type' => 'email'],
                'phone'        => ['label' => 'Phone', 'type' => 'phone'],
                'country'      => ['label' => 'Country / city', 'max' => 80],
                'website_url'  => ['label' => 'Website', 'max' => 150],
                'message'      => ['label' => 'How would you like to work with Imara?', 'required' => true, 'max' => 2000],
                'consent'      => ['label' => 'Consent to be contacted', 'required' => true, 'type' => 'bool'],
            ],
        ],
        'camp' => [
            'label' => 'Medical camp request',
            'prefix' => 'CAMP',
            'name_field' => 'contact_name',
            'contact_fields' => ['phone', 'email'],
            'summary' => fn ($d) => $d['org_name'] . ' · ' . $d['district'] . ($d['people'] ? ' · ~' . $d['people'] . ' people' : ''),
            'fields' => [
                'org_name'     => ['label' => 'Community, school or institution', 'required' => true, 'max' => 150],
                'contact_name' => ['label' => 'Contact person', 'required' => true, 'max' => 120],
                'role_title'   => ['label' => 'Role (e.g. LC1, head teacher, VHT)', 'max' => 100],
                'phone'        => ['label' => 'Phone / WhatsApp', 'required' => true, 'type' => 'phone'],
                'email'        => ['label' => 'Email', 'type' => 'email'],
                'district'     => ['label' => 'District', 'required' => true, 'max' => 80],
                'place'        => ['label' => 'Sub-county, parish or village', 'required' => true, 'max' => 150],
                'people'       => ['label' => 'People expected', 'type' => 'int', 'min' => 1, 'max' => 100000],
                'dates'        => ['label' => 'Preferred dates', 'max' => 120],
                'services'     => ['label' => 'Services needed', 'type' => 'options', 'option' => 'camp_services'],
                'venue'        => ['label' => 'Venue available', 'type' => 'option', 'allowed' => ['Yes', 'No', 'Not sure']],
                'message'      => ['label' => 'Main health needs in the community', 'max' => 1500],
                'consent'      => ['label' => 'Consent to be contacted', 'required' => true, 'type' => 'bool'],
            ],
        ],
        'contact' => [
            'label' => 'Contact message',
            'prefix' => 'MSG',
            'name_field' => 'name',
            'contact_fields' => ['email', 'phone'],
            'summary' => fn ($d) => $d['subject'] ?: 'General enquiry',
            'one_of' => ['email', 'phone'],
            'fields' => [
                'name'    => ['label' => 'Your name', 'required' => true, 'max' => 120],
                'email'   => ['label' => 'Email', 'type' => 'email'],
                'phone'   => ['label' => 'Phone', 'type' => 'phone'],
                'subject' => ['label' => 'Subject', 'type' => 'option', 'allowed' => ['General enquiry', 'Patient support', 'Partnership', 'Volunteering', 'Media', 'Donations']],
                'message' => ['label' => 'Message', 'required' => true, 'max' => 2000],
            ],
        ],
        'donation' => [
            'label' => 'Donation pledge',
            'prefix' => 'DON',
            'name_field' => 'donor_name',
            'contact_fields' => ['email', 'phone'],
            'summary' => fn ($d) => 'UGX ' . number_format((int) $d['amount']) . ' · ' . $d['purpose'] . ' · ' . $d['method'] . ($d['anonymous'] ? ' · anonymous' : ''),
            'fields' => [
                'amount'     => ['label' => 'Amount (UGX)', 'required' => true, 'type' => 'int', 'min' => 1000, 'max' => 1000000000],
                'purpose'    => ['label' => 'Purpose', 'required' => true, 'type' => 'option', 'option' => 'donation_purposes'],
                'method'     => ['label' => 'Preferred way to give', 'required' => true, 'type' => 'option', 'allowed' => ['MTN Mobile Money', 'Airtel Money', 'PayPal', 'Bank transfer', 'Not sure yet']],
                'anonymous'  => ['label' => 'Give anonymously', 'type' => 'bool'],
                'donor_name' => ['label' => 'Your name', 'max' => 120, 'required_unless' => 'anonymous'],
                'email'      => ['label' => 'Email', 'type' => 'email'],
                'phone'      => ['label' => 'Phone', 'type' => 'phone'],
                'message'    => ['label' => 'Message', 'max' => 500],
            ],
            'one_of' => ['email', 'phone'],
        ],
    ];
}

/** Returns [clean data, errors]. */
function validate_submission(string $type, array $in): array
{
    $schema = submission_types()[$type];
    $d = [];
    $err = [];
    foreach ($schema['fields'] as $name => $rule) {
        $t = $rule['type'] ?? 'text';
        $raw = $in[$name] ?? ($in[$name . '[]'] ?? null);
        switch ($t) {
            case 'bool':
                $d[$name] = ($raw === '1' || $raw === 'on') ? 1 : 0;
                if (!empty($rule['required']) && !$d[$name]) {
                    $err[$name] = 'Please tick this box to continue.';
                }
                continue 2;
            case 'options':
                $allowed = $rule['allowed'] ?? option_values($rule['option']);
                $vals = is_array($raw) ? $raw : [];
                $d[$name] = array_values(array_intersect(array_map('strval', $vals), $allowed));
                continue 2;
            case 'option':
                $v = str_clean($raw, 120);
                $allowed = $rule['allowed'] ?? option_values($rule['option']);
                if ($v !== '' && !in_array($v, $allowed, true)) {
                    $v = '';
                }
                $d[$name] = $v;
                break;
            case 'phone':
                $v = str_clean($raw, 30);
                if ($v !== '') {
                    $p = normalise_phone($v, str_clean($in[$name . '_code'] ?? '+256', 6));
                    if ($p === null) {
                        $err[$name] = 'Please enter a valid phone number.';
                    }
                    $v = $p ?? '';
                }
                $d[$name] = $v;
                break;
            case 'email':
                $v = str_clean($raw, 160);
                if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $err[$name] = 'Please enter a valid email address.';
                }
                $d[$name] = $v;
                break;
            case 'int':
                $v = preg_replace('/[^\d]/', '', (string) (is_string($raw) ? $raw : '')) ?? '';
                $d[$name] = $v === '' ? 0 : (int) $v;
                if ($v !== '' && ($d[$name] < ($rule['min'] ?? 0) || $d[$name] > ($rule['max'] ?? PHP_INT_MAX))) {
                    $err[$name] = 'Please enter a number between ' . number_format($rule['min'] ?? 0) . ' and ' . number_format($rule['max'] ?? PHP_INT_MAX) . '.';
                }
                break;
            default:
                $d[$name] = str_clean($raw, $rule['max'] ?? 200);
        }
        $empty = $d[$name] === '' || $d[$name] === 0;
        if (!isset($err[$name]) && $empty && !empty($rule['required'])) {
            $err[$name] = 'Please fill in ' . strtolower($rule['label']) . '.';
        }
    }
    // conditional rules
    foreach ($schema['fields'] as $name => $rule) {
        if (isset($rule['required_if']) && ($d[$rule['required_if'][0]] ?? '') === $rule['required_if'][1] && $d[$name] === '') {
            $err[$name] = 'Please fill in ' . strtolower($rule['label']) . '.';
        }
        if (isset($rule['required_unless']) && empty($d[$rule['required_unless']]) && $d[$name] === '') {
            $err[$name] = 'Please enter your name, or choose to give anonymously.';
        }
    }
    if (!empty($schema['one_of'])) {
        $any = false;
        foreach ($schema['one_of'] as $f) {
            $any = $any || ($d[$f] ?? '') !== '';
        }
        if (!$any && !array_intersect_key($err, array_flip($schema['one_of']))) {
            $err[$schema['one_of'][0]] = 'Please give an email or a phone number so we can reply.';
        }
    }
    $token = str_clean($in['client_token'] ?? '', 64);
    $d['_token'] = preg_match('/^[a-f0-9-]{16,64}$/i', $token) ? strtolower($token) : null;
    return [$d, $err];
}

/** Returns [row, duplicate]. */
function create_submission(string $type, array $d): array
{
    $schema = submission_types()[$type];
    $pdo = db();
    if ($d['_token']) {
        $st = $pdo->prepare('SELECT * FROM submissions WHERE client_token = ?');
        $st->execute([$d['_token']]);
        if ($row = $st->fetch()) {
            return [$row, true];
        }
    }
    $token = $d['_token'];
    unset($d['_token']);

    $name = $type === 'donation' && !empty($d['anonymous']) && $d['donor_name'] === '' ? 'Anonymous donor' : (string) $d[$schema['name_field']];
    $contact = [];
    foreach ($schema['contact_fields'] as $f) {
        if (($d[$f] ?? '') !== '') {
            $contact[$f] = $d[$f];
        }
    }
    $summary = ($schema['summary'])($d);
    $now = now_utc();

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO submissions (type, reference, created_at, updated_at, status, summary, name_enc, contact_enc, payload_enc, amount, client_token, ip_hash)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $type, 'PENDING-' . bin2hex(random_bytes(8)), $now, $now, 'new', mb_substr($summary, 0, 250),
            encrypt_field($name), encrypt_field(json_encode($contact)), encrypt_field(json_encode($d, JSON_UNESCAPED_UNICODE)),
            $type === 'donation' ? (int) $d['amount'] : null, $token, ip_hash(),
        ]);
    $id = (int) $pdo->lastInsertId();
    $countSt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE type = ? AND id <= ?');
    $countSt->execute([$type, $id]);
    $ref = sprintf('%s-%04d', $schema['prefix'], (int) $countSt->fetchColumn());
    $pdo->prepare('UPDATE submissions SET reference = ? WHERE id = ?')->execute([$ref, $id]);
    $pdo->commit();

    $st = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
    $st->execute([$id]);
    return [$st->fetch(), false];
}

function submission_label(string $type): string
{
    return submission_types()[$type]['label'] ?? ucfirst($type);
}
