<?php

declare(strict_types=1);

/*
 * Copy this file to config/config.php on production hosting, then fill DB_* values
 * directly or provide them via environment variables in the hosting panel.
 */

if (!function_exists('config_env')) {
    function config_env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }
        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }

        return $value === false || $value === '' ? $default : $value;
    }

    function config_env_bool(string $key, bool $default = false): bool
    {
        $value = config_env($key, null);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    function config_env_int(string $key, int $default): int
    {
        $value = config_env($key, null);
        return $value === null ? $default : (int)$value;
    }
}

return [
    'app_name' => 'E-Raport KumerBot',
    'env' => 'production',
    'debug' => config_env_bool('APP_DEBUG', false),
    'timezone' => (string)config_env('APP_TIMEZONE', 'Asia/Jakarta'),
    'base_url' => (string)config_env('APP_URL', 'https://domain-anda.sch.id'),

    'db' => [
        'driver' => 'mysql',
        'host' => (string)config_env('DB_HOST', 'localhost'),
        'port' => config_env_int('DB_PORT', 3306),
        'database' => (string)config_env('DB_DATABASE', 'nama_database'),
        'username' => (string)config_env('DB_USERNAME', 'user_database'),
        'password' => (string)config_env('DB_PASSWORD', 'password_database'),
        'charset' => (string)config_env('DB_CHARSET', 'utf8mb4'),
        'collation' => (string)config_env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'strict' => config_env_bool('DB_STRICT', true),
        'timezone' => (string)config_env('DB_TIMEZONE', '+07:00'),
        'unix_socket' => (string)config_env('DB_SOCKET', ''),
        'timeout' => config_env_int('DB_TIMEOUT', 10),
    ],

    'telegram' => [
        'bot_token' => (string)config_env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => (string)config_env('TELEGRAM_WEBHOOK_SECRET', ''),
    ],

    'schedule_reminder' => [
        'secret' => (string)config_env('SCHEDULE_REMINDER_SECRET', ''),
        'minutes_before' => config_env_int('SCHEDULE_REMINDER_MINUTES_BEFORE', 10),
    ],

    'security' => [
        'session_name' => 'ERAPORTSESSID',
        'max_upload_bytes' => 10 * 1024 * 1024,
        'max_backup_upload_bytes' => 128 * 1024 * 1024,
        'max_image_upload_bytes' => 2 * 1024 * 1024,
        'login_max_attempts' => 5,
        'login_window_seconds' => 15 * 60,
    ],

    'school' => [
        'name' => (string)config_env('SCHOOL_NAME', 'Nama Sekolah'),
        'academic_year' => (string)config_env('SCHOOL_ACADEMIC_YEAR', '2025/2026'),
        'semester' => (string)config_env('SCHOOL_SEMESTER', 'Genap'),
    ],
];
