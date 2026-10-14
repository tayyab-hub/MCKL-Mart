<?php
// The PHP development server ignores .htaccess. Mirror internal-path protection
// for the disposable test servers; Apache protection is tested separately.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
// Windows drops empty values from a child environment block. Restore the
// explicitly chosen empty test password without reading the private app config.
if (getenv('MCKL_TEST_EMPTY_PASSWORD') === '1') putenv('MCKL_DB_PASSWORD=');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~(?:^|/)(?:includes|database|tests|tools|docs|\.[^/]*)(?:/|$)|\.(?:sql|log|bak|old|ini|env|zip)$~i', $path)) {
    http_response_code(403); exit('Forbidden');
}
return false;
