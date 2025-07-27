<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'classes/ConfigManager.php';

$configManager = new ConfigManager();
$servers = $configManager->getServers();

// 获取服务器状态数据
$agentData = [];
if (file_exists('data/agent_data.json')) {
    $agentData = json_decode(file_get_contents('data/agent_data.json'), true) ?: [];
}

// 格式化字节
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

// 获取状态颜色
function getStatusColor($status) {
    if ($status === 'online') return 'success';
    if ($status === 'warning') return 'warning';
    return 'danger';
}

// 检查服务器状态
function checkServerStatus($lastUpdate) {
    $lastTime = strtotime($lastUpdate);
    $currentTime = time();
    $diff = $currentTime - $lastTime;
    
    if ($diff < 60) return 'online';
    if ($diff < 300) return 'warning';
    return 'offline';
}

// 获取使用率颜色
function getUsageColor($usage) {
    if ($usage < 50) return 'success';
    if ($usage < 80) return 'warning';
    return 'danger';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器监控 - Sing-box 管理面板</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .progress {
            height: 8px;
        }
        .server-card {
            margin-bottom: 20px;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #6c757d;
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
                <a class="nav-link" href="index.php">用户信息</a>
                <a class="nav-link" href="servers.php">服务器管理</a>
                <a class="nav-link active" href="dashboard.php">服务器监控</a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> 退出登录
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-server"></i> 服务器监控
                </h2>
            </div>
        </div>

        <?php if (empty($agentData)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <i class="bi bi-cloud-slash" style="font-size: 4rem; color: #6c757d;"></i>
                            <h4 class="mt-4 text-muted">暂无服务器数据</h4>
                            <p class="text-muted">请确保Agent已正确配置并运行</p>
                            <a href="servers.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> 添加服务器
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($agentData as $hostname => $data): 
                    $status = checkServerStatus($data['last_update']);
                    $statusColor = getStatusColor($status);
                    $statusText = [
                        'online' => '在线',
                        'warning' => '延迟',
                        'offline' => '离线'
                    ][$status];
                ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card server-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="status-indicator bg-<?php echo $statusColor; ?>"></span>
                                    <strong><?php echo htmlspecialchars($data['server_name'] ?? $hostname); ?></strong>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($data['ip_address']); ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="metric-value text-<?php echo getUsageColor($data['cpu']['usage_percent']); ?>">
                                            <?php echo number_format($data['cpu']['usage_percent'], 1); ?>%
                                        </div>
                                        <div class="metric-label">CPU使用率</div>
                                        <small class="text-muted"><?php echo $data['cpu']['cores']; ?> 核心</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="metric-value text-<?php echo getUsageColor($data['memory']['used_percent']); ?>">
                                            <?php echo number_format($data['memory']['used_percent'], 1); ?>%
                                        </div>
                                        <div class="metric-label">内存使用率</div>
                                        <small class="text-muted">
                                            <?php echo formatBytes($data['memory']['used']); ?> / 
                                            <?php echo formatBytes($data['memory']['total']); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small">CPU</span>
                                        <span class="small"><?php echo number_format($data['cpu']['usage_percent'], 1); ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo getUsageColor($data['cpu']['usage_percent']); ?>" 
                                             style="width: <?php echo min($data['cpu']['usage_percent'], 100); %>%"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small">内存</span>
                                        <span class="small"><?php echo number_format($data['memory']['used_percent'], 1); ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo getUsageColor($data['memory']['used_percent']); ?>" 
                                             style="width: <?php echo min($data['memory']['used_percent'], 100); %>%"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small">磁盘</span>
                                        <span class="small"><?php echo number_format($data['disk']['used_percent'], 1); ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo getUsageColor($data['disk']['used_percent']); ?>" 
                                             style="width: <?php echo min($data['disk']['used_percent'], 100); %>%"></div>
                                    </div>
                                </div>

                                <div class="row text-center">
                                    <div class="col-6">
                                        <small class="text-muted">磁盘</small>
                                        <div class="small">
                                            <?php echo formatBytes($data['disk']['used']); ?> / 
                                            <?php echo formatBytes($data['disk']['total']); ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">网络流量</small>
                                        <div class="small">
                                            ↑ <?php echo formatBytes($data['network']['bytes_sent']); ?>
                                            <br>
                                            ↓ <?php echo formatBytes($data['network']['bytes_recv']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>
                                    <i class="bi bi-clock"></i> 
                                    最后更新: <?php echo htmlspecialchars($data['last_update']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动刷新页面
        setTimeout(function() {
            location.reload();
        }, 30000); // 30秒刷新一次
    </script>
</body>
</html>
