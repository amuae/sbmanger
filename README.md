# Sing-box Manager

一个基于PHP的sing-box配置管理面板，支持用户管理、自动过期删除、远程配置部署等功能。

## 功能特性

- ✅ 用户管理（添加、删除用户）
- ✅ 自动生成32位随机密码
- ✅ 用户到期时间设置
- ✅ 自动删除过期用户
- ✅ 配置文件备份
- ✅ 远程配置部署到多台服务器
- ✅ Web界面管理
- ✅ 响应式设计
- ✅ 用户认证系统

## 安装

### 1. 克隆项目

```bash
git clone https://github.com/amuae/sbmanger.git
cd sbmanger
```

### 2. 配置Web服务器

#### Nginx配置
复制 `nginx.conf.example` 到 `/etc/nginx/sites-available/` 并修改为你的域名和证书路径：

```bash
sudo cp nginx.conf.example /etc/nginx/sites-available/sing-box-manager
sudo ln -s /etc/nginx/sites-available/sing-box-manager /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

#### PHP配置
确保已安装PHP 8.2和必要的扩展：

```bash
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-sqlite3 php8.2-curl
sudo systemctl restart php8.2-fpm
```

### 3. 设置权限

```bash
sudo chown -R www-data:www-data /var/www/sing-box-manager
sudo chmod -R 755 /var/www/sing-box-manager
sudo chmod -R 775 /var/www/sing-box-manager/data
sudo chmod -R 775 /var/www/sing-box-manager/backups
sudo chmod -R 775 /var/www/sing-box-manager/logs
```

### 4. 访问管理面板

打开浏览器访问你的域名，使用默认凭据登录：
- 用户名: `admin`
- 密码: `password`

## 使用说明

### 添加用户
1. 登录管理面板
2. 在"添加新用户"表单中输入备注名称和到期时间
3. 点击"添加用户"按钮
4. 系统会自动生成32位随机密码

### 管理服务器
1. 点击顶部导航的"服务器管理"
2. 添加需要部署配置的服务器信息
3. 每台服务器需要运行agent程序

### 部署配置
1. 在用户管理页面点击"部署配置"按钮
2. 配置会自动推送到所有已配置的服务器
3. 服务器会自动重启sing-box服务

## Agent部署

### 1. 下载Agent
从GitHub Releases下载最新版本的agent：

```bash
wget https://github.com/amuae/sbmanger/releases/latest/download/sing-box-agent.tar.gz
tar -xzf sing-box-agent.tar.gz
```

### 2. 安装Agent
```bash
sudo ./install.sh
```

### 3. 配置Agent
编辑 `/opt/sing-box-agent/update-config.php` 文件，设置你的token：

```php
define('TOKEN', 'your-secure-token-here');
```

### 4. 配置Nginx
按照安装脚本的提示配置nginx，确保PHP-FPM正常运行。

## 目录结构

```
sbmanger/
├── index.php              # 主管理页面
├── login.php              # 登录页面
├── logout.php             # 退出登录
├── servers.php            # 服务器管理页面
├── config.php             # 配置文件
├── classes/
│   ├── ConfigManager.php  # 配置管理类
│   └── UserManager.php    # 用户管理类
├── agent/
│   └── update-config.php  # 远程配置更新脚本
├── .github/
│   └── workflows/
│       └── build-agent.yml # GitHub Actions工作流
├── data/                  # 数据目录（自动创建）
├── backups/               # 配置文件备份目录（自动创建）
├── logs/                  # 日志目录（自动创建）
├── nginx.conf.example     # Nginx配置示例
└── README.md              # 本文档
```

## 安全建议

1. **修改默认密码**：首次登录后立即修改默认密码
2. **使用HTTPS**：配置SSL证书，强制HTTPS访问
3. **强Token**：为每台服务器设置复杂的token
4. **定期备份**：定期备份配置文件和数据库
5. **限制访问**：通过防火墙限制管理面板的访问IP

## 故障排除

### 常见问题

1. **权限错误**：检查文件和目录权限
2. **PHP扩展缺失**：确保安装了php-sqlite3和php-curl
3. **配置部署失败**：检查服务器URL和token是否正确
4. **sing-box重启失败**：检查配置文件格式是否正确

### 日志文件

- Web服务器日志：`/var/log/nginx/`
- Agent日志：`/var/log/sing-box-update.log`
- 应用日志：`logs/` 目录下

## 开发

### 本地开发

```bash
# 启动PHP内置服务器
php -S localhost:8000
```

### 构建Agent

创建新的tag会自动触发GitHub Actions构建：

```bash
git tag v1.0.0
git push origin v1.0.0
```

## 许可证

MIT License
