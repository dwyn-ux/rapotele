<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Services/schedule.php';
require_once dirname(__DIR__) . '/app/Services/fcm.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Pengingat jurnal sore: guru yang punya jadwal hari ini tapi belum mengisi jurnal.
 * Hanya dikirim ke aplikasi mobile (FCM).
 */
function fcm_send_journal_reminders_web(DateTimeImmutable $now): array
{
    $today  = $now->format('Y-m-d');
    $day    = (int)$now->format('N');
    $sent   = 0;
    $failed = 0;

    if (!schedule_tables_ready() || !table_exists('journals')) {
        return ['sent' => 0, 'failed' => 0];
    }

    $teachers = fetch_all(
        "SELECT DISTINCT t.id AS teacher_id, t.name AS teacher_name
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         WHERE ls.day_of_week = ?
           AND NOT EXISTS (
               SELECT 1 FROM journals j
               JOIN teaching_assignments ta ON ta.id = j.assignment_id
               WHERE ta.teacher_id = t.id AND j.date = ?
           )",
        [$day, $today]
    );

    foreach ($teachers as $t) {
        $ok = schedule_push_to_teacher(
            (int)$t['teacher_id'],
            '📝 Jurnal Hari Ini Belum Diisi',
            'Hai ' . $t['teacher_name'] . ', jurnal mengajar hari ini belum diisi. Yuk lengkapi!',
            '/?page=journals',
            'jurnal',
            'jurnal-' . $today . '-' . $t['teacher_id']
        );
        $ok ? $sent++ : $failed++;
    }

    return ['sent' => $sent, 'failed' => $failed];
}

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
    $result['fcm_configured'] = fcm_is_configured();

    $minute = (int)$now->format('i');
    $hour = (int)$now->format('H');
    $today = $now->format('Y-m-d');
    $summaryResult = ['morning' => null, 'afternoon' => null, 'absensi' => null, 'jurnal' => null];

    if ($hour === 6 && $minute >= 43 && $minute <= 47) {
        $summaryResult['morning'] = schedule_send_morning_reminders($now);
        if (get_app_setting('fcm_absensi_pagi_sent', '') !== $today) {
            $summaryResult['absensi'] = fcm_send_to_all_teachers(
                '📋 Absensi Kehadiran',
                'Selamat pagi! Jangan lupa isi absensi kehadiran hari ini sebelum KBM dimulai.',
                '/?page=teacher-attendance-self',
                'absensi',
                'absensi-pagi-' . $today
            );
            set_app_setting('fcm_absensi_pagi_sent', $today);
        }
    }
    if ($hour === 14 && $minute >= 0 && $minute <= 4) {
        $summaryResult['afternoon'] = schedule_send_afternoon_reminders($now);
        if (get_app_setting('fcm_jurnal_sent', '') !== $today) {
            $summaryResult['jurnal'] = fcm_send_journal_reminders_web($now);
            set_app_setting('fcm_jurnal_sent', $today);
        }
    }

    echo json_encode(['ok' => true] + $result + ['summary' => $summaryResult], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['ok' => false, 'message' => friendly_error($exception)], JSON_UNESCAPED_UNICODE);
}
