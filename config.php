<?php
// 数据库配置
define('DB_FILE', 'data/users.db');
define('CONFIG_FILE', 'config.json');
define('BACKUP_DIR', 'backups/');

// 认证配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // password

// 服务器配置
define('SERVERS_FILE', 'data/servers.json');

// 创建必要的目录
if (!is_dir('data')) mkdir('data', 0755, true);
if (!is_dir('backups')) mkdir('backups', 0755, true);
if (!is_dir('logs')) mkdir('logs', 0755, true);

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
