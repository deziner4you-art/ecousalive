<?php

/*
=====================================================
USER MODEL
Authentication + user management queries
=====================================================
*/

class User {

    private static $userPermissionsReady = false;
    private static $loginLogsReady = false;
    private static $appConfigReady = false;

    private static function ensureUserPermissionsTable(): void {
        if(self::$userPermissionsReady) return;

        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS eco_user_permissions (
                    user_id  INT NOT NULL PRIMARY KEY,
                    settings LONGTEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch(Exception $e){}

        self::$userPermissionsReady = true;
    }

    private static function ensureLoginLogsTable(): void {
        if(self::$loginLogsReady) return;

        db()->exec("
            CREATE TABLE IF NOT EXISTS eco_login_logs (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                username   VARCHAR(100) NOT NULL,
                role       VARCHAR(30) NOT NULL,
                ip_address VARCHAR(50) DEFAULT NULL,
                country    VARCHAR(100) DEFAULT NULL,
                region     VARCHAR(100) DEFAULT NULL,
                city       VARCHAR(100) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $columns = [
            "ALTER TABLE eco_login_logs ADD COLUMN user_id INT NOT NULL DEFAULT 0",
            "ALTER TABLE eco_login_logs ADD COLUMN username VARCHAR(100) NOT NULL DEFAULT ''",
            "ALTER TABLE eco_login_logs ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT ''",
            "ALTER TABLE eco_login_logs ADD COLUMN ip_address VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE eco_login_logs ADD COLUMN country VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE eco_login_logs ADD COLUMN region VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE eco_login_logs ADD COLUMN city VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE eco_login_logs ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE eco_login_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        ];
        foreach($columns as $sql){
            try{ db()->exec($sql); }catch(Exception $e){}
        }

        self::$loginLogsReady = true;
    }

    private static function ensureAppConfigTable(): void {
        if(self::$appConfigReady) return;

        db()->exec("
            CREATE TABLE IF NOT EXISTS eco_app_config (
                config_key   VARCHAR(60) NOT NULL PRIMARY KEY,
                config_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        db()->exec("INSERT IGNORE INTO eco_app_config (config_key,config_value) VALUES ('logout_token','init')");

        self::$appConfigReady = true;
    }

    public static function findByUsername(string $username): ?array {
        $stmt = db()->prepare("SELECT * FROM eco_tool_users WHERE username=?");
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function getAll(): array {
        return db()->query("SELECT id, username, role FROM eco_tool_users ORDER BY id DESC")->fetchAll();
    }

    public static function getWorkers(): array {
        return db()->query("SELECT id, username FROM eco_tool_users WHERE role IN ('worker', 'ai_work') ORDER BY username ASC")->fetchAll();
    }

    public static function getPayrollWorkers(): array {
        return db()->query("SELECT id, username, role FROM eco_tool_users WHERE role IN ('worker','qa','ai_work','seo_manager') ORDER BY username ASC")->fetchAll();
    }

    public static function getClients(): array {
        return db()->query("SELECT id, username FROM eco_tool_users WHERE role='eco_client' ORDER BY username ASC")->fetchAll();
    }

    public static function create(string $username, string $password, string $role): void {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare("INSERT INTO eco_tool_users (username,password,role) VALUES (?,?,?)")->execute([$username,$hash,$role]);
        if($role !== 'administrator'){
            try{
                db()->prepare("INSERT IGNORE INTO eco_permissions (role,settings) VALUES(?,?)")
                   ->execute([$role, json_encode(['filters'=>['','']])]);
            }catch(Exception $e){}
        }
    }

    public static function update(int $id, string $username, string $role, string $password = ''): void {
        if($password){
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare("UPDATE eco_tool_users SET username=?, role=?, password=? WHERE id=?")->execute([$username,$role,$hash,$id]);
        } else {
            db()->prepare("UPDATE eco_tool_users SET username=?, role=? WHERE id=?")->execute([$username,$role,$id]);
        }
    }

    public static function delete(int $id): void {
        db()->prepare("DELETE FROM eco_tool_users WHERE id=?")->execute([$id]);
        try{
            self::ensureUserPermissionsTable();
            db()->prepare("DELETE FROM eco_user_permissions WHERE user_id=?")->execute([$id]);
            ModulePermission::deleteForUser($id);
        }catch(Exception $e){}
    }

    public static function changePassword(int $id, string $current, string $new1, string $new2): array {
        if(strlen($new1) < 6) return ['ok'=>false,'message'=>'Naya password kam az kam 6 characters ka hona chahiye'];
        if($new1 !== $new2)   return ['ok'=>false,'message'=>'Naya password aur confirm password match nahi kar rahe'];

        $row = db()->prepare("SELECT password FROM eco_tool_users WHERE id=?");
        $row->execute([$id]);
        $dbRow = $row->fetch();

        if(!$dbRow || !password_verify($current, $dbRow['password'])){
            return ['ok'=>false,'message'=>'Purana password galat hai'];
        }

        $hash = password_hash($new1, PASSWORD_DEFAULT);
        db()->prepare("UPDATE eco_tool_users SET password=? WHERE id=?")->execute([$hash, $id]);
        return ['ok'=>true,'message'=>'Password kamyabi se badal diya gaya!'];
    }

    public static function getLoginToken(): string {
        self::ensureAppConfigTable();
        return (string) db()->query("SELECT config_value FROM eco_app_config WHERE config_key='logout_token'")->fetchColumn();
    }

    public static function forceLogoutAll(&$currentSessionToken = null): string {
        self::ensureAppConfigTable();
        $new_token = bin2hex(crypto_random_bytes(16));
        db()->prepare("UPDATE eco_app_config SET config_value=? WHERE config_key='logout_token'")->execute([$new_token]);
        if($currentSessionToken !== null) $currentSessionToken = $new_token;
        return $new_token;
    }

    public static function getLoginLogs(int $limit = 200): array {
        self::ensureLoginLogsTable();
        $limit = max(1, min(1000, (int)$limit));
        return db()->query("
            SELECT id, username, role, ip_address, country, region, city, user_agent, created_at
            FROM eco_login_logs
            ORDER BY id DESC
            LIMIT {$limit}
        ")->fetchAll();
    }

    public static function analyticsStatus(): array {
        self::ensureLoginLogsTable();
        $count = (int) db()->query("SELECT COUNT(*) FROM eco_login_logs")->fetchColumn();
        $latest = db()->query("
            SELECT id, username, role, ip_address, country, city, created_at
            FROM eco_login_logs
            ORDER BY id DESC
            LIMIT 5
        ")->fetchAll();
        return ['count' => $count, 'latest' => $latest];
    }

    public static function logCurrentSessionForAnalytics(): void {
        $user = current_user();
        if(!$user) return;
        self::logLogin($user);
    }

    public static function importCSV($tmpFile): void {
        $handle = fopen($tmpFile, 'r');
        fgetcsv($handle); /* skip header */
        while(($row = fgetcsv($handle, 1000, ',')) !== false){
            $product_no = trim($row[0] ?? '');
            $title      = trim($row[1] ?? '');
            if($product_no && $title){
                db()->prepare("INSERT INTO wp_eco_aplus_tasks (product_no,title,status) VALUES (?,?,'Pending')")
                   ->execute([$product_no, $title]);
            }
        }
        fclose($handle);
    }

    public static function getPermissions(): array {
        $userRoles = db()->query("SELECT DISTINCT role FROM eco_tool_users WHERE role != 'administrator' ORDER BY role ASC")->fetchAll(PDO::FETCH_COLUMN);
        $rows = db()->query("SELECT role, settings FROM eco_permissions WHERE role != 'administrator'")->fetchAll();
        $data = []; $permRoles = [];
        foreach($rows as $r){
            $data[$r['role']] = json_decode($r['settings'], true);
            $permRoles[] = $r['role'];
        }
        $allRoles = array_values(array_unique(array_merge($userRoles, $permRoles)));
        sort($allRoles);
        return ['data'=>$data, 'roles'=>$allRoles];
    }

    public static function savePermissions(string $role, string $settings): array {
        $decoded = json_decode($settings, true);
        if($decoded === null) return ['ok'=>false,'message'=>'Invalid JSON'];
        if(empty($role) || $role === 'administrator') return ['ok'=>false,'message'=>'Invalid role'];
        db()->prepare("INSERT INTO eco_permissions (role,settings) VALUES(?,?) ON DUPLICATE KEY UPDATE settings=?")->execute([$role,$settings,$settings]);
        return ['ok'=>true];
    }

    public static function getUserPermissions(): array {
        self::ensureUserPermissionsTable();
        $rows = db()->query("SELECT user_id, settings FROM eco_user_permissions")->fetchAll();
        $data = [];
        foreach($rows as $r){
            $data[(int)$r['user_id']] = json_decode($r['settings'], true) ?: [];
        }
        return $data;
    }

    public static function getEffectiveFiltersForUser(array $user): ?array {
        if(($user['role'] ?? '') === 'administrator') return null;
        self::ensureUserPermissionsTable();

        try{
            $stmt = db()->prepare("SELECT settings FROM eco_user_permissions WHERE user_id=?");
            $stmt->execute([(int)$user['id']]);
            $settings = $stmt->fetchColumn();
            if($settings !== false){
                $decoded = json_decode($settings, true);
                if(is_array($decoded) && array_key_exists('filters', $decoded)){
                    $f = $decoded['filters'];
                    if(is_array($f) && !in_array('Info Done', $f)){
                        $f[] = 'Info Done';
                    }
                    return $f;
                }
            }
        }catch(Exception $e){}

        try{
            $stmt = db()->prepare("SELECT settings FROM eco_permissions WHERE role=?");
            $stmt->execute([$user['role']]);
            $settings = $stmt->fetchColumn();
            if($settings !== false){
                $decoded = json_decode($settings, true);
                if(is_array($decoded) && isset($decoded['filters']) && is_array($decoded['filters'])){
                    $f = $decoded['filters'];
                    if(!in_array('Info Done', $f)){
                        $f[] = 'Info Done';
                    }
                    return $f;
                }
            }
        }catch(Exception $e){}

        return null;
    }

    public static function saveUserPermissions(int $userId, string $settings): array {
        self::ensureUserPermissionsTable();
        $decoded = json_decode($settings, true);
        if($decoded === null) return ['ok'=>false,'message'=>'Invalid JSON'];
        if($userId <= 0) return ['ok'=>false,'message'=>'Invalid user'];

        $stmt = db()->prepare("SELECT id FROM eco_tool_users WHERE id=?");
        $stmt->execute([$userId]);
        if(!$stmt->fetchColumn()) return ['ok'=>false,'message'=>'User not found'];

        db()->prepare("
            INSERT INTO eco_user_permissions (user_id,settings)
            VALUES (?,?)
            ON DUPLICATE KEY UPDATE settings=?
        ")->execute([$userId, $settings, $settings]);

        return ['ok'=>true];
    }

    public static function logLogin(array $user): void {
        try{
            self::ensureLoginLogsTable();
            $ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
                ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
                : ($_SERVER['REMOTE_ADDR'] ?? '');
            if(!empty($_SERVER['HTTP_CF_CONNECTING_IP'])){
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            }
            if(!empty($_SERVER['HTTP_X_REAL_IP'])){
                $ip = $_SERVER['HTTP_X_REAL_IP'];
            }

            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            db()->prepare("INSERT INTO eco_login_logs (user_id,username,role,ip_address,country,region,city,user_agent) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$user['id'],$user['username'],$user['role'],$ip,'','','',$ua]);
            $logId = (int) db()->lastInsertId();

            $country = ''; $city = ''; $region = '';
            if($ip && $ip !== '127.0.0.1' && $ip !== '::1'){
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $geoJson = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,regionName,city,status", false, $ctx);
                if(!$geoJson){
                    $geoJson = @file_get_contents("https://ipapi.co/{$ip}/json/", false, $ctx);
                }
                $geo = @json_decode($geoJson, true);
                if($geo && ($geo['status'] ?? '') === 'success'){
                    $country = $geo['country'] ?? '';
                    $region  = $geo['regionName'] ?? '';
                    $city    = $geo['city'] ?? '';
                }elseif($geo){
                    $country = $geo['country_name'] ?? '';
                    $region  = $geo['region'] ?? '';
                    $city    = $geo['city'] ?? '';
                }
            }
            if($logId && ($country || $region || $city)){
                db()->prepare("UPDATE eco_login_logs SET country=?, region=?, city=? WHERE id=?")
                   ->execute([$country,$region,$city,$logId]);
            }
        }catch(Exception $e){}
    }

}
