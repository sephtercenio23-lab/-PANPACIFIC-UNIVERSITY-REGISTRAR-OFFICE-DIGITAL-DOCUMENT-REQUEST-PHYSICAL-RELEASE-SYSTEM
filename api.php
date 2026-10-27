<?php
/**
 * api.php - the only endpoint the browser talks to.
 *
 * It replaces google.script.run. The SERVE form does not know it exists: the
 * bridge in assets/serve-bootstrap.js forwards every google.script.run call
 * here as {"action":"functionName","args":[...]}.
 *
 * Examples
 *   POST api.php  {"action":"getStaff","args":[]}
 *   POST api.php  {"action":"submitEvaluation","args":[{"studentType":"Present Student",...}]}
 *   GET  api.php?action=health
 */

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/router.php';

/* Last resort: never answer with a half rendered page or a stack trace. */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatal, true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok'    => false,
        'error' => [
            'name'    => 'ServerError',
            'message' => 'The server could not complete the request. Please try again.',
        ],
    ]);
});

api_dispatch();
