<?php
// 配置
define('CONFIG_PATH', '/root/sing-box/config.json');
define('TOKEN', 'your-secret-token-here'); // 从环境变量或配置文件读取
define('LOG_FILE', '/var/log/sing-box-update.log');

// 验证token
if (!isset($_POST['token']) || $_POST['token'] !== TOKEN) {
    http_response_code(401);
    exit('Unauthorized');
}

// 验证配置数据
if (!isset($_POST['config'])) {
    http_response_code(400);
    exit('Bad Request');
}

$config = $_POST['config'];

// 验证JSON格式
$decoded = json_decode($config, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit('Invalid JSON');
}

// 创建备份
if (file_exists(CONFIG_PATH)) {
    $backupPath = CONFIG_PATH . '.backup.' . date('Y-m-d_H-i-s');
    if (!copy(CONFIG_PATH, $backupPath)) {
        logMessage("备份失败: " . CONFIG_PATH);
        http_response_code(500);
        exit('Backup failed');
    }
}

// 写入新配置
if (file_put_contents(CONFIG_PATH, $config) === false) {
    logMessage("写入配置失败: " . CONFIG_PATH);
    http_response_code(500);
    exit('Write failed');
}

// 重启sing-box服务
exec('systemctl restart sing-box 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    logMessage("重启sing-box失败: " . implode("\n", $output));
    
    // 恢复备份
    if (isset($backupPath) && file_exists($backupPath)) {
        copy($backupPath, CONFIG_PATH);
    }
    
    http_response_code(500);
    exit('Restart failed');
}

logMessage("配置更新成功");
echo 'OK';

function logMessage($message) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
