# SBManger - Sing-Box 配置管理系统

## 项目简介
SBManger 是一个基于 Web 的 sing-box 配置管理系统，使用 nginx + PHP8.2 + SQLite3 构建。支持对 Trojan 用户进行可视化管理，并提供一键部署到多台服务器的功能。

## 功能特性

### 用户管理
- ✅ 卡片式用户管理界面
- ✅ 添加/编辑/删除 Trojan 用户
- ✅ 随机密码生成
- ✅ 到期日期管理
- ✅ 用户状态显示（有效/过期）
- ✅ 连接链接生成（URL?id=密码格式）

### 服务器管理
- ✅ 卡片式服务器管理界面
- ✅ 添加/编辑/删除服务器
- ✅ 服务器在线状态检测
- ✅ IP地址和端口隐藏显示
- ✅ 一键部署到所有服务器
- ✅ 单服务器部署

### 部署功能
- ✅ SSH私钥认证
- ✅ 配置文件自动同步
- ✅ 服务状态检查
- ✅ 部署日志查看

## 技术栈
- **Web服务器**: nginx
- **后端**: PHP 8.2
- **数据库**: SQLite3
- **前端**: Bootstrap 5 + jQuery
- **认证**: 简单登录系统

## 安装部署

### 1. 环境准备
```bash
# 安装必要软件
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-sqlite3 sqlite3

# 安装网络工具
sudo apt install netcat-openbsd
```

### 2. 项目部署
```bash
# 克隆项目
git clone [项目地址] /var/www/sbmanger

# 设置权限
sudo chown -R www-data:www-data /var/www/sbmanger
sudo chmod -R 755 /var/www/sbmanger

# 配置SSH密钥
sudo mkdir -p /var/www/.ssh
sudo cp ~/.ssh/id_rsa /var/www/.ssh/
sudo chown -R www-data:www-data /var/www/.ssh
sudo chmod 700 /var/www/.ssh
sudo chmod 600 /var/www/.ssh/id_rsa
```

### 3. 数据库初始化
```bash
# 创建数据库
sqlite3 data/sbmanger.db < data/database.sql

# 设置权限
sudo chown www-data:www-data data/sbmanger.db
sudo chmod 664 data/sbmanger.db
```

### 4. nginx配置
```bash
# 复制nginx配置
sudo cp config/nginx.conf /etc/nginx/conf.d/sbmanger.conf

# 测试并重载nginx
sudo nginx -t
sudo systemctl reload nginx
```

### 5. 修改配置文件
编辑以下文件中的URL配置：
- `public/users.php` 中的 `$base_url` 变量
- 修改为您的实际域名

## 使用说明

### 首次登录
- 访问: http://your-domain.com
- 用户名: admin
- 密码: admin123

### 用户管理
1. 点击"添加用户"创建新用户
2. 系统会自动生成24位随机密码
3. 设置到期日期
4. 每个用户会显示连接链接

### 服务器管理
1. 点击"添加服务器"添加对接服务器
2. 输入服务器备注、IP和端口
3. 系统会自动检测服务器在线状态
4. 支持一键部署到所有服务器

### 部署流程
1. 在用户管理中添加用户
2. 在服务器管理中添加服务器
3. 点击"一键部署全部"或单个服务器部署
4. 查看部署结果和日志

## 文件结构
```
sbmanger/
├── config.json                 # sing-box配置文件模板
├── README.md                   # 项目文档
├── setup.sh                    # 安装脚本
├── config/
│   └── nginx.conf             # nginx配置模板
├── data/
│   ├── database.sql           # 数据库初始化脚本
│   └── sbmanger.db            # SQLite数据库
├── includes/
│   ├── auth.php               # 认证功能
│   └── config.php             # 全局配置
└── public/
    ├── api.php                # API接口
    ├── footer.php             # 页脚
    ├── header.php             # 页头
    ├── index.php              # 首页
    ├── login.php              # 登录页面
    ├── servers.php            # 服务器管理
    └── users.php              # 用户管理
```

## 安全建议
1. 修改默认管理员密码
2. 使用HTTPS协议
3. 定期更新系统
4. 限制SSH密钥权限
5. 定期备份数据库

## 故障排除

### 部署失败
- 检查SSH密钥权限
- 确认服务器在线状态
- 查看系统日志

### 连接问题
- 确认防火墙设置
- 检查sing-box服务状态
- 验证配置文件格式

## 更新日志
- v1.0.0: 初始版本，支持用户和服务器管理
- v1.1.0: 添加卡片式界面和一键部署功能
- v1.2.0: 添加连接链接生成功能

## 技术支持
如有问题，请查看系统日志或联系技术支持。
