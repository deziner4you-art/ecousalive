<?php

/*
=====================================================
AUTH MIDDLEWARE
Session management + force-logout check
=====================================================
*/

class AuthMiddleware {

    /*
    ──────────────────────────────────────────────────
    handle()
    Called on every request — starts session and
    checks the force-logout token.
    ──────────────────────────────────────────────────
    */
    public static function handle(string $action = ''): void {

        /* Session hardening */
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }

        /* Generate CSRF token for this session */
        if(empty($_SESSION[CSRF_TOKEN_NAME])){
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(crypto_random_bytes(32));
        }

        /* Force-logout check (exact logic from monolith lines 12–26) */
        if(isset($_SESSION['eco_user']) && !in_array($action, ['login','logout','force_logout_all'])){

            try{

                $cur_tok = User::getLoginToken();

                if(
                    $cur_tok &&
                    $cur_tok !== 'init' &&
                    ($_SESSION['logout_token'] ?? '') !== $cur_tok
                ){
                    session_destroy();

                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                           || ($action !== '' && $action !== 'login');

                    if($isAjax){
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success'      => false,
                            'force_logout' => true,
                            'message'      => 'Session expired. Please login again.',
                        ]);
                    } else {
                        header('Location: '.PUBLIC_URL.'/');
                    }

                    exit;
                }

            }catch(Exception $e){}

        }

    }

    /*
    ──────────────────────────────────────────────────
    requireAuth()
    Aborts with 401 / redirect if not logged in
    ──────────────────────────────────────────────────
    */
    public static function requireAuth(bool $isApi = true): void {

        if(!current_user()){

            if($isApi){
                json_error('Not authenticated', 401);
            } else {
                header('Location: '.PUBLIC_URL.'/');
                exit;
            }

        }

    }

}
