<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

define('SERVERS_FILE', '../data/servers.json');

// 确保文件存在
if (!file_exists(SERVERS_FILE)) {
    file_put_contents(SERVERS_FILE, json_encode(['servers' => []], JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getServers();
        break;
    case 'POST':
        addServer();
        break;
    case 'DELETE':
        deleteServer();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不允许']);
}

function getServers() {
    $serversData = json_decode(file_get_contents(SERVERS_FILE), true);
    
    // 检查每个服务器的状态
    foreach ($serversData['servers'] as &$server) {
        $server['status'] = checkServerStatus($server);
    }
    
    echo json_encode($serversData);
}

function addServer() {
    $remark = $_POST['remark'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $port = intval($_POST['port'] ?? 22);
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    $key = $_POST['key'] ?? '';
    
    if (empty($remark) || empty($ip) || empty($username)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        return;
    }
    
    if (empty($password) && empty($key)) {
        echo json_encode(['success' => false, 'message' => '密码或密钥必须提供一项']);
        return;
    }
    
    $serversData = json_decode(file_get_contents(SERVERS_FILE), true);
    
    // 检查IP是否已存在
    foreach ($serversData['servers'] as $server) {
        if ($server['ip'] === $ip) {
            echo json_encode(['success' => false, 'message' => '服务器IP已存在']);
            return;
        }
    }
    
    // 添加新服务器
    $newServer = [
        'remark' => $remark,
        'ip' => $ip,
        'port' => $port,
        'username' => $username,
        'password' => $password,
        'key' => $key
    ];
    
    $serversData['servers'][] = $newServer;
    file_put_contents(SERVERS_FILE, json_encode($serversData, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
}

function deleteServer() {
    $action = $_GET['action'] ?? '';
    if ($action !== 'delete') {
        echo json_encode(['success' => false, 'message' => '无效操作']);
        return;
    }
    
    $index = intval($_GET['index'] ?? -1);
    if ($index < 0) {
        echo json_encode(['success' => false, 'message' => '无效的服务器索引']);
        return;
    }
    
    $serversData = json_decode(file_get_contents(SERVERS_FILE), true);
    
    if (!isset($serversData['servers'][$index])) {
        echo json_encode(['success' => false, 'message' => '服务器不存在']);
        return;
    }
    
    // 删除服务器
    array_splice($serversData['servers'], $index, 1);
    
    file_put_contents(SERVERS_FILE, json_encode($serversData, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
}

function checkServerStatus($server) {
    // 使用SSH登录验证服务器状态
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    // 检测Windows系统
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // 构建SSH测试命令 - 完全自动化，无需人工干预
    if (!empty($server['key'])) {
        // 使用密钥认证
        $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_test_');
        file_put_contents($keyFile, $server['key']);
        chmod($keyFile, 0600);
        
        $command = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -p %d %s@%s "echo SSH_OK" 2>&1',
            escapeshellarg($keyFile),
            $port,
            $username,
            $ip
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        // 清理密钥文件
        if (file_exists($keyFile)) {
            unlink($keyFile);
        }
        
        return $returnCode === 0 && !empty($output) && trim($output[0]) === 'SSH_OK';
    } else {
        // 使用密码认证 - 完全自动化
        if ($isWindows) {
            // Windows使用plink，使用echo y管道输入
            $plinkPath = 'C:\Program Files\PuTTY\plink.exe';
            if (file_exists($plinkPath)) {
                $command = sprintf(
                    'echo y | "%s" -pw %s -P %d -batch %s@%s "echo SSH_OK" 2>&1',
                    $plinkPath,
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip
                );
            } else {
                // 尝试PATH中的plink
                $command = sprintf(
                    'echo y | plink -pw %s -P %d -batch %s@%s "echo SSH_OK" 2>&1',
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip
                );
            }
        } else {
            // Linux使用sshpass
            $command = sprintf(
                'sshpass -p %s ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -p %d %s@%s "echo SSH_OK" 2>/dev/null',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        }
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        return $returnCode === 0 && !empty($output) && trim($output[0]) === 'SSH_OK';
    }
}
