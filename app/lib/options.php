<?php
declare(strict_types=1);

/** Dropdown lists shared with the HTML forms (config/form-options.json). */
function form_options(): array
{
    static $opts = null;
    if ($opts === null) {
        $opts = json_decode((string) file_get_contents(IMARA_ROOT . '/config/form-options.json'), true) ?: [];
    }
    return $opts;
}

/** Flat list of allowed values for a key. */
function option_values(string $key): array
{
    $o = form_options()[$key] ?? [];
    if (isset($o['groups'])) {
        $vals = [];
        foreach ($o['groups'] as $g) {
            foreach ($g['options'] as $v) {
                $vals[] = $v;
            }
        }
        return $vals;
    }
    $vals = [];
    foreach ($o['options'] ?? [] as $v) {
        $vals[] = is_array($v) ? ($v['code'] ?? '') : $v;
    }
    return $vals;
}

function option_default(string $key): string
{
    return (string) (form_options()[$key]['default'] ?? '');
}

/** Value if allowed, otherwise the default (for optional dropdowns). */
function pick_option(string $key, string $value): string
{
    return in_array($value, option_values($key), true) ? $value : option_default($key);
}

function is_option(string $key, string $value): bool
{
    return in_array($value, option_values($key), true);
}

/** Which group (e.g. "Gulu City divisions") a location belongs to. */
function location_group(string $location): string
{
    foreach (form_options()['locations']['groups'] ?? [] as $g) {
        if (in_array($location, $g['options'], true)) {
            return $g['label'];
        }
    }
    return '';
}

/**
 * Normalise a phone number. Returns E.164 digits without "+" (e.g. 256772123456) or null.
 */
function normalise_phone(string $raw, string $code = '+256'): ?string
{
    $cc = ltrim($code, '+');
    if (!in_array('+' . $cc, option_values('phone_countries'), true)) {
        $cc = '256';
    }
    $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
    if (str_starts_with($digits, '+')) {
        $digits = substr($digits, 1);
        if (str_starts_with($digits, $cc)) {
            $digits = substr($digits, strlen($cc));
        }
    } elseif (str_starts_with($digits, $cc) && strlen($digits) > 10) {
        $digits = substr($digits, strlen($cc));
    }
    $digits = ltrim($digits, '0');
    if ($cc === '256') {
        return preg_match('/^[347]\d{8}$/', $digits) ? $cc . $digits : null;
    }
    return preg_match('/^\d{7,12}$/', $digits) ? $cc . $digits : null;
}

function format_phone(string $e164): string
{
    if (str_starts_with($e164, '256') && strlen($e164) === 12) {
        return '+256 ' . substr($e164, 3, 3) . ' ' . substr($e164, 6, 3) . ' ' . substr($e164, 9);
    }
    return '+' . $e164;
}
