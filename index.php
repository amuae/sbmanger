<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// 配置文件路径
define('CONFIG_PATH', 'config.json');

// 读取配置文件
function getConfig() {
    if (!file_exists(CONFIG_PATH)) {
        return ['inbounds' => [['tag' => 'trojan-1', 'users' => []]]];
    }
    $config = json_decode(file_get_contents(CONFIG_PATH), true);
    if (!isset($config['inbounds'])) {
        $config['inbounds'] = [['tag' => 'trojan-1', 'users' => []]];
    }
    return $config;
}

// 保存配置文件
function saveConfig($config) {
    file_put_contents(CONFIG_PATH, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 生成随机密码
function generatePassword($length = 24) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// 检查用户是否过期
function isExpired($name) {
    if (preg_match('/-(\d{4}-\d{2}-\d{2})$/', $name, $matches)) {
        $expireDate = $matches[1];
        return strtotime($expireDate) < time();
    }
    return false;
}

// 处理用户配置操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $config = getConfig();
    
    switch ($_POST['action']) {
        case 'add_user':
            $username = trim($_POST['username']);
            $expireDate = trim($_POST['expire_date']);
            
            if ($username && $expireDate) {
                $name = $username . '-' . $expireDate;
                $password = generatePassword();
                
                // 查找trojan-1 inbound
                $trojanInbound = null;
                foreach ($config['inbounds'] as &$inbound) {
                    if ($inbound['tag'] === 'trojan-1') {
                        $trojanInbound = &$inbound;
                        break;
                    }
                }
                
                if ($trojanInbound === null) {
                    $config['inbounds'][] = ['tag' => 'trojan-1', 'users' => []];
                    $trojanInbound = &$config['inbounds'][count($config['inbounds']) - 1];
                }
                
                // 添加新用户
                $trojanInbound['users'][] = [
                    'name' => $name,
                    'password' => $password
                ];
                
                saveConfig($config);
                $_SESSION['message'] = '用户添加成功！';
            }
            break;
            
        case 'delete_user':
            $userIndex = $_POST['user_index'];
            $trojanInbound = null;
            
            foreach ($config['inbounds'] as &$inbound) {
                if ($inbound['tag'] === 'trojan-1') {
                    $trojanInbound = &$inbound;
                    break;
                }
            }
            
            if ($trojanInbound && isset($trojanInbound['users'][$userIndex])) {
                unset($trojanInbound['users'][$userIndex]);
                $trojanInbound['users'] = array_values($trojanInbound['users']);
                saveConfig($config);
                $_SESSION['message'] = '用户删除成功！';
            }
            break;
            
        case 'cleanup_expired':
            $trojanInbound = null;
            foreach ($config['inbounds'] as &$inbound) {
                if ($inbound['tag'] === 'trojan-1') {
                    $trojanInbound = &$inbound;
                    break;
                }
            }
            
            if ($trojanInbound && isset($trojanInbound['users'])) {
                $originalCount = count($trojanInbound['users']);
                $trojanInbound['users'] = array_filter($trojanInbound['users'], function($user) {
                    return !isExpired($user['name']);
                });
                $trojanInbound['users'] = array_values($trojanInbound['users']);
                
                if (count($trojanInbound['users']) < $originalCount) {
                    saveConfig($config);
                    $_SESSION['message'] = '已清理过期用户！';
                }
            }
            break;
    }
    
    header('Location: index.php');
    exit;
}

// 清理过期用户（自动）
$config = getConfig();
$trojanInbound = null;
foreach ($config['inbounds'] as &$inbound) {
    if ($inbound['tag'] === 'trojan-1') {
        $trojanInbound = &$inbound;
        break;
    }
}

if ($trojanInbound && isset($trojanInbound['users'])) {
    $originalCount = count($trojanInbound['users']);
    $trojanInbound['users'] = array_filter($trojanInbound['users'], function($user) {
        return !isExpired($user['name']);
    });
    $trojanInbound['users'] = array_values($trojanInbound['users']);
    
    if (count($trojanInbound['users']) < $originalCount) {
        saveConfig($config);
    }
}

$users = $trojanInbound['users'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sing-Box 配置管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .expired {
            background-color: #fff3cd;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-gear-fill"></i> Sing-Box 管理</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="?page=users"><i class="bi bi-people-fill"></i> 用户配置</a>
                <a class="nav-link" href="?page=servers"><i class="bi bi-server"></i> 服务器配置</a>
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> 退出</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php
        $page = $_GET['page'] ?? 'users';
        if ($page === 'users') {
            include 'users.php';
        } else {
            include 'servers.php';
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
