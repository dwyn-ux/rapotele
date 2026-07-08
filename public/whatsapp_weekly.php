<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/report_pdf.php';
require_once dirname(__DIR__) . '/app/whatsapp.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['ok' => false, 'message' => 'Method tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!app_installed()) {
        throw new RuntimeException('Aplikasi e-rapor belum diinstall.');
    }

    $expectedSecret = whatsapp_cron_secret();
    $givenSecret = trim((string)(($_SERVER['HTTP_X_ERAPORT_WHATSAPP_SECRET'] ?? '') ?: ($_POST['secret'] ?? $_GET['secret'] ?? '')));
    if ($expectedSecret === '' || !hash_equals($expectedSecret, $givenSecret)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Secret WhatsApp weekly tidak valid atau belum dikonfigurasi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = whatsapp_run_weekly_cron(
        trim((string)($_POST['start_date'] ?? $_GET['start_date'] ?? '')) ?: null,
        trim((string)($_POST['end_date'] ?? $_GET['end_date'] ?? '')) ?: null,
        (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0),
        (int)($_POST['limit'] ?? $_GET['limit'] ?? 100)
    );

    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['ok' => false, 'message' => friendly_error($exception)], JSON_UNESCAPED_UNICODE);
}
