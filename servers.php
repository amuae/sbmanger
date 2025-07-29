<?php
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

// 测试SSH连接
function testSSHConnection($server) {
    $command = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -p %d %s@%s "echo OK" 2>/dev/null',
        escapeshellarg($server['password']),
        $server['port'],
        escapeshellarg($server['username']),
        escapeshellarg($server['ip'])
    );
    
    $output = shell_exec($command);
    return trim($output) === 'OK';
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

// 处理服务器操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $servers = getServers();
    
    switch ($_POST['action']) {
        case 'add_server':
            $server = [
                'id' => uniqid(),
                'name' => trim($_POST['name']),
                'ip' => trim($_POST['ip']),
                'port' => intval($_POST['port']),
                'username' => trim($_POST['username']),
                'password' => trim($_POST['password'])
            ];
            
            if ($server['name'] && $server['ip'] && $server['port'] && $server['username'] && $server['password']) {
                $servers[] = $server;
                if (saveServers($servers)) {
                    $_SESSION['message'] = '服务器添加成功！';
                } else {
                    $_SESSION['message'] = '服务器添加失败，请检查文件权限！';
                }
            } else {
                $_SESSION['message'] = '请填写完整的服务器信息！';
            }
            break;
            
        case 'delete_server':
            $serverId = $_POST['server_id'];
            $servers = array_filter($servers, function($server) use ($serverId) {
                return $server['id'] !== $serverId;
            });
            $servers = array_values($servers);
            if (saveServers($servers)) {
                $_SESSION['message'] = '服务器删除成功！';
            } else {
                $_SESSION['message'] = '服务器删除失败！';
            }
            break;
            
        case 'test_connection':
            $serverId = $_POST['server_id'];
            foreach ($servers as $server) {
                if ($server['id'] === $serverId) {
                    $isConnected = testSSHConnection($server);
                    $_SESSION['message'] = $isConnected ? 'SSH连接成功！' : 'SSH连接失败！';
                    break;
                }
            }
            break;
            
        case 'distribute_single':
            $serverId = $_POST['server_id'];
            foreach ($servers as $server) {
                if ($server['id'] === $serverId) {
                    $result = distributeConfig($server);
                    if ($result['success']) {
                        restartSingBox($server);
                        $_SESSION['message'] = '配置分发成功！';
                    } else {
                        $_SESSION['message'] = '分发失败: ' . $result['message'];
                    }
                    break;
                }
            }
            break;
            
        case 'distribute_all':
            $successCount = 0;
            $errorMessages = [];
            
            foreach ($servers as $server) {
                $result = distributeConfig($server);
                if ($result['success']) {
                    $successCount++;
                    restartSingBox($server);
                } else {
                    $errorMessages[] = $server['name'] . ': ' . $result['message'];
                }
            }
            
            if ($successCount > 0) {
                $_SESSION['message'] = "成功分发到 {$successCount} 台服务器";
                if (!empty($errorMessages)) {
                    $_SESSION['message'] .= '<br>失败的服务器:<br>' . implode('<br>', $errorMessages);
                }
            } else {
                $_SESSION['message'] = '分发失败: ' . implode(', ', $errorMessages);
            }
            break;
    }
    
    header('Location: index.php?page=servers');
    exit;
}

$servers = getServers();

// 测试所有服务器的连接状态
$serverStatus = [];
foreach ($servers as $server) {
    $serverStatus[$server['id']] = testSSHConnection($server);
}
?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-server"></i> 服务器列表</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="distribute_all">
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('确定要分发配置到所有服务器吗？')">
                        <i class="bi bi-cloud-upload"></i> 分发到全部
                    </button>
                </form>
            </div>
            <div class="card-body">
                <?php if (empty($servers)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">暂无服务器</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($servers as $server): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= htmlspecialchars($server['name']) ?></h6>
                                        <span class="badge bg-<?= $serverStatus[$server['id']] ? 'success' : 'danger' ?>">
                                            <i class="bi bi-<?= $serverStatus[$server['id']] ? 'check-circle' : 'x-circle' ?>"></i>
                                            <?= $serverStatus[$server['id']] ? '在线' : '离线' ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1"><strong>IP:</strong> <?= htmlspecialchars($server['ip']) ?></p>
                                        <p class="mb-1"><strong>端口:</strong> <?= $server['port'] ?></p>
                                        <p class="mb-1"><strong>用户名:</strong> <?= htmlspecialchars($server['username']) ?></p>
                                        <div class="mt-3">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="test_connection">
                                                <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-wifi"></i> 测试连接
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="distribute_single">
                                                <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-cloud-upload"></i> 分发配置
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_server">
                                                <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定要删除这个服务器吗？')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 添加服务器</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_server">
                    <div class="mb-3">
                        <label for="name" class="form-label">备注名称</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="ip" class="form-label">IP地址</label>
                        <input type="text" class="form-control" id="ip" name="ip" required>
                    </div>
                    <div class="mb-3">
                        <label for="port" class="form-label">端口</label>
                        <input type="number" class="form-control" id="port" name="port" value="22" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" value="root" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus"></i> 添加服务器
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
