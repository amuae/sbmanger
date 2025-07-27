<?php
class ConfigManager {
    private $configFile;
    private $backupDir;
    
    public function __construct() {
        $this->configFile = CONFIG_FILE;
        $this->backupDir = BACKUP_DIR;
    }
    
    public function getConfig() {
        if (!file_exists($this->configFile)) {
            throw new Exception("配置文件不存在");
        }
        
        $config = json_decode(file_get_contents($this->configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("配置文件格式错误");
        }
        
        return $config;
    }
    
    public function saveConfig($config) {
        // 创建备份
        $this->createBackup();
        
        // 保存新配置
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->configFile, $json) === false) {
            throw new Exception("无法保存配置文件");
        }
        
        return true;
    }
    
    public function createBackup() {
        if (!file_exists($this->configFile)) {
            return false;
        }
        
        $backupFile = $this->backupDir . 'config_' . date('Y-m-d_H-i-s') . '.json';
        return copy($this->configFile, $backupFile);
    }
    
    public function getUsersFromConfig() {
        $config = $this->getConfig();
        return $config['inbounds'][0]['users'] ?? [];
    }
    
    public function updateUsersInConfig($users) {
        $config = $this->getConfig();
        $config['inbounds'][0]['users'] = $users;
        return $this->saveConfig($config);
    }
    
    public static function getServers() {
        if (!file_exists(SERVERS_FILE)) {
            return [];
        }
        
        $servers = json_decode(file_get_contents(SERVERS_FILE), true);
        return is_array($servers) ? $servers : [];
    }
    
    public function deployConfig() {
        $servers = self::getServers();
        if (empty($servers)) {
            return false;
        }
        
        $config = $this->getConfig();
        $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        $successCount = 0;
        foreach ($servers as $server) {
            if ($this->deployToServer($server, $configJson)) {
                $successCount++;
            }
        }
        
        // 记录部署日志
        $this->logDeployment($successCount, count($servers));
        
        return $successCount === count($servers);
    }
    
    private function deployToServer($server, $configJson) {
        $url = rtrim($server['url'], '/') . '/update-config.php';
        $token = $server['token'];
        
        $data = [
            'token' => $token,
            'config' => $configJson
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200 && $response === 'OK';
    }
    
    private function logDeployment($success, $total) {
        $logFile = 'logs/deployment_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " - 部署: {$success}/{$total} 成功\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>
