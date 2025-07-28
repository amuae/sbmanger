<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// 默认配置文件路径
define('CONFIG_PATH', 'data/config.json');
define('USERS_FILE', 'data/users.json');
define('SERVERS_FILE', 'data/servers.json');

// 确保数据目录存在
if (!is_dir('data')) {
    mkdir('data', 0755, true);
}

// 创建默认文件
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode(['users' => []], JSON_PRETTY_PRINT));
}
if (!file_exists(SERVERS_FILE)) {
    file_put_contents(SERVERS_FILE, json_encode(['servers' => []], JSON_PRETTY_PRINT));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SB Manager - Sing-box配置管理器</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            padding-top: 60px;
        }
        .navbar {
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
            border-radius: 5px;
        }
        .server-card {
            transition: transform 0.2s;
        }
        .server-card:hover {
            transform: translateY(-2px);
        }
        .status-online {
            color: #28a745;
        }
        .status-offline {
            color: #dc3545;
        }
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            margin-bottom: 5px;
            padding: 2px 0;
        }
        .log-container {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .progress {
            height: 25px;
        }
        .progress-bar {
            font-size: 0.9em;
            line-height: 25px;
        }
        @media (max-width: 768px) {
            .navbar-nav {
                flex-direction: row;
                justify-content: space-around;
                width: 100%;
            }
            .nav-item {
                margin: 0 5px;
            }
            .nav-link {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar navbar-expand-md navbar-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">SB Manager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-tab="users">
                            <i class="bi bi-people"></i> 用户配置
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-tab="servers">
                            <i class="bi bi-server"></i> 服务器配置
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> 退出登录
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="container-fluid mt-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2" id="page-title">用户配置</h1>
        </div>

        <!-- 用户配置页面 -->
        <div id="users-tab" class="tab-content">
            <div class="d-flex justify-content-between mb-3">
                <h3>Trojan用户管理</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus"></i> 添加用户
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>密码</th>
                            <th>到期时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="users-table">
                        <!-- 用户数据将通过AJAX加载 -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 服务器配置页面 -->
        <div id="servers-tab" class="tab-content" style="display: none;">
            <div class="d-flex justify-content-between mb-3">
                <h3>服务器管理</h3>
                <div>
                    <button class="btn btn-success me-2" onclick="deployAllServers()">
                        <i class="bi bi-cloud-upload"></i> 一键全部分发
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                        <i class="bi bi-plus"></i> 添加服务器
                    </button>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">SSH密钥管理</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addKeyModal">
                                <i class="bi bi-plus"></i> 添加密钥
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row" id="keys-container">
                                <!-- 密钥卡片将通过AJAX加载 -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row" id="servers-container">
                <!-- 服务器卡片将通过AJAX加载 -->
            </div>
        </div>

        <!-- 添加用户模态框 -->
        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">添加用户</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addUserForm">
                            <div class="mb-3">
                                <label class="form-label">用户名</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">到期日期</label>
                                <input type="date" class="form-control" name="expiry_date" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" onclick="addUser()">添加</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加服务器模态框 -->
        <div class="modal fade" id="addServerModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">添加服务器</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addServerForm">
                            <div class="mb-3">
                                <label class="form-label">备注</label>
                                <input type="text" class="form-control" name="remark" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">IP地址</label>
                                <input type="text" class="form-control" name="ip" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">端口</label>
                                <input type="number" class="form-control" name="port" value="22" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">用户名</label>
                                <input type="text" class="form-control" name="username" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">认证方式</label>
                                <select class="form-select" name="auth_type" onchange="toggleAuthFields()">
                                    <option value="password">密码认证</option>
                                    <option value="key_id">选择已有密钥</option>
                                    <option value="key_content">直接输入密钥</option>
                                </select>
                            </div>
                            <div class="mb-3" id="password-field">
                                <label class="form-label">密码</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="mb-3" id="key-id-field" style="display: none;">
                                <label class="form-label">选择密钥</label>
                                <select class="form-select" name="key_id">
                                    <option value="">请选择密钥</option>
                                </select>
                            </div>
                            <div class="mb-3" id="key-content-field" style="display: none;">
                                <label class="form-label">SSH私钥内容</label>
                                <textarea class="form-control" name="key_content" rows="4" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" onclick="addServer()">添加</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加密钥模态框 -->
        <div class="modal fade" id="addKeyModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">添加SSH密钥</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs" id="keyTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-key" type="button" role="tab">文件上传</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-key" type="button" role="tab">文本输入</button>
                            </li>
                        </ul>
                        <div class="tab-content mt-3" id="keyTabContent">
                            <div class="tab-pane fade show active" id="upload-key" role="tabpanel">
                                <form id="addKeyFormUpload" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">密钥名称</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">备注</label>
                                        <input type="text" class="form-control" name="remark">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">选择私钥文件</label>
                                        <input type="file" class="form-control" name="key_file" accept=".key,.pem,.ppk" required>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="text-key" role="tabpanel">
                                <form id="addKeyFormText">
                                    <div class="mb-3">
                                        <label class="form-label">密钥名称</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">备注</label>
                                        <input type="text" class="form-control" name="remark">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">私钥内容</label>
                                        <textarea class="form-control" name="content" rows="6" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" onclick="addKey()">添加</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 一键全部分发模态框 -->
        <div class="modal fade" id="deployAllModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">一键全部分发配置</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                            </div>
                        </div>
                        <div class="log-container" style="height: 400px; overflow-y: auto; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                            <div id="deployAllLog"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/app.js"></script>
    </main>
</body>
</html>
