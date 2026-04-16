<?php
declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $confirmInput = $_POST['confirm_password'] ?? '';

    if ($usernameInput === '' || $passwordInput === '' || $confirmInput === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $usernameInput)) {
        $error = 'Username chỉ gồm chữ, số, dấu gạch dưới và dài 3-30 ký tự.';
    } elseif (strlen($passwordInput) < 6) {
        $error = 'Password tối thiểu 6 ký tự.';
    } elseif ($passwordInput !== $confirmInput) {
        $error = 'Xác nhận password không khớp.';
    } else {
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $checkStmt->execute(['username' => $usernameInput]);

        if ($checkStmt->fetch()) {
            $error = 'Username đã tồn tại.';
        } else {
            $passwordHash = password_hash($passwordInput, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)');
            $insertStmt->execute([
                'username' => $usernameInput,
                'password_hash' => $passwordHash,
                'role' => 'user',
            ]);
            $success = 'Đăng ký thành công. Bạn có thể đăng nhập ngay.';
            $usernameInput = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web server Primary - Đăng ký</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020712;
            --bg-soft: #071423;
            --card: rgba(3, 10, 20, 0.92);
            --line: #2a75c7;
            --primary: #31b6ff;
            --primary-soft: rgba(49, 182, 255, 0.26);
            --text: #e8f4ff;
            --muted: #94b8d8;
            --danger: #ff7272;
            --ok: #6be09a;
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
                linear-gradient(145deg, var(--bg) 0%, var(--bg-soft) 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 460px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent 40%),
                var(--card);
            border: 2px solid #39a2ff;
            border-radius: 16px;
            padding: 28px;
            box-shadow:
                0 0 0 1px rgba(61, 188, 255, 0.38),
                0 0 30px rgba(49, 182, 255, 0.28),
                0 18px 44px rgba(0, 0, 0, 0.52);
        }

        .title,
        .subtitle,
        label,
        .meta,
        .msg {
            text-shadow:
                0 0 4px rgba(111, 216, 255, 0.85),
                0 0 12px rgba(61, 188, 255, 0.45);
        }

        .title { margin: 0; font-size: 26px; }
        .subtitle { margin: 8px 0 24px; color: var(--muted); font-size: 14px; }
        .field { margin-bottom: 16px; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #d8efff;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            background: rgba(4, 12, 22, 0.96);
            color: var(--text);
            padding: 12px 14px;
            border-radius: 10px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-soft), 0 0 18px rgba(49, 182, 255, 0.3);
            transform: translateY(-1px);
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 12px 14px;
            color: #03111f;
            background: linear-gradient(135deg, #3fd0ff 0%, #1f87ff 60%, #0d63ff 100%);
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 0 22px rgba(49, 182, 255, 0.32);
        }

        .msg {
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 14px;
        }

        .error {
            border: 1px solid rgba(255, 114, 114, 0.4);
            background: rgba(255, 114, 114, 0.14);
            color: #ffd0d0;
        }

        .success {
            border: 1px solid rgba(107, 224, 154, 0.45);
            background: rgba(107, 224, 154, 0.14);
            color: #d4ffe2;
        }

        .meta {
            margin-top: 16px;
            font-size: 14px;
            color: var(--muted);
            text-align: center;
        }

        .meta a {
            color: #5ecbff;
            text-decoration: none;
        }

        .meta a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main class="card">
        <h1 class="title">Tạo tài khoản</h1>
        <p class="subtitle">Đăng ký người dùng cho Web server Primary.</p>

        <?php if ($error !== ''): ?>
            <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($usernameInput, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password">
            </div>

            <div class="field">
                <label for="confirm_password">Xác nhận Password</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
            </div>

            <button class="btn" type="submit">Đăng ký</button>
        </form>

        <p class="meta">Đã có tài khoản? <a href="index.php">Đăng nhập</a></p>
    </main>
</body>
</html>
