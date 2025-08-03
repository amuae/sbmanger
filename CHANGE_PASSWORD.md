# 修改登录密码指南

## 方法一：使用命令行（推荐）

### 修改管理员密码
```bash
# 生成新密码的hash
php -r "echo password_hash('您的新密码', PASSWORD_DEFAULT);"

# 使用sqlite3更新密码（将下面的hash替换为上面生成的）
sqlite3 data/sbmanger.db "UPDATE users SET password = '生成的hash' WHERE username = 'admin'"
```

### 示例：将密码改为 "admin456"
```bash
# 生成hash
php -r "echo password_hash('admin456', PASSWORD_DEFAULT);"
# 输出: $2y$10$7B7/7S2rY/mZh2WP0yLQ9ejvCYex4W.a/jb1EttXkouCO/1figXqe

# 更新密码
sqlite3 data/sbmanger.db "UPDATE users SET password = '\$2y\$10\$7B7/7S2rY/mZh2WP0yLQ9ejvCYex4W.a/jb1EttXkouCO/1figXqe' WHERE username = 'admin'"
```

## 方法二：使用Web界面（未来版本）

### 当前状态
- 用户名: admin
- 新密码: newpassword123 （已更新）

## 验证密码修改
```bash
# 检查当前密码hash
sqlite3 data/sbmanger.db "SELECT username, password FROM users"
```

## 注意事项
1. 密码使用 bcrypt 加密存储
2. 建议使用强密码（8位以上，包含大小写字母、数字和特殊字符）
3. 修改后请立即测试登录
4. 定期更换密码以提高安全性

## 重置密码
如果忘记密码，可以重新运行上述命令生成新的密码hash。
