<?php

/*
=====================================================
ROLE MIDDLEWARE
Role-based access checks
=====================================================
*/

class RoleMiddleware {

    /*
    ──────────────────────────────────────────────────
    requireAdmin()
    Abort with 403 if not administrator
    ──────────────────────────────────────────────────
    */
    public static function requireAdmin(): void {
        if(!is_admin()){
            json_error('Unauthorized — admin only', 403);
        }
    }

    /*
    ──────────────────────────────────────────────────
    requireAnyOf(array $roles)
    Abort with 403 if current role not in list
    ──────────────────────────────────────────────────
    */
    public static function requireAnyOf(array $roles): void {
        $user = current_user();
        if(!$user || !in_array($user['role'], $roles)){
            json_error('Unauthorized', 403);
        }
    }

    /*
    ──────────────────────────────────────────────────
    getFilterPermissions()
    Returns allowed filter array for role (null = all)
    ──────────────────────────────────────────────────
    */
    public static function getFilterPermissions(): ?array {

        if(is_admin()) return null; /* Admin sees everything */

        $user = current_user();
        if(!$user) return [];

        $userFilters = User::getEffectiveFiltersForUser($user);
        if($userFilters !== null) {
            if(!in_array('Info Done', $userFilters)){
                $userFilters[] = 'Info Done';
            }
            return $userFilters;
        }

        try{
            $stmt = db()->prepare("SELECT settings FROM eco_permissions WHERE role=?");
            $stmt->execute([$user['role']]);
            $row = $stmt->fetch();
            if($row){
                $s = json_decode($row['settings'], true);
                $filters = $s['filters'] ?? null;
                if(is_array($filters)){
                    if(!in_array('Info Done', $filters)){
                        $filters[] = 'Info Done';
                    }
                    return $filters;
                }
            }
        }catch(Exception $e){}

        return null;

    }

}
