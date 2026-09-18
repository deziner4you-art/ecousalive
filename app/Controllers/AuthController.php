<?php

/*
=====================================================
AUTH CONTROLLER
Login, logout, password, force-logout, login logs
=====================================================
*/

class AuthController {

    public function login(): void {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = User::findByUsername($username);

        if($user && password_verify($password, $user['password'])){

            session_regenerate_id(true);

            $_SESSION['eco_user'] = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];

            /* Store current logout_token to enable force-logout */
            try{
                $tok = User::getLoginToken();
                $_SESSION['logout_token'] = $tok;
            }catch(Exception $e){}

            /* Log the login event */
            User::logLogin($user);

            header('Location: '.PUBLIC_URL.'/');
            exit;

        }

        /* Login failed — show login page with error */
        $loginError = 'Invalid Login';
        require_once ROOT.'/views/layouts/auth.php';

    }

    public function logout(): void {
        session_destroy();
        header('Location: '.PUBLIC_URL.'/');
        exit;
    }

    public function changePassword(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();

        $u    = current_user();
        $curr = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password']     ?? '';
        $new2 = $_POST['confirm_password'] ?? '';

        $result = User::changePassword($u['id'], $curr, $new1, $new2);

        if($result['ok']){
            json_success(['message' => $result['message']]);
        } else {
            json_error($result['message']);
        }
    }

    public function forceLogoutAll(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $_SESSION['logout_token'] = User::forceLogoutAll();
        json_success();
    }

    public function getLoginLogs(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();

        try{
            $limit = intval($_GET['limit'] ?? 200);
            $data  = User::getLoginLogs($limit);
            json_success(['data' => $data, 'status' => User::analyticsStatus()]);
        }catch(Exception $e){
            json_error('Analytics logs read failed: '.$e->getMessage(), 500);
        }
    }

    public function logCurrentSession(): void {
        AuthMiddleware::requireAuth();
        try{
            User::logCurrentSessionForAnalytics();
            json_success();
        }catch(Exception $e){
            json_error('Analytics log failed: '.$e->getMessage(), 500);
        }
    }

    public function refreshSession(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user = current_user();
        if($user){
            try{
                $db = db();
                $stmt = $db->prepare("
                    INSERT INTO eco_user_usage (user_id, active_seconds, last_ping_at)
                    VALUES (?, 30, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE active_seconds = active_seconds + 30, last_ping_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$user['id']]);
            }catch(Exception $e){}
        }
        json_success();
    }

    public function showLogin(): void {
        if(current_user()){
            header('Location: '.PUBLIC_URL.'/');
            exit;
        }
        $loginError = null;
        require_once ROOT.'/views/layouts/auth.php';
    }

}
