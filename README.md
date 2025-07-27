# SBManager - Sing-box管理面板

## 项目简介

SBManager是一个完整的Sing-box管理解决方案，包含主控面板和Agent系统。主控面板使用PHP 8.2 + Nginx构建，Agent使用Go语言开发，支持在纯净的Debian 12系统上无依赖运行。

## 系统架构

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   主控面板       │    │   Agent         │    │   被控服务器     │
│   PHP 8.2       │◄──►│   Go二进制       │◄──►│   Sing-box      │
│   Nginx         │    │   无依赖         │    │   系统服务       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 功能特性

### 主控面板
- ✅ **用户管理** - 添加/删除用户，设置到期时间
- ✅ **服务器管理** - 添加/删除被控服务器
- ✅ **实时监控** - 查看所有服务器状态
- ✅ **配置部署** - 一键部署配置到所有服务器
- ✅ **用户认证** - 登录验证系统
- ✅ **响应式设计** - 支持移动端访问

### Agent功能
- ✅ **系统监控** - CPU、内存、磁盘、网络状态
- ✅ **配置管理** - 接收并应用Sing-box配置
- ✅ **安全认证** - Token验证
- ✅ **系统服务** - 支持systemd服务
- ✅ **跨平台** - 支持Linux/Windows/macOS

## 快速部署

### 主控面板部署

#### 1. 环境要求
- PHP 8.2+
- Nginx
- curl扩展

#### 2. 安装步骤
```bash
# 克隆项目
git clone https://github.com/your-repo/sbmanger.git
cd sbmanger

# 设置权限
chmod -R 755 data logs api

# 配置Nginx
sudo cp nginx.conf.example /etc/nginx/sites-available/sbmanger
sudo ln -s /etc/nginx/sites-available/sbmanger /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

#### 3. 访问面板
- 默认用户名: `admin`
- 默认密码: `admin123`
- 访问地址: `http://your-domain.com`

### Agent部署

#### 1. 在被控服务器上安装Agent
```bash
# 下载Agent
curl -L https://github.com/your-repo/releases/latest/download/agent-linux-amd64 -o /usr/local/bin/agent
chmod +x /usr/local/bin/agent

# 配置Agent
agent config

# 编辑配置文件
nano agent-config.json
```

#### 2. 配置Agent
```json
{
  "master_ip": "your-master-server-ip",
  "master_port": 8081,
  "agent_port": 8080,
  "token": "your-secret-token",
  "interval": 30
}
```

#### 3. 启动Agent
```bash
# 安装为系统服务
sudo agent install

# 启动服务
sudo systemctl start sbmanager-agent
sudo systemctl enable sbmanager-agent
```

## 使用指南

### 1. 添加服务器
1. 登录主控面板
2. 进入"服务器管理"页面
3. 点击"添加服务器"
4. 填写服务器信息并获取Token
5. 在服务器上配置Agent

### 2. 添加用户
1. 进入"用户信息"页面
2. 填写用户备注和到期时间
3. 系统自动生成密码
4. 点击"部署配置"应用到所有服务器

### 3. 监控服务器
1. 进入"服务器监控"页面
2. 查看实时状态信息
3. 页面每30秒自动刷新

## 文件结构

```
sbmanger/
├── agent/                    # Agent源码
│   ├── main.go              # Agent主程序
│   ├── go.mod               # Go模块
│   ├── build.ps1            # Windows构建脚本
│   └── README.md            # Agent文档
├── api/                     # API接口
│   ├── report.php           # 接收Agent数据
│   └── config.php           # 配置部署接口
├── classes/                 # PHP类库
│   ├── UserManager.php      # 用户管理
│   └── ConfigManager.php    # 配置管理
├── data/                    # 数据文件
│   ├── users.json           # 用户列表
│   ├── servers.json         # 服务器列表
│   └── agent_data.json      # Agent数据
├── logs/                    # 日志文件
├── index.php               # 用户管理页面
├── servers.php             # 服务器管理页面
├── dashboard.php           # 监控页面
├── login.php               # 登录页面
├── logout.php              # 退出登录
├── config.php              # 系统配置
└── README.md               # 本文档
```

## API接口

### 主控面板API

#### 接收Agent数据
```
POST /api/report.php
Headers: X-Token: your-token
Body: JSON格式的系统信息
```

#### 部署配置
```
POST /api/config.php
Headers: X-Token: your-token
Body: {"config": "sing-box配置JSON"}
```

### Agent API

#### 健康检查
```
GET /health
```

#### 获取系统信息
```
GET /info
Headers: X-Token: your-token
```

#### 更新配置
```
POST /config
Headers: X-Token: your-token
Body: {"config": "sing-box配置JSON"}
```

## 配置示例

### Sing-box配置模板
```json
{
  "log": {
    "level": "info",
    "timestamp": true
  },
  "dns": {
    "servers": ["8.8.8.8", "1.1.1.1"]
  },
  "inbounds": [
    {
      "type": "trojan",
      "listen": "0.0.0.0",
      "listen_port": 443,
      "users": [
        {
          "password": "user1-password",
          "name": "用户1"
        }
      ]
    }
  ],
  "outbounds": [
    {
      "type": "direct",
      "tag": "direct"
    }
  ],
  "route": {
    "rules": [
      {
        "geosite": "cn",
        "outbound": "direct"
      }
    ]
  }
}
```

## 故障排除

### 主控面板问题

1. **无法访问面板**
   - 检查Nginx配置
   - 确认PHP 8.2已安装
   - 检查文件权限

2. **登录失败**
   - 确认用户名密码正确
   - 检查data/admin.json文件

3. **数据不更新**
   - 检查data目录权限
   - 查看logs/agent.log日志

### Agent问题

1. **连接失败**
   - 检查网络连接
   - 确认Token正确
   - 检查防火墙设置

2. **配置部署失败**
   - 确认Agent有root权限
   - 检查sing-box服务状态
   - 验证配置文件语法

## 安全建议

1. **修改默认密码**
   - 立即修改admin密码
   - 使用强密码策略

2. **网络配置**
   - 限制Agent端口访问
   - 使用HTTPS传输

3. **定期维护**
   - 定期更新系统
   - 监控日志文件
   - 备份配置文件

## 性能优化

### 主控面板
- 使用Nginx缓存静态资源
- 启用PHP OPcache
- 定期清理日志文件

### Agent
- 调整上报间隔
- 限制并发连接数
- 使用系统服务运行

## 更新日志

### v1.0.0
- 初始版本发布
- 支持用户管理
- 支持服务器监控
- 支持配置部署
- 支持系统服务

## 技术支持

- GitHub Issues: https://github.com/your-repo/sbmanger/issues
- 文档: https://github.com/your-repo/sbmanger/wiki

## 许可证
MIT License
