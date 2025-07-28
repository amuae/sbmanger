# sbmanger SSH连接修复说明

## 问题描述
sbmanger的SSH连接全部失败，密码和私钥都不成功。

## 问题原因
1. **缺少必要的SSH工具**：Windows系统缺少 `plink` 和 `pscp` 工具
2. **路径问题**：PuTTY工具未正确配置到系统PATH
3. **认证方式**：标准的 `ssh` 命令需要交互式输入密码，不适合自动化脚本

## 修复方案

### 1. 安装PuTTY工具包
已自动安装PuTTY工具包，包含：
- `plink.exe` - SSH客户端（Windows版）
- `pscp.exe` - SCP文件传输工具（Windows版）

安装路径：`C:\Program Files\PuTTY\`

### 2. 代码更新
已更新所有SSH相关文件：
- `api/servers.php` - 服务器状态检查
- `api/deploy.php` - 配置部署功能
- `api/deploy-log.php` - 部署日志和进度

### 3. 改进特性
- ✅ 自动检测Windows/Linux系统
- ✅ 使用完整路径调用PuTTY工具
- ✅ 支持密码和SSH密钥认证
- ✅ 改进错误处理和日志输出
- ✅ 添加系统环境检测

## 使用方法

### 测试SSH连接
```bash
php test_final.php
```

### 正常操作流程
1. 访问 sbmanger Web界面
2. 添加服务器配置（IP、端口、用户名、密码/密钥）
3. 系统会自动检测SSH连接状态
4. 点击"部署配置"按钮进行配置部署

## 故障排除

### 如果仍然连接失败
1. **检查服务器信息**：确认IP、端口、用户名、密码正确
2. **检查服务器SSH服务**：确保服务器运行SSH服务
3. **检查防火墙**：确认防火墙允许SSH连接
4. **检查sing-box**：确保服务器已安装sing-box

### 手动测试
```bash
# 测试SSH连接
"C:\Program Files\PuTTY\plink.exe" -pw [密码] [用户名]@[IP] "echo test"

# 测试文件传输
"C:\Program Files\PuTTY\pscp.exe" -pw [密码] [本地文件] [用户名]@[IP]:[远程路径]
```

## 文件说明
- `test_final.php` - 完整的SSH连接测试脚本
- `api/servers.php` - 服务器管理API（已修复）
- `api/deploy.php` - 配置部署API（已修复）
- `api/deploy-log.php` - 部署日志API（已修复）

## 系统要求
- Windows 10/11 或 Linux
- PHP 7.4+
- PuTTY工具包（Windows）
- OpenSSH客户端（Linux）

修复完成！现在sbmanger应该可以正常进行SSH连接和配置部署了。
