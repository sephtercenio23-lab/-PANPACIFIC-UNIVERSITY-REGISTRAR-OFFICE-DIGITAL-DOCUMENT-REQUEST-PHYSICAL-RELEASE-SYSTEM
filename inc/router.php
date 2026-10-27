<?php
/**
 * router.php - maps a request action onto a backend function.
 *
 * The action name is the Apps Script function name, so the frontend can call
 * exactly the same functions it always called:
 *
 *   POST api.php  {"action":"getStaff","args":[]}
 *   POST api.php  {"action":"submitEvaluation","args":[{...payload...}]}
 *
 * Success -> {"ok":true,"result":...}
 * Failure -> {"ok":false,"error":{"name":"Error","message":"..."}}
 */

/** Whitelist of callable actions (no user supplied function names are used). */
function api_action_map()
{
    return [
        /* --- SERVE evaluation form (called by index.html) --- */
        'getStaff'             => 'act_getStaff',
        'submitEvaluation'     => 'act_submitEvaluation',
        'getEvaluationSummary' => 'act_getEvaluationSummary',

        /* --- Digital document request system (Code.gs port) --- */
        'getPublicConfig'      => 'act_getPublicConfig',
        'getDocumentTypes'     => 'act_getDocumentTypes',
        'requestOTP'           => 'act_requestOTP',
        'verifyOTP'            => 'act_verifyOTP',
        'logout'               => 'act_logout',
        'submitRequest'        => 'act_submitRequest',
        'getStudentRequests'   => 'act_getStudentRequests',

        /* --- Maintenance --- */
        'health'               => 'act_health',
        'setupSystem'          => 'act_setupSystem',
    ];
}

/**
 * Read the requested action and its positional arguments.
 *
 * @return array{0:string,1:array<int,mixed>}
 */
function api_read_request()
{
    $action = '';
    $args = [];

    $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
    $raw = file_get_contents('php://input');

    if ($raw !== false && $raw !== '' && strpos($contentType, 'json') !== false) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            fail('Malformed request body.');
        }
        $action = isset($decoded['action']) ? (string) $decoded['action'] : '';
        if (isset($decoded['args'])) {
            $args = $decoded['args'];
        } elseif (isset($decoded['payload'])) {
            $args = [$decoded['payload']];
        }
    } else {
        $source = $_POST;
        if (!empty($source)) {
            $action = isset($source['action']) ? (string) $source['action'] : '';
            if (isset($source['args'])) {
                $args = is_array($source['args']) ? $source['args'] : json_decode((string) $source['args'], true);
            } elseif (isset($source['payload'])) {
                $args = is_array($source['payload']) ? [$source['payload']] : [json_decode((string) $source['payload'], true)];
            }
        } else {
            // GET is only used for quick manual checks in a browser.
            $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
            if (isset($_GET['args'])) {
                $args = json_decode((string) $_GET['args'], true);
            }
        }
    }

    if (!is_array($args)) {
        $args = [];
    }
    // Keep the argument list small and predictable.
    $args = array_slice(array_values($args), 0, 10);

    return [$action, $args];
}

/** Run one API call and print the JSON result. */
function api_dispatch()
{
    ob_start();

    try {
        app_boot();

        list($action, $args) = api_read_request();
        $action = trim($action);

        if ($action === '') {
            throw new ApiError('No action was specified.');
        }

        $map = api_action_map();
        if (!isset($map[$action])) {
            throw new ApiError('Unknown action: ' . $action);
        }

        $function = $map[$action];

        $reflection = new ReflectionFunction($function);
        if (count($args) < $reflection->getNumberOfRequiredParameters()) {
            throw new ApiError('Missing information for ' . $action . '.');
        }

        $result = call_user_func_array($function, $args);

        ob_end_clean();
        json_response(['ok' => true, 'result' => $result]);
    } catch (ApiError $e) {
        ob_end_clean();
        json_error($e->getMessage(), 200, 'Error');
    } catch (Throwable $e) {
        ob_end_clean();
        error_log('[PPU API] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        $message = (bool) config('debug', false)
            ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            : 'The server could not complete the request. Please try again.';

        json_error($message, 500, 'ServerError');
    }
}
