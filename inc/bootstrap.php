<?php
/**
 * bootstrap.php - loads the whole backend. Required by api.php and setup.php.
 */

define('PPU_ROOT', dirname(__DIR__));
define('PPU_VERSION', '1.0.0');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/actions/documents.php';
require_once __DIR__ . '/actions/otp.php';
require_once __DIR__ . '/actions/requests.php';
require_once __DIR__ . '/actions/evaluations.php';
require_once __DIR__ . '/actions/system.php';

/**
 * Apply the runtime settings that depend on config.php. Safe to call more than
 * once; throws ApiError when config.php is broken/missing.
 */
function app_boot()
{
    static $booted = false;
    if ($booted) {
        return;
    }

    date_default_timezone_set((string) config('timezone', 'Asia/Manila'));
    error_reporting(E_ALL);
    ini_set('display_errors', (bool) config('debug', false) ? '1' : '0');
    ini_set('log_errors', '1');

    $booted = true;
}

/* Any PHP warning/notice becomes an exception, so the API always answers with
   valid JSON instead of a half-printed page. */
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false; // silenced with @
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
