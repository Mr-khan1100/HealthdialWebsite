<?php
/**
 * Bootstrap for JSON/AJAX endpoints — keeps the response body valid JSON even
 * if PHP emits a warning/notice or hits a fatal error.
 *
 * On the live server display_errors is ON, so a stray warning prepends
 * "<br /><b>Warning</b>: …" HTML to the body and breaks JSON.parse() in the
 * browser ("Unexpected token '<'"). We turn OFF inline error display (errors
 * still go to the PHP error log) and convert any fatal into a clean JSON 500.
 *
 * Include this FIRST, before any other require, in JSON endpoints.
 */
if (!defined('HD_JSON_GUARD')) {
    define('HD_JSON_GUARD', 1);

    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');

    register_shutdown_function(function () {
        $e = error_get_last();
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if ($e && in_array($e['type'], $fatal, true)) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'ok'      => false,
                'success' => false,
                'error'   => 'Server error. Please try again.',
                'message' => 'Server error. Please try again.',
            ]);
        }
    });
}
