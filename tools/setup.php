<?php
/**
 * First-time setup (run on the server or your computer):
 *
 *   php tools/setup.php                 create config + database, then ask for the first admin
 *   php tools/setup.php --dev           same, for local testing (shows errors, emails go to data/logs/mail.log)
 *   php tools/setup.php --admin-email=you@example.org --admin-name="Your Name" --admin-password="..."
 *
 * Safe to run again: it never overwrites an existing config.local.php or app_key.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$localConfig = $root . '/app/config.local.php';
$args = getopt('', ['dev', 'admin-email:', 'admin-name:', 'admin-password:', 'base-url:']);
$dev = isset($args['dev']);

if (!is_file($localConfig)) {
    $key = base64_encode(random_bytes(32));
    $baseUrl = $args['base-url'] ?? ($dev ? 'http://127.0.0.1:8090' : 'https://www.imaraafrika.org');
    $env = $dev ? 'development' : 'production';
    $php = <<<PHP
<?php
/**
 * Imara — private settings for THIS server. Never commit, email or share this file.
 * Keep an offline copy of app_key: without it, encrypted patient data in backups cannot be read.
 */
return [
    'env' => '$env',
    'base_url' => '$baseUrl',
    'app_key' => '$key',

    // Who receives "New Patient Request" emails
    'notify_emails' => ['hello@imaraafrika.org'],

    // Email delivery: 'log' (testing), 'mail' (PHP mail on Hostinger), or 'smtp'
    'mail_transport' => 'log',
    'smtp' => [
        'host' => '',          // e.g. smtp.hostinger.com
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',      // e.g. hello@imaraafrika.org
        'password' => '',
    ],

    // Official WhatsApp Business Platform (Meta Cloud API) — see docs/WHATSAPP-TEMPLATES.md
    'whatsapp' => [
        'token' => '',
        'phone_number_id' => '',
        'admin_numbers' => [],               // e.g. ['256777106608']
        'template_new_request' => '',        // e.g. 'imara_new_request'
        'template_patient_confirm' => '',    // e.g. 'imara_request_received'
    ],
];

PHP;
    file_put_contents($localConfig, $php);
    @chmod($localConfig, 0600);
    echo "Created app/config.local.php (env: $env)\n";
} else {
    echo "app/config.local.php already exists — left unchanged.\n";
}

require $root . '/app/bootstrap.php';

$pdo = db();
echo 'Database ready at ' . realpath((string) config('db_path')) . ' (schema v' . $pdo->query('PRAGMA user_version')->fetchColumn() . ")\n";

$admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($admins > 0 && !isset($args['admin-email'])) {
    echo "An administrator already exists. Setup complete.\n";
    exit(0);
}

$ask = function (string $q, bool $hidden = false): string {
    echo $q;
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $v;
    }
    return trim((string) fgets(STDIN));
};

$email = $args['admin-email'] ?? $ask('First administrator email: ');
$name = $args['admin-name'] ?? $ask('Their full name: ');
$password = $args['admin-password'] ?? $ask('Password (min 10 characters, letters and a number): ', true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "That email is not valid.\n");
    exit(1);
}
if ($problem = password_problem($password)) {
    fwrite(STDERR, "Password: $problem\n");
    exit(1);
}
try {
    $id = create_user($name ?: 'Administrator', $email, $password, 'admin', false);
} catch (PDOException $ex) {
    fwrite(STDERR, "Could not create the user (does that email already exist?)\n");
    exit(1);
}
audit('user_create', 'user', $id, 'first administrator via setup');
echo "Administrator created: $email\nSign in at " . base_url('admin/') . "\n";
