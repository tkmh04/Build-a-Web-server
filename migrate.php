<?php
declare(strict_types=1);

$config = require __DIR__ . '/db_config.php';
require_once __DIR__ . '/ua_parser.php';
[
    'dbHost' => $dbHost,
    'dbName' => $dbName,
    'dbUser' => $dbUser,
    'dbPass' => $dbPass,
    'dbPort' => $dbPort,
    'schemaVersion' => $schemaVersion,
] = $config;

function connectPdo(string $dsn, string $dbUser, string $dbPass): PDO
{
    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function execIgnoreMysqlError(PDO $pdo, string $sql, int $mysqlErrorCode): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $exception) {
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
        if ($driverCode !== $mysqlErrorCode) {
            throw $exception;
        }
    }
}

$messages = [];
$success = true;

try {
    $bootstrapDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $bootstrapPdo = connectPdo($bootstrapDsn, $dbUser, $dbPass);
    $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $messages[] = 'Da kiem tra/tao database webserver.';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = connectPdo($dsn, $dbUser, $dbPass);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT "user",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            username_at_login VARCHAR(30) NOT NULL,
            role_at_login VARCHAR(20) NOT NULL DEFAULT "user",
            login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            ua_device_type VARCHAR(20) NOT NULL DEFAULT "Desktop",
            ua_device_name VARCHAR(80) NOT NULL DEFAULT "Unknown",
            ua_os_name VARCHAR(60) NOT NULL DEFAULT "Unknown",
            ua_os_version VARCHAR(40) NOT NULL DEFAULT "-",
            ua_browser_name VARCHAR(80) NOT NULL DEFAULT "Unknown",
            ua_browser_version VARCHAR(40) NOT NULL DEFAULT "-",
            INDEX idx_user_id (user_id),
            INDEX idx_login_at (login_at),
            INDEX idx_user_login_at (user_id, login_at)
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_meta (
            meta_key VARCHAR(64) PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    execIgnoreMysqlError($pdo, 'ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT "user" AFTER password_hash', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN username_at_login VARCHAR(30) NOT NULL DEFAULT "" AFTER user_id', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN role_at_login VARCHAR(20) NOT NULL DEFAULT "user" AFTER username_at_login', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_device_type VARCHAR(20) NOT NULL DEFAULT "Desktop" AFTER user_agent', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_device_name VARCHAR(80) NOT NULL DEFAULT "Unknown" AFTER ua_device_type', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_os_name VARCHAR(60) NOT NULL DEFAULT "Unknown" AFTER ua_device_name', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_os_version VARCHAR(40) NOT NULL DEFAULT "-" AFTER ua_os_name', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_browser_name VARCHAR(80) NOT NULL DEFAULT "Unknown" AFTER ua_os_version', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD COLUMN ua_browser_version VARCHAR(40) NOT NULL DEFAULT "-" AFTER ua_browser_name', 1060);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD INDEX idx_login_at (login_at)', 1061);
    execIgnoreMysqlError($pdo, 'ALTER TABLE login_history ADD INDEX idx_user_login_at (user_id, login_at)', 1061);

    $pdo->exec(
        'UPDATE login_history lh
         LEFT JOIN users u ON u.id = lh.user_id
         SET
            lh.username_at_login = CASE
                WHEN lh.username_at_login = "" THEN COALESCE(u.username, CONCAT("deleted_user_", lh.user_id))
                ELSE lh.username_at_login
            END,
            lh.role_at_login = CASE
                WHEN lh.role_at_login = "" OR lh.role_at_login IS NULL THEN COALESCE(u.role, "user")
                ELSE lh.role_at_login
            END'
    );

    $uaRowsStmt = $pdo->query(
        'SELECT id, user_agent
         FROM login_history
         WHERE user_agent != ""'
    );
    $uaRows = $uaRowsStmt->fetchAll();
    if (count($uaRows) > 0) {
        $updateUaStmt = $pdo->prepare(
            'UPDATE login_history
             SET
                ua_device_type = :ua_device_type,
                ua_device_name = :ua_device_name,
                ua_os_name = :ua_os_name,
                ua_os_version = :ua_os_version,
                ua_browser_name = :ua_browser_name,
                ua_browser_version = :ua_browser_version
             WHERE id = :id'
        );

        foreach ($uaRows as $row) {
            $parsedAgent = parseUserAgentDetails((string) $row['user_agent']);
            $updateUaStmt->execute([
                'ua_device_type' => $parsedAgent['device_type'],
                'ua_device_name' => $parsedAgent['device_name'],
                'ua_os_name' => $parsedAgent['os_name'],
                'ua_os_version' => $parsedAgent['os_version'],
                'ua_browser_name' => $parsedAgent['browser_name'],
                'ua_browser_version' => $parsedAgent['browser_version'],
                'id' => (int) $row['id'],
            ]);
        }
        $messages[] = 'Da cap nhat thong tin User-Agent cho ' . count($uaRows) . ' ban ghi lich su.';
    }

    $foreignKeysStmt = $pdo->query(
        "SELECT CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'login_history'
           AND REFERENCED_TABLE_NAME = 'users'"
    );
    $foreignKeys = $foreignKeysStmt->fetchAll();
    foreach ($foreignKeys as $fkRow) {
        $constraintName = (string) ($fkRow['CONSTRAINT_NAME'] ?? '');
        if ($constraintName !== '') {
            $pdo->exec('ALTER TABLE login_history DROP FOREIGN KEY `' . str_replace('`', '``', $constraintName) . '`');
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

    $setVersionStmt = $pdo->prepare(
        'INSERT INTO app_meta (meta_key, meta_value)
         VALUES (:meta_key, :meta_value)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)'
    );
    $setVersionStmt->execute([
        'meta_key' => 'schema_version',
        'meta_value' => (string) $schemaVersion,
    ]);

    $messages[] = 'Migration thanh cong. Schema version = ' . $schemaVersion . '.';
    $messages[] = 'Tai khoan admin mac dinh: username admin, password 123 (neu chua ton tai).';
} catch (Throwable $error) {
    http_response_code(500);
    $success = false;
    $messages[] = 'Migration that bai: ' . $error->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - webserver</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; margin: 0; padding: 24px; background: #f3f8ff; color: #15314f; }
        .box { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #d6e8fb; border-radius: 12px; padding: 18px; }
        h1 { margin: 0 0 12px; color: #12548f; }
        .ok { color: #177748; }
        .bad { color: #9a2638; }
        ul { margin: 10px 0 0; padding-left: 18px; }
        li { margin-bottom: 8px; }
        a { color: #1f86e6; text-decoration: none; }
    </style>
</head>
<body>
    <main class="box">
        <h1>Database Migration</h1>
        <p class="<?= $success ? 'ok' : 'bad' ?>"><?= $success ? 'Da hoan tat.' : 'Co loi xay ra.' ?></p>
        <ul>
            <?php foreach ($messages as $message): ?>
                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="index.php">Quay ve trang dang nhap</a></p>
    </main>
</body>
</html>
