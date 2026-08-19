<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/schedule.php';

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
    $now = new DateTimeImmutable('now');
    $result = schedule_send_due_reminders($now, $minutes);

    $minute = (int)$now->format('i');
    $hour = (int)$now->format('H');
    $summaryResult = ['morning' => null, 'afternoon' => null];
    if ($hour === 6 && $minute >= 43 && $minute <= 47) {
        $summaryResult['morning'] = schedule_send_morning_reminders($now);
    }
    if ($hour === 14 && $minute >= 0 && $minute <= 4) {
        $summaryResult['afternoon'] = schedule_send_afternoon_reminders($now);
    }

    echo json_encode(['ok' => true] + $result + ['summary' => $summaryResult], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, friendly_error($exception) . PHP_EOL);
    exit(1);
}
