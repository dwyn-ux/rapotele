<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/pdf.php';

require_once dirname(__DIR__) . '/app/Services/whatsapp.php';
if (!app_installed()) {
    fwrite(STDERR, "Aplikasi belum diinstall.\n");
    exit(1);
}

$startDate = null;
$endDate = null;
$classId = 0;
$limit = 100;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--start=')) {
        $startDate = substr($argument, strlen('--start='));
    } elseif (str_starts_with($argument, '--end=')) {
        $endDate = substr($argument, strlen('--end='));
    } elseif (str_starts_with($argument, '--class-id=')) {
        $classId = (int)substr($argument, strlen('--class-id='));
    } elseif (str_starts_with($argument, '--limit=')) {
        $limit = max(1, min(200, (int)substr($argument, strlen('--limit='))));
    }
}

try {
    $result = whatsapp_run_weekly_cron($startDate, $endDate, $classId, $limit);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, friendly_error($exception) . PHP_EOL);
    exit(1);
}
