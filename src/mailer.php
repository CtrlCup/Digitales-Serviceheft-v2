<?php
declare(strict_types=1);

/**
 * Simple SMTP mailer with STARTTLS/SSL support and fallback to PHP mail().
 * Configure via constants in config.php. Example keys are in config.example.php.
 */

/**
 * Send an email.
 *
 * @param string $toEmail Recipient email
 * @param string $toName  Recipient name (optional)
 * @param string $subject Subject line (UTF-8)
 * @param string $htmlBody HTML body (UTF-8). If empty, textBody will be used as plain text.
 * @param string $textBody Plain text body fallback (UTF-8). If empty, a text version will be derived from HTML.
 * @param array  $headers  Additional headers as associative array [Header-Name => value]
 * @return bool true on success, false on failure
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody = '', string $textBody = '', array $headers = []): bool {
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ('no-reply@' . (defined('APP_DOMAIN') ? APP_DOMAIN : 'localhost'));
    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'App');

    // Prepare MIME message
    $boundary = 'bnd_' . bin2hex(random_bytes(8));
    $charset = 'UTF-8';
    if ($textBody === '' && $htmlBody !== '') {
        $textBody = html_to_text($htmlBody);
    }

    $baseHeaders = array_merge([
        'From' => format_address($fromEmail, $fromName),
        'To' => format_address($toEmail, $toName),
        'Subject' => encode_header($subject, $charset),
        'MIME-Version' => '1.0',
        'Date' => date('r'),
    ], $headers);

    $body = '';
    $contentType = '';

    if ($htmlBody !== '') {
        $contentType = "multipart/alternative; boundary=\"$boundary\"";
        $body = "--$boundary\r\n" .
                "Content-Type: text/plain; charset=$charset\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n" .
                chunk_split(base64_encode($textBody ?: '')) .
                "--$boundary\r\n" .
                "Content-Type: text/html; charset=$charset\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n" .
                chunk_split(base64_encode($htmlBody)) .
                "--$boundary--\r\n";
    } else {
        $contentType = "text/plain; charset=$charset";
        $body = $textBody;
    }

    $baseHeaders['Content-Type'] = $contentType;

    // Choose transport
    $hasSmtpConfig = defined('SMTP_HOST') && SMTP_HOST && defined('SMTP_PORT');
    try {
        if ($hasSmtpConfig) {
            return smtp_send($toEmail, $baseHeaders, $body);
        }
        // Fallback to mail()
        return mail_send($toEmail, $baseHeaders, $body);
    } catch (Throwable $e) {
        // last resort fallback to mail()
        try { return mail_send($toEmail, $baseHeaders, $body); } catch (Throwable $e2) { return false; }
    }
}

function format_address(string $email, string $name = ''): string {
    $email = trim($email);
    $name = trim($name);
    if ($name === '') { return $email; }
    // Encode display name if needed
    $encodedName = encode_header($name, 'UTF-8');
    return "$encodedName <{$email}>";
}

function encode_header(string $value, string $charset = 'UTF-8'): string {
    if (!preg_match('/[\x80-\xFF]/', $value)) {
        return $value; // ASCII only
    }
    return '=?' . $charset . '?B?' . base64_encode($value) . '?=';
}

function html_to_text(string $html): string {
    // remove scripts/styles
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', $html) ?? $html;
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

function mail_send(string $toEmail, array $headers, string $body): bool {
    $subject = $headers['Subject'] ?? '(no subject)';
    unset($headers['Subject']);

    // Build headers string
    $headerLines = [];
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'to') { continue; }
        $headerLines[] = $k . ': ' . $v;
    }
    $headersStr = implode("\r\n", $headerLines);
    return @mail($toEmail, $subject, $body, $headersStr);
}

function smtp_send(string $toEmail, array $headers, string $body): bool {
    $host = SMTP_HOST;
    $port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
    $user = defined('SMTP_USER') ? (string)SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
    $enc  = defined('SMTP_ENCRYPTION') ? strtolower((string)SMTP_ENCRYPTION) : 'tls'; // tls|ssl|none
    $timeout = 15;

    $remote = $host;
    $contextOptions = [];
    if ($enc === 'ssl') {
        $remote = 'ssl://' . $host;
        $contextOptions['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];
    }

    $context = stream_context_create($contextOptions);
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        throw new RuntimeException('SMTP connect failed: ' . $errstr);
    }
    stream_set_timeout($fp, $timeout);

    $expect = function (string $prefix) use ($fp): void {
        $line = '';
        while (($l = fgets($fp, 515)) !== false) {
            $line .= $l;
            if (isset($l[3]) && $l[3] !== '-') break; // last line of response
        }
        if (strpos($line, $prefix) !== 0) {
            throw new RuntimeException('SMTP unexpected response: ' . trim($line));
        }
    };

    $send = function (string $cmd) use ($fp): void { fwrite($fp, $cmd . "\r\n"); };

    $expect('220');
    $send('EHLO ' . (defined('APP_DOMAIN') ? APP_DOMAIN : 'localhost'));
    $expect('250');

    if ($enc === 'tls') {
        $send('STARTTLS');
        $expect('220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        // EHLO again after STARTTLS
        $send('EHLO ' . (defined('APP_DOMAIN') ? APP_DOMAIN : 'localhost'));
        $expect('250');
    }

    if ($user !== '' || $pass !== '') {
        $send('AUTH LOGIN');
        $expect('334');
        $send(base64_encode($user));
        $expect('334');
        $send(base64_encode($pass));
        $expect('235');
    }

    // MAIL FROM
    $from = $headers['From'] ?? ('no-reply@' . (defined('APP_DOMAIN') ? APP_DOMAIN : 'localhost'));
    // Extract address inside <>
    if (preg_match('/<([^>]+)>/', $from, $m)) { $from = $m[1]; }

    $send('MAIL FROM: <' . $from . '>');
    $expect('250');

    // RCPT TO
    $send('RCPT TO: <' . $toEmail . '>');
    $expect('250');

    // DATA
    $send('DATA');
    $expect('354');

    // Build full headers including To (some servers require it)
    $lines = [];
    foreach ($headers as $k => $v) { $lines[] = $k . ': ' . $v; }
    $data = implode("\r\n", $lines) . "\r\n\r\n" . $body . "\r\n.";

    $send($data);
    $expect('250');

    $send('QUIT');
    fclose($fp);
    return true;
}
