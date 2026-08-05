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
