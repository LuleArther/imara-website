<?php
declare(strict_types=1);

/**
 * Field-level encryption for personal and health data (AES-256-GCM).
 * The key is app_key in config.local.php — keep a safe offline copy of it:
 * without it, encrypted fields in backups cannot be read.
 */
function app_key_bytes(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $raw = base64_decode((string) config('app_key'), true);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException('app_key must be 32 random bytes, base64-encoded. Run php tools/setup.php');
    }
    return $key = $raw;
}

function encrypt_field(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key_bytes(), OPENSSL_RAW_DATA, $iv, $tag, 'imara-v1', 16);
    if ($cipher === false) {
        throw new RuntimeException('Encryption failed');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_field(?string $stored): string
{
    if ($stored === null || $stored === '') {
        return '';
    }
    if (!str_starts_with($stored, 'v1:')) {
        return '';
    }
    $bin = base64_decode(substr($stored, 3), true);
    if ($bin === false || strlen($bin) < 29) {
        return '';
    }
    $iv = substr($bin, 0, 12);
    $tag = substr($bin, 12, 16);
    $cipher = substr($bin, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', app_key_bytes(), OPENSSL_RAW_DATA, $iv, $tag, 'imara-v1');
    return $plain === false ? '[unreadable]' : $plain;
}

/** Keyed hash so staff can search by exact phone number without storing it in plain text. */
function blind_index(string $value): string
{
    $k = hash_hmac('sha256', 'imara-blind-index', app_key_bytes(), true);
    return hash_hmac('sha256', $value, $k);
}
