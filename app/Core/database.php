<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config('db');
    $driver = (string)($db['driver'] ?? 'sqlite');

    if ($driver === 'sqlite') {
        $path = (string)$db['database'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn);
    } elseif ($driver === 'mysql') {
        $charset = (string)($db['charset'] ?? 'utf8mb4');
        $collation = (string)($db['collation'] ?? 'utf8mb4_unicode_ci');
        $port = (int)($db['port'] ?? 3306);
        $database = trim((string)($db['database'] ?? ''));
        if ($database === '') {
            throw new RuntimeException('Konfigurasi DB_DATABASE MySQL belum diisi.');
        }

        $socket = trim((string)($db['unix_socket'] ?? ''));
        $dsn = $socket !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $database, $charset)
            : sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                (string)($db['host'] ?? 'localhost'),
                $port,
                $database,
                $charset
            );

        $options = [
            PDO::ATTR_TIMEOUT => (int)($db['timeout'] ?? 10),
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE {$collation}";
        }

        $pdo = new PDO($dsn, (string)($db['username'] ?? ''), (string)($db['password'] ?? ''), $options);
    } else {
        throw new RuntimeException('Unsupported database driver: ' . $driver);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    if ($driver === 'mysql') {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        mysql_bootstrap_connection($pdo, $db);
    }

    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

function mysql_bootstrap_connection(PDO $pdo, array $db): void
{
    $charset = (string)($db['charset'] ?? 'utf8mb4');
    $collation = (string)($db['collation'] ?? 'utf8mb4_unicode_ci');
    $timezone = trim((string)($db['timezone'] ?? '+07:00'));
    $strict = (bool)($db['strict'] ?? true);

    if (!preg_match('/^[A-Za-z0-9_]+$/', $charset) || !preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
        throw new RuntimeException('Konfigurasi charset/collation MySQL tidak valid.');
    }

    try {
        $pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
    } catch (Throwable) {
    }

    if ($timezone !== '') {
        if (!preg_match('/^[+-]\d{2}:\d{2}$/', $timezone)) {
            throw new RuntimeException('Konfigurasi DB_TIMEZONE harus berupa offset, contoh +07:00.');
        }
        try {
            $pdo->exec("SET time_zone = " . $pdo->quote($timezone));
        } catch (Throwable) {
        }
    }

    if ($strict) {
        try {
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (Throwable) {
        }
    }
}

function db_driver(): string
{
    return (string)config('db.driver', 'sqlite');
}

function db_insert_ignore(): string
{
    return db_driver() === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
}

function db_insert_replace(): string
{
    return db_driver() === 'mysql' ? 'REPLACE' : 'INSERT OR REPLACE';
}

function db_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        throw new InvalidArgumentException('Identifier database tidak valid.');
    }

    return db_driver() === 'mysql'
        ? '`' . $identifier . '`'
        : '"' . $identifier . '"';
}

function table_exists(string $table): bool
{
    if (db_driver() === 'sqlite') {
        $stmt = db()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $exception) {
        $stmt = db()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}

function table_columns(string $table): array
{
    if (db_driver() === 'sqlite') {
        return array_map(
            fn (array $row): string => (string)$row['name'],
            fetch_all('PRAGMA table_info(' . db_identifier($table) . ')')
        );
    }

    try {
        $stmt = db()->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$table]);
        return array_map(
            fn (array $row): string => (string)$row['COLUMN_NAME'],
            $stmt->fetchAll()
        );
    } catch (PDOException $exception) {
        $rows = fetch_all('SHOW COLUMNS FROM ' . db_identifier($table));
        return array_map(
            fn (array $row): string => (string)$row['Field'],
            $rows
        );
    }
}

function table_column_exists(string $table, string $column): bool
{
    return in_array($column, table_columns($table), true);
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function execute_sql(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function app_installed(): bool
{
    try {
        $count = db()->query('SELECT COUNT(*) FROM ' . db_identifier('users'))->fetchColumn();
        return (int)$count > 0;
    } catch (Throwable $exception) {
        $message = strtolower($exception->getMessage());
        if ($exception instanceof PDOException && (
            $exception->getCode() === '42S02'
            || str_contains($message, 'no such table')
            || str_contains($message, "doesn't exist")
        )) {
            return false;
        }

        throw new RuntimeException('Database aplikasi tidak bisa dibaca. Periksa config/config.php dan pastikan database yang dipakai web adalah database yang sudah di-import.', 0, $exception);
    }
}


