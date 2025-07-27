<?php
// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 配置文件
require_once '../config.php';

// 数据文件
define('AGENT_DATA_FILE', 'data/agent_data.json');
define('LOG_FILE', 'logs/agent.log');

// 创建必要的目录
if (!is_dir('data')) mkdir('data', 0755, true);
if (!is_dir('logs')) mkdir('logs', 0755, true);

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 验证Token
$token = $_SERVER['HTTP_X_TOKEN'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

// 验证Token是否有效
$servers = [];
if (file_exists(SERVERS_FILE)) {
    $servers = json_decode(file_get_contents(SERVERS_FILE), true) ?: [];
}

$validToken = false;
$serverName = '';

foreach ($servers as $server) {
    if (isset($server['token']) && $server['token'] === $token) {
        $validToken = true;
        $serverName = $server['name'];
        break;
    }
}

if (!$validToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// 获取POST数据
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// 验证必要字段
$requiredFields = ['hostname', 'ip_address', 'timestamp', 'cpu', 'memory', 'disk', 'network'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// 处理数据
$agentData = [
    'hostname' => $data['hostname'],
    'server_name' => $serverName,
    'ip_address' => $data['ip_address'],
    'timestamp' => $data['timestamp'],
    'last_update' => date('Y-m-d H:i:s'),
    'cpu' => [
        'usage_percent' => $data['cpu']['usage_percent'] ?? 0,
        'cores' => $data['cpu']['cores'] ?? 1
    ],
    'memory' => [
        'total' => $data['memory']['total'] ?? 0,
        'used' => $data['memory']['used'] ?? 0,
        'free' => $data['memory']['free'] ?? 0,
        'used_percent' => $data['memory']['used_percent'] ?? 0
    ],
    'disk' => [
        'total' => $data['disk']['total'] ?? 0,
        'used' => $data['disk']['used'] ?? 0,
        'free' => $data['disk']['free'] ?? 0,
        'used_percent' => $data['disk']['used_percent'] ?? 0
    ],
    'network' => [
        'bytes_sent' => $data['network']['bytes_sent'] ?? 0,
        'bytes_recv' => $data['network']['bytes_recv'] ?? 0
    ]
];

// 保存数据
saveAgentData($data['hostname'], $agentData);

// 记录日志
logMessage(sprintf("收到来自 %s (%s) 的数据", $data['hostname'], $serverName));

// 返回成功响应
echo json_encode(['status' => 'success', 'message' => 'Data received']);

function saveAgentData($hostname, $data) {
    $allData = [];
    if (file_exists(AGENT_DATA_FILE)) {
        $allData = json_decode(file_get_contents(AGENT_DATA_FILE), true) ?: [];
    }
    
    $allData[$hostname] = $data;
    
    file_put_contents(AGENT_DATA_FILE, json_encode($allData, JSON_PRETTY_PRINT));
}

function logMessage($message) {
    $logEntry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
