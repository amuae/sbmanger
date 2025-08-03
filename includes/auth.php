<?php
require_once __DIR__ . '/config.php';

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        }
    }
    
    $error = "用户名或密码错误";
}

// 处理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
