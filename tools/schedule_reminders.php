<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/schedule.php';
require_once dirname(__DIR__) . '/app/Services/webpush.php';

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

    // ── Web Push: mirror reminder Telegram ke Web Push ──
    $pushResult = ['sent' => 0, 'failed' => 0];
    if (webpush_is_configured() && table_exists('push_subscriptions')) {
        $pushedCount = webpush_send_schedule_reminders($now, $minutes);
        $pushResult  = $pushedCount;
    }
    $result['web_push'] = $pushResult;

    $minute = (int)$now->format('i');
    $hour   = (int)$now->format('H');
    $summaryResult = ['morning' => null, 'afternoon' => null];

    if ($hour === 6 && $minute >= 43 && $minute <= 47) {
        $summaryResult['morning'] = schedule_send_morning_reminders($now);
        // Web Push: notif absensi pagi ke semua guru
        if (webpush_is_configured() && table_exists('push_subscriptions')) {
            webpush_send_to_all_teachers(
                '📋 Absensi Kehadiran',
                'Selamat pagi! Jangan lupa isi absensi kehadiran hari ini sebelum KBM dimulai.',
                '/?page=teacher-attendance-self',
                'absensi',
                'absensi-pagi-' . $now->format('Y-m-d')
            );
        }
    }

    if ($hour === 14 && $minute >= 0 && $minute <= 4) {
        $summaryResult['afternoon'] = schedule_send_afternoon_reminders($now);
        // Web Push: notif pengingat jurnal sore ke guru yang mengajar hari ini
        if (webpush_is_configured() && table_exists('push_subscriptions')) {
            webpush_send_journal_reminders($now);
        }
    }

    echo json_encode(
        ['ok' => true] + $result + ['summary' => $summaryResult],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    ) . PHP_EOL;

} catch (Throwable $exception) {
    fwrite(STDERR, friendly_error($exception) . PHP_EOL);
    exit(1);
}

// ─── Web Push Helpers (hanya dipakai di script ini) ───────────────────────────

/**
 * Kirim Web Push reminder jadwal mengajar ke guru yang jadwalnya
 * akan dimulai dalam $minutesBefore menit.
 */
function webpush_send_schedule_reminders(DateTimeImmutable $now, ?int $minutesBefore = null): array
{
    $minutesBefore ??= (int)get_app_setting('schedule_reminder_minutes_before', 10);
    $day     = (int)$now->format('N');
    $nowTs   = $now->getTimestamp();
    $sent    = 0;
    $failed  = 0;

    if (!schedule_tables_ready()) {
        return ['sent' => 0, 'failed' => 0];
    }

    $schedules = fetch_all(
        "SELECT ls.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name,
                u.id AS user_id
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         JOIN users u ON u.teacher_id = ls.teacher_id AND u.active = 1
         WHERE ls.day_of_week = ? AND ls.start_time IS NOT NULL",
        [$day]
    );

    foreach ($schedules as $sch) {
        $startTs = strtotime($now->format('Y-m-d') . ' ' . $sch['start_time']);
        if ($startTs === false) continue;

        $diffMin = (int)round(($startTs - $nowTs) / 60);
        // Window: antara minutesBefore-1 dan minutesBefore+1
        if ($diffMin < ($minutesBefore - 1) || $diffMin > ($minutesBefore + 1)) {
            continue;
        }

        $endTime = $sch['end_time'] ?? date('H:i:s', $startTs + 40 * 60);
        $timeRange = substr($sch['start_time'], 0, 5) . ' – ' . substr($endTime, 0, 5);

        $userId = (int)$sch['user_id'];
        $result = webpush_send_to_user(
            $userId,
            '📚 ' . $diffMin . ' menit lagi: ' . $sch['subject_name'],
            'KBM kelas ' . $sch['class_name'] . ' • ' . $timeRange . '. Harap segera bersiap.',
            '/?page=teaching-wizard',
            'jadwal',
            'jadwal-' . $sch['id'] . '-' . $now->format('Y-m-d')
        );
        $sent   += $result['sent'];
        $failed += $result['failed'];
    }

    return ['sent' => $sent, 'failed' => $failed];
}

/**
 * Kirim Web Push pengingat pengisian jurnal ke guru yang sudah mengajar hari ini
 * tapi belum mengisi jurnal.
 */
function webpush_send_journal_reminders(DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $day   = (int)$now->format('N');
    $sent  = 0;
    $failed = 0;

    if (!schedule_tables_ready() || !table_exists('journals')) {
        return ['sent' => 0, 'failed' => 0];
    }

    // Ambil guru yang punya jadwal hari ini tapi belum ada jurnal untuk hari ini
    $teachers = fetch_all(
        "SELECT DISTINCT t.id AS teacher_id, u.id AS user_id, t.name AS teacher_name,
                GROUP_CONCAT(s.name SEPARATOR ', ') AS subjects
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN subjects s ON s.id = ls.subject_id
         JOIN users u ON u.teacher_id = t.id AND u.active = 1
         WHERE ls.day_of_week = ?
           AND NOT EXISTS (
               SELECT 1 FROM journals j
               JOIN teaching_assignments ta ON ta.id = j.assignment_id
               WHERE ta.teacher_id = t.id AND j.date = ?
           )
         GROUP BY t.id, u.id, t.name",
        [$day, $today]
    );

    foreach ($teachers as $t) {
        $userId = (int)$t['user_id'];
        $result = webpush_send_to_user(
            $userId,
            '📝 Jurnal Hari Ini Belum Diisi',
            'Hai ' . $t['teacher_name'] . ', jurnal mengajar ' . ($t['subjects'] ?? '') . ' hari ini belum diisi. Yuk lengkapi!',
            '/?page=journals',
            'jurnal',
            'jurnal-' . $today . '-' . $t['teacher_id']
        );
        $sent   += $result['sent'];
        $failed += $result['failed'];
    }

    return ['sent' => $sent, 'failed' => $failed];
}
