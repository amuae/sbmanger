// 标签切换
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link[data-tab]');
    const tabContents = {
        'users': document.getElementById('users-tab'),
        'servers': document.getElementById('servers-tab')
    };
    const pageTitle = document.getElementById('page-title');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // 更新导航状态
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // 切换内容
            const tabName = this.getAttribute('data-tab');
            Object.keys(tabContents).forEach(key => {
                tabContents[key].style.display = key === tabName ? 'block' : 'none';
            });
            
            // 更新标题
            pageTitle.textContent = tabName === 'users' ? '用户配置' : '服务器配置';
            
            // 加载对应数据
            if (tabName === 'users') {
                loadUsers();
            } else {
                loadServers();
            }
        });
    });

    // 初始加载用户数据
    loadUsers();
});

// 用户管理
function loadUsers() {
    fetch('api/users.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('users-table');
            tbody.innerHTML = '';
            
            data.users.forEach(user => {
                const isExpired = new Date(user.expiry_date) < new Date();
                const status = isExpired ? '已过期' : '有效';
                const statusClass = isExpired ? 'text-danger' : 'text-success';
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${user.name}</td>
                    <td>${user.password}</td>
                    <td>${user.expiry_date}</td>
                    <td class="${statusClass}">${status}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser('${user.name}')">
                            <i class="bi bi-trash"></i> 删除
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => console.error('Error loading users:', error));
}

function addUser() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    
    fetch('api/users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            loadUsers();
        } else {
            alert(data.message || '添加用户失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('添加用户失败');
    });
}

function deleteUser(username) {
    if (!confirm(`确定要删除用户 ${username} 吗？`)) return;
    
    fetch(`api/users.php?action=delete&username=${encodeURIComponent(username)}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUsers();
        } else {
            alert(data.message || '删除用户失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('删除用户失败');
    });
}

// 服务器管理
function loadServers() {
    fetch('api/servers.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('servers-container');
            container.innerHTML = '';
            
            data.servers.forEach((server, index) => {
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                col.innerHTML = `
                    <div class="card server-card">
                        <div class="card-body">
                            <h5 class="card-title">${server.remark}</h5>
                            <h6 class="card-subtitle mb-2 text-muted">${server.ip}:${server.port}</h6>
                            <p class="card-text">
                                <small>用户名: ${server.username}</small><br>
                                <span class="status-${server.status ? 'online' : 'offline'}">
                                    <i class="bi bi-circle-fill"></i> 
                                    ${server.status ? 'SSH登录成功' : 'SSH登录失败'}
                                </span>
                            </p>
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-sm btn-primary" onclick="deployConfig(${index})">
                                    <i class="bi bi-cloud-upload"></i> 部署配置
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteServer(${index})">
                                    <i class="bi bi-trash"></i> 删除
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(col);
            });
        })
    .catch(error => console.error('Error loading servers:', error));
}

function addServer() {
    const form = document.getElementById('addServerForm');
    const formData = new FormData(form);
    
    fetch('api/servers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addServerModal')).hide();
            loadServers();
        } else {
            alert(data.message || '添加服务器失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('添加服务器失败');
    });
}

function deleteServer(index) {
    if (!confirm('确定要删除这个服务器吗？')) return;
    
    fetch(`api/servers.php?action=delete&index=${index}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadServers();
        } else {
            alert(data.message || '删除服务器失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('删除服务器失败');
    });
}

function deployConfig(serverIndex) {
    if (!confirm('确定要将配置部署到该服务器吗？')) return;
    
    // 创建模态框
    const modal = createDeployModal(serverIndex);
    document.body.appendChild(modal);
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // 开始部署
    startDeploy(serverIndex, modal);
    
    // 清理模态框
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

function createDeployModal(serverIndex) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'deployModal';
    modal.setAttribute('tabindex', '-1');
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">部署配置到服务器</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                    </div>
                    <div class="log-container" style="height: 300px; overflow-y: auto; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                        <div id="deployLog"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    `;
    return modal;
}

function startDeploy(serverIndex, modal) {
    const logContainer = modal.querySelector('.log-container');
    const logDiv = document.getElementById('deployLog');
    const progressBar = modal.querySelector('.progress-bar');
    
    // 创建EventSource连接
    const eventSource = new EventSource(`api/deploy-log.php?server=${serverIndex}`);
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
        // 添加日志
        const logEntry = document.createElement('div');
        logEntry.innerHTML = `<small class="text-muted">[${data.timestamp}]</small> ${data.message}`;
        logDiv.appendChild(logEntry);
        
        // 滚动到底部
        logContainer.scrollTop = logContainer.scrollHeight;
        
        // 更新进度条
        if (data.progress !== undefined) {
            progressBar.style.width = data.progress + '%';
            progressBar.textContent = data.progress + '%';
        }
        
        // 处理完成状态
        if (data.type === 'complete' || data.type === 'error' || data.type === 'warning') {
            eventSource.close();
            
            if (data.type === 'complete') {
                progressBar.className = 'progress-bar bg-success';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(modal).hide();
                    loadServers(); // 刷新服务器列表
                }, 2000);
            } else if (data.type === 'error') {
                progressBar.className = 'progress-bar bg-danger';
            } else if (data.type === 'warning') {
                progressBar.className = 'progress-bar bg-warning';
            }
        }
    };
    
    eventSource.onerror = function() {
        eventSource.close();
        const logEntry = document.createElement('div');
        logEntry.innerHTML = '<small class="text-muted">[连接错误]</small> 部署连接中断';
        logDiv.appendChild(logEntry);
        progressBar.className = 'progress-bar bg-danger';
    };
}
