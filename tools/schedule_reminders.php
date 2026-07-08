<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/schedule.php';

if (!app_installed()) {
    fwrite(STDERR, "Aplikasi belum diinstall.\n");
    exit(1);
}

$minutes = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--minutes=')) {
        $minutes = max(1, min(120, (int)substr($argument, strlen('--minutes='))));
    }
}

try {
    $result = schedule_send_due_reminders(null, $minutes);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, friendly_error($exception) . PHP_EOL);
    exit(1);
}
