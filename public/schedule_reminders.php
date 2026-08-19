<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/schedule.php';

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

    $expectedSecret = schedule_reminder_secret();
    $givenSecret = trim((string)(($_SERVER['HTTP_X_ERAPORT_REMINDER_SECRET'] ?? '') ?: ($_POST['secret'] ?? $_GET['secret'] ?? '')));
    if ($expectedSecret === '' || !hash_equals($expectedSecret, $givenSecret)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Secret reminder tidak valid atau belum dikonfigurasi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $now = new DateTimeImmutable('now');
    $result = schedule_send_due_reminders($now);

    $minute = (int)$now->format('i');
    $hour = (int)$now->format('H');
    $summaryResult = ['morning' => null, 'afternoon' => null];
    if ($hour === 6 && $minute >= 43 && $minute <= 47) {
        $summaryResult['morning'] = schedule_send_morning_reminders($now);
    }
    if ($hour === 14 && $minute >= 0 && $minute <= 4) {
        $summaryResult['afternoon'] = schedule_send_afternoon_reminders($now);
    }

    echo json_encode(['ok' => true] + $result + ['summary' => $summaryResult], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['ok' => false, 'message' => friendly_error($exception)], JSON_UNESCAPED_UNICODE);
}
