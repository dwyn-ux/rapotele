<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (db_driver() !== 'mysql') {
    fwrite(STDERR, "DB driver saat ini bukan mysql. Set APP_ENV=production dan DB_DRIVER=mysql, atau edit config/config.php.\n");
    exit(1);
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "Ekstensi PDO MySQL belum aktif di PHP hosting ini.\n");
    exit(1);
}

try {
    $pdo = db();
    $checks = [
        'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'connection_charset' => $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch(),
        'connection_collation' => $pdo->query("SHOW VARIABLES LIKE 'collation_connection'")->fetch(),
        'sql_mode' => $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(),
        'time_zone' => $pdo->query('SELECT @@SESSION.time_zone')->fetchColumn(),
    ];

    echo "MySQL connection OK.\n";
    echo "Version: " . (string)$checks['mysql_version'] . "\n";
    echo "Database: " . (string)$checks['database'] . "\n";
    echo "Charset: " . (string)($checks['connection_charset']['Value'] ?? '-') . "\n";
    echo "Collation: " . (string)($checks['connection_collation']['Value'] ?? '-') . "\n";
    echo "SQL mode: " . (string)$checks['sql_mode'] . "\n";
    echo "Time zone: " . (string)$checks['time_zone'] . "\n";

    if (table_exists('users')) {
        echo "Users table: ada (" . (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " akun).\n";
    } else {
        echo "Users table: belum ada. Jalankan installer/migrasi setelah cek koneksi OK.\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL check gagal: " . $exception->getMessage() . "\n");
    exit(1);
}
