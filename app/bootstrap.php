<?php
/**
 * Loaded by every API endpoint and admin page.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Imara requires PHP 8.1 or newer.');
}

define('IMARA_ROOT', dirname(__DIR__));
define('IMARA_APP', __DIR__);

$GLOBALS['IMARA_CONFIG'] = require __DIR__ . '/config.php';

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['IMARA_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) config('timezone', 'Africa/Kampala'));

$isDev = config('env') === 'development';
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting(E_ALL);

foreach ([config('log_dir'), config('backup_dir'), dirname((string) config('db_path'))] as $dir) {
    if ($dir && !is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}
ini_set('log_errors', '1');
ini_set('error_log', rtrim((string) config('log_dir'), '/\\') . '/php-error.log');

foreach (['pdo_sqlite', 'openssl', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        error_log("Imara: missing PHP extension $ext");
        exit('Server setup incomplete: PHP extension "' . $ext . '" is not enabled.');
    }
}

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/crypto.php';
require __DIR__ . '/lib/options.php';
require __DIR__ . '/lib/audit.php';
require __DIR__ . '/lib/mailer.php';
require __DIR__ . '/lib/whatsapp.php';
require __DIR__ . '/lib/notify.php';
require __DIR__ . '/lib/requests.php';
require __DIR__ . '/lib/submissions.php';
require __DIR__ . '/lib/auth.php';

if (config('app_key') === '' && PHP_SAPI !== 'cli') {
    http_response_code(503);
    error_log('Imara: app_key missing — run php tools/setup.php');
    exit('The site is not set up yet. Please run: php tools/setup.php');
}
