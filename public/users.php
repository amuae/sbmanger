<?php
require_once __DIR__ . '/../includes/config.php';

$base_url = 'https://mydomain.com';

// 处理用户操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';

        if (empty($name) || empty($expiry_date)) {
            $error = "用户名和到期日期不能为空";
        } else {
            if (empty($password)) {
                $password = bin2hex(random_bytes(12));
            }
            $stmt = $db->prepare("INSERT INTO trojan_users (name, password, expiry_date) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $name, SQLITE3_TEXT);
            $stmt->bindValue(2, $password, SQLITE3_TEXT);
            $stmt->bindValue(3, $expiry_date, SQLITE3_TEXT);
            if ($stmt->execute()) {
                syncUsersToConfig();
                $success = "用户添加成功";
            } else {
                $error = "添加失败，用户名可能已存在";
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';

        if (empty($name) || empty($expiry_date)) {
            $error = "用户名和到期日期不能为空";
        } else {
            if (empty($password)) {
                $stmt = $db->prepare("UPDATE trojan_users SET name = ?, expiry_date = ? WHERE id = ?");
                $stmt->bindValue(1, $name, SQLITE3_TEXT);
                $stmt->bindValue(2, $expiry_date, SQLITE3_TEXT);
                $stmt->bindValue(3, $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare("UPDATE trojan_users SET name = ?, password = ?, expiry_date = ? WHERE id = ?");
                $stmt->bindValue(1, $name, SQLITE3_TEXT);
                $stmt->bindValue(2, $password, SQLITE3_TEXT);
                $stmt->bindValue(3, $expiry_date, SQLITE3_TEXT);
                $stmt->bindValue(4, $id, SQLITE3_INTEGER);
            }
            if ($stmt->execute()) {
                syncUsersToConfig();
                $success = "用户更新成功";
            } else {
                $error = "更新失败";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM trojan_users WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        if ($stmt->execute()) {
            syncUsersToConfig();
            $success = "用户删除成功";
        } else {
            $error = "删除失败";
        }
    }
}

// 获取所有用户
$db = getDB();
$result = $db->query("SELECT * FROM trojan_users ORDER BY created_at DESC");
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

include 'header.php';
?>

<style>
.user-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    background-color: #fff;
}

.user-card .info-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.user-card .name {
    font-weight: 600;
    font-size: 1rem;
    max-width: 60%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-card .expiry {
    font-size: 0.875rem;
    color: #6c757d;
}

.user-card .password {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    word-break: break-all;
    margin-bottom: 0.75rem;
}

.card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>用户管理</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-lg"></i> 添加用户
                </button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people" style="font-size: 3rem; color: #ccc;"></i>
                    <h4 class="text-muted mt-3">暂无用户</h4>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($users as $user): ?>
                        <?php
                        $is_expired = strtotime($user['expiry_date']) < time();
                        $connection_url = $base_url . '?id=' . urlencode($user['password']);
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                            <div class="user-card">
                                <div class="info-line">
                                    <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                                    <div class="expiry <?= $is_expired ? 'text-danger' : '' ?>">
                                        <?= date('Y-m-d', strtotime($user['expiry_date'])) ?>
                                    </div>
                                </div>
                                <div class="password"><?= htmlspecialchars($user['password']) ?></div>
                                <div class="card-actions">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openLinkModal('<?= addslashes($connection_url) ?>')">
                                        <i class="bi bi-link-45deg"></i> 链接
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="bi bi-pencil"></i> 修改
                                    </button>
                                    <form method="POST" style="display: contents;" onsubmit="return confirm('确定删除吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> 删除
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 添加用户模态框 -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加用户</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="text" class="form-control" name="password" placeholder="留空生成随机密码">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">到期日期</label>
                        <input type="date" class="form-control" name="expiry_date" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑用户</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="text" class="form-control" name="password" placeholder="留空保持原密码">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">到期日期</label>
                        <input type="date" class="form-control" name="expiry_date" id="editExpiryDate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 链接详情模态框 -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>连接链接</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold">完整链接</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="fullLink" readonly>
                    <button class="btn btn-outline-primary" type="button" onclick="copyLinkFromModal()">
                        <i class="bi bi-clipboard"></i> 复制
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLinkModal(link) {
    document.getElementById('fullLink').value = link;
    new bootstrap.Modal(document.getElementById('linkModal')).show();
}

function copyLinkFromModal() {
    const link = document.getElementById('fullLink');
    link.select();
    navigator.clipboard.writeText(link.value).then(() => {
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 alert alert-success alert-dismissible fade show';
        toast.style.zIndex = '9999';
        toast.innerHTML = `<i class="bi bi-check-circle-fill me-2"></i>链接已复制`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

function editUser(user) {
    document.getElementById('editId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editExpiryDate').value = user.expiry_date;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php include 'footer.php'; ?>
