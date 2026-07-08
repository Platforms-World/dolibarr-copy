<?php
if (!defined('TAKEPOS_API_V1_RESPONSE_INCLUDED')) {
    define('TAKEPOS_API_V1_RESPONSE_INCLUDED', 1);

    function takeposApiLogError($message, $level = LOG_WARNING)
    {
        if (function_exists('dol_syslog')) {
            dol_syslog('[TakePOS][API v1][' . takeposApiRequestId() . '] ' . (string) $message, $level);
        }
    }

    function takeposApiRequestId()
    {
        static $requestId = null;
        if ($requestId !== null) {
            return $requestId;
        }

        try {
            $requestId = 'REQ-' . gmdate('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } catch (Throwable $e) {
            $requestId = 'REQ-' . gmdate('YmdHis') . '-' . strtoupper(substr(sha1(uniqid('', true)), 0, 8));
        }

        return $requestId;
    }

    function takeposApiPrepareResponse()
    {
        if (defined('TAKEPOS_API_V1_RESPONSE_READY')) {
            return;
        }

        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');

        if (ob_get_level() === 0) {
            ob_start();
        }

        set_error_handler('takeposApiHandlePhpError');
        set_exception_handler('takeposApiHandleUnhandledException');
        register_shutdown_function('takeposApiHandleShutdown');

        define('TAKEPOS_API_V1_RESPONSE_READY', 1);
    }

    function takeposApiHandlePhpError($severity, $message, $file = '', $line = 0)
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        takeposApiLogError('PHP warning suppressed: ' . $message . ' in ' . $file . ':' . $line, LOG_WARNING);
        return true;
    }

    function takeposApiClearBuffers()
    {
        while (ob_get_level()) {
            @ob_end_clean();
        }
    }

    function takeposApiSend($payload, $httpCode = 200, $extraHeaders = array())
    {
        if (defined('TAKEPOS_API_V1_RESPONSE_SENT')) {
            exit;
        }

        define('TAKEPOS_API_V1_RESPONSE_SENT', 1);
        takeposApiClearBuffers();

        if (headers_sent() === false) {
            if (function_exists('header_remove')) {
                @header_remove('Location');
                @header_remove('Set-Cookie');
            }
            http_response_code((int) $httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Request-Id: ' . takeposApiRequestId());
            foreach ((array) $extraHeaders as $headerLine) {
                header((string) $headerLine);
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function takeposApiBuildSuccessPayload($data = array(), $meta = array())
    {
        return array(
            'success' => true,
            'data' => $data,
            'meta' => (array) $meta,
            'request_id' => takeposApiRequestId(),
        );
    }

    function takeposApiSuccess($data = array(), $meta = array(), $httpCode = 200)
    {
        takeposApiSend(takeposApiBuildSuccessPayload($data, $meta), $httpCode);
    }

    function takeposApiBuildErrorPayload($code, $message, $details = null, $meta = array())
    {
        $payload = array(
            'success' => false,
            'error' => array('code' => (string) $code, 'message' => (string) $message),
            'request_id' => takeposApiRequestId(),
        );
        if ($details !== null) {
            $payload['error']['details'] = $details;
        }
        if (empty($meta) === false) {
            $payload['meta'] = (array) $meta;
        }

        return $payload;
    }

    function takeposApiError($code, $message, $httpCode = 400, $details = null, $meta = array(), $headers = array())
    {
        takeposApiSend(takeposApiBuildErrorPayload($code, $message, $details, $meta), $httpCode, $headers);
    }

    function takeposApiHandleUnhandledException($exception)
    {
        if ($exception instanceof TakeposApiException) {
            $headers = array();
            if ((int) $exception->getHttpStatus() === 401) {
                $headers[] = 'WWW-Authenticate: Bearer';
            }

            takeposApiError($exception->getApiCode(), $exception->getMessage(), $exception->getHttpStatus(), $exception->getDetails(), array(), $headers);
        }

        takeposApiLogError('Unhandled exception: ' . $exception->getMessage(), LOG_ERR);
        takeposApiError('INTERNAL_ERROR', $exception->getMessage() . ' | ' . basename($exception->getFile()) . ':' . $exception->getLine(), 500);
    }

    function takeposApiHandleShutdown()
    {
        if (defined('TAKEPOS_API_V1_RESPONSE_SENT')) {
            return;
        }

        $error = error_get_last();
        if (empty($error) or is_array($error) === false) {
            return;
        }

        $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (in_array((int) $error['type'], $fatalTypes, true) === false) {
            return;
        }

        takeposApiLogError('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'], LOG_ERR);
        takeposApiError('INTERNAL_ERROR', 'Internal server error', 500);
    }

    function takeposApiRequireMethod($allowedMethods)
    {
        $allowedMethods = array_values(array_unique(array_map('strtoupper', (array) $allowedMethods)));
        $requestMethod = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');

        if (in_array($requestMethod, $allowedMethods, true) === false) {
            takeposApiError('METHOD_NOT_ALLOWED', 'Method not allowed.', 405, null, array(), array('Allow: ' . implode(', ', $allowedMethods)));
        }
    }
}