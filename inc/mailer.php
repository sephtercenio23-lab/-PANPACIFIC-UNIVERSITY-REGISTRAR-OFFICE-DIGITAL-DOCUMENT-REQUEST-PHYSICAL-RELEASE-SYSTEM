<?php
/**
 * mailer.php - outgoing e-mail.
 *
 * InfinityFree blocks PHP mail() and only allows outbound SMTP on port 587
 * with STARTTLS, so a small dependency-free SMTP client is included here.
 * No Composer, no PHPMailer, no external library to upload.
 *
 * Modes (config.php -> mail.mode):
 *   smtp : real delivery through the configured SMTP account
 *   mail : PHP mail() (paid hosting / XAMPP / local)
 *   log  : nothing is sent, the message is stored in the notifications table
 */

/**
 * @return array{ok:bool,status:string,error:string}
 */
function mail_send($to, $subject, $body)
{
    $to = trim((string) $to);
    $subject = (string) $subject;
    $body = (string) $body;

    if (!is_valid_email($to)) {
        return ['ok' => false, 'status' => 'Failed', 'error' => 'Invalid recipient address.'];
    }

    $mode = strtolower((string) config('mail.mode', 'log'));
    $host = trim((string) config('mail.host', ''));

    if ($mode === 'smtp' && $host === '') {
        // SMTP not configured yet - do not lose the message.
        $mode = 'log';
    }

    try {
        if ($mode === 'smtp') {
            smtp_send($to, $subject, $body);
            return ['ok' => true, 'status' => 'Sent', 'error' => ''];
        }

        if ($mode === 'mail') {
            $headers = mail_headers();
            $sent = @mail($to, mail_encode_header($subject), $body, $headers);
            return $sent
                ? ['ok' => true, 'status' => 'Sent', 'error' => '']
                : ['ok' => false, 'status' => 'Failed', 'error' => 'PHP mail() returned false.'];
        }

        // log mode: keep a copy in the database, send nothing.
        return ['ok' => true, 'status' => 'Logged', 'error' => ''];
    } catch (Exception $e) {
        return ['ok' => false, 'status' => 'Failed', 'error' => $e->getMessage()];
    }
}

/** RFC 2047 encoded header value (safe for UTF-8 subjects). */
function mail_encode_header($value)
{
    $value = str_replace(["\r", "\n"], '', (string) $value);
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

/** From/To/Date/Message-ID headers used by both SMTP and mail(). */
function mail_headers()
{
    $fromEmail = trim((string) config('mail.from_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $fromName = (string) config('mail.from_name', (string) config('university_name', 'Registrar'));

    return [
        'from_email' => $fromEmail,
        'from_name'  => $fromName,
        'date'       => gmdate('D, d M Y H:i:s') . ' +0000',
        'message_id' => '<' . bin2hex(random_bytes(12)) . '@' . (strpos($fromEmail, '@') !== false ? substr($fromEmail, strpos($fromEmail, '@') + 1) : 'localhost') . '>',
    ];
}

/** Build the complete RFC 5322 message used by the SMTP client. */
function mail_build_message($to, $subject, $body)
{
    $headers = mail_headers();
    $from = $headers['from_name'] !== ''
        ? mail_encode_header($headers['from_name']) . ' <' . $headers['from_email'] . '>'
        : $headers['from_email'];

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);

    return implode("\r\n", [
        'Date: ' . $headers['date'],
        'From: ' . $from,
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: ' . $headers['message_id'],
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: PPU-Registrar-PHP',
        '',
        $body,
    ]);
}

/* =========================================================================
   Minimal SMTP client (STARTTLS on 587, implicit TLS on 465)
   ========================================================================= */

function smtp_send($to, $subject, $body)
{
    $host     = (string) config('mail.host');
    $port     = (int) config('mail.port', 587);
    $enc      = strtolower((string) config('mail.encryption', 'tls'));
    $username = (string) config('mail.username', '');
    $password = (string) config('mail.password', '');
    $timeout  = max(3, min(8, (int) config('mail.timeout', 6)));

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'SNI_enabled'      => true,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new RuntimeException('SMTP connection to ' . $host . ':' . $port . ' failed. ' . ($errstr !== '' ? $errstr : ''));
    }
    stream_set_timeout($socket, $timeout);

    $hostname = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST);
    if (!$hostname) {
        $hostname = 'localhost';
    }

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . $hostname, [250]);

        if ($enc === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (@stream_socket_enable_crypto($socket, true, $crypto) !== true) {
                throw new RuntimeException('Could not start TLS with the SMTP server.');
            }
            smtp_command($socket, 'EHLO ' . $hostname, [250]);
        }

        if ($username !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);
        }

        $headers = mail_headers();
        smtp_command($socket, 'MAIL FROM:<' . $headers['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $message = mail_build_message($to, $subject, $body);
        // Dot-stuffing, as required by RFC 5321.
        $message = preg_replace('/^\./m', '..', $message);
        smtp_write($socket, $message . "\r\n.");
        smtp_expect($socket, [250]);

        try {
            smtp_command($socket, 'QUIT', [221]);
        } catch (Exception $e) {
            // The message was already accepted - a failed QUIT is not an error.
        }
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}

function smtp_write($socket, $data)
{
    $written = @fwrite($socket, $data . "\r\n");
    if ($written === false) {
        throw new RuntimeException('The SMTP connection was closed unexpectedly.');
    }
}

function smtp_read($socket)
{
    $response = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    if ($response === '') {
        throw new RuntimeException('The SMTP server did not respond.');
    }
    return $response;
}

function smtp_expect($socket, array $expectedCodes)
{
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim(preg_replace('/\s+/', ' ', $response)));
    }
    return $response;
}

function smtp_command($socket, $command, array $expectedCodes)
{
    smtp_write($socket, $command);
    return smtp_expect($socket, $expectedCodes);
}
