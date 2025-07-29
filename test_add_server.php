<?php
// 测试服务器添加功能
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
    $result = file_put_contents(SERVERS_FILE, json_encode($servers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($result === false) {
        error_log("Failed to save servers.json");
        return false;
    }
    return true;
}

// 测试数据
$testServer = [
    'id' => 'test-' . time(),
    'name' => '测试服务器',
    'ip' => '192.168.1.100',
    'port' => 22,
    'username' => 'root',
    'password' => 'test123'
];

$servers = getServers();
$servers[] = $testServer;

if (saveServers($servers)) {
    echo "测试服务器添加成功！\n";
    echo "当前服务器数量: " . count($servers) . "\n";
    echo "最后添加的服务器: " . json_encode(end($servers), JSON_PRETTY_PRINT);
} else {
    echo "测试服务器添加失败！\n";
    echo "请检查文件权限: " . realpath(SERVERS_FILE);
}
?>
