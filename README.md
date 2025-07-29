# Sing-Box 配置管理系统

基于nginx+php8.2的sing-box配置管理系统，用于管理trojan-1用户配置和分发到多个服务器。

## 功能特性

- **用户认证系统**: 必须登录才能访问系统
- **用户配置管理**: 
  - 管理sing-box的trojan-1用户
  - 用户名格式: "用户名-到期日期"
  - 自动生成24位随机密码
  - 自动清理过期用户
  - 支持手动添加/删除用户
- **服务器配置管理**:
  - 通过SSH分发config.json到多个服务器
  - 服务器卡片显示SSH连接状态
  - 支持批量分发配置
  - 支持单个服务器配置分发
- **配置文件管理**:
  - 实时生成新的config.json
  - 支持配置文件下载

## 系统要求

- nginx
- PHP 8.2+
- sshpass (用于SSH连接)
- Debian 12 (推荐)

## 安装部署

### 1. 安装依赖
```bash
# Debian/Ubuntu
apt update
apt install nginx php8.2-fpm php8.2-cli sshpass

# 确保PHP-FPM运行
systemctl start php8.2-fpm
systemctl enable php8.2-fpm
```

### 2. 部署代码
```bash
# 创建项目目录
mkdir -p /var/www/singbox-manager
cd /var/www/singbox-manager

# 复制所有文件到该目录
# 设置权限
chown -R www-data:www-data /var/www/singbox-manager
chmod -R 755 /var/www/singbox-manager
```

### 3. 配置nginx
```bash
# 复制nginx.conf到sites-available
cp nginx.conf /etc/nginx/sites-available/singbox-manager

# 创建软链接
ln -s /etc/nginx/sites-available/singbox-manager /etc/nginx/sites-enabled/

# 测试配置并重载nginx
nginx -t
systemctl reload nginx
```

### 4. 访问系统
- 打开浏览器访问: `http://your-server-ip`
- 默认登录账号: admin / admin123
- **重要**: 首次登录后请修改默认密码

## 使用说明

### 用户配置页面
1. 添加用户: 输入用户名和到期日期
2. 系统自动生成24位随机密码
3. 过期用户会自动标记并可以手动清理
4. 支持一键下载配置文件

### 服务器配置页面
1. 添加服务器: 填写备注、IP、端口、用户名、密码
2. 测试SSH连接状态
3. 分发配置到单个服务器或全部服务器
4. 实时显示服务器在线状态

## 文件结构
```
/var/www/singbox-manager/
├── index.php          # 主页面
├── login.php          # 登录页面
├── logout.php         # 退出登录
├── users.php          # 用户配置页面
├── servers.php        # 服务器配置页面
├── download.php       # 配置文件下载
├── distribute.php     # 配置分发接口
├── config.json        # sing-box配置文件
├── servers.json       # 服务器列表
└── nginx.conf         # nginx配置模板
```

## 安全建议

1. **修改默认密码**: 首次登录后立即修改admin密码
2. **使用HTTPS**: 生产环境建议配置SSL证书
3. **限制访问**: 配置防火墙只允许特定IP访问
4. **定期更新**: 保持系统和软件更新

## 故障排除

### SSH连接失败
- 确保目标服务器已安装sshpass
- 检查防火墙设置
- 验证用户名密码正确性
- 确保目标服务器SSH服务正常运行

### 权限问题
- 确保PHP进程有读写权限
- 检查nginx用户权限
- 验证目标服务器目录权限

## 更新日志

- v1.0.0: 初始版本，包含用户管理和服务器分发功能
