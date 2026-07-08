<?php

declare(strict_types=1);

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];
    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function app_root(): string
{
    return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
}

function app_debug(): bool
{
    return (bool)config('debug', false);
}

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function ensure_directory(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder aplikasi tidak bisa dibuat.');
    }
}

function storage_path(string $relativePath = ''): string
{
    $base = app_root() . '/storage';
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
    return $relativePath === '' ? $base : $base . '/' . $relativePath;
}

function safe_relative_path(string $path, array $allowedPrefixes = []): string
{
    $normalized = trim(str_replace('\\', '/', $path), '/');
    if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
        throw new RuntimeException('Path file tidak valid.');
    }

    foreach (explode('/', $normalized) as $segment) {
        if ($segment === '' || $segment === '.') {
            throw new RuntimeException('Path file tidak valid.');
        }
    }

    if ($allowedPrefixes) {
        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new RuntimeException('Lokasi file tidak diizinkan.');
        }
    }

    return $normalized;
}

function app_file_path(string $relativePath, array $allowedPrefixes = []): string
{
    $relative = safe_relative_path($relativePath, $allowedPrefixes);
    $path = app_root() . '/' . $relative;
    $rootReal = realpath(app_root());
    $pathReal = realpath($path);

    if ($rootReal !== false && $pathReal !== false) {
        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
        $pathReal = str_replace('\\', '/', $pathReal);
        if (!str_starts_with($pathReal, $rootReal)) {
            throw new RuntimeException('Path file tidak valid.');
        }
    }

    return $path;
}

function normalize_http_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        throw new RuntimeException('URL harus berupa HTTP/HTTPS yang valid.');
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('URL harus berupa HTTP/HTTPS yang valid.');
    }

    return rtrim($url, '/');
}

function max_upload_bytes(): int
{
    return max(1, (int)config('security.max_upload_bytes', 2 * 1024 * 1024));
}

function uploaded_file(string $field, bool $required = true): ?array
{
    if (!isset($_FILES[$field]) || (string)($_FILES[$field]['name'] ?? '') === '') {
        if ($required) {
            throw new RuntimeException('Pilih file yang akan diupload.');
        }
        return null;
    }

    $file = $_FILES[$field];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal.');
    }
    if (!is_uploaded_file((string)$file['tmp_name'])) {
        throw new RuntimeException('Upload file tidak valid.');
    }
    if ((int)($file['size'] ?? 0) > max_upload_bytes()) {
        throw new RuntimeException('Ukuran file melebihi batas.');
    }

    return $file;
}

function detected_mime_type(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return function_exists('mime_content_type') ? (string)mime_content_type($path) : 'application/octet-stream';
}

function log_exception(Throwable $exception): void
{
    $message = '[' . date('Y-m-d H:i:s') . '] ' . (string)$exception . PHP_EOL;
    try {
        $dir = storage_path('logs');
        ensure_directory($dir);
        file_put_contents($dir . '/app.log', $message, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
        error_log($message);
    }
}

function friendly_error(Throwable $exception, string $fallback = 'Terjadi kesalahan internal. Silakan coba lagi atau hubungi admin.'): string
{
    log_exception($exception);
    if (app_debug()) {
        return $exception->getMessage();
    }
    if ($exception instanceof InvalidArgumentException) {
        return $exception->getMessage();
    }
    if ($exception instanceof RuntimeException && !$exception instanceof PDOException) {
        return $exception->getMessage();
    }
    return $fallback;
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function rate_limit_path(string $bucket, string $identity): string
{
    $safeBucket = preg_replace('/[^a-z0-9_-]/i', '-', $bucket) ?: 'default';
    $dir = storage_path('rate-limit');
    ensure_directory($dir);
    return $dir . '/' . $safeBucket . '-' . hash('sha256', $identity) . '.json';
}

function rate_limit_hits(string $bucket, string $identity, int $windowSeconds): array
{
    $path = rate_limit_path($bucket, $identity);
    $now = time();
    if (!is_file($path)) {
        return [];
    }
    $payload = json_decode((string)file_get_contents($path), true);
    $hits = is_array($payload) ? array_map('intval', $payload) : [];
    return array_values(array_filter($hits, fn (int $hit): bool => $hit > ($now - $windowSeconds)));
}

function rate_limited(string $bucket, string $identity, int $maxHits, int $windowSeconds): bool
{
    return count(rate_limit_hits($bucket, $identity, $windowSeconds)) >= $maxHits;
}

function rate_limit_hit(string $bucket, string $identity, int $windowSeconds): void
{
    $path = rate_limit_path($bucket, $identity);
    $hits = rate_limit_hits($bucket, $identity, $windowSeconds);
    $hits[] = time();
    file_put_contents($path, json_encode($hits), LOCK_EX);
}

function rate_limit_clear(string $bucket, string $identity): void
{
    $path = rate_limit_path($bucket, $identity);
    if (is_file($path)) {
        unlink($path);
    }
}

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
        // Some shared hosts restrict SET NAMES; DSN charset still protects the connection.
    }

    if ($timezone !== '') {
        if (!preg_match('/^[+-]\d{2}:\d{2}$/', $timezone)) {
            throw new RuntimeException('Konfigurasi DB_TIMEZONE harus berupa offset, contoh +07:00.');
        }
        try {
            $pdo->exec("SET time_zone = " . $pdo->quote($timezone));
        } catch (Throwable) {
            // Keep running if a hosting provider blocks session time_zone changes.
        }
    }

    if ($strict) {
        try {
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (Throwable) {
            // MySQL variants can differ; schema validation still catches most issues.
        }
    }
}

function db_driver(): string
{
    return (string)config('db.driver', 'sqlite');
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

function table_columns(string $table): array
{
    if (!table_exists($table)) {
        return [];
    }

    if (db_driver() === 'sqlite') {
        return array_map(
            fn (array $row): string => (string)$row['name'],
            fetch_all('PRAGMA table_info(' . db_identifier($table) . ')')
        );
    }

    return array_map(
        fn (array $row): string => (string)$row['Field'],
        fetch_all('SHOW COLUMNS FROM ' . db_identifier($table))
    );
}

function table_column_exists(string $table, string $column): bool
{
    return in_array($column, table_columns($table), true);
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validate_password_strength(string $password): void
{
    if (strlen($password) < 8) {
        throw new RuntimeException('Password minimal 8 karakter.');
    }

    if (is_weak_password($password)) {
        throw new RuntimeException('Password terlalu mudah ditebak.');
    }
}

function is_weak_password(string $password): bool
{
    $weakPasswords = ['administrator', 'password', '12345678', 'qwerty123', 'guru123'];
    return in_array(strtolower($password), $weakPasswords, true);
}

function app_url(string $path = ''): string
{
    $base = rtrim((string)config('base_url', ''), '/');
    if ($base === '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base === '/') {
            $base = '';
        }
    }

    return $base . '/' . ltrim($path, '/');
}

function signature_asset(?string $type = null, ?int $id = null): ?array
{
    try {
        if (!table_exists('signatures')) {
            return null;
        }

        if ($id !== null && $id > 0) {
            return fetch_one('SELECT * FROM signatures WHERE id = ?', [$id]);
        }

        $type = trim((string)$type);
        if ($type === '') {
            return null;
        }

        return fetch_one('SELECT * FROM signatures WHERE type = ? AND file_path IS NOT NULL AND file_path <> ? ORDER BY id DESC LIMIT 1', [$type, '']);
    } catch (Throwable) {
        return null;
    }
}

function signature_media_url(string $type = 'logo', ?int $id = null): string
{
    $asset = signature_asset($type, $id);
    if (!$asset || empty($asset['file_path'])) {
        return '';
    }

    $params = $id !== null && $id > 0
        ? ['signature_id' => $id]
        : ['signature' => $type];
    $params['v'] = (string)($asset['updated_at'] ?? $asset['id'] ?? time());

    return app_url('media.php') . '?' . http_build_query($params);
}

function route_url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return app_url('index.php') . '?' . http_build_query($params);
}

function redirect_to(string $page, array $params = []): never
{
    header('Location: ' . route_url($page, $params));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi form tidak valid. Silakan kembali dan ulangi.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user;
    if (is_array($user) && (int)$user['id'] === (int)$_SESSION['user_id']) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        redirect_to('login');
    }
}

function user_role(): string
{
    return (string)(current_user()['role'] ?? 'guest');
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(user_role(), $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function table_exists(string $table): bool
{
    try {
        if (db_driver() === 'sqlite') {
            $stmt = db()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = db()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
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

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function date_ymd(?string $value = null): string
{
    $value = $value ?: date('Y-m-d');
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : date('Y-m-d');
}

function allowed_statuses(): array
{
    return [
        'hadir' => 'Hadir',
        'sakit' => 'Sakit',
        'izin' => 'Izin',
        'alpa' => 'Alpa',
        'terlambat' => 'Terlambat',
    ];
}

function teacher_attendance_statuses(): array
{
    return [
        'hadir' => 'Hadir',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'dinas' => 'Dinas',
        'alpa' => 'Alpa',
    ];
}

function teacher_scope_sql(string $alias = 'ta'): array
{
    $user = current_user();
    if (!$user || is_admin()) {
        return ['', []];
    }

    if (!empty($user['teacher_id'])) {
        return [" AND {$alias}.teacher_id = ?", [(int)$user['teacher_id']]];
    }

    return [' AND 1 = 0', []];
}

function get_app_setting(string $key, mixed $default = ''): mixed
{
    if (!table_exists('app_settings')) {
        return $default;
    }

    $row = fetch_one('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    return $row ? $row['setting_value'] : $default;
}

function set_app_setting(string $key, mixed $value): void
{
    if (!table_exists('app_settings')) {
        return;
    }

    $existing = fetch_one('SELECT id FROM app_settings WHERE setting_key = ?', [$key]);
    if ($existing) {
        execute_sql('UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?', [(string)$value, now_string(), $key]);
        return;
    }

    execute_sql('INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)', [$key, (string)$value, now_string()]);
}
