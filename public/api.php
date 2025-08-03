<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'sync') {
    if (syncUsersToConfig()) {
        echo json_encode(['success' => true, 'message' => '同步成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '同步失败']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的操作']);
}
?>
