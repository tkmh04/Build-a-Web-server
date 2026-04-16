<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Webserver DB Diagnose ===\n";
echo 'PHP SAPI: ' . PHP_SAPI . "\n";
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'Loaded php.ini: ' . (php_ini_loaded_file() ?: 'N/A') . "\n";
echo 'PDO loaded: ' . (extension_loaded('pdo') ? 'yes' : 'no') . "\n";
echo 'pdo_mysql loaded: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n\n";

$host = '127.0.0.1';
$port = 3306;
$db = 'webserver';
$user = 'root';
$pass = '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

echo 'Testing DSN: ' . $dsn . "\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $version = $pdo->query('SELECT VERSION() AS v')->fetch();
    echo "RESULT: CONNECTED\n";
    echo 'MySQL Version: ' . ($version['v'] ?? 'unknown') . "\n";
} catch (PDOException $e) {
    echo "RESULT: FAILED\n";
    echo 'SQLSTATE: ' . (string) $e->getCode() . "\n";
    echo 'Message: ' . $e->getMessage() . "\n";
}
