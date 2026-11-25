<?php
/**
 * POST /api/patient-request.php — Imara Health Bridge Gulu "Get Care" form.
 * Answers JSON for the website's JavaScript, or a simple page for phones without JavaScript.
 */
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
require_post();

$json = wants_json();
$fail = function (string $message, array $errors = [], int $status = 422) use ($json): never {
    if ($json) {
        json_response(['ok' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }
    $list = '';
    foreach ($errors as $msg) {
        $list .= '<li>' . e($msg) . '</li>';
    }
    render_public_message('Please check your form', '<div class="notice danger"><i class="fa-solid fa-circle-exclamation"></i><div><p><strong>' . e($message) . '</strong></p>' . ($list ? '<ul style="margin:10px 0 0 18px">' . $list . '</ul>' : '') . '<p style="margin-top:12px">Please go back, correct this and send again. You can also call us on ' . e((string) config('public_phone')) . '.</p></div></div>', $status);
};

if ($spam = spam_check()) {
    $fail($spam, [], 400);
}
if (!rate_limit_hit('patient', (int) config('public_rate_per_hour', 8), 3600)) {
    $fail('We have received several requests from this device in the last hour. Please call or WhatsApp us on ' . config('public_phone') . ' instead.', [], 429);
}

[$data, $errors] = validate_patient_request($_POST);
if ($errors) {
    $fail('Please check the highlighted fields.', $errors);
}

try {
    [$req, $duplicate] = create_request($data);
} catch (Throwable $ex) {
    error_log('Imara patient-request: ' . $ex->getMessage());
    $fail('Sorry — something went wrong on our side and your request was not saved. Please call or WhatsApp us on ' . config('public_phone') . '.', [], 500);
}

$firstName = explode(' ', trim($data['full_name']))[0] ?: $data['full_name'];

if (!$duplicate) {
    audit('request_created', 'request', (int) $req['id'], $req['is_urgent'] ? 'urgent' : '');
    // Alerts must never stop the patient from getting their reference number.
    try {
        notify_new_request($req, $data['full_name'], $data['phone']);
        notify_patient_confirmation($req, $firstName, $data['phone']);
    } catch (Throwable $ex) {
        error_log('Imara notify: ' . $ex->getMessage());
    }
}

if ($json) {
    json_response([
        'ok' => true,
        'reference' => $req['reference'],
        'first_name' => $firstName,
        'urgent' => (bool) $req['is_urgent'],
        'duplicate' => $duplicate,
    ]);
}

$ref = e($req['reference']);
$name = e($firstName);
render_public_message('Request received', <<<HTML
<div class="confirm-card">
  <div class="tick">&#10003;</div>
  <p style="color:#1E7B4C;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:13px">Request received successfully</p>
  <p class="apwoyo">Apwoyo matek!</p>
  <h2>Thank you, {$name}.</h2>
  <p>Your healthcare assistance request has been received.</p>
  <div class="ref-box"><small>Your reference number</small><strong>{$ref}</strong></div>
  <p>An Imara team member will contact you within 24 hours during working days to guide you through the next steps.</p>
  <div class="notice danger"><p><strong>Need urgent medical attention?</strong> Do not wait for Imara to respond. Visit the nearest appropriate health facility immediately or seek emergency assistance.</p></div>
  <p class="sign">Strong Communities. Strong Health. Strong Afrika. &mdash; Team Imara Gulu</p>
</div>
HTML);
