<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'sbmanager');
define('DB_USER', 'root');
define('DB_PASS', '');

// 文件路径配置
define('USERS_FILE', 'data/users.json');
define('SERVERS_FILE', 'data/servers.json');
define('CONFIG_FILE', 'data/config.json');
define('AGENT_DATA_FILE', 'data/agent_data.json');

// 系统配置
define('SESSION_TIMEOUT', 3600); // 1小时
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // 生产环境请修改

// 创建必要的目录
if (!is_dir('data')) mkdir('data', 0755, true);
if (!is_dir('logs')) mkdir('logs', 0755, true);

// 初始化文件
$files = [USERS_FILE, SERVERS_FILE, CONFIG_FILE, AGENT_DATA_FILE];
foreach ($files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }
}

// 初始化管理员用户
if (!file_exists('data/admin.json')) {
    $admin = [
        'username' => ADMIN_USERNAME,
        'password' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents('data/admin.json', json_encode($admin, JSON_PRETTY_PRINT));
}
?>
