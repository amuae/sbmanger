<?php
class ConfigManager {
    private $serversFile;
    private $configFile;
    
    public function __construct() {
        $this->serversFile = SERVERS_FILE;
        $this->configFile = CONFIG_FILE;
    }
    
    public function getServers() {
        if (!file_exists($this->serversFile)) {
            return [];
        }
        
        return json_decode(file_get_contents($this->serversFile), true) ?: [];
    }
    
    public function getConfig() {
        if (!file_exists($this->configFile)) {
            return $this->getDefaultConfig();
        }
        
        return json_decode(file_get_contents($this->configFile), true) ?: $this->getDefaultConfig();
    }
    
    public function getDefaultConfig() {
        return [
            'log' => [
                'level' => 'info',
                'timestamp' => true
            ],
            'dns' => [
                'servers' => [
                    '8.8.8.8',
                    '1.1.1.1'
                ]
            ],
            'inbounds' => [
                [
                    'type' => 'trojan',
                    'listen' => '0.0.0.0',
                    'listen_port' => 443,
                    'users' => []
                ]
            ],
            'outbounds' => [
                [
                    'type' => 'direct',
                    'tag' => 'direct'
                ],
                [
                    'type' => 'block',
                    'tag' => 'block'
                ]
            ],
            'route' => [
                'rules' => [
                    [
                        'geosite' => 'cn',
                        'outbound' => 'direct'
                    ],
                    [
                        'geoip' => 'cn',
                        'outbound' => 'direct'
                    ]
                ]
            ]
        ];
    }
    
    public function generateConfig() {
        $userManager = new UserManager();
        $users = $userManager->getActiveUsers();
        
        $config = $this->getDefaultConfig();
        
        // 添加用户到配置
        foreach ($users as $user) {
            $config['inbounds'][0]['users'][] = [
                'password' => $user['password'],
                'name' => $user['name']
            ];
        }
        
        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    public function deployConfig() {
        $config = $this->generateConfig();
        $servers = $this->getServers();
        
        $successCount = 0;
        $totalCount = count($servers);
        
        foreach ($servers as $server) {
            $url = "http://{$server['ip']}:{$server['port']}/config";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['config' => $config]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Token: ' . $server['token']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $successCount++;
            }
        }
        
        return $successCount === $totalCount;
    }
    
    public function testServerConnection($server) {
        $url = "http://{$server['ip']}:{$server['port']}/health";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    public function getServerStatus($server) {
        $url = "http://{$server['ip']}:{$server['port']}/info";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Token: ' . $server['token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
}
?>
