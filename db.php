<?php
declare(strict_types=1);

$dbHost = '127.0.0.1';
$dbName = 'webserver';
$dbUser = 'root';
$dbPass = '';
$dbPort = 3306;
$isLocalDebug = true;

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT "user",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT "user" AFTER password_hash');
    } catch (PDOException $schemaError) {
        $driverCode = isset($schemaError->errorInfo[1]) ? (int) $schemaError->errorInfo[1] : 0;
        if ($driverCode !== 1060) {
            throw $schemaError;
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            INDEX idx_user_id (user_id),
            CONSTRAINT fk_login_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
} catch (PDOException $e) {
    if ((int) $e->getCode() === 1049) {
        try {
            $bootstrapDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $bootstrapPdo = new PDO($bootstrapDsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $bootstrapPdo->exec("USE `{$dbName}`");
            $bootstrapPdo->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(30) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role VARCHAR(20) NOT NULL DEFAULT "user",
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB'
            );

            $bootstrapPdo->exec(
                'CREATE TABLE IF NOT EXISTS login_history (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) NOT NULL,
                    INDEX idx_user_id (user_id),
                    CONSTRAINT fk_login_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );

            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $bootstrapError) {
            http_response_code(500);
            $message = 'Không thể tạo database tự động. Kiểm tra MySQL/XAMPP và quyền user root.';
            if ($isLocalDebug) {
                $message .= ' | SQLSTATE=' . (string) $bootstrapError->getCode() . ' | ' . $bootstrapError->getMessage();
            }
            exit($message);
        }
    } else {
        http_response_code(500);
        $message = 'Không thể kết nối đến MySQL. Kiểm tra MySQL đã Start trong XAMPP và thông số db.php.';
        if ($isLocalDebug) {
            $message .= ' | SQLSTATE=' . (string) $e->getCode() . ' | ' . $e->getMessage();
        }
        exit($message);
    }
}

$adminStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$adminStmt->execute(['username' => 'admin']);
$adminUser = $adminStmt->fetch();

if ($adminUser) {
    $setRoleStmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
    $setRoleStmt->execute([
        'role' => 'admin',
        'id' => (int) $adminUser['id'],
    ]);
} else {
    $createAdminStmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
    );
    $createAdminStmt->execute([
        'username' => 'admin',
        'password_hash' => password_hash('123', PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
}
