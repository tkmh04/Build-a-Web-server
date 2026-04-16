<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ua_parser.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminId = (int) $_SESSION['user_id'];
$adminName = (string) ($_SESSION['username'] ?? 'admin');
$error = '';
$success = '';
$ajaxRequest = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');

if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

function buildDbErrorMessage(string $prefix, PDOException $exception): string
{
    $sqlState = (string) $exception->getCode();
    $driverCode = isset($exception->errorInfo[1]) ? (string) $exception->errorInfo[1] : 'N/A';
    return $prefix . ' | SQLSTATE=' . $sqlState . ' | DRIVER=' . $driverCode . ' | DETAIL=' . $exception->getMessage();
}

function redirectWithSuccess(string $message, string $location = 'admin.php'): void
{
    $_SESSION['flash_success'] = $message;
    header('Location: ' . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
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
                        if ($ajaxRequest) {
                            $success = 'Đã thêm tài khoản mới.';
                        } else {
                            redirectWithSuccess('Đã thêm tài khoản mới.');
                        }
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
                            if ($ajaxRequest) {
                                $success = 'Đã cập nhật tài khoản.';
                            } else {
                                redirectWithSuccess('Đã cập nhật tài khoản.');
                            }
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
                        if ($ajaxRequest) {
                            $success = 'Đã xóa tài khoản.';
                        } else {
                            redirectWithSuccess('Đã xóa tài khoản.');
                        }
                    }
                }
            } elseif ($action === 'cleanup_history') {
                $cleanupStmt = $pdo->prepare('DELETE FROM login_history WHERE login_at < (NOW() - INTERVAL 30 DAY)');
                $cleanupStmt->execute();
                if ($ajaxRequest) {
                    $success = 'Đã dọn lịch sử đăng nhập cũ hơn 30 ngày.';
                } else {
                    redirectWithSuccess('Đã dọn lịch sử đăng nhập cũ hơn 30 ngày.');
                }
            }
        } catch (PDOException $dbError) {
            $error = buildDbErrorMessage('Thao tác cơ sở dữ liệu thất bại', $dbError);
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

$history = [];
try {
    $historyStmt = $pdo->query(
        'SELECT
            login_at,
            ip_address,
            username_at_login,
            role_at_login,
            ua_device_type AS parsed_device_type,
            ua_device_name AS parsed_device_name,
            ua_os_name AS parsed_os_name,
            ua_os_version AS parsed_os_version,
            ua_browser_name AS parsed_browser_name,
            ua_browser_version AS parsed_browser_version
         FROM login_history
         ORDER BY login_at DESC
         LIMIT 120'
    );
    $history = $historyStmt->fetchAll();
} catch (PDOException $historyError) {
    // Fallback for old schema before running migrate.php.
    $legacyHistoryStmt = $pdo->query(
        'SELECT login_at, ip_address, user_agent, username_at_login, role_at_login
         FROM login_history
         ORDER BY login_at DESC
         LIMIT 120'
    );
    $history = $legacyHistoryStmt->fetchAll();

    foreach ($history as &$historyItem) {
        $parsedAgent = parseUserAgentDetails((string) $historyItem['user_agent']);
        $historyItem['parsed_device_type'] = $parsedAgent['device_type'];
        $historyItem['parsed_device_name'] = $parsedAgent['device_name'];
        $historyItem['parsed_os_name'] = $parsedAgent['os_name'];
        $historyItem['parsed_os_version'] = $parsedAgent['os_version'];
        $historyItem['parsed_browser_name'] = $parsedAgent['browser_name'];
        $historyItem['parsed_browser_version'] = $parsedAgent['browser_version'];
    }
    unset($historyItem);
}

$totalUsers = count($users);
$adminUsers = 0;
foreach ($users as $userItem) {
    if (($userItem['role'] ?? 'user') === 'admin') {
        $adminUsers++;
    }
}
$normalUsers = $totalUsers - $adminUsers;
$historyCount = count($history);

if ($ajaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $usersPayload = [];
    foreach ($users as $user) {
        $usersPayload[] = [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'role' => (string) ($user['role'] ?? 'user'),
            'created_at' => (string) $user['created_at'],
        ];
    }

    echo json_encode([
        'ok' => $error === '',
        'message' => $error === '' ? $success : $error,
        'stats' => [
            'totalUsers' => $totalUsers,
            'adminUsers' => $adminUsers,
            'normalUsers' => $normalUsers,
            'historyCount' => $historyCount,
        ],
        'users' => $usersPayload,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - webserver Demo</title>
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
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(145deg, var(--bg) 0%, var(--bg-soft) 100%);
            min-height: 100vh;
            padding: 24px;
        }

        .layout {
            width: min(1080px, calc(100vw - 48px));
            margin: 0 auto;
            display: grid;
            gap: 14px;
        }

        .layout > * {
            min-width: 0;
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
            min-width: 0;
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
            min-width: 0;
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
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .form-grid.with-edit {
            grid-template-columns: 1fr 1fr;
        }

        .add-row {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr 0.8fr;
            gap: 10px;
            align-items: end;
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
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: auto;
            border: 1px solid #ddebf9;
            border-radius: 10px;
            background: #fff;
        }

        .history-wrap {
            max-height: 360px;
            max-width: 100%;
            overflow: auto;
        }

        .history-wrap table {
            min-width: 1520px;
        }

        .history-wrap th,
        .history-wrap td {
            white-space: nowrap;
        }

        .history-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 1;
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

            .form-grid.with-edit { grid-template-columns: 1fr; }

            .add-row { grid-template-columns: 1fr; }
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
                <span>Tổng user: <strong id="stat-total-users"><?= $totalUsers ?></strong></span>
                <span>Admin: <strong id="stat-admin-users"><?= $adminUsers ?></strong></span>
                <span>User thường: <strong id="stat-normal-users"><?= $normalUsers ?></strong></span>
                <span>Bản ghi lịch sử: <strong id="stat-history-count"><?= $historyCount ?></strong></span>
            </div>
        </section>

        <section class="panel">
            <h2>Quản lý tài khoản</h2>
            <p class="note">Xem danh sách user, bấm Sửa để cập nhật nhanh, bấm Xóa để xóa tài khoản.</p>

            <div id="admin-error" class="msg error" style="<?= $error === '' ? 'display:none;' : '' ?>">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div id="admin-success" class="msg success" style="<?= $success === '' ? 'display:none;' : '' ?>">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="form-grid<?= $editUser !== null ? ' with-edit' : '' ?>">
                <div class="subpanel">
                    <h3>Thêm tài khoản</h3>
                    <form id="add-user-form" class="js-ajax-form" method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="add-row">
                            <div class="field">
                                <label for="new_username">Username</label>
                                <input id="new_username" name="username" type="text" maxlength="30" required>
                            </div>
                            <div class="field">
                                <label for="new_password">Password</label>
                                <input id="new_password" name="password" type="password" required>
                            </div>
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

                <div class="subpanel" id="edit-user" style="<?= $editUser !== null ? '' : 'display:none;' ?>">
                    <h3>Sửa tài khoản</h3>
                        <form id="edit-user-form" class="js-ajax-form" method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="update_user">
                            <input id="edit_user_id" type="hidden" name="user_id" value="<?= $editUser !== null ? (int) $editUser['id'] : 0 ?>">
                            <div class="row">
                                <div class="field">
                                    <label for="edit_username">Username</label>
                                    <input id="edit_username" name="username" type="text" value="<?= $editUser !== null ? htmlspecialchars((string) $editUser['username'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
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
                                <button id="cancel-edit-btn" class="btn secondary" type="button">Bỏ chọn</button>
                            </div>
                        </form>
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
                    <tbody id="users-table-body">
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
                                        <a class="btn secondary small edit-user-btn"
                                           href="admin.php?edit_id=<?= (int) $user['id'] ?>#edit-user"
                                           data-user-id="<?= (int) $user['id'] ?>"
                                           data-username="<?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?>"
                                           data-role="<?= htmlspecialchars((string) ($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?>">Sửa</a>
                                        <form class="js-ajax-form delete-user-form" method="post" action="" onsubmit="return confirm('Bạn chắc chắn muốn xóa tài khoản này?');">
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

            <form id="cleanup-history-form" class="js-ajax-form" method="post" action="" style="margin-bottom: 12px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="cleanup_history">
                <button class="btn secondary" type="submit">Dọn lịch sử cũ hơn 30 ngày</button>
            </form>

            <div class="table-wrap history-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Username</th>
                            <th>IP</th>
                            <th>Loại thiết bị</th>
                            <th>Tên thiết bị</th>
                            <th>Tên HĐH</th>
                            <th>Phiên bản HĐH</th>
                            <th>Tên trình duyệt</th>
                            <th>Phiên bản trình duyệt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($history) === 0): ?>
                            <tr>
                                <td colspan="9">Chưa có lịch sử đăng nhập.</td>
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
                                    <td><?= htmlspecialchars((string) $item['username_at_login'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_device_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_device_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_os_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_os_version'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_browser_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $item['parsed_browser_version'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const csrfToken = <?= json_encode((string) $_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>;
            const ajaxEndpoint = new URL('admin.php', window.location.href).toString();
            const usersTableBody = document.getElementById('users-table-body');
            const editPanel = document.getElementById('edit-user');
            const editUserIdInput = document.getElementById('edit_user_id');
            const editUsernameInput = document.getElementById('edit_username');
            const editRoleSelect = document.getElementById('edit_role');
            const editPasswordInput = document.getElementById('edit_password');
            const cancelEditBtn = document.getElementById('cancel-edit-btn');
            const statTotalUsers = document.getElementById('stat-total-users');
            const statAdminUsers = document.getElementById('stat-admin-users');
            const statNormalUsers = document.getElementById('stat-normal-users');
            const statHistoryCount = document.getElementById('stat-history-count');
            const errorBox = document.getElementById('admin-error');
            const successBox = document.getElementById('admin-success');

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function showMessage(isSuccess, message) {
                if (!errorBox || !successBox) {
                    return;
                }

                if (isSuccess) {
                    successBox.textContent = message;
                    successBox.style.display = '';
                    errorBox.style.display = 'none';
                } else {
                    errorBox.textContent = message;
                    errorBox.style.display = '';
                    successBox.style.display = 'none';
                }
            }

            function updateStats(stats) {
                if (!stats) {
                    return;
                }
                if (statTotalUsers) statTotalUsers.textContent = String(stats.totalUsers ?? 0);
                if (statAdminUsers) statAdminUsers.textContent = String(stats.adminUsers ?? 0);
                if (statNormalUsers) statNormalUsers.textContent = String(stats.normalUsers ?? 0);
                if (statHistoryCount) statHistoryCount.textContent = String(stats.historyCount ?? 0);
            }

            function renderUsers(users) {
                if (!usersTableBody || !Array.isArray(users)) {
                    return;
                }

                usersTableBody.innerHTML = users.map((user) => {
                    const role = user.role === 'admin' ? 'admin' : 'user';
                    const roleClass = role === 'admin' ? 'role-admin' : 'role-user';
                    return ''
                        + '<tr>'
                        + '<td>' + Number(user.id) + '</td>'
                        + '<td>' + escapeHtml(user.username) + '</td>'
                        + '<td><span class="role-pill ' + roleClass + '">' + escapeHtml(role) + '</span></td>'
                        + '<td>' + escapeHtml(user.created_at) + '</td>'
                        + '<td>'
                        + '  <div class="action-cell">'
                        + '    <a class="btn secondary small edit-user-btn" href="admin.php?edit_id=' + Number(user.id) + '#edit-user"'
                        + ' data-user-id="' + Number(user.id) + '" data-username="' + escapeHtml(user.username) + '" data-role="' + escapeHtml(role) + '">Sửa</a>'
                        + '    <form class="js-ajax-form delete-user-form" method="post" action="" onsubmit="return confirm(\'Bạn chắc chắn muốn xóa tài khoản này?\');">'
                        + '      <input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">'
                        + '      <input type="hidden" name="action" value="delete_user">'
                        + '      <input type="hidden" name="user_id" value="' + Number(user.id) + '">'
                        + '      <button class="btn danger small" type="submit">Xóa</button>'
                        + '    </form>'
                        + '  </div>'
                        + '</td>'
                        + '</tr>';
                }).join('');
            }

            function showEditPanel(userId, username, role) {
                if (!editPanel || !editUserIdInput || !editUsernameInput || !editRoleSelect) {
                    return;
                }
                editUserIdInput.value = String(userId);
                editUsernameInput.value = username;
                editRoleSelect.value = role === 'admin' ? 'admin' : 'user';
                if (editPasswordInput) {
                    editPasswordInput.value = '';
                }
                editPanel.style.display = '';
                editPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function hideEditPanel() {
                if (!editPanel || !editUserIdInput || !editUsernameInput || !editRoleSelect) {
                    return;
                }
                editUserIdInput.value = '0';
                editUsernameInput.value = '';
                editRoleSelect.value = 'user';
                if (editPasswordInput) {
                    editPasswordInput.value = '';
                }
                editPanel.style.display = 'none';
            }

            document.addEventListener('click', function (event) {
                const editBtn = event.target.closest('.edit-user-btn');
                if (editBtn) {
                    event.preventDefault();
                    showEditPanel(
                        Number(editBtn.getAttribute('data-user-id') || 0),
                        editBtn.getAttribute('data-username') || '',
                        editBtn.getAttribute('data-role') || 'user'
                    );
                    return;
                }

                if (cancelEditBtn && event.target === cancelEditBtn) {
                    event.preventDefault();
                    hideEditPanel();
                }
            });

            document.addEventListener('submit', async function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-ajax-form')) {
                    return;
                }

                // Respect canceled submit (e.g. user pressed "Cancel" in confirm dialog).
                if (event.defaultPrevented) {
                    return;
                }

                event.preventDefault();

                const formData = new FormData(form);
                formData.append('ajax', '1');

                try {
                    const response = await fetch(ajaxEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const payload = await response.json();
                    showMessage(Boolean(payload.ok), payload.message || 'Không có thông báo.');
                    updateStats(payload.stats || null);
                    renderUsers(payload.users || []);

                    const action = String(formData.get('action') || '');
                    if (payload.ok && action === 'add_user') {
                        form.reset();
                    }
                    if (payload.ok && action === 'update_user') {
                        hideEditPanel();
                    }
                } catch (error) {
                    showMessage(false, 'Không thể gửi yêu cầu AJAX: ' + (error instanceof Error ? error.message : String(error)) + '. Hệ thống sẽ gửi lại theo cách thường.');
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            });
        })();
    </script>
</body>
</html>
