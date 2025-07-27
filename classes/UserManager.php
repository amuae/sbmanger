<?php
class UserManager {
    private $dbFile;
    private $configManager;
    
    public function __construct() {
        $this->dbFile = DB_FILE;
        $this->configManager = new ConfigManager();
        $this->initDatabase();
    }
    
    private function initDatabase() {
        if (!file_exists($this->dbFile)) {
            $db = new SQLite3($this->dbFile);
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    password TEXT NOT NULL UNIQUE,
                    expiry TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $db->close();
        }
    }
    
    public function generatePassword($length = 32) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    public function addUser($name, $expiryDate) {
        $password = $this->generatePassword();
        
        // 添加到数据库
        $db = new SQLite3($this->dbFile);
        $stmt = $db->prepare("INSERT INTO users (name, password, expiry) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $name, SQLITE3_TEXT);
        $stmt->bindValue(2, $password, SQLITE3_TEXT);
        $stmt->bindValue(3, $expiryDate, SQLITE3_TEXT);
        
        $result = $stmt->execute();
        $db->close();
        
        if ($result) {
            // 同步到配置文件
            $this->syncToConfig();
            return true;
        }
        
        return false;
    }
    
    public function deleteUser($password) {
        // 从数据库删除
        $db = new SQLite3($this->dbFile);
        $stmt = $db->prepare("DELETE FROM users WHERE password = ?");
        $stmt->bindValue(1, $password, SQLITE3_TEXT);
        $result = $stmt->execute();
        $db->close();
        
        if ($result) {
            // 同步到配置文件
            $this->syncToConfig();
            return true;
        }
        
        return false;
    }
    
    public function getUsers() {
        $db = new SQLite3($this->dbFile);
        $result = $db->query("SELECT * FROM users ORDER BY expiry ASC");
        $users = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        $db->close();
        return $users;
    }
    
    public function checkExpiredUsers() {
        $db = new SQLite3($this->dbFile);
        $today = date('Y-m-d');
        
        // 查找过期用户
        $stmt = $db->prepare("SELECT * FROM users WHERE expiry < ?");
        $stmt->bindValue(1, $today, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        $expiredUsers = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $expiredUsers[] = $row;
        }
        
        // 删除过期用户
        if (!empty($expiredUsers)) {
            $stmt = $db->prepare("DELETE FROM users WHERE expiry < ?");
            $stmt->bindValue(1, $today, SQLITE3_TEXT);
            $stmt->execute();
            
            // 同步到配置文件
            $this->syncToConfig();
            
            // 记录日志
            $this->logExpiredUsers($expiredUsers);
        }
        
        $db->close();
        return $expiredUsers;
    }
    
    private function syncToConfig() {
        $users = $this->getUsers();
        $configUsers = [];
        
        foreach ($users as $user) {
            $configUsers[] = [
                'name' => $user['name'] . '-' . $user['expiry'],
                'password' => $user['password']
            ];
        }
        
        $this->configManager->updateUsersInConfig($configUsers);
    }
    
    private function logExpiredUsers($expiredUsers) {
        $logFile = 'logs/expired_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " - 自动删除过期用户:\n";
        
        foreach ($expiredUsers as $user) {
            $logEntry .= "  - {$user['name']} (到期: {$user['expiry']})\n";
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>
