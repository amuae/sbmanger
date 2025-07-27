<?php
require_once 'config.php';
require_once 'classes/ConfigManager.php';

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$configManager = new ConfigManager();
$servers = ConfigManager::getServers();
$message = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_server':
                $name = $_POST['name'];
                $url = $_POST['url'];
                $token = $_POST['token'];
                
                $servers[] = [
                    'name' => $name,
                    'url' => $url,
                    'token' => $token
                ];
                
                file_put_contents(SERVERS_FILE, json_encode($servers, JSON_PRETTY_PRINT));
                $message = '服务器添加成功';
                break;
                
            case 'delete_server':
                $index = $_POST['index'];
                if (isset($servers[$index])) {
                    unset($servers[$index]);
                    $servers = array_values($servers);
                    file_put_contents(SERVERS_FILE, json_encode($servers, JSON_PRETTY_PRINT));
                    $message = '服务器删除成功';
                }
                break;
        }
        
        header("Location: servers.php?message=" . urlencode($message));
        exit;
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器管理 - Sing-box 管理面板</title>
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-check"></i> Sing-box 管理面板
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">用户管理</a>
                <a class="nav-link active" href="servers.php">服务器管理</a>
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
                        <h5 class="mb-0">添加服务器</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_server">
                            <div class="mb-3">
                                <label for="name" class="form-label">服务器名称</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="url" class="form-label">服务器URL</label>
                                <input type="url" class="form-control" id="url" name="url" 
                                       placeholder="http://your-server.com" required>
                            </div>
                            <div class="mb-3">
                                <label for="token" class="form-label">Token</label>
                                <input type="text" class="form-control" id="token" name="token" 
                                       placeholder="从服务器获取的token" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> 添加服务器
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">服务器列表</h5>
                        <span class="badge bg-secondary"><?php echo count($servers); ?> 台服务器</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($servers)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-server" style="font-size: 3rem;"></i>
                                <p class="mt-3">暂无服务器</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>名称</th>
                                            <th>URL</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($servers as $index => $server): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($server['name']); ?></td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($server['url']); ?></small>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('确定要删除此服务器吗？')">
                                                        <input type="hidden" name="action" value="delete_server">
                                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
