# SBManager Agent - Sing-box管理Agent

## 项目简介

这是一个专为Sing-box设计的管理Agent，可以编译为二进制文件在纯净的Debian 12系统上运行，无需任何额外依赖。Agent负责收集服务器状态信息并上传到主控面板，同时接收并应用Sing-box配置更新。

## 功能特性

- ✅ **系统监控** - 实时收集CPU、内存、磁盘、网络状态
- ✅ **配置管理** - 接收并应用Sing-box配置更新
- ✅ **安全认证** - 基于Token的身份验证
- ✅ **跨平台** - 支持Linux、Windows、macOS
- ✅ **无依赖** - 单一二进制文件，无需额外安装
- ✅ **系统服务** - 支持安装为系统服务
- ✅ **自动上报** - 定时向主控面板推送数据

## 快速开始

### 1. 下载Agent

#### Linux AMD64
```bash
curl -L https://github.com/your-repo/releases/latest/download/agent-linux-amd64 -o /usr/local/bin/agent
chmod +x /usr/local/bin/agent
```

#### Linux ARM64
```bash
curl -L https://github.com/your-repo/releases/latest/download/agent-linux-arm64 -o /usr/local/bin/agent
chmod +x /usr/local/bin/agent
```

### 2. 配置Agent
```bash
agent config
```

编辑生成的 `agent-config.json` 文件：
```json
{
  "master_ip": "your-master-server-ip",
  "master_port": 8081,
  "agent_port": 8080,
  "token": "your-secret-token-from-panel",
  "interval": 30
}
```

### 3. 运行Agent
```bash
# 前台运行
agent

# 后台运行
nohup agent > agent.log 2>&1 &

# 安装为系统服务
sudo agent install
```

## 系统服务安装

### Linux (systemd)
```bash
# 安装服务
sudo agent install

# 启动服务
sudo systemctl start sbmanager-agent

# 设置开机启动
sudo systemctl enable sbmanager-agent

# 查看状态
sudo systemctl status sbmanager-agent

# 查看日志
sudo journalctl -u sbmanager-agent -f
```

### 卸载服务
```bash
sudo agent uninstall
```

## 构建Agent

### 在Windows上构建
```powershell
# 安装Go 1.21+
# 克隆项目
git clone https://github.com/your-repo/sbmanger-agent.git
cd sbmanager-agent

# 构建所有平台
.\build.ps1 -Version 1.0.0

# 构建特定平台
$env:GOOS="linux"; $env:GOARCH="amd64"; go build -o agent-linux-amd64
```

### 在Linux上构建
```bash
# 安装Go 1.21+
# 克隆项目
git clone https://github.com/your-repo/sbmanger-agent.git
cd sbmanager-agent

# 构建所有平台
./build.sh 1.0.0

# 构建特定平台
GOOS=linux GOARCH=amd64 go build -o agent-linux-amd64
```

## 支持的构建平台

| 平台 | 架构 | 文件名 |
|------|------|--------|
| Linux | amd64 | agent-linux-amd64 |
| Linux | arm64 | agent-linux-arm64 |
| Windows | amd64 | agent-windows-amd64.exe |
| Windows | arm64 | agent-windows-arm64.exe |
| macOS | amd64 | agent-darwin-amd64 |
| macOS | arm64 | agent-darwin-arm64 |

## API接口

Agent提供以下HTTP接口：

### 健康检查
```
GET /health
```

### 获取系统信息
```
GET /info
Headers: X-Token: your-token
```

### 更新配置
```
POST /config
Headers: X-Token: your-token
Body: {"config": "sing-box配置JSON"}
```

## 数据格式

Agent上报的数据格式：
```json
{
  "hostname": "server-name",
  "ip_address": "192.168.1.100",
  "timestamp": "2024-01-01T12:00:00Z",
  "cpu": {
    "usage_percent": 25.5,
    "cores": 4
  },
  "memory": {
    "total": 8589934592,
    "used": 4294967296,
    "free": 4294967296,
    "used_percent": 50.0
  },
  "disk": {
    "total": 53687091200,
    "used": 26843545600,
    "free": 26843545600,
    "used_percent": 50.0
  },
  "network": {
    "bytes_sent": 1024000,
    "bytes_recv": 2048000
  }
}
```

## 故障排除

### 常见问题

1. **Agent无法连接主控**
   - 检查网络连接
   - 确认主控IP和端口配置正确
   - 检查防火墙设置

2. **权限错误**
   - 确保Agent有权限写入 `/root/sing-box/config.json`
   - 使用root用户运行Agent

3. **sing-box重启失败**
   - 检查sing-box服务状态: `systemctl status sing-box`
   - 检查配置文件语法: `sing-box check -c /root/sing-box/config.json`

4. **Token验证失败**
   - 确认Token与主控面板配置一致
   - 检查Token是否包含特殊字符

### 日志查看

Agent日志输出到标准输出，可以通过以下方式查看：
```bash
# 前台运行时直接查看
agent

# 后台运行时查看日志文件
tail -f agent.log

# 系统服务日志
journalctl -u sbmanager-agent -f
```

## 安全建议

1. 使用强密码保护主控面板
2. 定期更新Agent和主控面板
3. 限制Agent端口的网络访问
4. 使用HTTPS传输敏感数据
5. 定期更换Token

## 更新说明

### v1.0.0
- 初始版本发布
- 支持基本系统监控
- 支持Sing-box配置管理
- 支持系统服务安装

## 许可证
MIT License
