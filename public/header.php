<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) redirectToLogin();
$current_page = basename($_SERVER['PHP_SELF']);

// 处理账户修改（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_account') {
    $oldUser = trim($_POST['old_username'] ?? '');
    $oldPass = $_POST['old_password'] ?? '';
    $newUser = trim($_POST['new_username'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $error = $success = '';
    $db = getDB();
    if ($oldUser === '' || $oldPass === '') {
        $error = "原用户名和密码必填";
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bindValue(1, $_SESSION['username'], SQLITE3_TEXT);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$user || !password_verify($oldPass, $user['password'])) {
            $error = "原用户名或密码错误";
        } elseif ($newUser === '' && $newPass === '') {
            $error = "请至少填写新用户名或新密码";
        } else {
            // 新用户名检查
            if ($newUser !== '' && $newUser !== $_SESSION['username']) {
                if (strlen($newUser) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $newUser)) {
                    $error = "新用户名至少3位，仅允许字母数字下划线";
                } else {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND username != ?");
                    $stmt->bindValue(1, $newUser, SQLITE3_TEXT);
                    $stmt->bindValue(2, $_SESSION['username'], SQLITE3_TEXT);
                    if ($stmt->execute()->fetchArray()) $error = "新用户名已存在";
                }
            }
            if ($newPass !== '' && strlen($newPass) < 6) $error = "新密码至少6位";
            if (!$error) {
                $sql = "UPDATE users SET ";
                $params = [];
                if ($newUser !== '') {
                    $sql .= "username = ?, ";
                    $params[] = $newUser;
                }
                if ($newPass !== '') {
                    $sql .= "password = ?, ";
                    $params[] = password_hash($newPass, PASSWORD_DEFAULT);
                }
                $sql = rtrim($sql, ', ') . " WHERE username = ?";
                $params[] = $_SESSION['username'];
                $stmt = $db->prepare($sql);
                foreach ($params as $i => $v) $stmt->bindValue($i + 1, $v, SQLITE3_TEXT);
                if ($stmt->execute()) {
                    if ($newUser !== '') $_SESSION['username'] = $newUser;
                    $success = "账户信息已更新";
                } else {
                    $error = "更新失败";
                }
            }
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => empty($error), 'message' => empty($error) ? $success : $error]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SBManger - 管理面板</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .nav-btn-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">SBManger</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="nav-btn-group me-auto">
                <a class="btn btn-outline-light btn-sm <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-people"></i> 用户管理
                </a>
                <a class="btn btn-outline-light btn-sm <?= basename($_SERVER['PHP_SELF']) == 'servers.php' ? 'active' : '' ?>" href="servers.php">
                    <i class="bi bi-server"></i> 服务器管理
                </a>
            </div>
            <div class="nav-btn-group">
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#changeAccountModal">
                    <i class="bi bi-person-gear"></i> 账户设置
                </button>
                <a class="btn btn-outline-light btn-sm" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> 退出
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- 账户修改弹窗 -->
<div class="modal fade" id="changeAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-gear"></i> 修改账户信息</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_account">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">原用户名</label>
                        <input type="text" class="form-control" name="old_username" value="<?= htmlspecialchars($_SESSION['username']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">原密码</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">新用户名（留空保持原值）</label>
                        <input type="text" class="form-control" name="new_username" placeholder="留空不修改" minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">新密码（留空保持原值）</label>
                        <input type="password" class="form-control" name="new_password" placeholder="留空不修改" minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
