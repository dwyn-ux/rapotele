<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (PHP_SAPI !== 'cli') {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    $appLogDir = APP_ROOT . '/storage/logs';
    if (!is_dir($appLogDir)) {
        @mkdir($appLogDir, 0775, true);
    }
    @ini_set('error_log', $appLogDir . '/php-error.log');
    set_exception_handler(function (Throwable $e): void {
        @error_log('[' . date('Y-m-d H:i:s') . '] ' . ($_SERVER['REQUEST_URI'] ?? 'cli') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . PHP_EOL
            . '  ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL
            . '  at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL
            . $e->getTraceAsString());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $details = '';
        if (function_exists('app_debug')) {
            try {
                if (app_debug()) {
                    $details = '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
                }
            } catch (Throwable) {
            }
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title></head><body><h1>Aplikasi gagal memproses halaman.</h1>' . $details . '</body></html>';
        exit;
    });
}

$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $sep = strpos($line, '=');
        if ($sep === false) {
            continue;
        }
        $key = trim(substr($line, 0, $sep));
        $value = trim(substr($line, $sep + 1));
        if (preg_match('/^"(.*)"$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config/config.example.php';
}

$GLOBALS['config'] = require $configFile;
date_default_timezone_set((string)($GLOBALS['config']['timezone'] ?? 'Asia/Jakarta'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    session_name((string)($GLOBALS['config']['security']['session_name'] ?? 'ERAPORTSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/Core/config.php';
require_once __DIR__ . '/Core/helpers.php';
require_once __DIR__ . '/Core/security.php';
require_once __DIR__ . '/Core/database.php';
require_once __DIR__ . '/Core/http.php';
require_once __DIR__ . '/migrations.php';
if (PHP_SAPI !== 'cli') {
    try {
        run_migrations();
    } catch (Throwable $migrationException) {
        @error_log('[' . date('Y-m-d H:i:s') . '] Migrasi database gagal: '
            . get_class($migrationException) . ': ' . $migrationException->getMessage() . PHP_EOL
            . '  at ' . $migrationException->getFile() . ':' . $migrationException->getLine() . PHP_EOL);
    }
}
require_once __DIR__ . '/Services/telegram.php';

send_security_headers();
