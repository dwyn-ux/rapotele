<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (PHP_SAPI !== 'cli' && app_installed()) {
        http_response_code(403);
        echo "Aplikasi sudah terpasang. Installer dikunci.\n";
        exit;
    }
    install_database();
    echo "Instalasi database selesai.\n";
    echo "Login awal: administrator / administrator\n";
    echo "Buka index.php?page=login\n";
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Instalasi gagal: ' . friendly_error($exception);
}
