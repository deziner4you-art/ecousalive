<?php

/*
=====================================================
RESPONSE HELPER
JSON responses + CSRF helpers
=====================================================
*/

function json_success(array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function json_error(string $message, int $code = 400): void {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/*
=====================================================
CSRF TOKEN
=====================================================
*/

function crypto_random_bytes(int $length): string {
    if(function_exists('random_bytes')){
        return random_bytes($length);
    }
    if(function_exists('openssl_random_pseudo_bytes')){
        return openssl_random_pseudo_bytes($length);
    }

    $seed = uniqid((string) mt_rand(), true)
          . microtime(true)
          . ($_SERVER['REMOTE_ADDR'] ?? '')
          . session_id();

    $result = '';
    while(strlen($result) < $length){
        $result .= hash('sha256', $seed . mt_rand() . microtime(true), true);
    }
    return substr($result, 0, $length);
}

function csrf_token(): string {
    if(empty($_SESSION[CSRF_TOKEN_NAME])){
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(crypto_random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf(): void {
    $token = $_POST['_csrf']
        ?? $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if($token !== ($_SESSION[CSRF_TOKEN_NAME] ?? '')){
        json_error('Security token mismatch. Please refresh the page.', 403);
    }
}
