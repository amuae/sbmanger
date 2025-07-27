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
$serverIP = '';
$serverPort = 0;

foreach ($servers as $server) {
    if (isset($server['token']) && $server['token'] === $token) {
        $validToken = true;
        $serverIP = $server['ip'];
        $serverPort = $server['port'];
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

// 验证配置数据
if (!isset($data['config'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing config field']);
    exit;
}

// 发送配置到指定服务器
$url = "http://{$serverIP}:{$serverPort}/config";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['config' => $data['config']]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Token: ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo json_encode(['status' => 'success', 'message' => '配置已部署']);
} else {
    echo json_encode(['status' => 'error', 'message' => '部署失败', 'http_code' => $httpCode]);
}
?>
