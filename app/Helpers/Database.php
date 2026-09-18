<?php

/*
=====================================================
DATABASE HELPER
PDO Singleton + Auth helpers
(Migrated from config.php)
=====================================================
*/

function db(): PDO {

    static $pdo = null;

    if(!$pdo){

        try{

            $pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

        }catch(PDOException $e){

            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode([
                'success'  => false,
                'message'  => 'DB connection failed',
            ]));

        }

    }

    return $pdo;

}

/*
=====================================================
SESSION HELPER: current logged-in user
=====================================================
*/

function current_user(): ?array {
    return $_SESSION['eco_user'] ?? null;
}

/*
=====================================================
ROLE CHECK
=====================================================
*/

function is_admin(): bool {
    return isset($_SESSION['eco_user'])
        && $_SESSION['eco_user']['role'] === 'administrator';
}
