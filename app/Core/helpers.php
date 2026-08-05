<?php

declare(strict_types=1);

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

function max_upload_bytes(): int
{
    return max(1, (int)config('security.max_upload_bytes', 2 * 1024 * 1024));
}

function uploaded_file(string $field, bool $required = true, ?int $maxBytes = null): ?array
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
    $limit = $maxBytes ?? max_upload_bytes();
    if ((int)($file['size'] ?? 0) > $limit) {
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

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
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
        'hadir' => 'Mengajar',
        'terlambat' => 'Terlambat Mengajar',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'dinas' => 'Tugas/Dinas',
        'alpa' => 'Alpa',
    ];
}

function haversine_distance(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function is_within_radius(float $userLat, float $userLng, float $schoolLat, float $schoolLng, int $radiusMeters): bool
{
    $distance = haversine_distance($userLat, $userLng, $schoolLat, $schoolLng);
    return $distance <= $radiusMeters;
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


