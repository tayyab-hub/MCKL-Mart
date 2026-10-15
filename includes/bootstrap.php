<?php
/** Safe session defaults for the standalone PHP portfolio demo. */
date_default_timezone_set('Asia/Kuala_Lumpur');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/demo.php';
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
if (public_demo_mode() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    demo_stop(413, 'This demo accepts small form submissions only.');
}
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('MCKLMARTSESSID');
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    // Always protect cookies on the known public HTTPS host, even behind the
    // hosting proxy. Never trust arbitrary client-supplied forwarding headers.
    $publicHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) === 'mcklmart.site.je';
    $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim($basePath, '/.') . '/',
        'secure' => $https || $publicHost,
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
