<?php
/** PDO connection. Configure via environment or ignored includes/config.local.php. */
$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];
if (!is_array($local)) {
    http_response_code(500);
    exit('Invalid local database configuration.');
}
$host = getenv('MCKL_DB_HOST') ?: ($local['host'] ?? 'localhost');
$port = getenv('MCKL_DB_PORT') ?: ($local['port'] ?? '3306');
$name = getenv('MCKL_DB_NAME') ?: ($local['name'] ?? 'mckl_mart_portfolio');
$user = getenv('MCKL_DB_USER') ?: ($local['user'] ?? '');
$pass = getenv('MCKL_DB_PASSWORD');
if ($pass === false) {
    $pass = $local['password'] ?? null;
}
if ($user === '' || !is_string($pass) || $pass === 'REPLACE_WITH_YOUR_LOCAL_PASSWORD') {
    http_response_code(503);
    exit('MCKL Mart has not been configured. See README.md for local database setup.');
}
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log('MCKL Mart: database connection failure');
    http_response_code(503);
    exit('The demo database is unavailable. Check your local configuration.');
}
