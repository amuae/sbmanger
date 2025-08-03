<?php
require_once __DIR__ . '/../includes/config.php';

function isTcpingAvailable() {
    return shell_exec('which tcping 2>/dev/null') !== null || shell_exec('which nc 2>/dev/null') !== null;
}
function checkServerOnline($ip, $port) {
    $timeout = 2;
    if (shell_exec('which tcping 2>/dev/null')) {
        $cmd = "tcping -t {$timeout} {$ip} {$port} 2>/dev/null | grep -c 'open'";
        return trim(shell_exec($cmd)) > 0;
    }
    if (shell_exec('which nc 2>/dev/null')) {
        $cmd = "timeout {$timeout} nc -z {$ip} {$port} 2>/dev/null && echo online || echo offline";
        return trim(shell_exec($cmd)) === 'online';
    }
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) { fclose($fp); return true; }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $ip   = trim($_POST['ip']   ?? '');
        $port = (int)($_POST['port'] ?? 22);
        if ($name === '' || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $error = "服务器名称和IP地址不能为空且IP格式正确";
        } else {
            $stmt = $db->prepare("INSERT INTO servers (name, ip, port) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $name, SQLITE3_TEXT);
            $stmt->bindValue(2, $ip, SQLITE3_TEXT);
            $stmt->bindValue(3, $port, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "服务器添加成功";
            } else {
                $error = "添加失败";
            }
        }
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['id']   ?? 0);
        $name = trim($_POST['name'] ?? '');
        $ip   = trim($_POST['ip']   ?? '');
        $port = (int)($_POST['port'] ?? 22);
        if ($name === '' || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $error = "服务器名称和IP地址不能为空且IP格式正确";
        } else {
            $stmt = $db->prepare("UPDATE servers SET name = ?, ip = ?, port = ? WHERE id = ?");
            $stmt->bindValue(1, $name, SQLITE3_TEXT);
            $stmt->bindValue(2, $ip, SQLITE3_TEXT);
            $stmt->bindValue(3, $port, SQLITE3_INTEGER);
            $stmt->bindValue(4, $id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "服务器更新成功";
            } else {
                $error = "更新失败";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM servers WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        if ($stmt->execute()) {
            $success = "服务器删除成功";
        } else {
            $error = "删除失败";
        }
    } elseif ($action === 'deploy_all') {
        $result = $db->query("SELECT * FROM servers");
        $servers = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['online'] = checkServerOnline($row['ip'], $row['port']);
            $servers[] = $row;
        }

        $successCount = 0;
        $configPath   = __DIR__ . '/../config.json';
        $sshKey       = '/var/www/.ssh/id_rsa';
        $sshOptions   = '-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR';

        if (!file_exists($sshKey)) {
            $error = "SSH 密钥不存在，请确保 /var/www/.ssh/id_rsa 已生成并授权";
        } else {
            chmod($sshKey, 0600);
            foreach ($servers as $sv) {
                if (!$sv['online']) continue;
                $remotePath = '/root/sing-box/config.json';
                $scpCmd = sprintf(
                    'scp %s -i %s -P %d %s root@%s:%s 2>&1',
                    $sshOptions, escapeshellarg($sshKey), $sv['port'],
                    escapeshellarg($configPath), escapeshellarg($sv['ip']),
                    escapeshellarg($remotePath)
                );
                $output = shell_exec($scpCmd);
                $scpOk  = ($output === null || trim($output) === '');
                if ($scpOk) {
                    $restartCmd = sprintf(
                        'ssh %s -i %s -p %d root@%s "systemctl restart sing-box && systemctl is-active sing-box" 2>&1',
                        $sshOptions, escapeshellarg($sshKey), $sv['port'], escapeshellarg($sv['ip'])
                    );
                    $status = trim(shell_exec($restartCmd));
                    if ($status === 'active') $successCount++;
                }
            }
            $success = "已尝试部署到所有在线服务器，成功 {$successCount} 台";
        }
    }
}

$db = getDB();
$result = $db->query("SELECT * FROM servers ORDER BY created_at DESC");
$servers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $row['online'] = checkServerOnline($row['ip'], $row['port']);
    $servers[] = $row;
}

include 'header.php';
?>

<style>
.server-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: #fff;
    transition: all 0.3s ease;
}
.server-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.server-card .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.server-card .name {
    font-weight: 600;
    font-size: 1rem;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.server-card .status {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 50px;
}
.server-card .field-row {
    display: flex;
    justify-content: space-between;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    color: #212529;
    margin-bottom: 0.75rem;
}
.server-card .label {
    font-size: 0.875rem;
    color: #6c757d;
}
.server-card .value {
    font-weight: 500;
}
.card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}
.card-actions .btn {
    padding: 0.4rem 0.2rem;
    font-size: 0.8rem;
    white-space: nowrap;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>服务器管理</h2>
                <div>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要一键部署到所有在线服务器吗？')">
                        <input type="hidden" name="action" value="deploy_all">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="bi bi-lightning"></i> 全部部署
                        </button>
                    </form>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                        <i class="bi bi-plus-lg"></i> 添加
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($servers)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-server" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="text-muted mt-3">暂无服务器</h4>
                    <p class="text-muted">点击“添加”开始管理</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($servers as $server): ?>
                        <?php
                        $isOnline = $server['online'];
                        $ipParts = explode('.', $server['ip']);
                        $ipDisplay = $ipParts[0] . '.' . $ipParts[1] . '.xxx.xxx';
                        $portStr = (string)$server['port'];
                        $portDisplay = strlen($portStr) <= 3 ? '**' : substr($portStr, 0, 2) . '**';
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <div class="server-card">
                                <div class="header">
                                    <div class="name"><?= htmlspecialchars($server['name']) ?></div>
                                    <span class="badge <?= $isOnline ? 'bg-success' : 'bg-danger' ?> status">
                                        <?= $isOnline ? '在线' : '离线' ?>
                                    </span>
                                </div>

                                <div class="field-row">
                                    <div class="info-item">
                                        <span class="label">IP地址</span>
                                        <span class="value"><?= $ipDisplay ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">端口</span>
                                        <span class="value"><?= $portDisplay ?></span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('确定部署到该服务器吗？')">
                                        <input type="hidden" name="action" value="deploy">
                                        <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                        <button type="submit" class="btn btn-outline-primary" <?= $isOnline ? '' : 'disabled' ?>>
                                            <i class="bi bi-cloud-upload"></i> 部署
                                        </button>
                                    </form>
                                    <button class="btn btn-outline-warning" onclick="editServer(<?= htmlspecialchars(json_encode($server)) ?>)">
                                        <i class="bi bi-pencil"></i> 修改
                                    </button>
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('确定删除吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i> 删除
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 添加服务器模态框 -->
<div class="modal fade" id="addServerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加服务器</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP地址</label>
                        <input type="text" class="form-control" name="ip" required pattern="\d+\.\d+\.\d+\.\d+">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">端口</label>
                        <input type="number" class="form-control" name="port" value="22" min="1" max="65535">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑服务器模态框 -->
<div class="modal fade" id="editServerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑服务器</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editServerId">
                    <div class="mb-3">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-control" name="name" id="editServerName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP地址</label>
                        <input type="text" class="form-control" name="ip" id="editServerIp" required pattern="\d+\.\d+\.\d+\.\d+">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">端口</label>
                        <input type="number" class="form-control" name="port" id="editServerPort" min="1" max="65535">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editServer(server) {
    document.getElementById('editServerId').value = server.id;
    document.getElementById('editServerName').value = server.name;
    document.getElementById('editServerIp').value = server.ip;
    document.getElementById('editServerPort').value = server.port;
    new bootstrap.Modal(document.getElementById('editServerModal')).show();
}
</script>

<?php include 'footer.php'; ?>
