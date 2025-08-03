#!/bin/bash

# SBManger 安装脚本
# 适用于 Ubuntu/Debian 系统

set -e

echo "=== SBManger 安装脚本 ==="
echo "开始安装 SBManger 系统..."

# 检查root权限
if [[ $EUID -ne 0 ]]; then
   echo "错误: 请使用root权限运行此脚本"
   exit 1
fi

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 安装依赖
echo -e "${YELLOW}正在安装依赖...${NC}"
apt update
apt install -y nginx php8.2-fpm php8.2-sqlite3 sqlite3 netcat-openbsd

# 创建项目目录
PROJECT_DIR="/var/www/sbmanger"
echo -e "${YELLOW}创建项目目录: $PROJECT_DIR${NC}"
mkdir -p $PROJECT_DIR

# 复制项目文件
echo -e "${YELLOW}复制项目文件...${NC}"
cp -r * $PROJECT_DIR/
cd $PROJECT_DIR

# 设置权限
echo -e "${YELLOW}设置文件权限...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod 664 $PROJECT_DIR/data/sbmanger.db

# 创建web用户SSH目录
echo -e "${YELLOW}配置SSH密钥...${NC}"
mkdir -p /var/www/.ssh
if [ -f /root/.ssh/id_rsa ]; then
    cp /root/.ssh/id_rsa /var/www/.ssh/
    chown -R www-data:www-data /var/www/.ssh
    chmod 700 /var/www/.ssh
    chmod 600 /var/www/.ssh/id_rsa
    echo -e "${GREEN}SSH密钥已配置${NC}"
else
    echo -e "${YELLOW}警告: 未找到/root/.ssh/id_rsa，请手动配置SSH密钥${NC}"
fi

# 配置nginx
echo -e "${YELLOW}配置nginx...${NC}"
cp config/nginx.conf /etc/nginx/conf.d/sbmanger.conf

# 测试nginx配置
nginx -t
if [ $? -eq 0 ]; then
    systemctl reload nginx
    echo -e "${GREEN}nginx配置已应用${NC}"
else
    echo -e "${RED}nginx配置测试失败${NC}"
    exit 1
fi

# 启动服务
systemctl enable nginx php8.2-fpm
systemctl start nginx php8.2-fpm

# 设置数据库权限
echo -e "${YELLOW}设置数据库权限...${NC}"
sqlite3 data/sbmanger.db < data/database.sql
chown www-data:www-data data/sbmanger.db

# 显示完成信息
echo -e "${GREEN}=== 安装完成 ===${NC}"
echo "项目路径: $PROJECT_DIR"
echo "访问地址: http://localhost"
echo "默认账号: admin"
echo "默认密码: admin123"
echo ""
echo "请修改以下配置："
echo "1. 编辑 public/users.php 中的 \$base_url 变量为您的实际域名"
echo "2. 确保SSH密钥已正确配置"
echo ""
echo -e "${YELLOW}重要: 请立即修改默认管理员密码${NC}"
