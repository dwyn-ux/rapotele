<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/web.php';

$errors = [];
$routeCount = 0;

foreach (app_routes() as $scope => $routes) {
    if (!is_array($routes)) {
        $errors[] = "Route scope $scope tidak valid.";
        continue;
    }

    foreach ($routes as $page => $handler) {
        $routeCount++;
        if (is_string($handler) && !function_exists($handler)) {
            $errors[] = "Route $scope/$page memakai handler $handler yang tidak ditemukan.";
        } elseif (!is_string($handler) && !is_callable($handler)) {
            $errors[] = "Route $scope/$page punya handler yang tidak callable.";
        }
    }
}

$actionCount = 0;
foreach (app_post_actions() as $action => $handler) {
    $actionCount++;
    if (is_string($handler) && !function_exists($handler)) {
        $errors[] = "Action $action memakai handler $handler yang tidak ditemukan.";
    } elseif (!is_string($handler) && !is_callable($handler)) {
        $errors[] = "Action $action punya handler yang tidak callable.";
    }
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[ERROR] $error\n");
    }
    exit(1);
}

echo "OK: $routeCount routes, $actionCount actions.\n";
