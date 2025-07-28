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

// 根据操作系统选择部署方式
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
if ($isWindows) {
    // Windows系统 - 使用plink或ssh命令
    $result = deployToServerWindows($server, $configContent);
} else {
    // Linux/Unix系统 - 使用ssh命令
    $result = deployToServerLinux($server, $configContent);
}

echo json_encode($result);

function deployToServerWindows($server, $configContent) {
    // 创建临时配置文件
    $tempFile = tempnam(sys_get_temp_dir(), 'singbox_config_');
    file_put_contents($tempFile, $configContent);
    
    $remotePath = '/root/sing-box/config.json';
    
    try {
        // 构建SSH命令
        $sshCommand = buildSSHCommand($server, $tempFile, $remotePath);
        
        // 执行命令
        $output = [];
        $returnCode = 0;
        exec($sshCommand . ' 2>&1', $output, $returnCode);
        
        // 清理临时文件
        unlink($tempFile);
        
        if ($returnCode === 0) {
            return ['success' => true, 'message' => '配置部署成功'];
        } else {
            return ['success' => false, 'message' => '部署失败: ' . implode("\n", $output)];
        }
    } catch (Exception $e) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        return ['success' => false, 'message' => '部署异常: ' . $e->getMessage()];
    }
}

function deployToServerLinux($server, $configContent) {
    // 创建临时配置文件
    $tempFile = tempnam('/tmp', 'singbox_config_');
    file_put_contents($tempFile, $configContent);
    
    $remotePath = '/root/sing-box/config.json';
    
    try {
        // 构建SSH命令
        $sshCommand = buildSSHCommand($server, $tempFile, $remotePath);
        
        // 执行命令
        $output = [];
        $returnCode = 0;
        exec($sshCommand . ' 2>&1', $output, $returnCode);
        
        // 清理临时文件
        unlink($tempFile);
        
        if ($returnCode === 0) {
            return ['success' => true, 'message' => '配置部署成功'];
        } else {
            return ['success' => false, 'message' => '部署失败: ' . implode("\n", $output)];
        }
    } catch (Exception $e) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        return ['success' => false, 'message' => '部署异常: ' . $e->getMessage()];
    }
}

function buildSSHCommand($server, $localFile, $remotePath) {
    $username = escapeshellarg($server['username']);
    $ip = escapeshellarg($server['ip']);
    $port = intval($server['port']);
    
    // 确保远程目录存在
    $mkdirCommand = "mkdir -p /root/sing-box";
    
    // 检测Windows系统
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // 构建完整的SSH命令 - 完全自动化，无需人工干预
    if (!empty($server['key'])) {
        // 使用密钥认证
        $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
        file_put_contents($keyFile, $server['key']);
        chmod($keyFile, 0600);
        
        $command = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s "%s && cat > %s" < %s',
            escapeshellarg($keyFile),
            $port,
            $username,
            $ip,
            $mkdirCommand,
            escapeshellarg($remotePath),
            escapeshellarg($localFile)
        );
        
        // 清理密钥文件
        register_shutdown_function(function() use ($keyFile) {
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
        });
    } else {
        // 使用密码认证 - 完全自动化
        if ($isWindows) {
            // Windows使用plink，使用echo y管道输入
            $plinkPath = 'C:\Program Files\PuTTY\plink.exe';
            if (file_exists($plinkPath)) {
                $command = sprintf(
                    'echo y | "%s" -pw %s -P %d -batch %s@%s "%s && cat > %s" < %s',
                    $plinkPath,
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip,
                    $mkdirCommand,
                    escapeshellarg($remotePath),
                    escapeshellarg($localFile)
                );
            } else {
                // 尝试PATH中的plink
                $command = sprintf(
                    'echo y | plink -pw %s -P %d -batch %s@%s "%s && cat > %s" < %s',
                    escapeshellarg($server['password']),
                    $port,
                    $username,
                    $ip,
                    $mkdirCommand,
                    escapeshellarg($remotePath),
                    escapeshellarg($localFile)
                );
            }
        } else {
            // Linux使用sshpass
            $command = sprintf(
                'sshpass -p %s ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -p %d %s@%s "%s && cat > %s" < %s',
                escapeshellarg($server['password']),
                $port,
                $username,
                $ip,
                $mkdirCommand,
                escapeshellarg($remotePath),
                escapeshellarg($localFile)
            );
        }
    }
    
    return $command;
}
