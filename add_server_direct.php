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

// 直接添加服务器
$server = [
    'id' => uniqid(),
    'name' => '测试服务器1',
    'ip' => '192.168.1.100',
    'port' => 22,
    'username' => 'root',
    'password' => 'testpass123'
];

$servers = getServers();
$servers[] = $server;

if (saveServers($servers)) {
    echo "✅ 服务器添加成功！\n";
    echo "服务器名称: " . $server['name'] . "\n";
    echo "IP地址: " . $server['ip'] . "\n";
    echo "当前总服务器数: " . count($servers) . "\n";
} else {
    echo "❌ 服务器添加失败！\n";
    echo "请检查文件权限: " . realpath(SERVERS_FILE) . "\n";
}

// 显示当前所有服务器
echo "\n📋 当前服务器列表:\n";
$allServers = getServers();
foreach ($allServers as $s) {
    echo "- {$s['name']} ({$s['ip']}:{$s['port']})\n";
}
?>
