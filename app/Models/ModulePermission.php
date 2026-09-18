<?php

/*
=====================================================
MODULE PERMISSION MODEL
RBAC: View / Add / Edit / Delete per module per role
Table: eco_module_permissions
=====================================================
*/

class ModulePermission {

    /* All controllable modules + their applicable actions */
    const MODULES = [
        'products'  => ['view', 'add', 'edit', 'delete'],
        'users'     => ['view', 'add', 'edit', 'delete'],
        'invoices'  => ['view', 'add', 'edit', 'delete'],
        'payroll'   => ['view', 'add', 'edit', 'delete'],
        'seo'       => ['view', 'edit'],
        'analytics' => ['view'],
    ];

    const MODULE_LABELS = [
        'products'  => 'Products',
        'users'     => 'Users',
        'invoices'  => 'Invoices',
        'payroll'   => 'Payroll',
        'seo'       => 'SEO Content',
        'analytics' => 'Analytics',
    ];

    /* Sensible defaults when a role has no saved permissions */
    const DEFAULTS = [
        'worker' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
            'payroll'   => ['view'=>1,'add'=>0,'edit'=>0,'delete'=>0],
        ],
        'ai_work' => [
            'products'  => ['view'=>1,'add'=>1,'edit'=>0,'delete'=>0],
            'payroll'   => ['view'=>1,'add'=>0,'edit'=>0,'delete'=>0],
        ],
        'eco_client' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
            'invoices'  => ['view'=>1,'add'=>0,'edit'=>0,'delete'=>0],
        ],
        'qa' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
            'payroll'   => ['view'=>1,'add'=>0,'edit'=>0,'delete'=>0],
        ],
        'd4u_writer' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
        ],
        'eco_listing' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
            'seo'       => ['view'=>1,'add'=>0,'edit'=>0,'delete'=>0],
        ],
        'seo_manager' => [
            'products'  => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
            'seo'       => ['view'=>1,'add'=>0,'edit'=>1,'delete'=>0],
        ],
    ];

    private static $tableReady = false;
    private static $userTableReady = false;

    private static function ensureTable(): void {
        if(self::$tableReady) return;

        db()->exec("
            CREATE TABLE IF NOT EXISTS eco_module_permissions (
                role       VARCHAR(30) NOT NULL,
                module     VARCHAR(40) NOT NULL,
                can_view   TINYINT(1) NOT NULL DEFAULT 0,
                can_add    TINYINT(1) NOT NULL DEFAULT 0,
                can_edit   TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (role, module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::$tableReady = true;
    }

    private static function ensureUserTable(): void {
        if(self::$userTableReady) return;

        db()->exec("
            CREATE TABLE IF NOT EXISTS eco_user_module_permissions (
                user_id    INT NOT NULL,
                module     VARCHAR(40) NOT NULL,
                can_view   TINYINT(1) NOT NULL DEFAULT 0,
                can_add    TINYINT(1) NOT NULL DEFAULT 0,
                can_edit   TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (user_id, module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::$userTableReady = true;
    }

    /*
    ──────────────────────────────────────────────────
    Get all permissions — returns nested array
    [role][module] => [view,add,edit,delete]
    ──────────────────────────────────────────────────
    */
    public static function getAll(): array {
        self::ensureTable();

        $rows = db()->query("SELECT * FROM eco_module_permissions")->fetchAll();

        $data = [];
        foreach($rows as $r){
            $data[$r['role']][$r['module']] = [
                'view'   => (int)$r['can_view'],
                'add'    => (int)$r['can_add'],
                'edit'   => (int)$r['can_edit'],
                'delete' => (int)$r['can_delete'],
            ];
        }

        /* Fill in defaults for roles/modules not yet saved */
        foreach(self::DEFAULTS as $role => $modules){
            foreach($modules as $module => $perms){
                if(!isset($data[$role][$module])){
                    $data[$role][$module] = array_merge(['view'=>0,'add'=>0,'edit'=>0,'delete'=>0], $perms);
                }
            }
        }

        return $data;
    }

    /*
    ──────────────────────────────────────────────────
    Get permissions for a single role
    ──────────────────────────────────────────────────
    */
    public static function getForRole(string $role): array {
        self::ensureTable();

        $stmt = db()->prepare("SELECT * FROM eco_module_permissions WHERE role=?");
        $stmt->execute([$role]);
        $rows = $stmt->fetchAll();

        $perms = [];
        foreach($rows as $r){
            $perms[$r['module']] = [
                'view'   => (int)$r['can_view'],
                'add'    => (int)$r['can_add'],
                'edit'   => (int)$r['can_edit'],
                'delete' => (int)$r['can_delete'],
            ];
        }

        /* Apply defaults for missing modules */
        $defaults = self::DEFAULTS[$role] ?? [];
        foreach($defaults as $module => $dp){
            if(!isset($perms[$module])){
                $perms[$module] = array_merge(['view'=>0,'add'=>0,'edit'=>0,'delete'=>0], $dp);
            }
        }

        return $perms;
    }

    public static function getUserAll(): array {
        self::ensureUserTable();
        $rows = db()->query("SELECT * FROM eco_user_module_permissions")->fetchAll();
        $data = [];
        foreach($rows as $r){
            $data[(int)$r['user_id']][$r['module']] = [
                'view'   => (int)$r['can_view'],
                'add'    => (int)$r['can_add'],
                'edit'   => (int)$r['can_edit'],
                'delete' => (int)$r['can_delete'],
            ];
        }
        return $data;
    }

    public static function getForUser(array $user): array {
        if(($user['role'] ?? '') === 'administrator'){
            return array_fill_keys(array_keys(self::MODULES), array_fill_keys(['view','add','edit','delete'], true));
        }

        self::ensureUserTable();
        $rolePerms = self::getForRole($user['role'] ?? '');

        $stmt = db()->prepare("SELECT * FROM eco_user_module_permissions WHERE user_id=?");
        $stmt->execute([(int)$user['id']]);
        $rows = $stmt->fetchAll();

        foreach($rows as $r){
            $rolePerms[$r['module']] = [
                'view'   => (int)$r['can_view'],
                'add'    => (int)$r['can_add'],
                'edit'   => (int)$r['can_edit'],
                'delete' => (int)$r['can_delete'],
            ];
        }

        return $rolePerms;
    }

    /*
    ──────────────────────────────────────────────────
    Save permissions for one role + module
    ──────────────────────────────────────────────────
    */
    public static function save(string $role, string $module, array $perms): void {
        self::ensureTable();

        $view   = !empty($perms['view'])   ? 1 : 0;
        $add    = !empty($perms['add'])    ? 1 : 0;
        $edit   = !empty($perms['edit'])   ? 1 : 0;
        $delete = !empty($perms['delete']) ? 1 : 0;

        db()->prepare("
            INSERT INTO eco_module_permissions (role,module,can_view,can_add,can_edit,can_delete)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE can_view=?,can_add=?,can_edit=?,can_delete=?
        ")->execute([$role,$module,$view,$add,$edit,$delete,$view,$add,$edit,$delete]);
    }

    public static function saveForUser(int $userId, string $module, array $perms): void {
        self::ensureUserTable();

        $view   = !empty($perms['view'])   ? 1 : 0;
        $add    = !empty($perms['add'])    ? 1 : 0;
        $edit   = !empty($perms['edit'])   ? 1 : 0;
        $delete = !empty($perms['delete']) ? 1 : 0;

        db()->prepare("
            INSERT INTO eco_user_module_permissions (user_id,module,can_view,can_add,can_edit,can_delete)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE can_view=?,can_add=?,can_edit=?,can_delete=?
        ")->execute([$userId,$module,$view,$add,$edit,$delete,$view,$add,$edit,$delete]);
    }

    public static function deleteForUser(int $userId): void {
        self::ensureUserTable();
        db()->prepare("DELETE FROM eco_user_module_permissions WHERE user_id=?")->execute([$userId]);
    }

    /*
    ──────────────────────────────────────────────────
    Check one permission (for PHP enforcement)
    Administrator always returns true
    ──────────────────────────────────────────────────
    */
    public static function check(string $role, string $module, string $action): bool {
        if($role === 'administrator') return true;

        try{
            self::ensureTable();
            $stmt = db()->prepare("SELECT can_{$action} FROM eco_module_permissions WHERE role=? AND module=?");
            $stmt->execute([$role, $module]);
            $val = $stmt->fetchColumn();
            if($val !== false) return (bool)$val;
        }catch(Exception $e){}

        /* Fall back to hardcoded defaults */
        return !empty(self::DEFAULTS[$role][$module][$action]);
    }

}
