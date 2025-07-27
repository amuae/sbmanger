# GitHub Actions 权限配置指南

## 配置GitHub Actions权限

### 1. 仓库设置权限

1. 访问你的GitHub仓库：https://github.com/amuae/sbmanger
2. 点击 **Settings** 标签
3. 在左侧菜单选择 **Actions** → **General**
4. 在 **Workflow permissions** 部分选择：
   - ✅ **Read and write permissions**（读写权限）
   - ✅ **Allow GitHub Actions to create and approve pull requests**（允许创建PR）

### 2. 配置Token权限

#### 方法一：使用默认GITHUB_TOKEN
1. 在仓库设置中，确保 **Settings** → **Actions** → **General** 中：
   - **Workflow permissions** 设置为 **Read and write permissions**
   - 勾选 **Allow GitHub Actions to create and approve pull requests**

#### 方法二：创建Personal Access Token（推荐）
1. 访问：https://github.com/settings/tokens
2. 点击 **Generate new token (classic)**
3. 设置Token权限：
   - ✅ **repo**（完整仓库权限）
   - ✅ **workflow**（工作流权限）
4. 复制生成的Token
5. 在仓库设置中：
   - **Settings** → **Secrets and variables** → **Actions**
   - 点击 **New repository secret**
   - Name: `GH_TOKEN`
   - Value: 粘贴你的Token

### 3. 手动创建Release（备用方案）

如果GitHub Actions仍然无法自动创建release，可以手动创建：

1. 访问：https://github.com/amuae/sbmanger/releases
2. 点击 **Draft a new release**
3. 选择标签：v1.0.1
4. 填写标题：Release v1.0.1
5. 上传agent包：sing-box-agent.tar.gz（可从Actions下载）

### 4. 验证权限

创建新的标签测试：
```bash
git tag v1.0.2
git push origin v1.0.2
```

### 5. 常见问题解决

#### 403错误
- 确保仓库是**public**（公开）
- 检查**Settings** → **Actions** → **General**权限设置
- 确认Token有足够权限

#### 工作流不触发
- 检查`.github/workflows/build-agent.yml`文件格式
- 确认标签格式为`v*`（如v1.0.0, v1.1.0等）

## 手动构建Agent（备用方案）

如果GitHub Actions无法工作，可以手动构建：

```bash
# 本地构建
mkdir -p agent-package
cp agent/update-config.php agent-package/
cp README.md agent-package/

# 创建安装脚本
cat > agent-package/install.sh << 'EOF'
#!/bin/bash
# 安装脚本内容...
EOF

chmod +x agent-package/install.sh
tar -czf sing-box-agent.tar.gz -C agent-package .
```

## 下一步操作

1. **配置权限**：按照上述步骤配置GitHub Actions权限
2. **创建Release**：手动或自动创建第一个release
3. **下载Agent**：从Releases页面下载sing-box-agent.tar.gz
4. **部署使用**：按照README.md部署到服务器

项目已完全就绪，权限配置后即可正常使用GitHub Actions自动构建功能！
