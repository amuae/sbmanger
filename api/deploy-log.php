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

$serverIndex = intval($_GET['server'] ?? -1);
if ($serverIndex < 0) {
    echo json_encode(['success' => false, 'message' => '无效的服务器索引']);
    exit;
}

$serversData = json_decode(file_get_contents(SERVERS_FILE), true);
if (!isset($serversData['servers'][$serverIndex])) {
    echo json_encode(['success' => false, 'message' => '服务器不存在']);
    exit;
}

$server = $serversData['servers'][$serverIndex];

if (!file_exists(CONFIG_FILE)) {
    echo json_encode(['success' => false, 'message' => '配置文件不存在']);
    exit;
}

$configContent = file_get_contents(CONFIG_FILE);

// 设置SSE头
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // 禁用Nginx缓冲

// 发送初始状态
sendSSEMessage('start', '开始部署配置...');

// 步骤1: 检查SSH连接
sendSSEMessage('progress', '正在检查SSH连接...', 10);
$sshStatus = checkSSHConnection($server);
if (!$sshStatus) {
    sendSSEMessage('error', 'SSH连接失败，请检查服务器配置', 100);
    exit;
}
sendSSEMessage('progress', 'SSH连接成功', 20);

// 步骤2: 创建远程目录
sendSSEMessage('progress', '正在创建远程目录...', 30);
$mkdirResult = executeRemoteCommand($server, 'mkdir -p /root/sing-box');
if (!$mkdirResult['success']) {
    sendSSEMessage('error', '创建目录失败: ' . $mkdirResult['message'], 100);
    exit;
}
sendSSEMessage('progress', '远程目录创建成功', 40);

// 步骤3: 上传配置文件
sendSSEMessage('progress', '正在上传配置文件...', 50);
$uploadResult = uploadConfigFile($server, $configContent);
if (!$uploadResult['success']) {
    sendSSEMessage('error', '上传配置文件失败: ' . $uploadResult['message'], 100);
    exit;
}
sendSSEMessage('progress', '配置文件上传成功', 70);

// 步骤4: 验证配置文件
sendSSEMessage('progress', '正在验证配置文件...', 80);
$validateResult = executeRemoteCommand($server, 'sing-box check -c /root/sing-box/config.json');
if (!$validateResult['success']) {
    sendSSEMessage('error', '配置文件验证失败: ' . $validateResult['message'], 100);
    exit;
}
sendSSEMessage('progress', '配置文件验证成功', 90);

// 步骤5: 重启sing-box服务
sendSSEMessage('progress', '正在重启sing-box服务...', 95);
$restartResult = executeRemoteCommand($server, 'systemctl restart sing-box');
if (!$restartResult['success']) {
    sendSSEMessage('warning', '重启sing-box服务失败，请手动重启: ' . $restartResult['message'], 100);
} else {
    sendSSEMessage('progress', 'sing-box服务重启成功', 100);
}

sendSSEMessage('complete', '配置部署完成！', 100);

function sendSSEMessage($type, $message, $progress = null) {
    $data = [
        'type' => $type,
        'message' => $message,
        'timestamp' => date('H:i:s')
    ];
    
    if ($progress !== null) {
        $data['progress'] = $progress;
    }
    
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

function checkSSHConnection($server) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    // 检测Windows系统
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if (!empty($server['key'])) {
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
        
        if (file_exists($keyFile)) {
            unlink($keyFile);
        }
        
        return $returnCode === 0 && !empty($output) && trim($output[0]) === 'SSH_OK';
    } else {
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

function executeRemoteCommand($server, $command) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    // 检测Windows系统
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if (!empty($server['key'])) {
        $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_exec_');
        file_put_contents($keyFile, $server['key']);
        chmod($keyFile, 0600);
        
        $sshCommand = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d %s@%s %s 2>&1',
            escapeshellarg($keyFile),
            $port,
            $username,
            $ip,
            escapeshellarg($command)
        );
        
        $output = [];
        $returnCode = 0;
        exec($sshCommand, $output, $returnCode);
        
        if (file_exists($keyFile)) {
            unlink($keyFile);
        }
        
        return [
            'success' => $returnCode === 0,
            'message' => implode("\n", $output),
            'output' => $output
        ];
    } else {
        if ($isWindows) {
            // Windows使用plink，使用echo y管道输入
            $plinkPath = 'C:\Program Files\PuTTY\plink.exe';
            if (file_exists($plinkPath)) {
                $sshCommand = sprintf(
                    'echo y | "%s" -pw %s -P %d -batch %s@%s %s 2>&1',
                    $plinkPath,
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip,
                    escapeshellarg($command)
                );
            } else {
                // 尝试PATH中的plink
                $sshCommand = sprintf(
                    'echo y | plink -pw %s -P %d -batch %s@%s %s 2>&1',
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip,
                    escapeshellarg($command)
                );
            }
        } else {
            $sshCommand = sprintf(
                'sshpass -p %s ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d %s@%s %s 2>&1',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip,
                escapeshellarg($command)
            );
        }
        
        $output = [];
        $returnCode = 0;
        exec($sshCommand, $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'message' => implode("\n", $output),
            'output' => $output
        ];
    }
}

function uploadConfigFile($server, $configContent) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'singbox_config_');
    file_put_contents($tempFile, $configContent);
    
    // 检测Windows系统
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if (!empty($server['key'])) {
        $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_upload_');
        file_put_contents($keyFile, $server['key']);
        chmod($keyFile, 0600);
        
        $scpCommand = sprintf(
            'scp -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:/root/sing-box/config.json 2>&1',
            escapeshellarg($keyFile),
            $port,
            escapeshellarg($tempFile),
            $username,
            $ip
        );
        
        $output = [];
        $returnCode = 0;
        exec($scpCommand, $output, $returnCode);
        
        if (file_exists($keyFile)) {
            unlink($keyFile);
        }
    } else {
        if ($isWindows) {
            // Windows使用pscp，使用echo y管道输入
            $pscpPath = 'C:\Program Files\PuTTY\pscp.exe';
            if (file_exists($pscpPath)) {
                $scpCommand = sprintf(
                    'echo y | "%s" -pw %s -P %d -batch %s %s@%s:/root/sing-box/config.json 2>&1',
                    $pscpPath,
                    escapeshellarg($server['password']),
                    $port,
                    escapeshellarg($tempFile),
                    $username,
                    $ip
                );
            } else {
                // 尝试PATH中的pscp
                $scpCommand = sprintf(
                    'echo y | pscp -pw %s -P %d -batch %s %s@%s:/root/sing-box/config.json 2>&1',
                    escapeshellarg($server['password']),
                    $port,
                    escapeshellarg($tempFile),
                    $username,
                    $ip
                );
            }
        } else {
            $scpCommand = sprintf(
                'sshpass -p %s scp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:/root/sing-box/config.json 2>&1',
                escapeshellarg($server['password']),
                $port,
                escapeshellarg($tempFile),
                $username,
                $ip
            );
        }
        
        $output = [];
        $returnCode = 0;
        exec($scpCommand, $output, $returnCode);
    }
    
    unlink($tempFile);
    
    return [
        'success' => $returnCode === 0,
        'message' => implode("\n", $output),
        'output' => $output
    ];
}
