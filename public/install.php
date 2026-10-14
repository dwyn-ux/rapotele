<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    if (PHP_SAPI !== 'cli' && app_installed()) {
        http_response_code(403);
        echo "Aplikasi sudah terpasang. Installer dikunci.\n";
        exit;
    }
    $initialAdminPassword = install_database();
    echo "Instalasi database selesai.\n";
    if ($initialAdminPassword !== null) {
        echo "Username awal: administrator\nPassword awal sekali pakai: $initialAdminPassword\nSimpan password ini sekarang dan segera ganti setelah login.\n";
    }
    echo "Buka index.php?page=login\n";
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Instalasi gagal: ' . friendly_error($exception);
}
