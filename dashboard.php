<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db.php';

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$currentTime = date('d/m/Y H:i:s');

$historyStmt = $pdo->prepare(
    'SELECT login_at, ip_address
     FROM login_history
     WHERE user_id = :user_id
     ORDER BY login_at DESC
     LIMIT 10'
);
$historyStmt->execute(['user_id' => $userId]);
$loginHistory = $historyStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web server Primary - Chào mừng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020712;
            --panel: rgba(3, 10, 20, 0.92);
            --line: #2a75c7;
            --text: #e8f4ff;
            --muted: #94b8d8;
            --accent: #34b9ff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Be Vietnam Pro', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 14% 18%, rgba(49, 182, 255, 0.18) 0%, transparent 24%),
                radial-gradient(circle at 82% 84%, rgba(35, 147, 255, 0.14) 0%, transparent 22%),
                linear-gradient(150deg, var(--bg) 0%, #071423 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .panel {
            width: 100%;
            max-width: 760px;
            border-radius: 18px;
            border: 2px solid #39a2ff;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent 40%),
                var(--panel);
            box-shadow:
                0 0 0 1px rgba(61, 188, 255, 0.38),
                0 0 30px rgba(49, 182, 255, 0.28),
                0 20px 45px rgba(0, 0, 0, 0.52);
            padding: 34px;
        }

        h1,
        .info,
        .history-title,
        .history-item,
        .history-empty {
            text-shadow:
                0 0 4px rgba(111, 216, 255, 0.85),
                0 0 12px rgba(61, 188, 255, 0.45);
        }

        h1 {
            margin: 0 0 10px;
            font-size: clamp(28px, 4.2vw, 42px);
        }

        .info {
            color: var(--muted);
            line-height: 1.7;
            margin: 8px 0;
        }

        .history-title {
            margin: 24px 0 12px;
            color: #d8efff;
            font-size: 18px;
        }

        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .history-item {
            border: 1px solid #2f77c8;
            border-radius: 10px;
            background: rgba(52, 185, 255, 0.08);
            color: #e1f4ff;
            padding: 10px 12px;
            font-size: 14px;
            box-shadow: 0 0 14px rgba(49, 182, 255, 0.12);
        }

        .history-empty {
            color: var(--muted);
            font-size: 14px;
        }

        .btn {
            margin-top: 26px;
            display: inline-block;
            padding: 11px 16px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3fd0ff 0%, #1f87ff 60%, #0d63ff 100%);
            color: #03111f;
            text-decoration: none;
            font-weight: 700;
            box-shadow: 0 0 22px rgba(49, 182, 255, 0.32);
        }
    </style>
</head>
<body>
    <main class="panel">
        <h1>Chào mừng đăng nhập</h1>
        <p class="info">Tên người dùng: <strong><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <p class="info">Thời gian hiện tại: <strong><?= htmlspecialchars($currentTime, ENT_QUOTES, 'UTF-8') ?></strong></p>

        <h2 class="history-title">Lịch sử đăng nhập</h2>
        <?php if (count($loginHistory) > 0): ?>
            <ul class="history-list">
                <?php foreach ($loginHistory as $item): ?>
                    <?php
                        $loginAtRaw = (string) $item['login_at'];
                        $loginAtTs = strtotime($loginAtRaw);
                        $loginAtDisplay = $loginAtTs !== false ? date('H:i:s d-m-Y', $loginAtTs) : $loginAtRaw;
                        $ipAddress = (string) $item['ip_address'];
                    ?>
                    <li class="history-item">
                        <?= htmlspecialchars($loginAtDisplay, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($ipAddress !== '::1'): ?>
                            | IP: <?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="history-empty">Chưa có dữ liệu lịch sử đăng nhập.</p>
        <?php endif; ?>

        <a class="btn" href="logout.php">Đăng xuất</a>
    </main>
</body>
</html>
