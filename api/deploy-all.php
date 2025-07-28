<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

define('SERVERS_FILE', '../data/servers.json');
define('CONFIG_FILE', '../data/config.json');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// 禁用输出缓冲
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ob_implicit_flush(1);

// 获取所有服务器
$serversData = json_decode(file_get_contents(SERVERS_FILE), true);
$servers = $serversData['servers'] ?? [];

if (empty($servers)) {
    sendMessage('没有可用的服务器', 'error', 0);
    exit;
}

// 检查配置文件是否存在
if (!file_exists(CONFIG_FILE)) {
    sendMessage('配置文件不存在，请先配置sing-box', 'error', 0);
    exit;
}

$configContent = file_get_contents(CONFIG_FILE);

// 开始批量部署
$totalServers = count($servers);
$current = 0;

sendMessage("开始批量部署到 {$totalServers} 台服务器...", 'info', 0);

foreach ($servers as $index => $server) {
    $current++;
    $progress = intval(($current / $totalServers) * 100);
    
    sendMessage("正在部署到服务器 {$current}/{$totalServers}: {$server['remark']} ({$server['ip']})", 'info', $progress);
    
    // 检查服务器状态
    if (!checkServerStatus($server)) {
        sendMessage("服务器 {$server['remark']} SSH连接失败，跳过", 'warning', $progress);
        continue;
    }
    
    // 执行部署
    $result = deployToServer($server, $configContent);
    
    if ($result['success']) {
        sendMessage("✅ 服务器 {$server['remark']} 部署成功", 'success', $progress);
    } else {
        sendMessage("❌ 服务器 {$server['remark']} 部署失败: {$result['message']}", 'error', $progress);
    }
}

sendMessage("批量部署完成", 'complete', 100);

function sendMessage($message, $type = 'info', $progress = null) {
    $data = [
        'message' => $message,
        'type' => $type,
        'timestamp' => date('H:i:s'),
        'progress' => $progress
    ];
    
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

function checkServerStatus($server) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if (!empty($server['key_path']) && file_exists($server['key_path'])) {
        // 使用密钥认证
        $keyPath = escapeshellarg($server['key_path']);
        
        $command = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -p %d %s@%s "echo SSH_OK" 2>&1',
            $keyPath,
            $port,
            $username,
            $ip
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        return $returnCode === 0 && !empty($output) && trim($output[0]) === 'SSH_OK';
    } else {
        // 使用密码认证 - 完全自动化
        if ($isWindows) {
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
                $command = sprintf(
                    'echo y | plink -pw %s -P %d -batch %s@%s "echo SSH_OK" 2>&1',
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip
                );
            }
        } else {
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

function deployToServer($server, $configContent) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // 创建临时配置文件
    $tempConfig = tempnam(sys_get_temp_dir(), 'singbox_config_');
    file_put_contents($tempConfig, $configContent);
    
    // 创建远程目录
    $mkdirCommand = '';
    if ($isWindows) {
        $plinkPath = 'C:\Program Files\PuTTY\plink.exe';
        if (file_exists($plinkPath)) {
            $mkdirCommand = sprintf(
                'echo y | "%s" -pw %s -P %d -batch %s@%s "mkdir -p /etc/sing-box" 2>&1',
                $plinkPath,
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        } else {
            $mkdirCommand = sprintf(
                'echo y | plink -pw %s -P %d -batch %s@%s "mkdir -p /etc/sing-box" 2>&1',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        }
    } else {
        if (!empty($server['key_path']) && file_exists($server['key_path'])) {
            $keyPath = escapeshellarg($server['key_path']);
            
            $mkdirCommand = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s "mkdir -p /etc/sing-box" 2>&1',
                $keyPath,
                $port,
                $username,
                $ip
            );
        } else {
            $mkdirCommand = sprintf(
                'sshpass -p %s ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s "mkdir -p /etc/sing-box" 2>&1',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        }
    }
    
    exec($mkdirCommand, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['success' => false, 'message' => '无法创建远程目录'];
    }
    
    // 上传配置文件
    $scpCommand = '';
    if ($isWindows) {
        $pscpPath = 'C:\Program Files\PuTTY\pscp.exe';
        if (file_exists($pscpPath)) {
            $scpCommand = sprintf(
                'echo y | "%s" -pw %s -P %d -scp %s %s@%s:/etc/sing-box/config.json 2>&1',
                $pscpPath,
                escapeshellarg($server['password']),
                $port,
                escapeshellarg($tempConfig),
                $username,
                $ip
            );
        } else {
            $scpCommand = sprintf(
                'echo y | pscp -pw %s -P %d -scp %s %s@%s:/etc/sing-box/config.json 2>&1',
                escapeshellarg($server['password']),
                $port,
                escapeshellarg($tempConfig),
                $username,
                $ip
            );
        }
    } else {
        if (!empty($server['key_path']) && file_exists($server['key_path'])) {
            $keyPath = escapeshellarg($server['key_path']);
            
            $scpCommand = sprintf(
                'scp -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:/etc/sing-box/config.json 2>&1',
                $keyPath,
                $port,
                escapeshellarg($tempConfig),
                $username,
                $ip
            );
        } else {
            $scpCommand = sprintf(
                'sshpass -p %s scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:/etc/sing-box/config.json 2>&1',
                escapeshellarg($server['password']),
                $port,
                escapeshellarg($tempConfig),
                $username,
                $ip
            );
        }
    }
    
    exec($scpCommand, $output, $returnCode);
    
    // 清理临时文件
    if (file_exists($tempConfig)) {
        unlink($tempConfig);
    }
    
    if ($returnCode !== 0) {
        return ['success' => false, 'message' => '配置文件上传失败'];
    }
    
    // 重启sing-box服务
    $restartCommand = '';
    if ($isWindows) {
        $plinkPath = 'C:\Program Files\PuTTY\plink.exe';
        if (file_exists($plinkPath)) {
            $restartCommand = sprintf(
                'echo y | "%s" -pw %s -P %d -batch %s@%s "systemctl restart sing-box" 2>&1',
                $plinkPath,
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        } else {
            $restartCommand = sprintf(
                'echo y | plink -pw %s -P %d -batch %s@%s "systemctl restart sing-box" 2>&1',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        }
    } else {
        if (!empty($server['key_path']) && file_exists($server['key_path'])) {
            $keyPath = escapeshellarg($server['key_path']);
            
            $restartCommand = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s "systemctl restart sing-box" 2>&1',
                $keyPath,
                $port,
                $username,
                $ip
            );
        } else {
            $restartCommand = sprintf(
                'sshpass -p %s ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s "systemctl restart sing-box" 2>&1',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip
            );
        }
    }
    
    exec($restartCommand, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['success' => false, 'message' => '服务重启失败，请检查sing-box是否已安装'];
    }
    
    return ['success' => true, 'message' => '部署成功'];
}
?>
