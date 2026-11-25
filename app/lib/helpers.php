<?php
declare(strict_types=1);

/** HTML-escape for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Current time as ISO-8601 in UTC (stored in the database). */
function now_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/** Show a stored UTC time in Kampala time. */
function local_time(?string $utc, string $format = 'D j M Y, H:i'): string
{
    if (!$utc) {
        return '—';
    }
    $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone((string) config('timezone')))->format($format);
}

function str_clean(mixed $value, int $max = 500): string
{
    $s = is_string($value) ? $value : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = trim(preg_replace('/[ \t]+/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function input(string $key, int $max = 500): string
{
    return str_clean($_POST[$key] ?? '', $max);
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers can be faked by the client.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** We never store raw IP addresses for public submissions, only a keyed hash. */
function ip_hash(): string
{
    return blind_index('ip|' . client_ip());
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        if (wants_json()) {
            json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
        }
        http_response_code(405);
        exit('Method not allowed');
    }
}

/**
 * Simple fixed-window rate limit stored in SQLite.
 * Returns true when the caller is still under the limit (and counts this hit).
 */
function rate_limit_hit(string $bucket, int $max, int $windowSeconds): bool
{
    $pdo = db();
    $key = $bucket . ':' . ip_hash();
    $now = time();
    $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);
    $row = $pdo->prepare('SELECT window_start, hits FROM rate_limits WHERE rkey = ?');
    $row->execute([$key]);
    $r = $row->fetch();
    if (!$r || (int) $r['window_start'] < $now - $windowSeconds) {
        $pdo->prepare('REPLACE INTO rate_limits (rkey, window_start, hits) VALUES (?, ?, 1)')->execute([$key, $now]);
        return true;
    }
    if ((int) $r['hits'] >= $max) {
        return false;
    }
    $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?')->execute([$key]);
    return true;
}

/** Spam checks shared by every public form. Returns an error message or null. */
function spam_check(): ?string
{
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        return 'Your form could not be sent. Please call or WhatsApp us instead.';
    }
    $started = (int) ($_POST['started_at'] ?? 0);
    if ($started > 0 && (time() - $started) < 3) {
        return 'That was very fast — please check your answers and send again.';
    }
    return null;
}

function setting(string $key, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT value FROM settings WHERE skey = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('REPLACE INTO settings (skey, value) VALUES (?, ?)')->execute([$key, $value]);
}

function base_url(string $path = ''): string
{
    return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
}

/** Very small page for people whose phones submit the form without JavaScript. */
function render_public_message(string $title, string $bodyHtml, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $org = e(config('org_name'));
    $tag = e(config('tagline'));
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} — {$org}</title>
<link rel="icon" type="image/svg+xml" href="../assets/images/ui/favicon.svg">
<link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/imara.css">
</head><body class="home-one">
<section class="imara-form-area soft"><div class="container"><div class="row justify-content-center"><div class="col-lg-8">
<div class="form-card">{$bodyHtml}
<p class="text-center" style="margin-top:30px"><a class="rts-btn btn-secondary" href="../index.html">Back to the Imara website</a></p>
</div><p class="text-center" style="margin-top:20px">{$org} &middot; {$tag}</p>
</div></div></div></section></body></html>
HTML;
    exit;
}
