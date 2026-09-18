<?php

/*
=====================================================
DATABASE CONFIG
Reads from /home/deziqvuj/secret/.env
=====================================================
*/

// Absolute path to .env file
$_envFile = '/home/deziqvuj/secret/.ecousaenv';

$_env = [];

if (file_exists($_envFile)) {
    $_env = parse_ini_file($_envFile, false, INI_SCANNER_RAW) ?: [];
} elseif (file_exists(dirname(__DIR__) . '/.env')) {
    $_env = parse_ini_file(dirname(__DIR__) . '/.env', false, INI_SCANNER_RAW) ?: [];
} else {
    die('Error: .env file not found at ' . $_envFile . ' or ' . dirname(__DIR__) . '/.env');
}

/*
=====================================================
DATABASE SETTINGS
=====================================================
*/

define('DB_HOST', $_env['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_env['DB_NAME'] ?? '');
define('DB_USER', $_env['DB_USER'] ?? '');
define('DB_PASS', $_env['DB_PASS'] ?? '');

/*
=====================================================
APPLICATION SETTINGS
=====================================================
*/

define('APP_URL', rtrim($_env['APP_URL'] ?? 'https://deziner4you.com/aplus', '/'));
define('APP_ENV', $_env['APP_ENV'] ?? 'production');

define('PUBLIC_URL', rtrim($_env['PUBLIC_URL'] ?? APP_URL, '/'));

/*
=====================================================
API KEYS
=====================================================
*/

define('OPENAI_API_KEY', $_env['OPENAI_API_KEY'] ?? '');
define('GEMINI_API_KEY', $_env['GEMINI_API_KEY'] ?? '');

/*
=====================================================
TIMEZONE (OPTIONAL)
=====================================================
*/

date_default_timezone_set($_env['APP_TIMEZONE'] ?? 'Asia/Karachi');