<?php
declare(strict_types=1);

$config = require __DIR__ . '/db_config.php';
[
    'dbHost' => $dbHost,
    'dbName' => $dbName,
    'dbUser' => $dbUser,
    'dbPass' => $dbPass,
    'dbPort' => $dbPort,
    'isLocalDebug' => $isLocalDebug,
] = $config;

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 1049) {
        http_response_code(500);
        $message = 'Database chưa được khởi tạo. Vui lòng chạy migrate.php trước, sau đó tải lại trang.';
        if ($isLocalDebug) {
            $message .= ' | SQLSTATE=' . (string) $e->getCode() . ' | ' . $e->getMessage();
        }
        exit($message);
    } else {
        http_response_code(500);
        $message = 'Không thể kết nối đến MySQL. Kiểm tra MySQL đã Start trong XAMPP và thông số db.php.';
        if ($isLocalDebug) {
            $message .= ' | SQLSTATE=' . (string) $e->getCode() . ' | ' . $e->getMessage();
        }
        exit($message);
    }
}
