<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

define('USERS_FILE', '../data/users.json');
define('CONFIG_TEMPLATE', '../data/config-template.json');

// 确保文件存在
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode(['users' => []], JSON_PRETTY_PRINT));
}

// 创建默认的sing-box配置模板
if (!file_exists(CONFIG_TEMPLATE)) {
    $defaultConfig = [
        "log" => [
            "level" => "info",
            "timestamp" => true
        ],
        "dns" => [
            "servers" => [
                ["tag" => "google", "address" => "https://dns.google/dns-query"],
                ["tag" => "local", "address" => "223.5.5.5", "detour" => "direct"]
            ],
            "rules" => [
                ["outbound" => "any", "server" => "local"],
                ["rule_set" => "geosite-cn", "server" => "local"]
            ],
            "final" => "google",
            "strategy" => "ipv4_only"
        ],
        "inbounds" => [
            [
                "type" => "trojan",
                "tag" => "trojan-1",
                "listen" => "::",
                "listen_port" => 443,
                "users" => [],
                "tls" => [
                    "enabled" => true,
                    "server_name" => "example.com",
                    "certificate_path" => "/root/sing-box/cert.pem",
                    "key_path" => "/root/sing-box/key.pem"
                ]
            ]
        ],
        "outbounds" => [
            [
                "type" => "direct",
                "tag" => "direct"
            ],
            [
                "type" => "block",
                "tag" => "block"
            ]
        ],
        "route" => [
            "rules" => [
                ["protocol" => "dns", "outbound" => "dns-out"],
                ["rule_set" => ["geosite-cn", "geoip-cn"], "outbound" => "direct"]
            ],
            "rule_set" => [
                [
                    "type" => "remote",
                    "tag" => "geosite-cn",
                    "format" => "binary",
                    "url" => "https://raw.githubusercontent.com/SagerNet/sing-geosite/rule-set/geosite-cn.srs"
                ],
                [
                    "type" => "remote",
                    "tag" => "geoip-cn",
                    "format" => "binary",
                    "url" => "https://raw.githubusercontent.com/SagerNet/sing-geoip/rule-set/geoip-cn.srs"
                ]
            ]
        ]
    ];
    file_put_contents(CONFIG_TEMPLATE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUsers();
        break;
    case 'POST':
        addUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不允许']);
}

function getUsers() {
    $usersData = json_decode(file_get_contents(USERS_FILE), true);
    
    // 过滤过期用户
    $currentDate = date('Y-m-d');
    $validUsers = array_filter($usersData['users'], function($user) use ($currentDate) {
        return $user['expiry_date'] >= $currentDate;
    });
    
    // 重新索引数组
    $usersData['users'] = array_values($validUsers);
    
    // 保存过滤后的用户
    file_put_contents(USERS_FILE, json_encode($usersData, JSON_PRETTY_PRINT));
    
    // 更新sing-box配置
    updateSingBoxConfig($validUsers);
    
    echo json_encode(['users' => $validUsers]);
}

function addUser() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    
    if (empty($username) || empty($password) || empty($expiry_date)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        return;
    }
    
    $usersData = json_decode(file_get_contents(USERS_FILE), true);
    
    // 检查用户名是否已存在
    foreach ($usersData['users'] as $user) {
        if ($user['name'] === $username) {
            echo json_encode(['success' => false, 'message' => '用户名已存在']);
            return;
        }
    }
    
    // 添加新用户
    $usersData['users'][] = [
        'name' => $username,
        'password' => $password,
        'expiry_date' => $expiry_date
    ];
    
    file_put_contents(USERS_FILE, json_encode($usersData, JSON_PRETTY_PRINT));
    
    // 更新sing-box配置
    updateSingBoxConfig($usersData['users']);
    
    echo json_encode(['success' => true]);
}

function deleteUser() {
    $action = $_GET['action'] ?? '';
    if ($action !== 'delete') {
        echo json_encode(['success' => false, 'message' => '无效操作']);
        return;
    }
    
    $username = $_GET['username'] ?? '';
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => '用户名不能为空']);
        return;
    }
    
    $usersData = json_decode(file_get_contents(USERS_FILE), true);
    
    // 删除用户
    $usersData['users'] = array_filter($usersData['users'], function($user) use ($username) {
        return $user['name'] !== $username;
    });
    
    // 重新索引数组
    $usersData['users'] = array_values($usersData['users']);
    
    file_put_contents(USERS_FILE, json_encode($usersData, JSON_PRETTY_PRINT));
    
    // 更新sing-box配置
    updateSingBoxConfig($usersData['users']);
    
    echo json_encode(['success' => true]);
}

function updateSingBoxConfig($users) {
    $config = json_decode(file_get_contents(CONFIG_TEMPLATE), true);
    
    // 更新trojan用户
    $trojanUsers = [];
    foreach ($users as $user) {
        $trojanUsers[] = [
            'name' => $user['name'],
            'password' => $user['password']
        ];
    }
    
    // 找到trojan-1入站配置并更新用户
    foreach ($config['inbounds'] as &$inbound) {
        if ($inbound['tag'] === 'trojan-1') {
            $inbound['users'] = $trojanUsers;
            break;
        }
    }
    
    // 保存配置文件
    file_put_contents('../data/config.json', json_encode($config, JSON_PRETTY_PRINT));
}
?>
