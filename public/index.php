<?php
require_once __DIR__ . '/../includes/config.php';
include 'header.php';
?>
        
        <div class="row">
            <div class="col-12">
                <h2>欢迎使用 SBManger</h2>
                <p class="text-muted">Sing-box配置管理系统</p>
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-3">用户管理</h5>
                                <p class="card-text">管理Trojan用户，包括添加、修改、删除用户</p>
                                <a href="users.php" class="btn btn-primary">进入管理</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-server text-success" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-3">服务器管理</h5>
                                <p class="card-text">管理对接服务器，推送配置并重启服务</p>
                                <a href="servers.php" class="btn btn-success">进入管理</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-gear text-warning" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-3">配置同步</h5>
                                <p class="card-text">将用户配置同步到config.json文件</p>
                                <button class="btn btn-warning" onclick="syncConfig()">立即同步</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function syncConfig() {
    if (confirm('确定要同步用户配置到config.json吗？')) {
        fetch('api.php?action=sync', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('同步成功！');
            } else {
                alert('同步失败：' + data.message);
            }
        })
        .catch(error => {
            alert('请求失败：' + error.message);
        });
    }
}
</script>
</body>
</html>
