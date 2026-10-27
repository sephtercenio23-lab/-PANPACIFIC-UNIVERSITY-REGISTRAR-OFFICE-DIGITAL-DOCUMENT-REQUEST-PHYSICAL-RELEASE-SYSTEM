<?php
/**
 * actions/otp.php - login by e-mail verification code + server sessions.
 *
 * Port of the Apps Script functions requestOTP(), verifyOTP(), requireSession_()
 * and the CacheService based rate limit. Everything lives in the MySQL
 * database instead of the Google script cache.
 */

/* =========================================================================
   Rate limiting (short lived keys)
   ========================================================================= */

/** Seconds left before $key may be used again (0 = not limited). */
function rate_limit_remaining($key)
{
    $expires = db_value('SELECT ExpiresAt FROM rate_limits WHERE RateKey = ?', [$key]);
    if ($expires === null) {
        return 0;
    }

    $date = to_datetime($expires);
    if ($date === null) {
        return 0;
    }

    $remaining = $date->getTimestamp() - app_now()->getTimestamp();
    if ($remaining <= 0) {
        db_exec('DELETE FROM rate_limits WHERE RateKey = ?', [$key]);
        return 0;
    }

    return $remaining;
}

/** Start (or restart) the cooldown of $key. */
function rate_limit_start($key, $seconds)
{
    $now = app_now();
    $expires = $now->modify('+' . max(1, (int) $seconds) . ' seconds');

    db_exec('DELETE FROM rate_limits WHERE RateKey = ?', [$key]);
    db_exec(
        'INSERT INTO rate_limits (RateKey, ExpiresAt, CreatedAt) VALUES (?, ?, ?)',
        [$key, $expires->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]
    );
}

/* =========================================================================
   OTP request / verification
   ========================================================================= */

/**
 * Apps Script: requestOTP(email)
 *
 * @return array{success:bool,token:string,maskedEmail:string}
 */
function act_requestOTP($email)
{
    $email = normalize_email($email);

    if (!is_valid_email($email)) {
        fail('Please enter a valid email address.');
    }

    $knownStudent = db_value('SELECT StudentID FROM students WHERE LOWER(Email) = ? LIMIT 1', [$email]);
    $knownStaff = db_value('SELECT UserID FROM users WHERE LOWER(Email) = ? AND Active <> 0 LIMIT 1', [$email]);
    $domain = preg_quote((string) config('university_email_domain', 'panpacificu.edu.ph'), '/');
    $universityEmail = (bool) preg_match('/@' . $domain . '$/i', $email);

    if (!$universityEmail && !$knownStudent && !$knownStaff) {
        fail('Use your Panpacific University email. Alumni/former students may use a personal email only if it is already registered with the Registrar.');
    }

    // Same 60 second throttle as the Apps Script cache key OTP_RATE_<email>.
    $rateKey = 'OTP_RATE_' . $email;
    if (rate_limit_remaining($rateKey) > 0) {
        fail('Please wait before requesting another code.');
    }

    $token = uuid4();
    $code = random_number(6);
    $now = app_now();
    $expires = $now->modify('+' . (int) config('otp_expiry_seconds', 600) . ' seconds');

    db_exec('DELETE FROM otp_tokens WHERE Token = ?', [$token]);
    db_exec(
        'INSERT INTO otp_tokens (Token, Email, CodeHash, Attempts, ExpiresAt, CreatedAt) VALUES (?, ?, ?, ?, ?, ?)',
        [
            $token,
            $email,
            password_hash($code, PASSWORD_DEFAULT),
            0,
            $expires->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
        ]
    );

    $body = "Your Registrar Portal verification code is: " . $code . "\r\n\r\n"
        . "This code expires in 10 minutes.\r\n\r\n"
        . "If you did not request access, ignore this message.\r\n\r\n"
        . config('university_name') . "\r\n" . config('office_name');

    $result = mail_send($email, config('university_name') . ' Registrar Portal Verification Code', $body);
    notification_log('', $email, 'OTP_CODE', config('university_name') . ' Registrar Portal Verification Code', $body, $result['status']);

    if (!$result['ok']) {
        db_exec('DELETE FROM otp_tokens WHERE Token = ?', [$token]);
        fail('The verification code could not be sent right now. Please try again later.');
    }

    rate_limit_start($rateKey, (int) config('otp_rate_limit_seconds', 60));

    $response = [
        'success'     => true,
        'token'       => $token,
        'maskedEmail' => mask_email($email),
    ];

    // Only for test deployments where e-mail is not configured yet.
    if ((bool) config('dev_show_otp', false)) {
        $response['devCode'] = $code;
    }

    return $response;
}

/**
 * Apps Script: verifyOTP(token, code, fullName, studentNumber, studentType)
 *
 * @return array{success:bool,session:string,user:array<string,string>}
 */
function act_verifyOTP($token, $code, $fullName = '', $studentNumber = '', $studentType = '')
{
    $token = trim((string) $token);
    if ($token === '') {
        fail('The verification code has expired. Please request a new one.');
    }

    $row = db_one('SELECT Token, Email, CodeHash, Attempts, ExpiresAt FROM otp_tokens WHERE Token = ? LIMIT 1', [$token]);
    if ($row === null) {
        fail('The verification code has expired. Please request a new one.');
    }

    $expires = to_datetime($row['ExpiresAt']);
    if ($expires === null || $expires->getTimestamp() <= app_now()->getTimestamp()) {
        db_exec('DELETE FROM otp_tokens WHERE Token = ?', [$token]);
        fail('The verification code has expired. Please request a new one.');
    }

    $submitted = trim((string) $code);
    if ($submitted === '' || !password_verify($submitted, (string) $row['CodeHash'])) {
        $attempts = (int) $row['Attempts'] + 1;
        if ($attempts >= (int) config('otp_max_attempts', 5)) {
            db_exec('DELETE FROM otp_tokens WHERE Token = ?', [$token]);
        } else {
            db_exec('UPDATE otp_tokens SET Attempts = ? WHERE Token = ?', [$attempts, $token]);
        }
        fail('Incorrect verification code.');
    }

    db_exec('DELETE FROM otp_tokens WHERE Token = ?', [$token]);

    $email = (string) $row['Email'];
    $student = get_or_create_student($email, $fullName, $studentNumber, $studentType);

    $sessionId = uuid4();
    $payload = [
        'email'         => $student['email'],
        'name'          => $student['name'],
        'studentNumber' => $student['studentNumber'],
        'studentType'   => $student['studentType'],
        'campus'        => $student['campus'],
        'role'          => 'Student',
    ];

    $now = app_now();
    $expires = $now->modify('+' . (int) config('session_expiry_seconds', 3600) . ' seconds');

    db_exec(
        'INSERT INTO sessions (SessionID, Email, Payload, ExpiresAt, CreatedAt) VALUES (?, ?, ?, ?, ?)',
        [
            $sessionId,
            $email,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $expires->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
        ]
    );

    return [
        'success' => true,
        'session' => $sessionId,
        'user'    => $student,
    ];
}

/* =========================================================================
   Sessions
   ========================================================================= */

/**
 * Apps Script: requireSession_(session)
 *
 * @return array<string,string>
 */
function require_session($session)
{
    $session = trim((string) $session);
    if ($session === '') {
        fail('Session expired. Please log in again.');
    }

    $row = db_one('SELECT Payload, ExpiresAt FROM sessions WHERE SessionID = ? LIMIT 1', [$session]);
    if ($row === null) {
        fail('Session expired. Please log in again.');
    }

    $expires = to_datetime($row['ExpiresAt']);
    if ($expires === null || $expires->getTimestamp() <= app_now()->getTimestamp()) {
        db_exec('DELETE FROM sessions WHERE SessionID = ?', [$session]);
        fail('Session expired. Please log in again.');
    }

    $payload = json_decode((string) $row['Payload'], true);
    if (!is_array($payload)) {
        fail('Session expired. Please log in again.');
    }

    return $payload;
}

/** Optional helper: end a session immediately. */
function act_logout($session)
{
    $session = trim((string) $session);
    if ($session !== '') {
        db_exec('DELETE FROM sessions WHERE SessionID = ?', [$session]);
    }
    return ['success' => true];
}
