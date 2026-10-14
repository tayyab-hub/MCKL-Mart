<?php
/** Safe session defaults for the standalone PHP portfolio demo. */
date_default_timezone_set('Asia/Kuala_Lumpur');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Never send exception messages, SQL, submitted data or credentials to the browser.
set_exception_handler(static function (Throwable $error): void {
    error_log('MCKL Mart: unhandled ' . get_class($error));
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo 'Sorry, this request could not be completed. Please try again.';
});
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('MCKLMARTSESSID');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim($basePath, '/.') . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}
