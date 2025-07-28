<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

define('KEYS_DIR', '../data/keys');
define('KEYS_FILE', '../data/keys.json');

// 确保目录存在
if (!is_dir(KEYS_DIR)) {
    mkdir(KEYS_DIR, 0700, true);
}

// 确保keys.json文件存在
if (!file_exists(KEYS_FILE)) {
    file_put_contents(KEYS_FILE, json_encode(['keys' => []], JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getKeys();
        break;
    case 'POST':
        if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) {
            uploadKeyFile();
        } else {
            addKeyText();
        }
        break;
    case 'DELETE':
        deleteKey();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不允许']);
}

function getKeys() {
    $keysData = json_decode(file_get_contents(KEYS_FILE), true);
    // 只返回密钥名称和备注，不返回内容
    $result = [];
    foreach ($keysData['keys'] as $key) {
        $result[] = [
            'id' => $key['id'],
            'name' => $key['name'],
            'remark' => $key['remark'],
            'file_path' => $key['file_path'],
            'created_at' => $key['created_at']
        ];
    }
    echo json_encode(['keys' => $result]);
}

function uploadKeyFile() {
    $name = $_POST['name'] ?? '';
    $remark = $_POST['remark'] ?? '';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => '密钥名称不能为空']);
        return;
    }
    
    $file = $_FILES['key_file'];
    $fileContent = file_get_contents($file['tmp_name']);
    
    // 验证是否为有效的私钥格式
    if (!preg_match('/-----BEGIN.*PRIVATE KEY-----.*-----END.*PRIVATE KEY-----/s', $fileContent)) {
        echo json_encode(['success' => false, 'message' => '无效的私钥格式']);
        return;
    }
    
    $keysData = json_decode(file_get_contents(KEYS_FILE), true);
    
    // 检查名称是否已存在
    foreach ($keysData['keys'] as $key) {
        if ($key['name'] === $name) {
            echo json_encode(['success' => false, 'message' => '密钥名称已存在']);
            return;
        }
    }
    
    // 生成唯一ID
    $id = uniqid();
    
    // 保存密钥文件
    $keyFile = KEYS_DIR . '/' . $id . '.key';
    file_put_contents($keyFile, $fileContent);
    chmod($keyFile, 0600);
    
    // 添加到keys.json
    $newKey = [
        'id' => $id,
        'name' => $name,
        'remark' => $remark,
        'file_path' => $keyFile,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $keysData['keys'][] = $newKey;
    file_put_contents(KEYS_FILE, json_encode($keysData, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'key_id' => $id]);
}

function addKeyText() {
    $name = $_POST['name'] ?? '';
    $remark = $_POST['remark'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($name) || empty($content)) {
        echo json_encode(['success' => false, 'message' => '密钥名称和内容不能为空']);
        return;
    }
    
    // 清理私钥内容，移除\r\n换行符，只保留\n
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // 验证是否为有效的私钥格式
    if (!preg_match('/-----BEGIN.*PRIVATE KEY-----.*-----END.*PRIVATE KEY-----/s', $content)) {
        echo json_encode(['success' => false, 'message' => '无效的私钥格式']);
        return;
    }
    
    $keysData = json_decode(file_get_contents(KEYS_FILE), true);
    
    // 检查名称是否已存在
    foreach ($keysData['keys'] as $key) {
        if ($key['name'] === $name) {
            echo json_encode(['success' => false, 'message' => '密钥名称已存在']);
            return;
        }
    }
    
    // 生成唯一ID
    $id = uniqid();
    
    // 保存密钥文件
    $keyFile = KEYS_DIR . '/' . $id . '.key';
    file_put_contents($keyFile, $content);
    chmod($keyFile, 0600);
    
    // 添加到keys.json
    $newKey = [
        'id' => $id,
        'name' => $name,
        'remark' => $remark,
        'file_path' => $keyFile,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $keysData['keys'][] = $newKey;
    file_put_contents(KEYS_FILE, json_encode($keysData, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'key_id' => $id]);
}

function deleteKey() {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => '密钥ID不能为空']);
        return;
    }
    
    $keysData = json_decode(file_get_contents(KEYS_FILE), true);
    
    // 查找并删除密钥
    $found = false;
    foreach ($keysData['keys'] as $index => $key) {
        if ($key['id'] === $id) {
            // 删除密钥文件
            if (file_exists($key['file_path'])) {
                unlink($key['file_path']);
            }
            
            // 从数组中移除
            array_splice($keysData['keys'], $index, 1);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo json_encode(['success' => false, 'message' => '密钥不存在']);
        return;
    }
    
    file_put_contents(KEYS_FILE, json_encode($keysData, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
}

// 获取密钥内容（内部使用，不对外暴露）
function getKeyContent($keyId) {
    $keysData = json_decode(file_get_contents(KEYS_FILE), true);
    foreach ($keysData['keys'] as $key) {
        if ($key['id'] === $keyId) {
            if (file_exists($key['file_path'])) {
                return file_get_contents($key['file_path']);
            }
            return false;
        }
    }
    return false;
}
?>
