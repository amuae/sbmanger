<?php
session_start();

// 销毁所有会话数据
$_SESSION = array();

// 销毁会话
session_destroy();

// 重定向到登录页面
header('Location: login.php');
exit;
?>
