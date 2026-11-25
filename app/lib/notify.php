<?php
declare(strict_types=1);

/**
 * Staff alerts and patient confirmations.
 * Rule from the brief: alerts carry only what staff need to act —
 * never the health concern or medical history.
 */
function log_notification(string $channel, string $recipient, string $subject, bool $ok, ?string $error, string $entity, int $entityId, bool $skipped = false): void
{
    db()->prepare('INSERT INTO notifications (created_at, channel, recipient, subject, status, error, entity, entity_id) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([now_utc(), $channel, mask_recipient($recipient), $subject, $skipped ? 'skipped' : ($ok ? 'sent' : 'failed'), $error, $entity, $entityId]);
}

function mask_recipient(string $r): string
{
    if (str_contains($r, '@')) {
        return $r; // staff mailbox
    }
    $d = preg_replace('/\D/', '', $r) ?? '';
    return strlen($d) > 4 ? str_repeat('•', strlen($d) - 4) . substr($d, -4) : $d;
}

/** Alert the Imara team about a new patient request (email + WhatsApp). */
function notify_new_request(array $req, string $name, string $phoneE164): void
{
    $urgent = (int) $req['is_urgent'] === 1;
    $link = base_url('admin/request.php?id=' . (int) $req['id']);
    $service = $req['service'] === 'Other' && $req['service_other'] ? 'Other: ' . $req['service_other'] : $req['service'];
    $district = $req['location'] === 'Other' && $req['location_other'] ? 'Other: ' . $req['location_other'] : $req['location'];
    $subject = ($urgent ? 'URGENT — ' : '') . 'New Patient Request — Imara Health Bridge Gulu';

    $lines = [
        $urgent ? "⚠ URGENT REQUEST — the patient says they need help TODAY.\nPlease review immediately and call the patient. Remind them to go to the nearest facility if symptoms are severe.\n" : 'A new healthcare assistance request has been received.' . "\n",
        'Reference:          ' . $req['reference'],
        'Patient:            ' . $name . ((int) $req['is_minor'] === 1 ? ' (child, age ' . (int) $req['age'] . ' — guardian details in dashboard)' : ''),
        'Phone / WhatsApp:   ' . format_phone($phoneE164),
        'Service requested:  ' . $service,
        'Preferred facility: ' . $req['preferred_facility'],
        'Urgency:            ' . $req['urgency'],
        'Location:           ' . $district,
        '',
        'Review and follow up in the secure dashboard:',
        $link,
        '',
        'This email does not include health details. Log in to see the full request.',
        '',
        config('org_name') . ' — ' . config('tagline'),
    ];
    $body = implode("\n", $lines);

    foreach ((array) config('notify_emails') as $to) {
        [$ok, $err] = send_email((string) $to, $subject, $body);
        log_notification('email', (string) $to, $subject, $ok, $err, 'request', (int) $req['id']);
    }

    $template = (string) config('whatsapp.template_new_request');
    foreach ((array) config('whatsapp.admin_numbers') as $num) {
        if (!whatsapp_enabled()) {
            log_notification('whatsapp', (string) $num, 'New request alert', false, 'WhatsApp Business not configured yet', 'request', (int) $req['id'], true);
            continue;
        }
        [$ok, $err] = whatsapp_send_template((string) $num, $template, [
            $req['reference'],
            $name,
            $service,
            ($urgent ? 'URGENT — ' : '') . $req['urgency'],
            $district,
        ]);
        log_notification('whatsapp', (string) $num, 'New request alert', $ok, $err, 'request', (int) $req['id']);
    }
    if (!(array) config('whatsapp.admin_numbers')) {
        log_notification('whatsapp', 'no admin number set', 'New request alert', false, 'No WhatsApp admin number configured', 'request', (int) $req['id'], true);
    }
}

/** Send the patient their reference on WhatsApp (only if the Business API is set up). */
function notify_patient_confirmation(array $req, string $firstName, string $phoneE164): void
{
    $template = (string) config('whatsapp.template_patient_confirm');
    if (!whatsapp_enabled() || $template === '') {
        log_notification('whatsapp', $phoneE164, 'Patient confirmation', false, 'WhatsApp Business not configured — reference shown on screen', 'request', (int) $req['id'], true);
        return;
    }
    [$ok, $err] = whatsapp_send_template($phoneE164, $template, [$firstName, $req['reference']]);
    log_notification('whatsapp', $phoneE164, 'Patient confirmation', $ok, $err, 'request', (int) $req['id']);
}

/** Alert staff about volunteer / partner / camp / contact / donation forms. */
function notify_new_submission(array $sub, string $name, string $label): void
{
    $subject = 'New ' . $label . ' — ' . $sub['reference'];
    $body = implode("\n", [
        'A new ' . strtolower($label) . ' has been submitted on the Imara website.',
        '',
        'Reference: ' . $sub['reference'],
        'From:      ' . $name,
        'Summary:   ' . ($sub['summary'] ?? ''),
        '',
        'Open it in the dashboard:',
        base_url('admin/submission.php?id=' . (int) $sub['id']),
        '',
        config('org_name') . ' — ' . config('tagline'),
    ]);
    foreach ((array) config('notify_emails') as $to) {
        [$ok, $err] = send_email((string) $to, $subject, $body);
        log_notification('email', (string) $to, $subject, $ok, $err, 'submission', (int) $sub['id']);
    }
}
