<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$configPath = 'config.json';
if (!file_exists($configPath)) {
    die('配置文件不存在');
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="config.json"');
header('Content-Length: ' . filesize($configPath));

readfile($configPath);
exit;
?>
