<?php
class UserManager {
    private $usersFile;
    
    public function __construct() {
        $this->usersFile = USERS_FILE;
    }
    
    public function getUsers() {
        if (!file_exists($this->usersFile)) {
            return [];
        }
        
        $users = json_decode(file_get_contents($this->usersFile), true) ?: [];
        
        // 按添加时间排序
        usort($users, function($a, $b) {
            return strtotime($b['added_date']) - strtotime($a['added_date']);
        });
        
        return $users;
    }
    
    public function addUser($name, $expiry_date) {
        $users = $this->getUsers();
        
        // 生成随机密码
        $password = $this->generatePassword();
        
        $user = [
            'name' => $name,
            'password' => $password,
            'expiry' => $expiry_date,
            'added_date' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        $users[] = $user;
        
        return file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT)) !== false;
    }
    
    public function deleteUser($password) {
        $users = $this->getUsers();
        
        foreach ($users as $key => $user) {
            if ($user['password'] === $password) {
                unset($users[$key]);
                $users = array_values($users);
                return file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT)) !== false;
            }
        }
        
        return false;
    }
    
    public function checkExpiredUsers() {
        $users = $this->getUsers();
        $updated = false;
        
        foreach ($users as &$user) {
            if (strtotime($user['expiry']) < time()) {
                $user['status'] = 'expired';
                $updated = true;
            }
        }
        
        if ($updated) {
            file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
        }
    }
    
    public function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    public function getActiveUsers() {
        $users = $this->getUsers();
        $activeUsers = [];
        
        foreach ($users as $user) {
            if (strtotime($user['expiry']) >= time()) {
                $activeUsers[] = $user;
            }
        }
        
        return $activeUsers;
    }
}
?>
