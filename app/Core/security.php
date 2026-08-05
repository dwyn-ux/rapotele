<?php

declare(strict_types=1);

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
