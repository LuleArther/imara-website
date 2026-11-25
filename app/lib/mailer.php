<?php
declare(strict_types=1);

/**
 * Send a plain-text email. Transport is chosen in config:
 *   log  — write to data/logs/mail.log (testing / demo)
 *   mail — PHP mail() (works on most shared hosting, e.g. Hostinger)
 *   smtp — authenticated SMTP (recommended for reliable delivery)
 * Returns [bool sent, string|null error].
 */
function send_email(string $to, string $subject, string $body, ?string $replyTo = null): array
{
    $transport = (string) config('mail_transport', 'log');
    $from = (string) config('mail_from');
    $fromName = (string) config('mail_from_name');
    $subject = str_replace(["\r", "\n"], ' ', $subject);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'invalid recipient'];
    }

    try {
        if ($transport === 'log') {
            $line = "==== " . now_utc() . "\nTo: $to\nSubject: $subject\n\n$body\n\n";
            file_put_contents(rtrim((string) config('log_dir'), '/\\') . '/mail.log', $line, FILE_APPEND | LOCK_EX);
            return [true, null];
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $headers = [
            'From: ' . $encodedFrom,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Imara',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        if ($transport === 'mail') {
            $ok = mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $from);
            return [$ok, $ok ? null : 'mail() returned false'];
        }

        if ($transport === 'smtp') {
            return smtp_send($to, $encodedSubject, $body, $headers);
        }
        return [false, 'unknown mail transport'];
    } catch (Throwable $ex) {
        error_log('Imara mail error: ' . $ex->getMessage());
        return [false, $ex->getMessage()];
    }
}

function smtp_send(string $to, string $subject, string $body, array $headers): array
{
    $c = (array) config('smtp');
    $host = (string) ($c['host'] ?? '');
    $port = (int) ($c['port'] ?? 587);
    $enc = (string) ($c['encryption'] ?? 'tls');
    $timeout = (int) ($c['timeout'] ?? 15);
    if ($host === '') {
        return [false, 'SMTP host not configured'];
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return [false, "connect failed: $errstr ($errno)"];
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $expect) use ($fp, $read): string {
        if ($command !== '') {
            fwrite($fp, $command . "\r\n");
        }
        $resp = $read();
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $expect, true)) {
            throw new RuntimeException('SMTP error after "' . explode(' ', $command)[0] . '": ' . trim($resp));
        }
        return $resp;
    };

    try {
        $cmd('', [220]);
        $ehloHost = parse_url((string) config('base_url'), PHP_URL_HOST) ?: 'localhost';
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        if (!empty($c['username'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $c['username']), [334]);
            $cmd(base64_encode((string) $c['password']), [235]);
        }
        $from = (string) config('mail_from');
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = implode("\r\n", array_merge($headers, [
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
        ])) . "\r\n\r\n";
        // dot-stuffing and CRLF line endings
        $bodyLines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        foreach ($bodyLines as $line) {
            $msg .= (str_starts_with($line, '.') ? '.' : '') . $line . "\r\n";
        }
        $cmd($msg . '.', [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return [true, null];
    } catch (Throwable $ex) {
        @fclose($fp);
        return [false, $ex->getMessage()];
    }
}
