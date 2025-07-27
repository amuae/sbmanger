<?php
require_once 'config.php';
require_once 'classes/ConfigManager.php';
require_once 'classes/UserManager.php';

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$configManager = new ConfigManager();
$userManager = new UserManager();
$users = $userManager->getUsers();
$message = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $name = $_POST['name'];
                $expiry_date = $_POST['expiry_date'];
                $result = $userManager->addUser($name, $expiry_date);
                $message = $result ? '用户添加成功' : '添加用户失败';
                break;
                
            case 'delete_user':
                $password = $_POST['password'];
                $result = $userManager->deleteUser($password);
                $message = $result ? '用户删除成功' : '删除用户失败';
                break;
                
            case 'update_config':
                $result = $configManager->deployConfig();
                $message = $result ? '配置已部署到所有服务器' : '部署失败';
                break;
        }
        header("Location: index.php?message=" . urlencode($message));
        exit;
    }
}

// 检查过期用户并自动删除
$userManager->checkExpiredUsers();

// 获取消息
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sing-box 用户管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-check"></i> Sing-box 管理面板
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> 退出登录
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">添加新用户</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_user">
                            <div class="mb-3">
                                <label for="name" class="form-label">备注名称</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">到期时间</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> 添加用户
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">配置管理</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('确定要部署配置到所有服务器吗？')">
                            <input type="hidden" name="action" value="update_config">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-cloud-upload"></i> 部署配置
                            </button>
                        </form>
                        <small class="text-muted mt-2 d-block">
                            当前服务器数量: <?php echo count(ConfigManager::getServers()); ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">用户列表</h5>
                        <span class="badge bg-secondary"><?php echo count($users); ?> 个用户</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>备注</th>
                                        <th>密码</th>
                                        <th>到期时间</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <?php 
                                        $expiry = new DateTime($user['expiry']);
                                        $now = new DateTime();
                                        $isExpired = $expiry < $now;
                                        ?>
                                        <tr class="<?php echo $isExpired ? 'table-danger' : ''; ?>">
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td>
                                                <small class="font-monospace"><?php echo $user['password']; ?></small>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?php echo $user['password']; ?>')">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </td>
                                            <td><?php echo $user['expiry']; ?></td>
                                            <td>
                                                <?php if ($isExpired): ?>
                                                    <span class="badge bg-danger">已过期</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">有效</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除此用户吗？')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="password" value="<?php echo $user['password']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('密码已复制到剪贴板');
            });
        }
    </script>
</body>
</html>
