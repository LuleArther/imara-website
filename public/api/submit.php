<?php
/**
 * POST /api/submit.php?type=volunteer|partner|camp|contact|donation
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
require_post();

$json = wants_json();
$type = (string) ($_GET['type'] ?? $_POST['form_type'] ?? '');
$types = submission_types();

$fail = function (string $message, array $errors = [], int $status = 422) use ($json): never {
    if ($json) {
        json_response(['ok' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }
    $list = '';
    foreach ($errors as $msg) {
        $list .= '<li>' . e($msg) . '</li>';
    }
    render_public_message('Please check your form', '<div class="notice danger"><div><p><strong>' . e($message) . '</strong></p>' . ($list ? '<ul style="margin:10px 0 0 18px">' . $list . '</ul>' : '') . '<p style="margin-top:12px">Please go back and try again.</p></div></div>', $status);
};

if (!isset($types[$type])) {
    $fail('Unknown form.', [], 400);
}
if ($spam = spam_check()) {
    $fail($spam, [], 400);
}
if (!rate_limit_hit('form', (int) config('public_rate_per_hour', 8) + 2, 3600)) {
    $fail('Too many forms sent from this device in the last hour. Please email ' . config('public_email') . ' instead.', [], 429);
}

$input = $_POST;
if ($type === 'donation' && ($input['amount'] ?? '') === 'custom') {
    $input['amount'] = (string) ($input['amount_custom'] ?? '');
}
[$data, $errors] = validate_submission($type, $input);
if ($type === 'donation' && isset($errors['amount']) && ($_POST['amount'] ?? '') === 'custom') {
    $errors['amount_custom'] = $errors['amount'];
}
if ($errors) {
    $fail('Please check the highlighted fields.', $errors);
}

try {
    [$sub, $duplicate] = create_submission($type, $data);
} catch (Throwable $ex) {
    error_log('Imara submit: ' . $ex->getMessage());
    $fail('Sorry — something went wrong and your form was not saved. Please email ' . config('public_email') . '.', [], 500);
}

$schema = $types[$type];
$name = $type === 'donation' && !empty($data['anonymous']) && ($data['donor_name'] ?? '') === '' ? 'Anonymous donor' : (string) ($data[$schema['name_field']] ?? '');

if (!$duplicate) {
    audit('submission_created', 'submission', (int) $sub['id'], $type);
    try {
        notify_new_submission($sub, $name, $schema['label']);
        // Automatic thank-you email to donors who gave an email address
        if ($type === 'donation' && ($data['email'] ?? '') !== '') {
            $body = implode("\n", [
                'Dear ' . ($name === 'Anonymous donor' ? 'friend' : explode(' ', $name)[0]) . ',',
                '',
                'Thank you for choosing to support ' . config('org_name') . '.',
                'We have recorded your pledge of UGX ' . number_format((int) $data['amount']) . ' for: ' . $data['purpose'] . '.',
                'Your reference is ' . $sub['reference'] . '.',
                '',
                'A member of our team will contact you with verified payment details for ' . $data['method'] . '.',
                'Please only send money to details confirmed by Imara through ' . config('public_email') . '.',
                '',
                'Your contribution helps us reach underserved communities across Northern Uganda with free medical services and health education.',
                '',
                'Apwoyo matek!',
                'Team Imara Gulu',
                config('tagline'),
            ]);
            [$ok, $err] = send_email((string) $data['email'], 'Thank you for supporting Imara — ' . $sub['reference'], $body);
            log_notification('email', (string) $data['email'], 'Donor thank-you', $ok, $err, 'submission', (int) $sub['id']);
        }
    } catch (Throwable $ex) {
        error_log('Imara notify: ' . $ex->getMessage());
    }
}

if ($json) {
    json_response([
        'ok' => true,
        'reference' => $sub['reference'],
        'first_name' => explode(' ', trim($name))[0] ?: $name,
        'duplicate' => $duplicate,
    ]);
}

render_public_message('Thank you', '<div class="confirm-card"><div class="tick">&#10003;</div><p class="apwoyo">Apwoyo matek!</p><h2>Thank you, we have received your form.</h2><div class="ref-box"><small>Your reference</small><strong>' . e($sub['reference']) . '</strong></div><p>Our team will be in touch soon.</p></div>');
