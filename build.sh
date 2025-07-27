#!/bin/bash

# Sing-box Manager 手动构建脚本
# 用于创建agent安装包

set -e

echo "=== Sing-box Manager 手动构建脚本 ==="

# 创建构建目录
BUILD_DIR="build"
PACKAGE_DIR="$BUILD_DIR/agent-package"

# 清理旧构建
rm -rf "$BUILD_DIR"
mkdir -p "$PACKAGE_DIR"

# 复制agent文件
echo "复制agent文件..."
cp agent/update-config.php "$PACKAGE_DIR/"
cp README.md "$PACKAGE_DIR/"

# 创建安装脚本
echo "创建安装脚本..."
cat > "$PACKAGE_DIR/install.sh" << 'EOF'
#!/bin/bash
set -e

echo "======================================"
echo "  Sing-box Config Agent 安装器"
echo "======================================"

# 检查root权限
if [[ $EUID -ne 0 ]]; then
   echo "❌ 此脚本必须以root身份运行" 
   exit 1
fi

# 设置变量
AGENT_DIR="/opt/sing-box-agent"
CONFIG_PATH="/root/sing-box/config.json"
LOG_FILE="/var/log/sing-box-update.log"

echo "📦 开始安装..."

# 创建目录
echo "📁 创建目录..."
mkdir -p "$AGENT_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

# 复制文件
echo "📋 复制文件..."
cp update-config.php "$AGENT_DIR/"
chmod +x "$AGENT_DIR/update-config.php"

# 创建systemd服务
echo "🔧 创建systemd服务..."
cat > /etc/systemd/system/sing-box-agent.service << 'SERVICE'
[Unit]
Description=Sing-box Config Agent
After=network.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/bin/true

[Install]
WantedBy=multi-user.target
SERVICE

# 创建nginx配置
echo "🌐 创建nginx配置..."
cat > /etc/nginx/sites-available/sing-box-agent << 'NGINX'
server {
    listen 80;
    server_name your-domain.com;
    
    location /update-config {
        if ($request_method != POST) {
            return 405;
        }
        
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /opt/sing-box-agent/update-config.php;
    }
}
NGINX

# 创建配置模板
echo "⚙️  创建配置模板..."
cat > "$AGENT_DIR/config.php" << 'CONFIG'
<?php
// 配置
define('CONFIG_PATH', '/root/sing-box/config.json');
define('TOKEN', 'your-secure-token-here'); // 修改为你的token
define('LOG_FILE', '/var/log/sing-box-update.log');

// 验证配置
if (!defined('CONFIG_PATH') || !defined('TOKEN')) {
    die("配置错误：请编辑config.php设置正确的参数");
}
?>
CONFIG

echo "✅ 安装完成！"
echo ""
echo "📖 下一步操作："
echo "1. 编辑配置文件：$AGENT_DIR/config.php"
echo "   - 设置你的token：TOKEN = 'your-secure-token-here'"
echo ""
echo "2. 配置nginx："
echo "   ln -s /etc/nginx/sites-available/sing-box-agent /etc/nginx/sites-enabled/"
echo "   nginx -t && systemctl restart nginx"
echo ""
echo "3. 测试配置："
echo "   curl -X POST -d 'token=YOUR_TOKEN&config={\"test\":\"config\"}' http://your-domain.com/update-config"
echo ""
echo "4. 查看日志："
echo "   tail -f $LOG_FILE"
EOF

chmod +x "$PACKAGE_DIR/install.sh"

# 创建版本信息
VERSION=$(git describe --tags --always 2>/dev/null || echo "v1.0.0")
echo "版本: $VERSION" > "$PACKAGE_DIR/VERSION"

# 创建压缩包
echo "📦 创建压缩包..."
cd "$BUILD_DIR"
tar -czf "sing-box-agent-$VERSION.tar.gz" agent-package/

# 移动压缩包到根目录
mv "sing-box-agent-$VERSION.tar.gz" ../

echo "✅ 构建完成！"
echo "📦 生成的文件：sing-box-agent-$VERSION.tar.gz"
echo ""
echo "📥 使用方法："
echo "1. 上传到服务器：scp sing-box-agent-$VERSION.tar.gz user@server:/tmp/"
echo "2. 解压安装：tar -xzf sing-box-agent-$VERSION.tar.gz && cd agent-package && sudo ./install.sh"
echo ""
echo "🎯 项目地址：https://github.com/amuae/sbmanger"
