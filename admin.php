<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminId = (int) $_SESSION['user_id'];
$adminName = (string) ($_SESSION['username'] ?? 'admin');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_user') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'user');
            $role = $role === 'admin' ? 'admin' : 'user';

            if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $error = 'Username phải gồm chữ, số, dấu gạch dưới và dài 3-30 ký tự.';
            } elseif (strlen($password) < 3) {
                $error = 'Password tối thiểu 3 ký tự.';
            } else {
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                $checkStmt->execute(['username' => $username]);
                if ($checkStmt->fetch()) {
                    $error = 'Username đã tồn tại.';
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
                    );
                    $insertStmt->execute([
                        'username' => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                    ]);
                    $success = 'Đã thêm tài khoản mới.';
                }
            }
        } elseif ($action === 'update_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'user');
            $role = $role === 'admin' ? 'admin' : 'user';

            if ($userId <= 0) {
                $error = 'ID user không hợp lệ.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $error = 'Username phải gồm chữ, số, dấu gạch dưới và dài 3-30 ký tự.';
            } else {
                $dupStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1');
                $dupStmt->execute([
                    'username' => $username,
                    'id' => $userId,
                ]);

                if ($dupStmt->fetch()) {
                    $error = 'Username đã được dùng bởi tài khoản khác.';
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET username = :username, role = :role WHERE id = :id');
                    $updateStmt->execute([
                        'username' => $username,
                        'role' => $role,
                        'id' => $userId,
                    ]);

                    if ($newPassword !== '') {
                        if (strlen($newPassword) < 3) {
                            $error = 'Mật khẩu mới tối thiểu 3 ký tự.';
                        } else {
                            $pwdStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                            $pwdStmt->execute([
                                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                                'id' => $userId,
                            ]);
                        }
                    }

                    if ($error === '') {
                        if ($userId === $adminId) {
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = $role;
                        }
                        $success = 'Đã cập nhật tài khoản.';
                    }
                }
            }
        } elseif ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                $error = 'ID user không hợp lệ.';
            } elseif ($userId === $adminId) {
                $error = 'Không thể tự xóa tài khoản admin đang đăng nhập.';
            } else {
                $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $adminCount = (int) $adminCountStmt->fetchColumn();

                $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
                $roleStmt->execute(['id' => $userId]);
                $target = $roleStmt->fetch();

                if (!$target) {
                    $error = 'Tài khoản không tồn tại.';
                } elseif (($target['role'] ?? 'user') === 'admin' && $adminCount <= 1) {
                    $error = 'Không thể xóa admin cuối cùng của hệ thống.';
                } else {
                    $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $deleteStmt->execute(['id' => $userId]);
                    $success = 'Đã xóa tài khoản.';
                }
            }
        } elseif ($action === 'cleanup_history') {
            $cleanupStmt = $pdo->prepare('DELETE FROM login_history WHERE login_at < (NOW() - INTERVAL 30 DAY)');
            $cleanupStmt->execute();
            $success = 'Đã dọn lịch sử đăng nhập cũ hơn 30 ngày.';
        }
    }
}

$usersStmt = $pdo->query(
    'SELECT id, username, role, created_at
     FROM users
     ORDER BY id ASC'
);
$users = $usersStmt->fetchAll();

$editUserId = (int) ($_GET['edit_id'] ?? 0);
$editUser = null;
if ($editUserId > 0) {
    foreach ($users as $userItem) {
        if ((int) $userItem['id'] === $editUserId) {
            $editUser = $userItem;
            break;
        }
    }
}

$historyStmt = $pdo->query(
    'SELECT lh.login_at, lh.ip_address, lh.user_agent, u.username
     FROM login_history lh
     INNER JOIN users u ON u.id = lh.user_id
     ORDER BY lh.login_at DESC
     LIMIT 120'
);
$history = $historyStmt->fetchAll();

$totalUsers = count($users);
$adminUsers = 0;
foreach ($users as $userItem) {
    if (($userItem['role'] ?? 'user') === 'admin') {
        $adminUsers++;
    }
}
$normalUsers = $totalUsers - $adminUsers;
$historyCount = count($history);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - webserver Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f8ff;
            --bg-soft: #ffffff;
            --panel: #ffffff;
            --line: #d6e8fb;
            --text: #15314f;
            --muted: #5b7592;
            --primary: #1f86e6;
            --primary-soft: rgba(31, 134, 230, 0.2);
            --danger: #cf3f56;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Be Vietnam Pro', sans-serif;
            color: var(--text);
            background: linear-gradient(145deg, var(--bg) 0%, var(--bg-soft) 100%);
            min-height: 100vh;
            padding: 24px;
        }

        .layout {
            max-width: 1080px;
            margin: 0 auto;
            display: grid;
            gap: 14px;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 16px 18px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .topbar h1 {
            margin: 0;
            font-size: clamp(22px, 4vw, 28px);
            color: #12548f;
        }

        .topbar p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
        }

        .stats strong {
            color: #12548f;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px;
        }

        .panel h2 {
            margin: 0 0 10px;
            font-size: 19px;
            color: #12548f;
        }

        .note {
            margin: 0 0 10px;
            color: var(--muted);
            font-size: 14px;
        }

        .msg {
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .error {
            background: #ffeef1;
            border: 1px solid #f1a6b3;
            color: #9a2638;
        }

        .success {
            background: #eafaf3;
            border: 1px solid #9eddbf;
            color: #177748;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .field {
            margin-bottom: 10px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: #36597c;
        }

        input,
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 11px;
            color: var(--text);
            background: #fbfdff;
            outline: none;
            font: inherit;
        }

        input:focus,
        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .subpanel {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px;
            background: #fcfdff;
        }

        .subpanel h3 {
            margin: 0 0 10px;
            color: #12548f;
            font-size: 16px;
        }

        .btn {
            border-radius: 8px;
            border: 1px solid #1f86e6;
            padding: 9px 13px;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            background: #1f86e6;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn.secondary {
            color: #12518e;
            background: #e8f3ff;
            border: 1px solid #b5d9ff;
        }

        .btn.danger {
            background: #cf3f56;
            border-color: #cf3f56;
        }

        .btn.small {
            padding: 6px 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .table-wrap {
            overflow: auto;
            border: 1px solid #ddebf9;
            border-radius: 10px;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th,
        td {
            text-align: left;
            padding: 9px 10px;
            border-bottom: 1px solid #edf4fb;
            font-size: 14px;
            vertical-align: top;
        }

        th {
            background: #f6faff;
            color: #2d5f90;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .role-pill {
            display: inline-block;
            font-size: 12px;
            border-radius: 999px;
            padding: 3px 9px;
            font-weight: 700;
            border: 1px solid;
        }

        .role-admin {
            color: #0d5d9e;
            background: #e3f2ff;
            border-color: #9bc8f0;
        }

        .role-user {
            color: #2f628f;
            background: #edf6ff;
            border-color: #c3def6;
        }

        .agent {
            max-width: 280px;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-cell {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        @media (max-width: 1020px) {
            .row {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <header class="topbar">
            <div>
                <h1>Admin Control Panel</h1>
                <p>Xin chào <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?> | Quản trị hệ thống demo webserver</p>
            </div>
            <div class="tools">
                <a class="btn secondary" href="dashboard.php">Trang người dùng</a>
                <a class="btn danger" href="logout.php">Đăng xuất</a>
            </div>
        </header>

        <section class="panel">
            <h2>Tổng quan hệ thống</h2>
            <div class="stats">
                <span>Tổng user: <strong><?= $totalUsers ?></strong></span>
                <span>Admin: <strong><?= $adminUsers ?></strong></span>
                <span>User thường: <strong><?= $normalUsers ?></strong></span>
                <span>Bản ghi lịch sử: <strong><?= $historyCount ?></strong></span>
            </div>
        </section>

        <section class="panel">
            <h2>Quản lý tài khoản</h2>
            <p class="note">Xem danh sách user, bấm Sửa để cập nhật nhanh, bấm Xóa để xóa tài khoản.</p>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="msg success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="subpanel">
                    <h3>Thêm tài khoản</h3>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="row">
                            <div class="field">
                                <label for="new_username">Username</label>
                                <input id="new_username" name="username" type="text" maxlength="30" required>
                            </div>
                            <div class="field">
                                <label for="new_password">Password</label>
                                <input id="new_password" name="password" type="password" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label for="new_role">Role</label>
                                <select id="new_role" name="role">
                                    <option value="user">user</option>
                                    <option value="admin">admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn" type="submit">Thêm tài khoản</button>
                        </div>
                    </form>
                </div>

                <div class="subpanel" id="edit-user">
                    <h3>Sửa tài khoản</h3>
                    <?php if ($editUser !== null): ?>
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                            <div class="row">
                                <div class="field">
                                    <label for="edit_username">Username</label>
                                    <input id="edit_username" name="username" type="text" value="<?= htmlspecialchars((string) $editUser['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="field">
                                    <label for="edit_role">Role</label>
                                    <select id="edit_role" name="role">
                                        <option value="user" <?= (($editUser['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>user</option>
                                        <option value="admin" <?= (($editUser['role'] ?? 'user') === 'admin') ? 'selected' : '' ?>>admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label for="edit_password">Mật khẩu mới (tùy chọn)</label>
                                <input id="edit_password" name="new_password" type="password" placeholder="Để trống nếu không đổi mật khẩu">
                            </div>
                            <div class="actions">
                                <button class="btn" type="submit">Lưu thay đổi</button>
                                <a class="btn secondary" href="admin.php">Bỏ chọn</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="note">Chưa chọn user cần sửa. Bấm nút <strong>Sửa</strong> trong bảng bên dưới.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-wrap" style="margin-top: 14px;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Tạo lúc</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= (int) $user['id'] ?></td>
                                <td><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="role-pill <?= (($user['role'] ?? 'user') === 'admin') ? 'role-admin' : 'role-user' ?>">
                                        <?= htmlspecialchars((string) ($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) $user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="action-cell">
                                        <a class="btn secondary small" href="admin.php?edit_id=<?= (int) $user['id'] ?>#edit-user">Sửa</a>
                                        <form method="post" action="" onsubmit="return confirm('Bạn chắc chắn muốn xóa tài khoản này?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <button class="btn danger small" type="submit">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2>Lịch sử đăng nhập</h2>
            <p class="note">Theo dõi thời gian, người dùng, IP và trình duyệt truy cập.</p>

            <form method="post" action="" style="margin-bottom: 12px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="cleanup_history">
                <button class="btn secondary" type="submit">Dọn lịch sử cũ hơn 30 ngày</button>
            </form>

            <div class="table-wrap">
                <table style="min-width: 620px;">
                    <thead>
                        <tr>
                                <th>Thời gian</th>
                            <th>Username</th>
                            <th>IP</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($history) === 0): ?>
                            <tr>
                                    <td colspan="4">Chưa có lịch sử đăng nhập.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $item): ?>
                                <?php
                                    $loginAtRaw = (string) $item['login_at'];
                                    $loginAtTs = strtotime($loginAtRaw);
                                    $loginAtDisplay = $loginAtTs !== false ? date('H:i:s d-m-Y', $loginAtTs) : $loginAtRaw;
                                    $ipAddress = (string) $item['ip_address'];
                                    if ($ipAddress === '::1') {
                                        $ipAddress = 'localhost';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($loginAtDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="agent" title="<?= htmlspecialchars((string) $item['user_agent'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $item['user_agent'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
