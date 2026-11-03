<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicDir = __DIR__;
$file = $publicDir . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require $publicDir . '/index.php';
