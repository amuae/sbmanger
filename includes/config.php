<?php
// 数据库配置
define('DB_PATH', __DIR__ . '/../data/sbmanger.db');

// 会话配置
session_start();

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 数据库连接
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}

// 检查是否登录
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 重定向到登录页
function redirectToLogin() {
    header('Location: login.php');
    exit;
}

// 生成随机密码
function generateRandomPassword($length = 24) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// 获取原始config.json内容
function getOriginalConfig() {
    $configPath = __DIR__ . '/../config.json';
    if (!file_exists($configPath)) {
        return null;
    }
    return json_decode(file_get_contents($configPath), true);
}

// 保存config.json
function saveConfig($config) {
    $configPath = __DIR__ . '/../config.json';
    return file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// 从数据库同步用户到config.json
function syncUsersToConfig() {
    $db = getDB();
    $result = $db->query("SELECT name, password FROM trojan_users WHERE expiry_date >= date('now')");
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = [
            'name' => $row['name'],
            'password' => $row['password']
        ];
    }
    
    $config = getOriginalConfig();
    if ($config && isset($config['inbounds'][0]['users'])) {
        $config['inbounds'][0]['users'] = $users;
        return saveConfig($config);
    }
    return false;
}
?>
