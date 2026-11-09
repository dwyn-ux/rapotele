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
    $class = $exception::class;
    $pos = strrpos($class, '\\');
    $shortClass = $pos === false ? $class : substr($class, $pos + 1);
    $origin = $exception->getFile() . ':' . $exception->getLine();
    if (app_debug()) {
        return $exception->getMessage() . ' [' . $shortClass . ' di ' . $origin . ']';
    }
    if ($exception instanceof InvalidArgumentException) {
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

function date_ymd(mixed $value = null): string
{
    if ($value === null || $value === '') {
        return date('Y-m-d');
    }
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
        throw new InvalidArgumentException('Tanggal harus dalam format Y-m-d yang valid.');
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException('Tanggal harus dalam format Y-m-d yang valid.');
    }

    return $value;
}

function strict_finite_float(mixed $value, string $message): float
{
    if (is_int($value) || is_float($value)) {
        $number = (float)$value;
    } elseif (is_string($value) && preg_match('/^[+-]?(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D', $value)) {
        $number = (float)$value;
    } else {
        throw new InvalidArgumentException($message);
    }

    if (!is_finite($number)) {
        throw new InvalidArgumentException($message);
    }

    return $number;
}

function grade_score_value(mixed $value): ?float
{
    if ($value === null || (is_string($value) && trim($value) === '')) {
        return null;
    }

    $score = strict_finite_float($value, 'Nilai harus berupa angka yang valid.');
    if ($score < 0 || $score > 100) {
        throw new InvalidArgumentException('Nilai harus antara 0 dan 100.');
    }

    return $score;
}

function allowed_status_value(mixed $value, array $allowed, string $message): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($message);
    }

    $status = strtolower($value);
    if (!array_key_exists($status, $allowed)) {
        throw new InvalidArgumentException($message);
    }

    return $status;
}

function positive_int_value(mixed $value, string $message): int
{
    if (is_int($value)) {
        if ($value > 0) {
            return $value;
        }
        throw new InvalidArgumentException($message);
    }
    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) {
        throw new InvalidArgumentException($message);
    }

    $max = (string)PHP_INT_MAX;
    if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
        throw new InvalidArgumentException($message);
    }

    $number = (int)$value;
    if ($number <= 0) {
        throw new InvalidArgumentException($message);
    }

    return $number;
}

function coordinate_value(mixed $value, float $min, float $max, string $message): ?float
{
    if ($value === null || (is_string($value) && trim($value) === '')) {
        return null;
    }

    $number = strict_finite_float($value, $message);
    if ($number < $min || $number > $max) {
        throw new InvalidArgumentException($message);
    }

    return $number;
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

function report_nip_label(): string
{
    $label = trim((string)get_app_setting('report.nip_label', 'NIP'));
    return $label !== '' ? $label : 'NIP';
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
        if ($type === 'logo') {
            $defaultLogo = APP_ROOT . '/public/assets/logo.png';
            if (is_file($defaultLogo)) {
                return app_url('assets/logo.png');
            }
        }
        return '';
    }

    $params = $id !== null && $id > 0
        ? ['signature_id' => $id]
        : ['signature' => $type];
    $params['v'] = (string)($asset['updated_at'] ?? $asset['id'] ?? time());

    return app_url('media.php') . '?' . http_build_query($params);
}

function generate_username_from_name(string $name): string
{
    $words = preg_split('/\s+/', trim($name));
    if (!$words) {
        return 'guru';
    }
    $username = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', implode('', array_slice($words, 0, 2))));
    $username = preg_replace('/[^a-z0-9]/', '', $username);
    return $username !== '' ? $username : 'guru';
}

function unique_username(string $base): string
{
    $username = $base;
    $counter = 1;
    while (fetch_one('SELECT id FROM users WHERE username = ?', [$username])) {
        $counter++;
        $username = $base . $counter;
    }
    return $username;
}

if (!function_exists('class_physical_group')) {
    function class_physical_group(int $classId): ?string
    {
        if ($classId <= 0 || !table_column_exists('classes', 'physical_group')) {
            return null;
        }
        $c = fetch_one('SELECT physical_group FROM classes WHERE id = ?', [$classId]);
        $g = trim((string)($c['physical_group'] ?? ''));
        return $g !== '' ? $g : null;
    }
}

if (!function_exists('sibling_classes_for_class')) {
    function sibling_classes_for_class(int $classId): array
    {
        $group = class_physical_group($classId);
        if (!$group) {
            return [];
        }
        $mainClass = fetch_one('SELECT school_id, academic_year FROM classes WHERE id = ?', [$classId]);
        if (!$mainClass) {
            return [];
        }
        $params = [$group, $classId, (string)$mainClass['academic_year']];
        $sql = 'SELECT * FROM classes WHERE physical_group = ? AND id <> ? AND academic_year = ? AND active = 1';
        if (!empty($mainClass['school_id'])) {
            $sql .= ' AND school_id = ?';
            $params[] = (int)$mainClass['school_id'];
        }
        $sql .= ' ORDER BY name';
        return fetch_all($sql, $params);
    }
}

if (!function_exists('physical_group_class_ids')) {
    function physical_group_class_ids(int $classId): array
    {
        $siblings = sibling_classes_for_class($classId);
        $ids = [$classId];
        foreach ($siblings as $s) {
            $ids[] = (int)$s['id'];
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('sibling_assignments_for_assignment')) {
    function sibling_assignments_for_assignment(array $assignment): array
    {
        $classId = (int)($assignment['class_id'] ?? 0);
        $teacherId = (int)($assignment['teacher_id'] ?? 0);
        $subjectId = (int)($assignment['subject_id'] ?? 0);
        if ($classId <= 0 || $teacherId <= 0 || $subjectId <= 0) {
            return [];
        }
        $siblings = sibling_classes_for_class($classId);
        if (!$siblings) {
            return [];
        }
        $siblingClassIds = array_map(fn($s) => (int)$s['id'], $siblings);
        $placeholders = implode(',', array_fill(0, count($siblingClassIds), '?'));
        $params = array_merge([$teacherId, $subjectId], $siblingClassIds);
        try {
            return fetch_all(
                "SELECT ta.*, t.name AS teacher_name, c.name AS class_name, c.level AS class_level, c.physical_group, s.name AS subject_name
                 FROM teaching_assignments ta
                 JOIN teachers t ON t.id = ta.teacher_id
                 JOIN classes c ON c.id = ta.class_id
                 JOIN subjects s ON s.id = ta.subject_id
                 WHERE ta.teacher_id = ? AND ta.subject_id = ? AND ta.class_id IN ($placeholders) AND ta.active = 1
                 ORDER BY c.name",
                $params
            );
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('class_order_by_clause')) {
    function class_order_by_clause(string $prefix = ''): string
    {
        $p = $prefix !== '' ? rtrim($prefix, '.') . '.' : '';
        $hasDisplayOrder = function_exists('table_column_exists') && table_column_exists('classes', 'display_order');
        $displayOrderClause = $hasDisplayOrder ? "COALESCE(NULLIF({$p}display_order, 0), 9999) ASC, " : '';

        return "{$displayOrderClause}CASE 
                    WHEN {$p}grade IN ('VII', '7') THEN 7
                    WHEN {$p}grade IN ('VIII', '8') THEN 8
                    WHEN {$p}grade IN ('IX', '9') THEN 9
                    WHEN {$p}grade IN ('X', '10') THEN 10
                    WHEN {$p}grade IN ('XI', '11') THEN 11
                    WHEN {$p}grade IN ('XII', '12') THEN 12
                    WHEN ({$p}grade + 0) > 0 THEN ({$p}grade + 0)
                    ELSE 99
                END ASC,
                {$p}name ASC,
                {$p}id ASC";
    }
}




