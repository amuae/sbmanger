# SB Manager - Sing-box配置管理器

基于nginx+php8.2的Web应用，用于管理sing-box的Trojan用户配置和服务器部署。

## 功能特性

- 🔐 **用户认证系统** - 必须登录才能访问
- 👥 **用户管理** - 管理Trojan用户（用户名/密码/到期日期）
- 🖥️ **服务器管理** - 通过SSH部署配置到多个服务器
- 📊 **状态监控** - 实时显示服务器在线状态
- ⚡ **自动清理** - 自动删除过期用户
- 🎯 **一键部署** - 将配置一键分发到所有服务器

## 项目结构

```
sbmanger/
├── index.php          # 主页面
├── login.php          # 登录页面
├── logout.php         # 退出登录
├── nginx.conf         # nginx配置文件
├── start.bat          # Windows启动脚本
├── js/
│   └── app.js         # 前端JavaScript
├── api/
│   ├── users.php      # 用户管理API
│   ├── servers.php    # 服务器管理API
│   └── deploy.php     # 配置部署API
└── data/
    ├── users.json     # 用户数据
    ├── servers.json   # 服务器数据
    ├── config.json    # 生成的sing-box配置
    └── config-template.json # 配置模板
```

## 安装和配置

### 环境要求

#### 基础环境
- nginx
- PHP 8.2+
- 支持平台：Windows 11 / Debian 12 / Ubuntu 22.04+

#### Debian 12 完整部署要求

##### 1. 系统更新和基础软件安装
```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装nginx和PHP
sudo apt install -y nginx php8.2-fpm php8.2-cli php8.2-curl php8.2-json php8.2-mbstring

# 安装SSH工具
sudo apt install -y sshpass openssh-client

# 安装额外工具
sudo apt install -y git curl wget unzip
```

##### 2. 创建部署目录并设置权限
```bash
# 创建部署目录
sudo mkdir -p /opt/sbmanger

# 设置目录权限
sudo chown -R www-data:www-data /opt/sbmanger
sudo chmod -R 755 /opt/sbmanger
sudo chmod -R 775 /opt/sbmanger/data
```

##### 3. 部署应用文件
```bash
# 克隆或复制项目文件到部署目录
sudo cp -r * /opt/sbmanger/

# 确保文件权限正确
sudo chown -R www-data:www-data /opt/sbmanger
sudo chmod -R 755 /opt/sbmanger
sudo chmod -R 775 /opt/sbmanger/data
sudo chmod -R 644 /opt/sbmanger/api/*.php
sudo chmod -R 644 /opt/sbmanger/*.php
```

##### 4. 配置nginx站点
```bash
# 创建nginx配置文件
sudo nano /etc/nginx/sites-available/sbmanger
```

添加以下内容：
```nginx
server {
    listen 80;
    server_name your-domain.com;  # 替换为你的域名或IP
    
    root /opt/sbmanger;
    index index.php index.html;
    
    # 安全头设置
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(json)$ {
        deny all;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # 超时设置
        fastcgi_read_timeout 300;
    }
    
    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

启用站点并重启服务：
```bash
# 启用站点
sudo ln -s /etc/nginx/sites-available/sbmanger /etc/nginx/sites-enabled/

# 测试nginx配置
sudo nginx -t

# 重启服务
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm

# 设置开机启动
sudo systemctl enable nginx
sudo systemctl enable php8.2-fpm
```

##### 5. 配置PHP-FPM
```bash
# 编辑PHP-FPM配置
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# 确保以下设置
; user = www-data
; group = www-data
; listen.owner = www-data
; listen.group = www-data
; listen.mode = 0660
```

##### 6. 防火墙配置（如启用）
```bash
# 允许HTTP和HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 如果修改了SSH端口，也要允许
sudo ufw allow 22/tcp
```

##### 7. SSL证书配置（可选但推荐）
```bash
# 安装Certbot
sudo apt install -y certbot python3-certbot-nginx

# 获取SSL证书
sudo certbot --nginx -d your-domain.com
```

### Windows系统配置

#### 1. 环境要求
- nginx for Windows
- PHP 8.2+ (Windows版)
- Windows 11 64位（已测试）

#### 2. 配置nginx

将`nginx.conf`复制到nginx配置目录，或添加到现有配置中：

```nginx
server {
    listen 80;
    server_name localhost;
    root C:/Users/30651/Desktop/工作区/github/sbmanger;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### 3. 启动服务

##### 方法1：使用nginx+PHP-FPM
1. 启动nginx
2. 启动PHP-FPM（监听9000端口）
3. 访问 http://localhost

##### 方法2：使用PHP内置服务器（测试用）
```bash
php -S localhost:8000
```

## 首次登录

默认账号：
- 用户名：admin
- 密码：admin123

**重要：首次登录后请立即修改默认密码**

## 使用说明

### 用户配置页面
1. 添加Trojan用户：输入用户名、密码和到期日期
2. 系统自动过滤过期用户
3. 每次操作都会重新生成`config.json`

### 服务器配置页面
1. 添加服务器：填写服务器信息（IP、端口、用户名、密码/密钥）
2. 查看服务器在线状态
3. 点击"部署配置"将`config.json`发送到服务器指定位置

### SSH部署说明

#### Linux系统（Debian 12）
- 已预装sshpass，支持密码认证
- 支持密钥认证（推荐）
- 配置文件路径：`/root/sing-box/config.json`

#### Windows系统
- 需要安装PuTTY（包含plink.exe）
- 将PuTTY安装目录添加到系统PATH
- 支持密码和密钥认证

## 配置文件路径

部署到服务器的配置文件将放置在：
```
/root/sing-box/config.json
```

## 目录权限检查清单

### Debian 12权限设置
```bash
# 检查当前权限
ls -la /opt/sbmanger/

# 修复权限（如需要）
sudo chown -R www-data:www-data /opt/sbmanger
sudo chmod -R 755 /opt/sbmanger
sudo chmod -R 775 /opt/sbmanger/data
sudo chmod -R 644 /opt/sbmanger/api/*.php
sudo chmod -R 644 /opt/sbmanger/*.php
```

### 关键文件权限要求
- `/opt/sbmanger/data/` - 需要读写权限 (775)
- `/opt/sbmanger/api/` - 需要执行权限 (755)
- `/opt/sbmanger/*.php` - 需要读取权限 (644)

## 扩展要求

### 推荐安装的PHP扩展
```bash
# Debian 12安装额外PHP扩展
sudo apt install -y php8.2-curl php8.2-json php8.2-mbstring php8.2-zip php8.2-xml
```

### 性能优化建议
```bash
# 安装Redis（可选，用于缓存）
sudo apt install -y redis-server php8.2-redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

## 安全加固

### 1. 修改默认密码
首次登录后立即修改管理员密码

### 2. 配置防火墙
```bash
# 仅允许必要端口
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp  # SSH端口
sudo ufw enable
```

### 3. 定期更新
```bash
# 设置自动更新
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

## 故障排除

### 部署失败
- 检查服务器SSH连接
- 确认用户名密码正确
- 检查防火墙设置
- 查看PHP错误日志：`/var/log/nginx/error.log`

### 权限问题
```bash
# Debian 12
sudo chown -R www-data:www-data /opt/sbmanger
sudo chmod -R 755 /opt/sbmanger
sudo chmod -R 775 /opt/sbmanger/data

# 检查SELinux状态（如启用）
sudo getenforce
sudo setenforce 0  # 临时禁用
```

### 常见错误解决
```bash
# PHP-FPM连接问题
sudo systemctl restart php8.2-fpm

# nginx配置错误
sudo nginx -t
sudo systemctl restart nginx

# 检查端口占用
sudo netstat -tulnp | grep :80
```

## 日志文件位置

- nginx访问日志：`/var/log/nginx/access.log`
- nginx错误日志：`/var/log/nginx/error.log`
- PHP-FPM日志：`/var/log/php8.2-fpm.log`
- 系统日志：`/var/log/syslog`

## 技术支持

如有问题，请检查以下日志获取详细信息：
- 浏览器控制台
- `/var/log/nginx/error.log`
- `/var/log/php8.2-fpm.log`

## 更新和维护

### 应用更新
```bash
# 备份数据
sudo cp -r /opt/sbmanger/data /opt/sbmanger-backup-$(date +%Y%m%d)

# 更新应用文件
cd /opt/sbmanger
sudo git pull origin main  # 如果使用git部署

# 修复权限
sudo chown -R www-data:www-data /opt/sbmanger
sudo chmod -R 755 /opt/sbmanger
sudo chmod -R 775 /opt/sbmanger/data
```

### 系统维护
```bash
# 定期更新系统
sudo apt update && sudo apt upgrade -y

# 清理日志
sudo journalctl --vacuum-time=7d
sudo find /var/log -name "*.log.*" -delete
