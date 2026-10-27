<?php
/**
 * helpers.php - configuration access, formatting helpers and shared errors.
 */

/** Thrown for expected (user facing) problems. The message is shown to the
 *  browser exactly as the Apps Script version did with `throw new Error()`. */
class ApiError extends RuntimeException
{
}

/**
 * Read the application configuration (config.php overridden by an optional
 * config.local.php).
 *
 * @param string|null $key     Dot-notation path, e.g. "db.host".
 * @param mixed       $default Value returned when the key is missing.
 * @return mixed
 */
function config($key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $root = dirname(__DIR__);
        $file = $root . '/config.php';

        if (!is_file($file)) {
            throw new ApiError('config.php is missing. Upload the complete project to your hosting account.');
        }

        $loaded = require $file;
        if (!is_array($loaded)) {
            throw new ApiError('config.php must return an array. Check the file for syntax errors.');
        }

        $localFile = $root . '/config.local.php';
        if (is_file($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                $loaded = array_replace_recursive($loaded, $local);
            }
        }

        $config = $loaded;
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/** Current date/time in the application timezone (Asia/Manila). */
function app_now()
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone((string) config('timezone', 'Asia/Manila'));
    }
    return new DateTimeImmutable('now', $tz);
}

/** Convert a stored value (string or DateTimeInterface) into a DateTimeImmutable. */
function to_datetime($value)
{
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value);
    }
    if ($value === null || $value === '' || $value === false) {
        return null;
    }

    try {
        return new DateTimeImmutable((string) $value, new DateTimeZone((string) config('timezone', 'Asia/Manila')));
    } catch (Exception $e) {
        return null;
    }
}

/** Apps Script: Utilities.formatDate(date, tz, 'MMM d, yyyy') */
function format_date($value)
{
    $date = to_datetime($value);
    return $date === null ? '' : $date->format('M j, Y');
}

/** Apps Script: Utilities.formatDate(date, tz, 'MMM d, yyyy h:mm a') */
function format_datetime($value)
{
    $date = to_datetime($value);
    return $date === null ? '' : $date->format('M j, Y g:i a');
}

/** Apps Script helper: addBusinessDays_(date, days) - weekdays only. */
function add_business_days(DateTimeImmutable $date, $days)
{
    $days = max(0, (int) $days);
    $result = $date;
    $count = 0;
    while ($count < $days) {
        $result = $result->modify('+1 day');
        $weekday = (int) $result->format('N'); // 1 = Monday ... 7 = Sunday
        if ($weekday < 6) {
            $count++;
        }
    }
    return $result;
}

/** Apps Script helper: normalizeEmail_() */
function normalize_email($email)
{
    return strtolower(trim((string) $email));
}

/** Apps Script helper: isValidEmail_() */
function is_valid_email($email)
{
    return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', (string) $email);
}

/** Apps Script helper: maskEmail_() */
function mask_email($email)
{
    $parts = explode('@', (string) $email, 2);
    $local = isset($parts[0]) ? $parts[0] : '';
    $domain = isset($parts[1]) ? $parts[1] : '';
    $visible = mb_strlen($local) <= 2 ? mb_substr($local, 0, 1) . '*' : mb_substr($local, 0, 2) . '***';
    return $visible . '@' . $domain;
}

/** RFC 4122 v4 UUID (Apps Script: Utilities.getUuid()). */
function uuid4()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/** Short random identifier such as "STU-9F3C1A2B". */
function short_id($prefix, $length = 8)
{
    $raw = strtoupper(substr(bin2hex(random_bytes(8)), 0, $length));
    return $prefix . $raw;
}

/** Random numeric string, e.g. random_number(6) => "482913". */
function random_number($digits)
{
    $digits = max(1, (int) $digits);
    $min = (int) (10 ** ($digits - 1));
    $max = (int) (10 ** $digits) - 1;
    return str_pad((string) random_int($min, $max), $digits, '0', STR_PAD_LEFT);
}

/** Throw a user-facing error (Apps Script: throw new Error(message)). */
function fail($message)
{
    throw new ApiError((string) $message);
}

/** Trim and cut a string so it always fits its database column. */
function limit_text($value, $length)
{
    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}

/** Client IP address (used only for abuse diagnostics). */
function request_ip()
{
    $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $value = explode(',', (string) $_SERVER[$key])[0];
            $value = trim($value);
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }
    }
    return '';
}

/** Send a JSON payload and stop. */
function json_response($payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send an error payload using the Apps Script failure-handler format. */
function json_error($message, $status = 200, $name = 'Error')
{
    json_response([
        'ok'    => false,
        'error' => [
            'name'    => $name,
            'message' => (string) $message,
        ],
    ], $status);
}
