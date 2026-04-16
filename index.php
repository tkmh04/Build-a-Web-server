<?php
declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
	header('Location: dashboard.php');
	exit;
}

require_once __DIR__ . '/db.php';

$error = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$usernameInput = trim($_POST['username'] ?? '');
	$passwordInput = $_POST['password'] ?? '';

	if ($usernameInput === '' || $passwordInput === '') {
		$error = 'Vui lòng nhập đầy đủ username và password.';
	} else {
		$stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
		$stmt->execute(['username' => $usernameInput]);
		$user = $stmt->fetch();

		if ($user && password_verify($passwordInput, $user['password_hash'])) {
			$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

			$historyStmt = $pdo->prepare(
				'INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (:user_id, :ip_address, :user_agent)'
			);
			$historyStmt->execute([
				'user_id' => (int) $user['id'],
				'ip_address' => substr($ipAddress, 0, 45),
				'user_agent' => substr($userAgent, 0, 255),
			]);

			session_regenerate_id(true);
			$_SESSION['user_id'] = (int) $user['id'];
			$_SESSION['username'] = $user['username'];
			header('Location: dashboard.php');
			exit;
		}

		$error = 'Thông tin đăng nhập không đúng.';
	}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Web server Primary - Đăng nhập</title>
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
			max-width: 440px;
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
		.error {
			text-shadow:
				0 0 4px rgba(111, 216, 255, 0.85),
				0 0 12px rgba(61, 188, 255, 0.45);
		}

		.title {
			margin: 0;
			font-size: 26px;
			letter-spacing: 0.2px;
		}

		.subtitle {
			margin: 8px 0 24px;
			color: var(--muted);
			font-size: 14px;
		}

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

		.error {
			margin-bottom: 14px;
			padding: 10px 12px;
			border-radius: 10px;
			border: 1px solid rgba(255, 114, 114, 0.48);
			background: rgba(255, 114, 114, 0.14);
			color: #ffd0d0;
			font-size: 14px;
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
		<h1 class="title">Web server Primary</h1>
		<p class="subtitle">Đăng nhập để vào website</p>

		<?php if ($error !== ''): ?>
			<div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>

		<form method="post" action="">
			<div class="field">
				<label for="username">Username</label>
				<input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($usernameInput, ENT_QUOTES, 'UTF-8') ?>">
			</div>

			<div class="field">
				<label for="password">Password</label>
				<input id="password" name="password" type="password" autocomplete="current-password">
			</div>

			<button class="btn" type="submit">Đăng nhập</button>
		</form>

		<p class="meta">Chưa có tài khoản? <a href="register.php">Đăng ký tại đây</a></p>
	</main>
</body>
</html>
