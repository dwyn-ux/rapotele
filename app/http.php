<?php

declare(strict_types=1);

function app_routes(): array
{
    static $routes;
    if (!is_array($routes)) {
        $routes = require __DIR__ . '/routes.php';
    }

    return $routes;
}

function app_post_actions(): array
{
    static $actions;
    if (!is_array($actions)) {
        $actions = require __DIR__ . '/actions.php';
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
