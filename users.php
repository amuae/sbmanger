<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people-fill"></i> 用户列表</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="cleanup_expired">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="bi bi-trash"></i> 清理过期用户
                        </button>
                    </form>
                    <a href="download.php" class="btn btn-success btn-sm">
                        <i class="bi bi-download"></i> 下载配置文件
                    </a>
                </div>
                
                <?php if (empty($users)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">暂无用户</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>用户名</th>
                                    <th>密码</th>
                                    <th>到期日期</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): 
                                    $isExpired = isExpired($user['name']);
                                    $nameParts = explode('-', $user['name']);
                                    $username = implode('-', array_slice($nameParts, 0, -1));
                                    $expireDate = end($nameParts);
                                ?>
                                    <tr class="<?= $isExpired ? 'expired' : '' ?>">
                                        <td><?= htmlspecialchars($username) ?></td>
                                        <td>
                                            <code><?= htmlspecialchars($user['password']) ?></code>
                                            <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?= addslashes($user['password']) ?>')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </td>
                                        <td><?= $expireDate ?></td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-warning">已过期</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">正常</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_index" value="<?= $index ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除这个用户吗？')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 添加用户</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="expire_date" class="form-label">到期日期</label>
                        <input type="date" class="form-control" id="expire_date" name="expire_date" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus"></i> 添加用户
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('密码已复制到剪贴板！');
    }, function(err) {
        console.error('无法复制文本: ', err);
    });
}
</script>
