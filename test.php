<?php
require_once 'config.php';
require_once 'classes/UserManager.php';
require_once 'classes/ConfigManager.php';

echo "=== Sing-box Manager 测试 ===\n\n";

// 测试用户管理
echo "1. 测试用户管理...\n";
$userManager = new UserManager();

// 添加测试用户
echo "   添加测试用户...\n";
$result = $userManager->addUser('测试用户', date('Y-m-d', strtotime('+30 days')));
echo "   " . ($result ? "成功" : "失败") . "\n";

// 获取用户列表
echo "   获取用户列表...\n";
$users = $userManager->getUsers();
echo "   当前用户数量: " . count($users) . "\n";

// 测试配置管理
echo "\n2. 测试配置管理...\n";
$configManager = new ConfigManager();

try {
    $config = $configManager->getConfig();
    echo "   配置文件读取成功\n";
    echo "   当前用户数量: " . count($config['inbounds'][0]['users']) . "\n";
} catch (Exception $e) {
    echo "   错误: " . $e->getMessage() . "\n";
}

// 测试服务器配置
echo "\n3. 测试服务器配置...\n";
$servers = ConfigManager::getServers();
echo "   已配置服务器数量: " . count($servers) . "\n";

echo "\n=== 测试完成 ===\n";
?>
