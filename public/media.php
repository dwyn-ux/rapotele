<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $allowedTypes = ['logo', 'logo_dinas', 'ttd_kepsek', 'ttd_wali', 'stempel'];
    $id = (int)($_GET['signature_id'] ?? 0);
    $type = strtolower(trim((string)($_GET['signature'] ?? '')));

    if ($id <= 0 && !in_array($type, $allowedTypes, true)) {
        http_response_code(404);
        echo 'Media tidak ditemukan.';
        exit;
    }

    $asset = $id > 0 ? signature_asset(null, $id) : signature_asset($type);
    if (!$asset || empty($asset['file_path']) || !in_array((string)$asset['type'], $allowedTypes, true)) {
        http_response_code(404);
        echo 'Media tidak ditemukan.';
        exit;
    }

    $path = app_file_path((string)$asset['file_path'], ['storage/uploads/signatures']);
    if (!is_file($path)) {
        http_response_code(404);
        echo 'File tidak ditemukan.';
        exit;
    }

    $mime = detected_mime_type($path);
    if (!str_starts_with($mime, 'image/')) {
        http_response_code(415);
        echo 'Tipe media tidak didukung.';
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
} catch (Throwable $exception) {
    http_response_code(500);
    echo friendly_error($exception);
}
