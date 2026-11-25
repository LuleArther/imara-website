<?php
declare(strict_types=1);

/**
 * Official WhatsApp Business Platform (Meta Cloud API) — no unofficial scripts.
 * Business-started messages must use a template approved by Meta.
 * See docs/WHATSAPP-TEMPLATES.md for the exact templates to submit.
 */
function whatsapp_enabled(): bool
{
    return config('whatsapp.token') !== '' && config('whatsapp.phone_number_id') !== '';
}

/**
 * Send an approved template. $params fill {{1}}, {{2}} ... in the template body.
 * Returns [bool sent, string|null error].
 */
function whatsapp_send_template(string $toE164, string $template, array $params): array
{
    if (!whatsapp_enabled()) {
        return [false, 'WhatsApp not configured'];
    }
    if ($template === '') {
        return [false, 'template name not configured'];
    }
    $body = [
        'messaging_product' => 'whatsapp',
        'to' => preg_replace('/\D/', '', $toE164),
        'type' => 'template',
        'template' => [
            'name' => $template,
            'language' => ['code' => (string) config('whatsapp.template_language', 'en')],
            'components' => [[
                'type' => 'body',
                'parameters' => array_map(
                    fn ($p) => ['type' => 'text', 'text' => mb_substr(str_replace(["\n", "\t"], ' ', (string) $p), 0, 200)],
                    array_values($params)
                ),
            ]],
        ],
    ];
    return whatsapp_post($body);
}

function whatsapp_post(array $payload): array
{
    if (!function_exists('curl_init')) {
        return [false, 'PHP curl extension missing'];
    }
    $url = sprintf(
        'https://graph.facebook.com/%s/%s/messages',
        rawurlencode((string) config('whatsapp.api_version', 'v21.0')),
        rawurlencode((string) config('whatsapp.phone_number_id'))
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . config('whatsapp.token'),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [false, 'network: ' . $err];
    }
    if ($status >= 200 && $status < 300) {
        return [true, null];
    }
    $msg = json_decode((string) $resp, true)['error']['message'] ?? ('HTTP ' . $status);
    return [false, (string) $msg];
}
