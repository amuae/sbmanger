<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 服务器配置文件
define('SERVERS_FILE', 'servers.json');

// 读取服务器列表
function getServers() {
    if (!file_exists(SERVERS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(SERVERS_FILE), true) ?? [];
}

// 分发配置文件到服务器
function distributeConfig($server) {
    $configPath = 'config.json';
    if (!file_exists($configPath)) {
        return ['success' => false, 'message' => '配置文件不存在'];
    }
    
    $remotePath = '/root/sing-box/config.json';
    
    // 创建远程目录
    $mkdirCommand = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -p %d %s@%s "mkdir -p /root/sing-box" 2>/dev/null',
        escapeshellarg($server['password']),
        $server['port'],
        escapeshellarg($server['username']),
        escapeshellarg($server['ip'])
    );
    
    shell_exec($mkdirCommand);
    
    // 上传配置文件
    $command = sprintf(
        'sshpass -p %s scp -o StrictHostKeyChecking=no -P %d %s %s@%s:%s 2>&1',
        escapeshellarg($server['password']),
        $server['port'],
        escapeshellarg($configPath),
        escapeshellarg($server['username']),
        escapeshellarg($server['ip']),
        escapeshellarg($remotePath)
    );
    
    $output = shell_exec($command);
    
    if ($output === null || strpos($output, 'Permission denied') !== false) {
        return ['success' => false, 'message' => 'SSH连接失败或权限不足'];
    }
    
    return ['success' => true, 'message' => '配置文件分发成功'];
}

// 重启sing-box服务
function restartSingBox($server) {
    $command = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -p %d %s@%s "systemctl restart sing-box" 2>/dev/null',
        escapeshellarg($server['password']),
        $server['port'],
        escapeshellarg($server['username']),
        escapeshellarg($server['ip'])
    );
    
    $output = shell_exec($command);
    return $output !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'distribute_single') {
        $serverId = $_POST['server_id'] ?? '';
        $servers = getServers();
        
        foreach ($servers as $server) {
            if ($server['id'] === $serverId) {
                $result = distributeConfig($server);
                if ($result['success']) {
                    restartSingBox($server);
                }
                echo json_encode($result);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => '服务器未找到']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => '无效请求']);
exit;
?>
