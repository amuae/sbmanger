#!/bin/bash

# Sing-box Manager 部署脚本
# 适用于Ubuntu/Debian系统

set -e

echo "=== Sing-box Manager 部署脚本 ==="

# 检查root权限
if [[ $EUID -ne 0 ]]; then
   echo "此脚本必须以root身份运行" 
   exit 1
fi

# 安装依赖
echo "安装依赖..."
apt update
apt install -y nginx php8.2 php8.2-fpm php8.2-sqlite3 php8.2-curl

# 创建目录
echo "创建目录..."
mkdir -p /var/www/sing-box-manager
mkdir -p /var/log/sing-box-manager

# 复制文件
echo "复制文件..."
cp -r * /var/www/sing-box-manager/
cd /var/www/sing-box-manager

# 设置权限
echo "设置权限..."
chown -R www-data:www-data /var/www/sing-box-manager
chmod -R 755 /var/www/sing-box-manager
chmod -R 775 /var/www/sing-box-manager/data
chmod -R 775 /var/www/sing-box-manager/backups
chmod -R 775 /var/www/sing-box-manager/logs

# 配置Nginx
echo "配置Nginx..."
cp nginx.conf.example /etc/nginx/sites-available/sing-box-manager

# 提示用户修改配置
echo ""
echo "=== 配置完成 ==="
echo "1. 编辑Nginx配置: /etc/nginx/sites-available/sing-box-manager"
echo "2. 启用站点: ln -s /etc/nginx/sites-available/sing-box-manager /etc/nginx/sites-enabled/"
echo "3. 测试配置: nginx -t"
echo "4. 重启服务: systemctl restart nginx php8.2-fpm"
echo ""
echo "5. 访问: http://your-domain.com"
echo "6. 默认登录: admin / password"
echo ""
echo "=== 安全建议 ==="
echo "1. 立即修改默认密码"
echo "2. 配置SSL证书"
echo "3. 设置防火墙规则"
echo "4. 定期备份数据"
