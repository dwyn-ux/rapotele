<?php

declare(strict_types=1);

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    // ponytail: 'unsafe-inline' in script-src needed for geolocation script in assessment.php.
    // Upgrade path: move that inline script to public/assets/app.js and switch to nonce-based CSP.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://*.tile.openstreetmap.org; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://unpkg.com; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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

function app_routes(): array
{
    static $routes;
    if (!is_array($routes)) {
        $routes = require __DIR__ . '/../routes.php';
    }

    return $routes;
}

function app_post_actions(): array
{
    static $actions;
    if (!is_array($actions)) {
        $actions = require __DIR__ . '/../actions.php';
    }

    return $actions;
}

function app_has_post_action(string $action): bool
{
    return array_key_exists($action, app_post_actions());
}

function app_call_handler(mixed $handler): void
{
    if (is_string($handler)) {
        if (!function_exists($handler)) {
            throw new RuntimeException('Handler aplikasi tidak ditemukan.');
        }
        $handler();
        return;
    }

    if (is_callable($handler)) {
        $handler();
        return;
    }

    throw new RuntimeException('Handler aplikasi tidak valid.');
}

function app_dispatch_page(string $page, string $scope): void
{
    $routes = app_routes()[$scope] ?? [];
    $handler = $routes[$page] ?? null;
    if ($handler === null) {
        app_render_not_found();
        return;
    }

    app_call_handler($handler);
}

function app_dispatch_post_action(): void
{
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');
    $handler = app_post_actions()[$action] ?? null;
    if ($handler === null) {
        throw new InvalidArgumentException('Action tidak dikenal.');
    }

    app_call_handler($handler);
}

function app_handle_post_request(): void
{
    try {
        app_dispatch_post_action();
    } catch (Throwable $exception) {
        flash('danger', 'Gagal: ' . friendly_error($exception));
        redirect_to((string)($_GET['page'] ?? 'dashboard'));
    }
}

function app_dispatch_request(): void
{
    $page = (string)($_GET['page'] ?? 'dashboard');

    try {
        if ($page === 'install') {
            app_dispatch_page($page, 'public');
            return;
        }

        if (array_key_exists($page, app_routes()['public'] ?? [])) {
            app_dispatch_page($page, 'public');
            return;
        }

        require_login();

        if (is_post()) {
            app_handle_post_request();
            return;
        }

        app_dispatch_page($page, 'private');
    } catch (Throwable $exception) {
        app_render_exception($exception);
    }
}

function app_render_not_found(): void
{
    http_response_code(404);
    if (current_user()) {
        render_header('Halaman Tidak Ditemukan');
        echo '<section class="panel"><h3>Halaman tidak ditemukan.</h3><p>Periksa kembali menu atau alamat halaman.</p></section>';
        render_footer();
        return;
    }

    render_public_header('Halaman Tidak Ditemukan');
    echo '<h1>Halaman tidak ditemukan</h1><p>Periksa kembali alamat halaman.</p>';
    render_public_footer();
}

function app_render_exception(Throwable $exception): void
{
    http_response_code(500);
    $message = friendly_error($exception);
    $renderAuthenticatedShell = false;
    try {
        $renderAuthenticatedShell = app_installed() && (bool)current_user();
    } catch (Throwable) {
        $renderAuthenticatedShell = false;
    }

    $logDir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/app-errors.log';
    $logLine = '[' . date('Y-m-d H:i:s') . '] ' . ($_SERVER['REQUEST_URI'] ?? 'cli') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . PHP_EOL
        . '  ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL
        . '  at ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL
        . $exception->getTraceAsString() . PHP_EOL . PHP_EOL;
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    if ($renderAuthenticatedShell) {
        render_header('Terjadi Kesalahan');
        echo '<section class="panel"><h3>Aplikasi gagal memproses halaman.</h3><p>' . e($message) . '</p></section>';
        render_footer();
        return;
    }

    render_public_header('Terjadi Kesalahan');
    echo '<h1>Terjadi Kesalahan</h1><p>' . e($message) . '</p>';
    render_public_footer();
}
