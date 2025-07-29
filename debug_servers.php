<?php
// 调试服务器添加
session_start();

// 服务器配置文件
define('SERVERS_FILE', 'servers.json');

// 读取服务器列表
function getServers() {
    if (!file_exists(SERVERS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(SERVERS_FILE), true) ?? [];
}

// 保存服务器列表
function saveServers($servers) {
    return file_put_contents(SERVERS_FILE, json_encode($servers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 显示调试信息
echo "<h1>服务器管理调试</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST数据:</h2>";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_server') {
        $server = [
            'id' => uniqid(),
            'name' => trim($_POST['name'] ?? ''),
            'ip' => trim($_POST['ip'] ?? ''),
            'port' => intval($_POST['port'] ?? 22),
            'username' => trim($_POST['username'] ?? ''),
            'password' => trim($_POST['password'] ?? '')
        ];
        
        echo "<h2>准备添加的服务器:</h2>";
        echo "<pre>" . htmlspecialchars(print_r($server, true)) . "</pre>";
        
        if ($server['name'] && $server['ip'] && $server['port'] && $server['username'] && $server['password']) {
            $servers = getServers();
            $servers[] = $server;
            
            if (saveServers($servers)) {
                echo "<h2 style='color: green;'>服务器添加成功！</h2>";
                echo "<p>当前服务器数量: " . count($servers) . "</p>";
            } else {
                echo "<h2 style='color: red;'>服务器添加失败！</h2>";
                echo "<p>请检查文件权限: " . realpath(SERVERS_FILE) . "</p>";
            }
        } else {
            echo "<h2 style='color: red;'>表单数据不完整！</h2>";
        }
    }
}

// 显示当前服务器列表
$servers = getServers();
echo "<h2>当前服务器列表:</h2>";
echo "<pre>" . htmlspecialchars(print_r($servers, true)) . "</pre>";
?>

<h2>添加服务器测试表单</h2>
<form method="POST">
    <input type="hidden" name="action" value="add_server">
    <div>
        <label>备注名称:</label>
        <input type="text" name="name" required>
    </div>
    <div>
        <label>IP地址:</label>
        <input type="text" name="ip" required>
    </div>
    <div>
        <label>端口:</label>
        <input type="number" name="port" value="22" required>
    </div>
    <div>
        <label>用户名:</label>
        <input type="text" name="username" value="root" required>
    </div>
    <div>
        <label>密码:</label>
        <input type="password" name="password" required>
    </div>
    <button type="submit">添加服务器</button>
</form>
