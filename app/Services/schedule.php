<?php

declare(strict_types=1);

function schedule_days(): array
{
    return [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];
}

function schedule_request_type_options(): array
{
    return [
        'prefer' => 'Utamakan',
        'avoid' => 'Hindari',
    ];
}

function schedule_period_options(int $max = 12): array
{
    $options = [];
    for ($period = 1; $period <= $max; $period++) {
        $options[$period] = 'Jam ke-' . $period;
    }
    return $options;
}

function schedule_period_times(): array
{
    return [
        1 => ['07:00', '07:35'],
        2 => ['07:35', '08:10'],
        3 => ['08:10', '08:45'],
        4 => ['09:00', '09:35'],
        5 => ['09:35', '10:10'],
        6 => ['10:10', '10:45'],
        7 => ['11:00', '11:35'],
        8 => ['11:35', '12:10'],
        9 => ['12:45', '13:20'],
        10 => ['13:20', '13:55'],
    ];
}

function schedule_activity_options(): array
{
    return [
        'pelajaran' => '📚 Pelajaran (KBM Normal)',
        'Upacara Bendera' => '🚩 Upacara Bendera',
        'Ekstrakurikuler' => '🏆 Ekstrakurikuler',
        'Jum\'at Sehat' => '🏃 Jum\'at Sehat',
        'Jum\'at Bersih' => '🧹 Jum\'at Bersih',
        'Sholat Dhuha / Pengajian' => '🕌 Sholat Dhuha / Pengajian',
        'Literasi / Apel Pagi' => '📖 Literasi / Apel Pagi',
        'Senam Bersama' => '🤸 Senam Bersama',
        'custom' => '✏️ Kegiatan Khusus / Lainnya...',
    ];
}

function schedule_get_period_activities(): array
{
    $raw = function_exists('get_app_setting') ? get_app_setting('schedule_period_activities', '') : '';
    if (!$raw) {
        return [];
    }
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function schedule_save_period_activities(array $activities): void
{
    if (function_exists('set_app_setting')) {
        set_app_setting('schedule_period_activities', json_encode($activities, JSON_UNESCAPED_UNICODE));
    }
}

function schedule_get_activity_for_period(int $dayOfWeek, int $periodNo): ?string
{
    $all = schedule_get_period_activities();
    $act = trim((string)($all[(string)$dayOfWeek][(string)$periodNo] ?? ''));
    if ($act === '' || strtolower($act) === 'pelajaran' || strtolower($act) === 'normal') {
        return null;
    }
    return $act;
}

function schedule_get_custom_period_times(): array
{
    $raw = function_exists('get_app_setting') ? get_app_setting('schedule_custom_period_times', '') : '';
    if (!$raw) {
        return [];
    }
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function schedule_save_custom_period_times(array $times): void
{
    if (function_exists('set_app_setting')) {
        set_app_setting('schedule_custom_period_times', json_encode($times, JSON_UNESCAPED_UNICODE));
    }
}

function schedule_is_short_day(int $dayOfWeek): bool
{
    $school = get_school_profile();
    $shortDays = trim((string)($school['short_days'] ?? ''));
    if ($shortDays === '') {
        return false;
    }
    $days = array_map('intval', array_map('trim', explode(',', $shortDays)));
    return in_array($dayOfWeek, $days, true);
}

function schedule_period_duration(int $dayOfWeek): int
{
    $school = get_school_profile();
    if (schedule_is_short_day($dayOfWeek)) {
        return (int)($school['short_period_minutes'] ?? 25);
    }
    return (int)($school['regular_period_minutes'] ?? 35);
}

function schedule_break_minutes_for_after(int $afterPeriod, ?int $dayOfWeek = null): int
{
    $school = get_school_profile();

    // 1. Pengaturan Khusus Spesifik Per-Hari (Senin, Selasa, Jumat, dll.)
    if ($dayOfWeek !== null && !empty($school['daily_breaks'])) {
        $daily = is_array($school['daily_breaks'])
            ? $school['daily_breaks']
            : json_decode((string)$school['daily_breaks'], true);

        if (is_array($daily) && !empty($daily[(string)$dayOfWeek]['enabled'])) {
            $dCfg = $daily[(string)$dayOfWeek];
            $b1After = (int)($dCfg['break1_after'] ?? 0);
            $b1Min = (int)($dCfg['break1_minutes'] ?? 0);
            $b2After = (int)($dCfg['break2_after'] ?? 0);
            $b2Min = (int)($dCfg['break2_minutes'] ?? 0);
            if ($b1After > 0 && $afterPeriod === $b1After) {
                return $b1Min;
            }
            if ($b2After > 0 && $afterPeriod === $b2After) {
                return $b2Min;
            }
            return 0;
        }
    }

    // 2. Hari Jum'at terpisah jika diaktifkan (5 = Jumat, legacy fallback)
    if ($dayOfWeek === 5 && !empty($school['friday_break_custom'])) {
        $fb1After = (int)($school['friday_break1_after'] ?? 0);
        $fb1Minutes = (int)($school['friday_break1_minutes'] ?? 0);
        $fb2After = (int)($school['friday_break2_after'] ?? 0);
        $fb2Minutes = (int)($school['friday_break2_minutes'] ?? 0);
        if ($fb1After > 0 && $afterPeriod === $fb1After) {
            return $fb1Minutes;
        }
        if ($fb2After > 0 && $afterPeriod === $fb2After) {
            return $fb2Minutes;
        }
        return 0;
    }

    // 3. Hari Khusus generic jika diaktifkan (legacy fallback)
    if (!empty($school['custom_break_enabled']) && $dayOfWeek !== null) {
        $customDays = trim((string)($school['custom_break_days'] ?? ''));
        $daysList = $customDays !== '' ? array_map('intval', array_map('trim', explode(',', $customDays))) : [];
        if (in_array($dayOfWeek, $daysList, true)) {
            $cb1After = (int)($school['custom_break1_after'] ?? 2);
            $cb1Minutes = (int)($school['custom_break1_minutes'] ?? 15);
            $cb2After = (int)($school['custom_break2_after'] ?? 5);
            $cb2Minutes = (int)($school['custom_break2_minutes'] ?? 15);
            if ($cb1After > 0 && $afterPeriod === $cb1After) {
                return $cb1Minutes;
            }
            if ($cb2After > 0 && $afterPeriod === $cb2After) {
                return $cb2Minutes;
            }
            return 0;
        }
    }

    // 4. Standar (Hari Biasa)
    $break1After = (int)($school['break1_after'] ?? 3);
    $break1Minutes = (int)($school['break1_minutes'] ?? 15);
    $break2After = (int)($school['break2_after'] ?? 6);
    $break2Minutes = (int)($school['break2_minutes'] ?? 15);
    if ($break1After > 0 && $afterPeriod === $break1After) {
        return $break1Minutes;
    }
    if ($break2After > 0 && $afterPeriod === $break2After) {
        return $break2Minutes;
    }
    return 0;
}

function schedule_day_has_custom_breaks(int $dayOfWeek): bool
{
    $school = get_school_profile();
    if (!empty($school['daily_breaks'])) {
        $daily = is_array($school['daily_breaks'])
            ? $school['daily_breaks']
            : json_decode((string)$school['daily_breaks'], true);
        if (is_array($daily) && !empty($daily[(string)$dayOfWeek]['enabled'])) {
            return true;
        }
    }
    if ($dayOfWeek === 5 && !empty($school['friday_break_custom'])) {
        return true;
    }
    if (!empty($school['custom_break_enabled'])) {
        $customDays = trim((string)($school['custom_break_days'] ?? ''));
        $daysList = $customDays !== '' ? array_map('intval', array_map('trim', explode(',', $customDays))) : [];
        if (in_array($dayOfWeek, $daysList, true)) {
            return true;
        }
    }
    return false;
}

function schedule_time_for_period(int $period, ?int $dayOfWeek = null): array
{
    $custom = schedule_get_custom_period_times();
    if ($dayOfWeek !== null && !empty($custom[(string)$dayOfWeek][(string)$period]['start']) && !empty($custom[(string)$dayOfWeek][(string)$period]['end'])) {
        return [
            substr((string)$custom[(string)$dayOfWeek][(string)$period]['start'], 0, 5),
            substr((string)$custom[(string)$dayOfWeek][(string)$period]['end'], 0, 5),
        ];
    }

    $school = get_school_profile();
    $startTime = trim((string)($school['start_time'] ?? '07:00'));
    $parts = explode(':', $startTime);
    $startHour = (int)($parts[0] ?? 7);
    $startMin = (int)($parts[1] ?? 0);
    $baseMinute = $startHour * 60 + $startMin;
    $duration = schedule_period_duration($dayOfWeek ?? 1);

    $currentMinute = $baseMinute;
    $effDay = $dayOfWeek ?? 1;
    for ($p = 1; $p < $period; $p++) {
        $currentMinute += $duration;
        $breakMinutes = schedule_break_minutes_for_after($p, $effDay);
        if ($breakMinutes > 0) {
            $currentMinute += $breakMinutes;
        }
    }

    $endMinute = $currentMinute + $duration;
    return [
        sprintf('%02d:%02d', intdiv($currentMinute, 60), $currentMinute % 60),
        sprintf('%02d:%02d', intdiv($endMinute, 60), $endMinute % 60),
    ];
}

function schedule_reminder_minutes_before(): int
{
    return schedule_clamped_int(config('schedule_reminder.minutes_before', 10), 1, 120, 10);
}

function schedule_reminder_secret(): string
{
    $secret = trim((string)config('schedule_reminder.secret', ''));
    if ($secret !== '') {
        return $secret;
    }

    return trim((string)config('telegram.webhook_secret', ''));
}

function schedule_reminder_logs_ready(): bool
{
    return table_exists('lesson_schedule_reminder_logs');
}

function schedule_require_reminder_logs(): void
{
    if (!schedule_reminder_logs_ready()) {
        run_migrations();
    }
    if (!schedule_reminder_logs_ready()) {
        throw new RuntimeException('Tabel log reminder jadwal belum tersedia. Jalankan php tools/install.php sekali.');
    }
}

function schedule_reminder_url(): string
{
    $secret = schedule_reminder_secret();
    $params = $secret !== '' ? ['secret' => $secret] : [];
    $query = $params ? '?' . http_build_query($params) : '';

    return app_url('schedule_reminders.php') . $query;
}

function schedule_reminder_message(array $row, int $remainingMinutes): string
{
    $start = substr((string)$row['start_time'], 0, 5);
    $end = substr((string)$row['end_time'], 0, 5);
    $remaining = $remainingMinutes > 0 ? $remainingMinutes . ' menit lagi' : 'sedang berlangsung';
    $dayName = schedule_days()[(int)$row['day_of_week']] ?? '-';
    $period = (int)$row['period_no'];
    $isNow = $remainingMinutes <= 0;

    $statusIcon = $isNow ? '🔴' : '🟡';
    $statusText = $isNow ? 'Sedang Berlangsung' : 'Akan Dimulai';

    $lines = [
        $statusIcon . ' <b>' . $statusText . '</b>',
        '',
        'Assalamu\'alaikum, <b>' . e($row['teacher_name']) . '</b> 🙏',
        '',
        '📚 ' . e($row['subject_name']),
        '👥 Kelas ' . e($row['class_name']),
        '📅 ' . e($dayName) . ', Jam ke-' . $period,
        '⏰ ' . e($start) . ' - ' . e($end),
        '',
        '⏳ ' . $remaining,
    ];

    $url = telegram_web_login_url('lesson-schedule');
    if ($url !== '') {
        $lines[] = '';
        $lines[] = '📋 <a href="' . e($url) . '">Buka Jadwal Pelajaran</a>';
    }

    return implode("\n", $lines);
}

function schedule_due_reminder_rows(DateTimeImmutable $now): array
{
    $day = (int)$now->format('N');
    if (!array_key_exists($day, schedule_days())) {
        return [];
    }

    return fetch_all(
        "SELECT ls.*, t.name AS teacher_name,
                COALESCE(NULLIF(t.telegram_chat_id, ''), (
                    SELECT NULLIF(u.telegram_chat_id, '')
                    FROM users u
                    WHERE u.teacher_id = t.id
                      AND u.active = 1
                      AND u.telegram_chat_id IS NOT NULL
                      AND u.telegram_chat_id <> ''
                    ORDER BY u.id
                    LIMIT 1
                )) AS teacher_chat_id,
                c.name AS class_name,
                s.name AS subject_name
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         WHERE ls.day_of_week = ?
           AND ls.start_time IS NOT NULL
         ORDER BY ls.start_time, c.grade, c.name, s.name",
        [$day]
    );
}

function schedule_reminder_already_sent(int $scheduleId, string $date, string $startTime, int $minutesBefore): bool
{
    return (bool)fetch_one(
        'SELECT id FROM lesson_schedule_reminder_logs
         WHERE schedule_id = ? AND reminder_date = ? AND schedule_start_time = ? AND reminder_minutes = ?',
        [$scheduleId, $date, $startTime, $minutesBefore]
    );
}

function schedule_log_reminder(array $row, string $date, string $startTime, int $minutesBefore, string $message): void
{
    execute_sql(
        'INSERT INTO lesson_schedule_reminder_logs
            (schedule_id, teacher_id, telegram_chat_id, reminder_date, schedule_start_time, reminder_minutes, message, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int)$row['id'],
            (int)$row['teacher_id'],
            (string)$row['teacher_chat_id'],
            $date,
            $startTime,
            $minutesBefore,
            $message,
            now_string(),
        ]
    );
}

function schedule_send_due_reminders(?DateTimeImmutable $now = null, ?int $minutesBefore = null): array
{
    schedule_require_reminder_logs();

    $now ??= new DateTimeImmutable('now');
    $minutesBefore ??= schedule_reminder_minutes_before();
    $date = $now->format('Y-m-d');
    $hasToken = trim((string)config('telegram.bot_token', '')) !== '';
    $result = [
        'checked' => 0,
        'due' => 0,
        'sent' => 0,
        'skipped_duplicate' => 0,
        'skipped_no_chat' => 0,
        'skipped_no_token' => 0,
        'minutes_before' => $minutesBefore,
        'now' => $now->format('Y-m-d H:i:s'),
    ];

    foreach (schedule_due_reminder_rows($now) as $row) {
        $result['checked']++;
        $startTime = substr((string)$row['start_time'], 0, 5);
        if ($startTime === '') {
            continue;
        }

        $startsAt = new DateTimeImmutable($date . ' ' . $startTime);
        $diffSeconds = $startsAt->getTimestamp() - $now->getTimestamp();
        if ($diffSeconds < 0 || $diffSeconds > ($minutesBefore * 60)) {
            continue;
        }

        $result['due']++;
        if (schedule_reminder_already_sent((int)$row['id'], $date, $startTime, $minutesBefore)) {
            $result['skipped_duplicate']++;
            continue;
        }

        $remainingMinutes = (int)ceil($diffSeconds / 60);
        $message = schedule_reminder_message($row, max(0, $remainingMinutes));
        $sentAny = false;

        // 1. Kirim via Telegram jika terhubung
        if ($hasToken && trim((string)($row['teacher_chat_id'] ?? '')) !== '') {
            try {
                telegram_send_message((string)$row['teacher_chat_id'], $message);
                $sentAny = true;
            } catch (Throwable $e) {
                log_exception($e);
            }
        }

        // 2. Kirim via Web Push ke HP / Browser Guru (PWA status bar)
        require_once dirname(__DIR__) . '/Services/webpush.php';
        if (function_exists('webpush_send_to_teacher')) {
            $pushTitle = '⏰ Pengingat Mengajar (' . max(1, $remainingMinutes) . ' Menit Lagi)';
            $pushBody  = ($row['subject_name'] ?? 'Mata Pelajaran') . ' di kelas ' . ($row['class_name'] ?? '') . ' mulai pukul ' . substr($startTime, 0, 5) . '.';
            $pushRes   = webpush_send_to_teacher((int)$row['teacher_id'], $pushTitle, $pushBody, '/?page=jadwal', 'jadwal', 'jadwal-' . $row['id']);
            if (!empty($pushRes['sent'])) {
                $sentAny = true;
            }
        }

        if ($sentAny) {
            schedule_log_reminder($row, $date, $startTime, $minutesBefore, $message);
            $result['sent']++;
        } else {
            if (!$hasToken && trim((string)($row['teacher_chat_id'] ?? '')) === '') {
                $result['skipped_no_chat']++;
            }
        }
    }

    return $result;
}

function schedule_daily_summary_already_sent(int $teacherId, string $type, string $date): bool
{
    if (!table_exists('daily_summary_reminder_logs')) {
        return false;
    }
    return (bool)fetch_one(
        'SELECT id FROM daily_summary_reminder_logs WHERE teacher_id = ? AND reminder_type = ? AND reminder_date = ?',
        [$teacherId, $type, $date]
    );
}

function schedule_log_daily_summary(int $teacherId, string $type, string $date, string $message): void
{
    execute_sql(
        'INSERT INTO daily_summary_reminder_logs (teacher_id, reminder_type, reminder_date, message, sent_at) VALUES (?, ?, ?, ?, ?)',
        [$teacherId, $type, $date, $message, now_string()]
    );
}

function schedule_fetch_teacher_schedules(int $teacherId, string $date): array
{
    $day = (int)(new DateTimeImmutable($date))->format('N');
    return fetch_all(
        "SELECT ls.*, c.name AS class_name, s.name AS subject_name
         FROM lesson_schedules ls
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         WHERE ls.teacher_id = ? AND ls.day_of_week = ?
         ORDER BY ls.start_time, ls.period_no",
        [$teacherId, $day]
    );
}

function schedule_send_morning_reminders(?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $date = $now->format('Y-m-d');
    $hasToken = trim((string)config('telegram.bot_token', '')) !== '';

    $result = ['checked' => 0, 'sent' => 0, 'skipped_duplicate' => 0, 'skipped_no_chat' => 0, 'skipped_no_token' => 0, 'skipped_empty' => 0];

    $teachers = fetch_all(
        "SELECT DISTINCT t.id, t.name,
                COALESCE(NULLIF(t.telegram_chat_id, ''), (
                    SELECT NULLIF(u.telegram_chat_id, '')
                    FROM users u WHERE u.teacher_id = t.id AND u.active = 1 AND u.telegram_chat_id IS NOT NULL AND u.telegram_chat_id <> ''
                    ORDER BY u.id LIMIT 1
                )) AS chat_id
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         WHERE ls.day_of_week = ?
         ORDER BY t.name",
        [(int)$now->format('N')]
    );

    foreach ($teachers as $teacher) {
        $result['checked']++;
        if (schedule_daily_summary_already_sent((int)$teacher['id'], 'morning', $date)) {
            $result['skipped_duplicate']++;
            continue;
        }

        $schedules = schedule_fetch_teacher_schedules((int)$teacher['id'], $date);
        if (!$schedules) {
            $result['skipped_empty']++;
            continue;
        }

        $dayName = schedule_days()[(int)$now->format('N')] ?? '-';
        $lines = [
            'Selamat pagi, <b>' . e($teacher['name']) . '</b> ☀️',
            '',
            'Berikut jadwal mengajar hari <b>' . e($dayName) . '</b>, ' . format_indonesian_date($date) . ':',
            '',
        ];
        foreach ($schedules as $i => $s) {
            $start = substr((string)$s['start_time'], 0, 5);
            $end = substr((string)$s['end_time'], 0, 5);
            $time = ($start && $end) ? $start . ' - ' . $end : 'Jam ke-' . (int)$s['period_no'];
            $lines[] = '📚 <b>' . e($s['subject_name']) . '</b>';
            $lines[] = '   👥 Kelas ' . e($s['class_name']) . ' · ⏰ ' . $time;
            $lines[] = '';
        }
        $lines[] = 'Selamat bekerja! Semoga lancar 🙏';

        $message = implode("\n", $lines);
        $sentAny = false;

        if ($hasToken && trim((string)($teacher['chat_id'] ?? '')) !== '') {
            try {
                telegram_send_message((string)$teacher['chat_id'], $message);
                $sentAny = true;
            } catch (Throwable $e) {
                log_exception($e);
            }
        }

        require_once dirname(__DIR__) . '/Services/webpush.php';
        if (function_exists('webpush_send_to_teacher')) {
            $pushTitle = '☀️ Jadwal Mengajar Hari Ini (' . count($schedules) . ' Kelas)';
            $firstSubj = $schedules[0]['subject_name'] ?? 'Pelajaran';
            $firstTime = substr((string)($schedules[0]['start_time'] ?? ''), 0, 5);
            $pushBody  = 'Hari ini Anda mengajar ' . count($schedules) . ' kelas, dimulai ' . $firstSubj . ($firstTime ? ' pukul ' . $firstTime : '') . '.';
            $pushRes   = webpush_send_to_teacher((int)$teacher['id'], $pushTitle, $pushBody, '/?page=jadwal', 'jadwal', 'morning-' . $date);
            if (!empty($pushRes['sent'])) {
                $sentAny = true;
            }
        }

        if ($sentAny) {
            schedule_log_daily_summary((int)$teacher['id'], 'morning', $date, $message);
            $result['sent']++;
        } else {
            $result['skipped_no_chat']++;
        }
    }

    return $result;
}

function schedule_send_afternoon_reminders(?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $date = $now->format('Y-m-d');
    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $tomorrowDay = (int)$now->modify('+1 day')->format('N');
    $hasToken = trim((string)config('telegram.bot_token', '')) !== '';

    $result = ['checked' => 0, 'sent' => 0, 'skipped_duplicate' => 0, 'skipped_no_chat' => 0, 'skipped_no_token' => 0, 'skipped_empty' => 0];

    $teachers = fetch_all(
        "SELECT DISTINCT t.id, t.name,
                COALESCE(NULLIF(t.telegram_chat_id, ''), (
                    SELECT NULLIF(u.telegram_chat_id, '')
                    FROM users u WHERE u.teacher_id = t.id AND u.active = 1 AND u.telegram_chat_id IS NOT NULL AND u.telegram_chat_id <> ''
                    ORDER BY u.id LIMIT 1
                )) AS chat_id
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         WHERE ls.day_of_week = ?
         ORDER BY t.name",
        [$tomorrowDay]
    );

    $tomorrowDayName = schedule_days()[$tomorrowDay] ?? '-';

    foreach ($teachers as $teacher) {
        $result['checked']++;
        if (schedule_daily_summary_already_sent((int)$teacher['id'], 'afternoon', $date)) {
            $result['skipped_duplicate']++;
            continue;
        }

        $schedules = schedule_fetch_teacher_schedules((int)$teacher['id'], $tomorrow);
        if (!$schedules) {
            $result['skipped_empty']++;
            continue;
        }

        $lines = [
            'Halo, <b>' . e($teacher['name']) . '</b> 👋',
            '',
            'Jadwal mengajar besok, <b>' . e($tomorrowDayName) . '</b>, ' . format_indonesian_date($tomorrow) . ':',
            '',
        ];
        foreach ($schedules as $s) {
            $start = substr((string)$s['start_time'], 0, 5);
            $end = substr((string)$s['end_time'], 0, 5);
            $time = ($start && $end) ? $start . ' - ' . $end : 'Jam ke-' . (int)$s['period_no'];
            $lines[] = '📚 <b>' . e($s['subject_name']) . '</b>';
            $lines[] = '   👥 Kelas ' . e($s['class_name']) . ' · ⏰ ' . $time;
            $lines[] = '';
        }
        $lines[] = '📋 Mohon siapkan materi dan perangkat pembelajaran besok.';
        $lines[] = 'Semangat mengajar! 💪';

        $message = implode("\n", $lines);
        $sentAny = false;

        if ($hasToken && trim((string)($teacher['chat_id'] ?? '')) !== '') {
            try {
                telegram_send_message((string)$teacher['chat_id'], $message);
                $sentAny = true;
            } catch (Throwable $e) {
                log_exception($e);
            }
        }

        require_once dirname(__DIR__) . '/Services/webpush.php';
        if (function_exists('webpush_send_to_teacher')) {
            $pushTitle = '📋 Jadwal Mengajar Besok (' . count($schedules) . ' Kelas)';
            $firstSubj = $schedules[0]['subject_name'] ?? 'Pelajaran';
            $firstTime = substr((string)($schedules[0]['start_time'] ?? ''), 0, 5);
            $pushBody  = 'Besok Anda mengajar ' . count($schedules) . ' kelas, dimulai ' . $firstSubj . ($firstTime ? ' pukul ' . $firstTime : '') . '.';
            $pushRes   = webpush_send_to_teacher((int)$teacher['id'], $pushTitle, $pushBody, '/?page=jadwal', 'jadwal', 'afternoon-' . $date);
            if (!empty($pushRes['sent'])) {
                $sentAny = true;
            }
        }

        if ($sentAny) {
            schedule_log_daily_summary((int)$teacher['id'], 'afternoon', $date, $message);
            $result['sent']++;
        } else {
            $result['skipped_no_chat']++;
        }
    }

    return $result;
}

function schedule_tables_ready(): bool
{
    return table_exists('teacher_schedule_requests') && table_exists('lesson_schedules');
}

function schedule_require_tables(): void
{
    if (!schedule_tables_ready()) {
        throw new RuntimeException('Tabel jadwal belum tersedia. Jalankan php tools/install.php untuk membuat migrasi terbaru.');
    }
    if (function_exists('migrate_lesson_schedule_schema')) {
        try {
            migrate_lesson_schedule_schema();
        } catch (Throwable) {}
    }
    if (function_exists('migrate_classes_display_order')) {
        try {
            migrate_classes_display_order();
        } catch (Throwable) {}
    }
}

function schedule_clamped_int(mixed $value, int $min, int $max, int $default): int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) {
        $int = $default;
    }
    return max($min, min($max, (int)$int));
}

function schedule_normalize_day(mixed $value): int
{
    $day = filter_var($value, FILTER_VALIDATE_INT);
    if ($day === false || !array_key_exists((int)$day, schedule_days())) {
        throw new RuntimeException('Hari jadwal tidak valid.');
    }
    return (int)$day;
}

function schedule_normalize_time(mixed $value): ?string
{
    $time = trim((string)$value);
    if ($time === '') {
        return null;
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
        throw new RuntimeException('Format jam harus HH:MM.');
    }
    return substr($time, 0, 5);
}

function schedule_current_teacher_id(): int
{
    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        throw new RuntimeException('Akun guru belum terhubung dengan data guru.');
    }
    return $teacherId;
}

function schedule_assignment_by_id(int $assignmentId): array
{
    $assignment = fetch_one(
        'SELECT ta.*, t.name AS teacher_name, c.name AS class_name, c.grade, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.id = ? AND ta.active = 1',
        [$assignmentId]
    );
    if (!$assignment) {
        throw new RuntimeException('Pembelajaran tidak ditemukan atau sudah nonaktif.');
    }
    return $assignment;
}

function schedule_assignment_options(): array
{
    $assignments = fetch_all(
        'SELECT ta.id, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.active = 1
         ORDER BY c.grade, c.name, s.name, t.name'
    );

    $options = [];
    foreach ($assignments as $assignment) {
        $options[(string)$assignment['id']] = $assignment['class_name'] . ' - ' . $assignment['subject_name'] . ' - ' . $assignment['teacher_name'];
    }
    return $options;
}

function schedule_teacher_options(): array
{
    return array_column_map(fetch_all('SELECT id, name FROM teachers WHERE active = 1 ORDER BY name'), 'id', 'name');
}

function schedule_class_options(): array
{
    $orderClause = function_exists('class_order_by_clause') ? class_order_by_clause() : 'display_order ASC, grade, name';
    return array_column_map(fetch_all('SELECT id, name FROM classes WHERE active = 1 ORDER BY ' . $orderClause), 'id', 'name');
}

function schedule_rows(array $filters = []): array
{
    $where = [];
    $params = [];

    if (!empty($filters['class_id'])) {
        $where[] = 'ls.class_id = ?';
        $params[] = (int)$filters['class_id'];
    }
    if (!empty($filters['teacher_id'])) {
        $where[] = 'ls.teacher_id = ?';
        $params[] = (int)$filters['teacher_id'];
    }

    $sql = 'SELECT ls.*, t.name AS teacher_name, c.name AS class_name, c.grade, s.name AS subject_name
            FROM lesson_schedules ls
            JOIN teachers t ON t.id = ls.teacher_id
            JOIN classes c ON c.id = ls.class_id
            JOIN subjects s ON s.id = ls.subject_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ls.day_of_week, ls.period_no, c.grade, c.name, s.name, t.name';

    return fetch_all($sql, $params);
}

function schedule_request_rows(?int $teacherId = null): array
{
    $params = [];
    $where = '';
    if ($teacherId !== null) {
        $where = ' WHERE r.teacher_id = ?';
        $params[] = $teacherId;
    }

    return fetch_all(
        "SELECT r.*, t.name AS teacher_name
         FROM teacher_schedule_requests r
         JOIN teachers t ON t.id = r.teacher_id
         $where
         ORDER BY r.active DESC, t.name, r.day_of_week, r.start_period",
        $params
    );
}

function schedule_request_by_id(int $id): array
{
    $request = fetch_one(
        'SELECT r.*, t.name AS teacher_name
         FROM teacher_schedule_requests r
         JOIN teachers t ON t.id = r.teacher_id
         WHERE r.id = ?',
        [$id]
    );
    if (!$request) {
        throw new RuntimeException('Request jadwal tidak ditemukan.');
    }
    if (!is_admin() && (int)$request['teacher_id'] !== schedule_current_teacher_id()) {
        throw new RuntimeException('Request jadwal ini bukan milik akun Anda.');
    }
    return $request;
}

function schedule_active_requests_by_teacher(): array
{
    $rows = fetch_all('SELECT * FROM teacher_schedule_requests WHERE active = 1 ORDER BY teacher_id, day_of_week, start_period');
    $requests = [];
    foreach ($rows as $row) {
        $requests[(int)$row['teacher_id']][] = $row;
    }
    return $requests;
}

function schedule_request_matches(array $requests, int $teacherId, int $day, int $period, string $type): bool
{
    foreach ($requests[$teacherId] ?? [] as $request) {
        if ((string)$request['request_type'] !== $type) {
            continue;
        }
        if ((int)$request['day_of_week'] !== $day) {
            continue;
        }
        if ($period >= (int)$request['start_period'] && $period <= (int)$request['end_period']) {
            return true;
        }
    }
    return false;
}

function schedule_mark_slot(
    array &$occupiedClass,
    array &$occupiedTeacher,
    array &$classDayLoad,
    array &$teacherDayLoad,
    array &$assignmentDays,
    array $row
): void {
    $classId = (int)$row['class_id'];
    $teacherId = (int)$row['teacher_id'];
    $assignmentId = (int)$row['assignment_id'];
    $day = (int)$row['day_of_week'];
    $period = (int)$row['period_no'];

    $occupiedClass[$classId][$day][$period] = true;
    $occupiedTeacher[$teacherId][$day][$period] = true;
    $classDayLoad[$classId][$day] = ($classDayLoad[$classId][$day] ?? 0) + 1;
    $teacherDayLoad[$teacherId][$day] = ($teacherDayLoad[$teacherId][$day] ?? 0) + 1;
    $assignmentDays[$assignmentId][$day] = true;
}

function schedule_pick_slot(
    array $assignment,
    array $days,
    int $maxPeriod,
    array $requests,
    array $occupiedClass,
    array $occupiedTeacher,
    array $classDayLoad,
    array $teacherDayLoad,
    array $assignmentDays
): ?array {
    $classId = (int)$assignment['class_id'];
    $teacherId = (int)$assignment['teacher_id'];
    $assignmentId = (int)$assignment['id'];
    $candidates = [];

    foreach ($days as $day) {
        for ($period = 1; $period <= $maxPeriod; $period++) {
            if (!empty($occupiedClass[$classId][$day][$period]) || !empty($occupiedTeacher[$teacherId][$day][$period])) {
                continue;
            }

            $avoid = schedule_request_matches($requests, $teacherId, $day, $period, 'avoid');
            $prefer = schedule_request_matches($requests, $teacherId, $day, $period, 'prefer');
            $score = (($classDayLoad[$classId][$day] ?? 0) * 6)
                + (($teacherDayLoad[$teacherId][$day] ?? 0) * 4)
                + ($period * 0.2)
                + ($day * 0.01);

            if (!empty($assignmentDays[$assignmentId][$day])) {
                $score += 18;
            }
            if ($avoid) {
                $score += 10000;
            }
            if ($prefer) {
                $score -= 50;
            }

            $candidates[] = [
                'day' => $day,
                'period' => $period,
                'score' => $score,
                'avoid' => $avoid,
            ];
        }
    }

    if (!$candidates) {
        return null;
    }

    usort($candidates, static function (array $left, array $right): int {
        $score = $left['score'] <=> $right['score'];
        if ($score !== 0) {
            return $score;
        }
        $day = $left['day'] <=> $right['day'];
        return $day !== 0 ? $day : ($left['period'] <=> $right['period']);
    });

    return $candidates[0];
}

function action_generate_lesson_schedule(): void
{
    require_role(['admin']);
    schedule_require_tables();

    $postedDays = (array)($_POST['days'] ?? []);
    $days = [];
    foreach ($postedDays as $day) {
        $normalized = schedule_normalize_day($day);
        $days[$normalized] = $normalized;
    }
    if (!$days) {
        $days = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
    }
    ksort($days);

    $maxPeriod = schedule_clamped_int($_POST['max_period'] ?? 8, 1, 12, 8);
    $periodsPerAssignment = schedule_clamped_int($_POST['periods_per_assignment'] ?? 1, 1, 4, 1);
    $keepLocked = isset($_POST['keep_locked']);

    $generated = 0;
    $failed = [];
    $pdo = db();

    try {
        $pdo->beginTransaction();
        execute_sql($keepLocked ? 'DELETE FROM lesson_schedules WHERE locked = 0' : 'DELETE FROM lesson_schedules');

        $occupiedClass = [];
        $occupiedTeacher = [];
        $classDayLoad = [];
        $teacherDayLoad = [];
        $assignmentDays = [];

        foreach (fetch_all('SELECT * FROM lesson_schedules') as $row) {
            schedule_mark_slot($occupiedClass, $occupiedTeacher, $classDayLoad, $teacherDayLoad, $assignmentDays, $row);
        }

        $assignments = fetch_all(
            'SELECT ta.*, t.name AS teacher_name, c.name AS class_name, c.grade, s.name AS subject_name
             FROM teaching_assignments ta
             JOIN teachers t ON t.id = ta.teacher_id
             JOIN classes c ON c.id = ta.class_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.active = 1
             ORDER BY c.grade, c.name, s.name, t.name'
        );
        $requests = schedule_active_requests_by_teacher();

        foreach ($assignments as $assignment) {
            for ($repeat = 1; $repeat <= $periodsPerAssignment; $repeat++) {
                $slot = schedule_pick_slot(
                    $assignment,
                    array_values($days),
                    $maxPeriod,
                    $requests,
                    $occupiedClass,
                    $occupiedTeacher,
                    $classDayLoad,
                    $teacherDayLoad,
                    $assignmentDays
                );

                if ($slot === null) {
                    $failed[] = $assignment['class_name'] . ' - ' . $assignment['subject_name'];
                    continue;
                }

                [$startTime, $endTime] = schedule_time_for_period((int)$slot['period']);
                $note = $slot['avoid'] ? 'Auto generate; memakai slot yang diminta dihindari karena jadwal penuh.' : 'Auto generate.';

                $newRow = [
                    'assignment_id' => (int)$assignment['id'],
                    'teacher_id' => (int)$assignment['teacher_id'],
                    'class_id' => (int)$assignment['class_id'],
                    'subject_id' => (int)$assignment['subject_id'],
                    'day_of_week' => (int)$slot['day'],
                    'period_no' => (int)$slot['period'],
                ];

                execute_sql(
                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, note, generated_by, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                    [
                        $newRow['assignment_id'],
                        $newRow['teacher_id'],
                        $newRow['class_id'],
                        $newRow['subject_id'],
                        $newRow['day_of_week'],
                        $newRow['period_no'],
                        $startTime,
                        $endTime,
                        $note,
                        (int)current_user()['id'],
                        now_string(),
                    ]
                );
                schedule_mark_slot($occupiedClass, $occupiedTeacher, $classDayLoad, $teacherDayLoad, $assignmentDays, $newRow);
                $generated++;
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $message = 'Jadwal otomatis dibuat: ' . $generated . ' slot.';
    if ($failed) {
        $message .= ' Belum mendapat slot: ' . count($failed) . ' pembelajaran.';
    }
    flash($failed ? 'warning' : 'success', $message);
    redirect_to('lesson-schedule');
}

function action_save_lesson_schedule(): void
{
    require_role(['admin', 'guru']);
    schedule_require_tables();

    $id = (int)($_POST['id'] ?? 0);
    $assignment = schedule_assignment_by_id((int)($_POST['assignment_id'] ?? 0));

    if (user_role() === 'guru') {
        $teacherId = schedule_current_teacher_id();
        if ((int)$assignment['teacher_id'] !== $teacherId) {
            throw new RuntimeException('Anda hanya dapat mengatur jadwal untuk penugasan mengajar Anda sendiri.');
        }
        if ($id > 0) {
            $existingSched = fetch_one('SELECT teacher_id, locked FROM lesson_schedules WHERE id = ?', [$id]);
            if (!$existingSched || (int)$existingSched['teacher_id'] !== $teacherId) {
                throw new RuntimeException('Anda tidak memiliki akses ke jadwal ini.');
            }
            if ((int)$existingSched['locked'] === 1) {
                throw new RuntimeException('Jadwal ini dikunci oleh admin.');
            }
        }
    }

    $day = schedule_normalize_day($_POST['day_of_week'] ?? 1);
    $period = schedule_clamped_int($_POST['period_no'] ?? 1, 1, 12, 1);
    [$defaultStart, $defaultEnd] = schedule_time_for_period($period, $day);
    $startTime = schedule_normalize_time($_POST['start_time'] ?? '') ?? $defaultStart;
    $endTime = schedule_normalize_time($_POST['end_time'] ?? '') ?? $defaultEnd;
    if ($endTime <= $startTime) {
        throw new RuntimeException('Jam selesai harus lebih besar dari jam mulai.');
    }
    $locked = (is_admin() && isset($_POST['locked'])) ? 1 : 0;
    $note = trim((string)($_POST['note'] ?? ''));

    if ($id > 0 && !fetch_one('SELECT id FROM lesson_schedules WHERE id = ?', [$id])) {
        throw new RuntimeException('Jadwal tidak ditemukan.');
    }

    $conflictClass = fetch_one(
        'SELECT id FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND id <> ?',
        [(int)$assignment['class_id'], $day, $period, $id]
    );
    if ($conflictClass) {
        throw new RuntimeException('Kelas sudah memiliki jadwal pada hari dan jam tersebut.');
    }

    $conflictTeacher = fetch_one(
        'SELECT ls.id, ls.class_id, ls.subject_id, c.physical_group
         FROM lesson_schedules ls
         JOIN classes c ON c.id = ls.class_id
         WHERE ls.teacher_id = ? AND ls.day_of_week = ? AND ls.period_no = ? AND ls.id <> ?',
        [(int)$assignment['teacher_id'], $day, $period, $id]
    );
    if ($conflictTeacher) {
        $curClass = fetch_one('SELECT physical_group FROM classes WHERE id = ?', [(int)$assignment['class_id']]);
        $isPhysicalGroupMatch = !empty($curClass['physical_group'])
            && !empty($conflictTeacher['physical_group'])
            && strcasecmp(trim((string)$curClass['physical_group']), trim((string)$conflictTeacher['physical_group'])) === 0
            && (int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id'];
        $isSameSubjectJoint = ((int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id']);
        if (!$isPhysicalGroupMatch && !$isSameSubjectJoint) {
            throw new RuntimeException('Guru sudah memiliki jadwal pada hari dan jam tersebut.');
        }
    }

    $params = [
        (int)$assignment['id'],
        (int)$assignment['teacher_id'],
        (int)$assignment['class_id'],
        (int)$assignment['subject_id'],
        $day,
        $period,
        $startTime,
        $endTime,
        $locked,
        $note,
        (int)current_user()['id'],
        now_string(),
    ];

    if ($id > 0) {
        execute_sql(
            'UPDATE lesson_schedules
             SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, day_of_week = ?, period_no = ?, start_time = ?, end_time = ?, locked = ?, note = ?, generated_by = ?, updated_at = ?
             WHERE id = ?',
            array_merge($params, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, note, generated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
    }

    // Auto-sync sibling assignments in physical group (Option 1)
    if (function_exists('sibling_assignments_for_assignment')) {
        $siblingAssignments = sibling_assignments_for_assignment($assignment);
        foreach ($siblingAssignments as $sib) {
            $sibClassId = (int)$sib['class_id'];
            $sibExisting = fetch_one(
                'SELECT id, locked FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ?',
                [$sibClassId, $day, $period]
            );
            if ($sibExisting) {
                if ((int)$sibExisting['locked'] !== 1) {
                    execute_sql(
                        'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, locked = ?, note = ?, generated_by = ?, updated_at = ? WHERE id = ?',
                        [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $startTime, $endTime, $locked, $note, (int)current_user()['id'], now_string(), (int)$sibExisting['id']]
                    );
                }
            } else {
                execute_sql(
                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, note, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $day, $period, $startTime, $endTime, $locked, $note, (int)current_user()['id'], now_string(), now_string()]
                );
            }
        }
    }

    flash('success', 'Jadwal pelajaran tersimpan.');
    redirect_to('lesson-schedule');
}

function action_delete_lesson_schedule(): void
{
    require_role(['admin', 'guru']);
    schedule_require_tables();

    $id = (int)($_POST['id'] ?? 0);
    $sched = fetch_one('SELECT * FROM lesson_schedules WHERE id = ?', [$id]);
    if ($sched) {
        if (user_role() === 'guru') {
            $teacherId = schedule_current_teacher_id();
            if ((int)$sched['teacher_id'] !== $teacherId) {
                flash('danger', 'Anda tidak memiliki hak akses untuk menghapus jadwal guru lain.');
                redirect_to('lesson-schedule');
            }
            if ((int)$sched['locked'] === 1) {
                flash('danger', 'Jadwal ini terkunci oleh admin dan tidak dapat dihapus.');
                redirect_to('lesson-schedule');
            }
        }
        $assignment = schedule_assignment_by_id((int)$sched['assignment_id']);
        if ($assignment && function_exists('sibling_assignments_for_assignment')) {
            $siblings = sibling_assignments_for_assignment($assignment);
            foreach ($siblings as $sib) {
                execute_sql(
                    'DELETE FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND locked = 0',
                    [(int)$sib['class_id'], (int)$sched['day_of_week'], (int)$sched['period_no']]
                );
            }
        }
        execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [$id]);
    }
    flash('success', 'Jadwal pelajaran dihapus.');
    redirect_to('lesson-schedule');
}

function action_save_schedule_request(): void
{
    require_role(['admin', 'guru']);
    schedule_require_tables();

    $id = (int)($_POST['id'] ?? 0);
    $teacherId = is_admin() ? (int)($_POST['teacher_id'] ?? 0) : schedule_current_teacher_id();
    if ($teacherId <= 0 || !fetch_one('SELECT id FROM teachers WHERE id = ? AND active = 1', [$teacherId])) {
        throw new RuntimeException('Guru tidak valid.');
    }
    if ($id > 0) {
        schedule_request_by_id($id);
    }

    $type = (string)($_POST['request_type'] ?? 'prefer');
    if (!array_key_exists($type, schedule_request_type_options())) {
        throw new RuntimeException('Tipe request jadwal tidak valid.');
    }

    $day = schedule_normalize_day($_POST['day_of_week'] ?? 1);
    $startPeriod = schedule_clamped_int($_POST['start_period'] ?? 1, 1, 12, 1);
    $endPeriod = schedule_clamped_int($_POST['end_period'] ?? $startPeriod, 1, 12, $startPeriod);
    if ($endPeriod < $startPeriod) {
        throw new RuntimeException('Jam akhir request harus lebih besar atau sama dengan jam awal.');
    }

    $note = trim((string)($_POST['note'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $params = [$teacherId, $type, $day, $startPeriod, $endPeriod, $note, $active, (int)current_user()['id'], now_string()];

    if ($id > 0) {
        execute_sql(
            'UPDATE teacher_schedule_requests
             SET teacher_id = ?, request_type = ?, day_of_week = ?, start_period = ?, end_period = ?, note = ?, active = ?, created_by = ?, updated_at = ?
             WHERE id = ?',
            array_merge($params, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO teacher_schedule_requests (teacher_id, request_type, day_of_week, start_period, end_period, note, active, created_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
    }

    flash('success', 'Request jadwal guru tersimpan.');
    redirect_to('lesson-schedule');
}

function action_delete_schedule_request(): void
{
    require_role(['admin', 'guru']);
    schedule_require_tables();

    $id = (int)($_POST['id'] ?? 0);
    schedule_request_by_id($id);
    execute_sql('DELETE FROM teacher_schedule_requests WHERE id = ?', [$id]);
    flash('success', 'Request jadwal dihapus.');
    redirect_to('lesson-schedule');
}

function page_lesson_schedule(): void
{
    require_role(['admin', 'guru', 'siswa']);
    render_header('Jadwal Pelajaran');

    if (!schedule_tables_ready()) {
        echo '<section class="panel"><h3>Tabel jadwal belum tersedia.</h3><p>Jalankan <code>php tools/install.php</code> sekali untuk membuat migrasi terbaru.</p></section>';
        render_footer();
        return;
    }

    if (is_admin()) {
        if (!empty($_GET['view']) && $_GET['view'] === 'teacher_matrix' && (int)($_GET['teacher_id'] ?? 0) > 0) {
            render_lesson_schedule_teacher_page((int)$_GET['teacher_id']);
        } else {
            render_lesson_schedule_admin_page();
        }
    } elseif (user_role() === 'guru') {
        render_lesson_schedule_teacher_page();
    } else {
        render_lesson_schedule_student_page();
    }

    render_footer();
}

function render_lesson_schedule_admin_page(): void
{
    $filterClassId = (int)($_GET['class_id'] ?? 0);
    $filterTeacherId = (int)($_GET['teacher_id'] ?? 0);
    $editSchedule = [];
    if ((int)($_GET['edit_schedule'] ?? 0) > 0) {
        $editSchedule = fetch_one('SELECT * FROM lesson_schedules WHERE id = ?', [(int)$_GET['edit_schedule']]) ?: [];
    }
    $editRequest = [];
    if ((int)($_GET['edit_request'] ?? 0) > 0) {
        $editRequest = schedule_request_by_id((int)$_GET['edit_request']);
    }

    $rows = schedule_rows([
        'class_id' => $filterClassId,
        'teacher_id' => $filterTeacherId,
    ]);
    $requests = schedule_request_rows();
    $assignmentTotal = (int)(fetch_one('SELECT COUNT(*) AS total FROM teaching_assignments WHERE active = 1')['total'] ?? 0);
    $scheduleTotal = (int)(fetch_one('SELECT COUNT(*) AS total FROM lesson_schedules')['total'] ?? 0);
    $activeRequestTotal = (int)(fetch_one('SELECT COUNT(*) AS total FROM teacher_schedule_requests WHERE active = 1')['total'] ?? 0);

    render_schedule_metrics([
        'Pembelajaran Aktif' => $assignmentTotal,
        'Slot Jadwal' => $scheduleTotal,
        'Hasil Filter' => count($rows),
    ]);
    render_schedule_template_panel();
    render_schedule_generate_panel();
    render_schedule_grid_form();
    render_schedule_filter_panel($filterClassId, $filterTeacherId);
    render_lesson_schedule_form($editSchedule);
    render_lesson_schedule_table($rows, true);
    // Fitur Request Jadwal di-hidden sementara
    // render_schedule_request_form($editRequest, schedule_teacher_options(), null);
    // render_schedule_request_table($requests, true);
}

function render_lesson_schedule_teacher_page(?int $customTeacherId = null): void
{
    $isGuru = user_role() === 'guru';
    $teacherId = $customTeacherId ?: ($isGuru ? (int)(current_user()['teacher_id'] ?? 0) : (int)($_GET['teacher_id'] ?? 0));

    if ($teacherId <= 0) {
        echo '<div class="alert danger">Akun Anda belum terhubung dengan data guru. Silakan hubungi Administrator untuk menghubungkan akun pengguna Anda ke data guru.</div>';
        return;
    }

    $teacher = fetch_one('SELECT * FROM teachers WHERE id = ?', [$teacherId]);
    if (!$teacher) {
        echo '<div class="alert danger">Data guru tidak ditemukan.</div>';
        return;
    }

    $orderClause = function_exists('class_order_by_clause') ? class_order_by_clause('c') : 'c.display_order, c.grade, c.name';
    $assignments = fetch_all(
        'SELECT ta.id, ta.class_id, ta.subject_id, c.name AS class_name, c.grade, c.physical_group, s.name AS subject_name, s.short_name
         FROM teaching_assignments ta
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.teacher_id = ? AND ta.active = 1
         ORDER BY ' . $orderClause . ', s.name',
        [$teacherId]
    );

    $assignedSubjects = [];
    foreach ($assignments as $a) {
        $assignedSubjects[$a['subject_name']][] = $a['class_name'] . (!empty($a['physical_group']) ? ' (' . $a['physical_group'] . ')' : '');
    }

    $existing = [];
    $totalFilled = 0;
    foreach (fetch_all('SELECT ls.*, c.name AS class_name, s.name AS subject_name FROM lesson_schedules ls JOIN classes c ON c.id = ls.class_id JOIN subjects s ON s.id = ls.subject_id WHERE ls.teacher_id = ?', [$teacherId]) as $row) {
        $existing[(int)$row['day_of_week']][(int)$row['period_no']] = $row;
        $totalFilled++;
    }

    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);
    $days = schedule_days();

    $occupied = [];
    foreach (fetch_all('SELECT ls.class_id, ls.day_of_week, ls.period_no, c.name AS class_name, t.name AS teacher_name FROM lesson_schedules ls JOIN classes c ON c.id = ls.class_id JOIN teachers t ON t.id = ls.teacher_id WHERE ls.teacher_id <> ?', [$teacherId]) as $occ) {
        $occupied[(int)$occ['day_of_week']][(int)$occ['period_no']][(int)$occ['class_id']] = $occ['teacher_name'];
    }

    if (is_admin()): ?>
        <div style="margin-bottom:1rem;">
            <a href="<?= e(route_url('lesson-schedule')) ?>" class="button small secondary" style="display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                <i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Kembali ke Jadwal Admin
            </a>
        </div>
    <?php endif;

    render_schedule_metrics([
        'Nama Guru' => (string)$teacher['name'],
        'Beban Penugasan' => count($assignments) . ' Penugasan Kelas',
        'Slot Terjadwal' => $totalFilled . ' Jam Pelajaran',
    ]);
    ?>

    <div class="alert info" style="margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:14px 18px;color:#0369a1;">
        <i data-lucide="info" style="width:24px;height:24px;flex-shrink:0;margin-top:2px;color:#0284c7;"></i>
        <div style="font-size:0.92rem;line-height:1.5;">
            <strong>Pengisian Jadwal Mengajar Mandiri:</strong><br>
            Silakan tentukan jadwal mengajar Anda dari hari <strong>Senin sampai Sabtu</strong> sesuai dengan jadwal sekolah yang sudah ada.
            Pilih kelas dan mata pelajaran pada jam yang sesuai, lalu klik <strong>"Simpan Jadwal Mengajar"</strong>.
            <div style="margin-top:4px;color:#0f766e;font-weight:600;">
                💡 Jadwal ini akan langsung digunakan pada aplikasi mobile untuk <strong>Presensi Masuk Kelas</strong> dan <strong>Absensi Siswa</strong>.
            </div>
        </div>
    </div>

    <div style="margin-bottom:1.25rem;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:8px;">
        <div style="font-size:0.85rem;font-weight:700;color:var(--text);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
            <i data-lucide="book-open" style="width:16px;height:16px;color:#0284c7;"></i> Penugasan Mengajar Anda:
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php if (empty($assignedSubjects)): ?>
                <span style="color:#ef4444;font-size:0.85rem;">Belum ada penugasan mengajar aktif untuk akun ini. Silakan hubungi bagian kurikulum.</span>
            <?php else: ?>
                <?php foreach ($assignedSubjects as $subj => $classList): ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;background:#f1f5f9;border:1px solid #cbd5e1;padding:4px 10px;border-radius:6px;font-size:0.82rem;">
                        <strong><?= e($subj) ?>:</strong> <?= e(implode(', ', array_unique($classList))) ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <section class="panel" style="overflow:hidden;margin-bottom:2rem;">
        <form method="post" id="teacher-schedule-matrix-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_teacher_schedule_matrix">
            <input type="hidden" name="teacher_id" value="<?= e($teacherId) ?>">

            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--surface);flex-wrap:wrap;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:1.05rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">
                        <i data-lucide="calendar" style="color:#0284c7;"></i> Matriks Jadwal Mengajar Mingguan
                    </span>
                    <span style="font-size:0.8rem;background:#e0f2fe;color:#0369a1;font-weight:600;padding:3px 10px;border-radius:12px;">
                        <?= e($teacher['name']) ?>
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="submit" class="button primary" style="font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="save" style="width:16px;height:16px;"></i> Simpan Jadwal Mengajar
                    </button>
                </div>
            </div>

            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0;">
                <table class="table" style="margin:0;width:100%;min-width:760px;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--table-header, #f8fafc);">
                            <th style="width:130px;text-align:center;padding:10px;position:sticky;left:0;background:var(--table-header, #f8fafc);z-index:2;border-right:2px solid var(--border);">Jam / Waktu</th>
                            <?php foreach ($days as $dayNum => $dayName): ?>
                                <th style="text-align:center;padding:10px;min-width:130px;"><?= e($dayName) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($p = 1; $p <= $maxPeriods; $p++):
                            [$defaultStart, $defaultEnd] = schedule_time_for_period($p, 1);
                        ?>
                            <tr>
                                <td class="schedule-matrix-sticky-col" style="position:sticky;left:0;background:#fff;z-index:1;border-right:2px solid var(--border);text-align:center;padding:8px 10px;">
                                    <div style="font-weight:700;font-size:0.85rem;color:var(--text);">Jam ke-<?= $p ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted,#64748b);"><?= e($defaultStart . ' - ' . $defaultEnd) ?></div>
                                </td>
                                <?php foreach ($days as $dayNum => $dayName):
                                    $current = $existing[$dayNum][$p] ?? null;
                                    $currentAssignmentId = $current ? (int)$current['assignment_id'] : 0;
                                    $isLocked = $current && (int)$current['locked'] === 1;
                                    [$dayStart, $dayEnd] = schedule_time_for_period($p, $dayNum);
                                    $dayBreak = schedule_break_minutes_for_after($p, $dayNum);
                                    $actForPeriod = schedule_get_activity_for_period($dayNum, $p);
                                ?>
                                    <td style="padding:6px;vertical-align:middle;text-align:center;<?= $actForPeriod !== null ? 'background:#f0f9ff;' : ($currentAssignmentId > 0 ? 'background:#f0fdf4;' : '') ?>">
                                        <div style="font-size:0.72rem;color:var(--text-muted,#64748b);font-weight:600;margin-bottom:3px;"><?= e($dayStart . ' - ' . $dayEnd) ?></div>
                                        <?php if ($actForPeriod !== null): ?>
                                            <div style="background:#e0f2fe;border:1px solid #7dd3fc;color:#0369a1;padding:6px 8px;border-radius:6px;font-size:0.78rem;font-weight:700;line-height:1.3;" title="Kegiatan Sekolah / Bersama">
                                                🚩 <?= e($actForPeriod) ?>
                                            </div>
                                            <div style="font-size:0.68rem;color:#0284c7;margin-top:2px;font-weight:600;">Kegiatan Bersama</div>
                                        <?php elseif ($isLocked): ?>
                                            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:6px 8px;border-radius:6px;font-size:0.78rem;font-weight:600;line-height:1.3;" title="Jadwal ini dikunci oleh admin">
                                                🔒 <?= e($current['class_name'] . ' - ' . $current['subject_name']) ?>
                                                <input type="hidden" name="teacher_slot[<?= $dayNum ?>][<?= $p ?>]" value="<?= $currentAssignmentId ?>">
                                            </div>
                                        <?php else: ?>
                                            <select name="teacher_slot[<?= $dayNum ?>][<?= $p ?>]"
                                                    class="form-control teacher-slot-select"
                                                    style="width:100%;font-size:0.8rem;padding:6px 8px;border-radius:6px;border:1px solid <?= $currentAssignmentId > 0 ? '#10b981' : '#cbd5e1' ?>;background:<?= $currentAssignmentId > 0 ? '#ecfdf5' : '#ffffff' ?>;color:<?= $currentAssignmentId > 0 ? '#065f46' : 'inherit' ?>;font-weight:<?= $currentAssignmentId > 0 ? '600' : 'normal' ?>;">
                                                <option value="0">-- Kosong --</option>
                                                <?php foreach ($assignments as $asgn):
                                                    $asgnId = (int)$asgn['id'];
                                                    $isSelected = ($currentAssignmentId === $asgnId);
                                                    $occTeacher = $occupied[$dayNum][$p][(int)$asgn['class_id']] ?? null;
                                                    $isConflict = ($occTeacher && !$isSelected);
                                                ?>
                                                    <option value="<?= $asgnId ?>" <?= $isSelected ? 'selected' : '' ?> <?= $isConflict ? 'disabled style="color:#94a3b8;background:#f8fafc;"' : '' ?>>
                                                        <?= e($asgn['class_name'] . ' - ' . $asgn['subject_name'] . ($isConflict ? ' [Bentrok: ' . $occTeacher . ']' : '')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        <?php if ($dayBreak > 0): ?>
                                            <div style="margin-top:3px;font-size:0.7rem;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:4px;padding:2px 4px;font-weight:600;display:inline-block;" title="Istirahat <?= $dayBreak ?> menit setelah jam ini">
                                                ☕ Istirahat <?= $dayBreak ?>m
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php
                            $daysWithBreak = [];
                            $firstBreakMins = 0;
                            foreach ($days as $dNum => $dName) {
                                $bm = schedule_break_minutes_for_after($p, $dNum);
                                if ($bm > 0) {
                                    $daysWithBreak[] = $dName;
                                    $firstBreakMins = $bm;
                                }
                            }
                            if (!empty($daysWithBreak) && count($daysWithBreak) === count($days)):
                                [, $bEnd] = schedule_time_for_period($p, 1);
                                [$bNext] = schedule_time_for_period($p + 1, 1);
                            ?>
                                <tr class="schedule-matrix-break-row">
                                    <td class="schedule-matrix-sticky-col" style="position:sticky;left:0;background:#fef3c7 !important;font-weight:700;border-right:2px solid var(--border);text-align:center;padding:6px;font-size:0.8rem;color:#92400e;">
                                        ☕ ISTIRAHAT
                                    </td>
                                    <td colspan="<?= count($days) ?>" style="text-align:center;background:#fef3c7 !important;color:#92400e;font-weight:600;padding:6px;font-size:0.82rem;">
                                        ☕ Istirahat (<?= $firstBreakMins ?> Menit: <?= e($bEnd) ?> - <?= e($bNext) ?>)
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div style="padding:14px 16px;border-top:1px solid var(--border);background:var(--surface);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div style="font-size:0.82rem;color:var(--text-muted, #64748b);">
                    Pastikan jadwal sudah sesuai sebelum menekan Simpan.
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="button secondary" onclick="if(confirm('Batalkan perubahan yang belum disimpan dan muat ulang halaman?')) location.reload();">
                        Reset
                    </button>
                    <button type="submit" class="button primary" style="font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="save" style="width:16px;height:16px;"></i> Simpan Jadwal Mengajar
                    </button>
                </div>
            </div>
        </form>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.teacher-slot-select').forEach(function(sel) {
            sel.addEventListener('change', function() {
                var val = parseInt(this.value, 10) || 0;
                var td = this.closest('td');
                if (val > 0) {
                    this.style.background = '#ecfdf5';
                    this.style.borderColor = '#10b981';
                    this.style.color = '#065f46';
                    this.style.fontWeight = '600';
                    if (td) td.style.background = '#f0fdf4';
                } else {
                    this.style.background = '#ffffff';
                    this.style.borderColor = '#cbd5e1';
                    this.style.color = 'inherit';
                    this.style.fontWeight = 'normal';
                    if (td) td.style.background = '';
                }
            });
        });
    });
    </script>

    <?php
    $rows = schedule_rows(['teacher_id' => $teacherId]);
    render_lesson_schedule_table($rows, true);
}

function render_lesson_schedule_student_page(): void
{
    $student = current_student();
    $classId = (int)($student['class_id'] ?? 0);
    $rows = $classId > 0 ? schedule_rows(['class_id' => $classId]) : [];
    render_schedule_metrics([
        'Kelas' => (string)($student['class_name'] ?? '-'),
        'Slot Pelajaran' => count($rows),
    ]);

    $activities = schedule_get_period_activities();
    $hasActivities = false;
    foreach ($activities as $d => $pList) {
        if (!empty($pList)) {
            $hasActivities = true;
            break;
        }
    }
    if ($hasActivities) {
        $days = schedule_days();
        echo '<div style="margin:16px 0;background:#f0f9ff;border:1px solid #bae6fd;border-left:4px solid #0284c7;padding:12px 16px;border-radius:8px;">';
        echo '<div style="font-weight:700;color:#0369a1;margin-bottom:8px;font-size:0.9rem;display:flex;align-items:center;gap:6px;">';
        echo '🚩 Kegiatan Rutin Mingguan Sekolah</div>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
        foreach ($activities as $d => $pList) {
            $dName = $days[(int)$d] ?? "Hari $d";
            foreach ($pList as $p => $act) {
                [$tS, $tE] = schedule_time_for_period((int)$p, (int)$d);
                echo '<span style="background:#ffffff;border:1px solid #7dd3fc;color:#0369a1;padding:4px 10px;border-radius:6px;font-size:0.8rem;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.03);">';
                echo e("{$dName} Jam ke-{$p} ({$tS} - {$tE}): {$act}");
                echo '</span>';
            }
        }
        echo '</div></div>';
    }

    render_lesson_schedule_table($rows, false);
}

function render_schedule_metrics(array $metrics): void
{
    echo '<div class="metric-grid">';
    foreach ($metrics as $label => $value) {
        echo '<div class="metric"><span>' . e($label) . '</span><strong>' . e($value) . '</strong></div>';
    }
    echo '</div>';
}

function render_schedule_template_panel(): void
{
    $templates = [
        [
            'name' => 'Reguler 5 Hari',
            'meta' => 'Senin-Jumat, 8 jam, 1 slot',
            'days' => '1,2,3,4,5',
            'max_period' => 8,
            'periods_per_assignment' => 1,
        ],
        [
            'name' => 'Reguler 6 Hari',
            'meta' => 'Senin-Sabtu, 7 jam, 1 slot',
            'days' => '1,2,3,4,5,6',
            'max_period' => 7,
            'periods_per_assignment' => 1,
        ],
        [
            'name' => 'Blok Mapel',
            'meta' => 'Senin-Jumat, 8 jam, 2 slot',
            'days' => '1,2,3,4,5',
            'max_period' => 8,
            'periods_per_assignment' => 2,
        ],
    ];
    ?>
    <section class="panel">
        <?php panel_title('Template Jadwal Cepat'); ?>
        <div class="schedule-template-grid">
            <?php foreach ($templates as $template): ?>
                <button
                    type="button"
                    class="schedule-template"
                    data-schedule-template
                    data-days="<?= e($template['days']) ?>"
                    data-max-period="<?= e($template['max_period']) ?>"
                    data-periods-per-assignment="<?= e($template['periods_per_assignment']) ?>"
                >
                    <strong><?= e($template['name']) ?></strong>
                    <span><?= e($template['meta']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="schedule-period-strip" style="flex-wrap:wrap;">
            <?php
            $school = get_school_profile();
            $maxPeriods = (int)($school['max_periods'] ?? 10);
            $allDays = schedule_days();

            $standardDays = [];
            $customGroups = [];

            foreach ($allDays as $dNum => $dName) {
                if (!schedule_day_has_custom_breaks($dNum)) {
                    $standardDays[] = $dName;
                } else {
                    $sig = '';
                    for ($p = 1; $p <= $maxPeriods; $p++) {
                        $sig .= $p . ':' . schedule_break_minutes_for_after($p, $dNum) . '|';
                    }
                    if (!isset($customGroups[$sig])) {
                        $customGroups[$sig] = [
                            'sample' => $dNum,
                            'names' => [],
                        ];
                    }
                    $customGroups[$sig]['names'][] = $dName;
                }
            }

            $regularDaySample = 2; // Default Selasa
            foreach ($allDays as $dNum => $dName) {
                if (!schedule_day_has_custom_breaks($dNum)) {
                    $regularDaySample = $dNum;
                    break;
                }
            }
            ?>
            <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);width:100%;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                <i data-lucide="clock" style="width:13px;height:13px;"></i> Hari Biasa (<?= e(!empty($standardDays) ? implode(', ', $standardDays) : 'Standar') ?>):
            </div>
            <?php for ($p = 1; $p <= $maxPeriods; $p++):
                [$start, $end] = schedule_time_for_period($p, $regularDaySample);
            ?>
                <span><strong><?= e($p) ?></strong><?= e($start . '-' . $end) ?></span>
            <?php
                $breakMins = schedule_break_minutes_for_after($p, $regularDaySample);
                if ($breakMins > 0):
                    [, $bEnd] = schedule_time_for_period($p, $regularDaySample);
                    [$bNext] = schedule_time_for_period($p + 1, $regularDaySample);
            ?>
                <span class="schedule-period-break"><strong>☕</strong><?= e($bEnd . '-' . $bNext) ?></span>
            <?php endif; endfor; ?>

            <?php foreach ($customGroups as $cg):
                $sampleDay = $cg['sample'];
                $dayLabel = implode(', ', $cg['names']);
            ?>
                <div style="font-size:0.75rem;font-weight:700;color:#0369a1;width:100%;margin-top:10px;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="sparkles" style="width:13px;height:13px;color:#0284c7;"></i> Hari Khusus: <?= e($dayLabel) ?>
                </div>
                <?php for ($p = 1; $p <= $maxPeriods; $p++):
                    [$start, $end] = schedule_time_for_period($p, $sampleDay);
                ?>
                    <span style="background:#f0f9ff;border-color:#bae6fd;"><strong><?= e($p) ?></strong><?= e($start . '-' . $end) ?></span>
                <?php
                    $breakMins = schedule_break_minutes_for_after($p, $sampleDay);
                    if ($breakMins > 0):
                        [, $bEnd] = schedule_time_for_period($p, $sampleDay);
                        [$bNext] = schedule_time_for_period($p + 1, $sampleDay);
                ?>
                    <span class="schedule-period-break" style="background:#fef3c7;border-color:#fde68a;"><strong>☕</strong><?= e($bEnd . '-' . $bNext) ?></span>
                <?php endif; endfor; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function render_schedule_generate_panel(): void
{
    ?>
    <section class="panel">
        <?php panel_title('Generate Jadwal Otomatis'); ?>
        <form method="post" class="grid four" data-schedule-generate-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate_lesson_schedule">
            <div class="wide">
                <label>Hari Aktif</label>
                <div class="actions">
                    <?php foreach (schedule_days() as $day => $label): ?>
                        <label class="check"><input type="checkbox" name="days[]" value="<?= e($day) ?>" <?= $day <= 5 ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <label>Jam Maksimal <input type="number" min="1" max="12" name="max_period" value="8"></label>
            <label>Slot per Pembelajaran <input type="number" min="1" max="4" name="periods_per_assignment" value="1"></label>
            <label class="check"><input type="checkbox" name="keep_locked" checked> Pertahankan jadwal terkunci</label>
            <div class="actions"><button class="button primary">Generate</button></div>
        </form>
    </section>
    <?php
}

function render_schedule_filter_panel(int $classId, int $teacherId): void
{
    $classes = ['' => 'Semua Kelas'] + schedule_class_options();
    $teachers = ['' => 'Semua Guru'] + schedule_teacher_options();
    ?>
    <section class="panel">
        <?php panel_title('Filter Jadwal'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="lesson-schedule">
            <label>Kelas <select name="class_id"><?= options($classes, $classId ?: '') ?></select></label>
            <label>Guru <select name="teacher_id"><?= options($teachers, $teacherId ?: '') ?></select></label>
            <div class="actions">
                <button class="button">Tampilkan</button>
                <a class="button" href="<?= e(route_url('lesson-schedule')) ?>">Reset</a>
                <?php if ($teacherId > 0): ?>
                    <a class="button primary small" href="<?= e(route_url('lesson-schedule', ['teacher_id' => $teacherId, 'view' => 'teacher_matrix'])) ?>" title="Buka matriks jadwal mingguan guru ini" style="display:inline-flex;align-items:center;gap:5px;">
                        <i data-lucide="calendar" style="width:14px;height:14px;"></i> Matriks Guru Ini
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php
}

function render_lesson_schedule_form(array $edit): void
{
    $period = (int)($edit['period_no'] ?? 1);
    [$defaultStart, $defaultEnd] = schedule_time_for_period($period, (int)($edit['day_of_week'] ?? 1));
    $assignments = schedule_assignment_options();
    input_panel_start($edit ? 'Edit Slot Jadwal' : 'Input Slot Jadwal', 'Tambah Slot Jadwal', (bool)$edit || isset($_GET['add_schedule']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_lesson_schedule">
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Pembelajaran <select name="assignment_id" required><?= options($assignments, $edit['assignment_id'] ?? '') ?></select></label>
            <label>Hari <select name="day_of_week"><?= options(schedule_days(), $edit['day_of_week'] ?? 1) ?></select></label>
            <label>Jam <select name="period_no"><?= options(schedule_period_options(), $edit['period_no'] ?? 1) ?></select></label>
            <label>Mulai <input type="time" name="start_time" value="<?= e($edit['start_time'] ?? $defaultStart) ?>"></label>
            <label>Selesai <input type="time" name="end_time" value="<?= e($edit['end_time'] ?? $defaultEnd) ?>"></label>
            <label class="check"><input type="checkbox" name="locked" <?= checked($edit['locked'] ?? 0) ?>> Kunci dari auto-generate</label>
            <label class="wide">Catatan <textarea name="note"><?= e($edit['note'] ?? '') ?></textarea></label>
            <div class="wide actions"><button class="button primary">Simpan Jadwal</button><a class="button" href="<?= e(route_url('lesson-schedule')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end();
}

function render_schedule_request_form(array $edit, array $teacherOptions, ?int $fixedTeacherId): void
{
    $teacher = $fixedTeacherId ? fetch_one('SELECT name FROM teachers WHERE id = ?', [$fixedTeacherId]) : null;
    ?>
    <section class="panel">
        <?php panel_title($edit ? 'Edit Request Guru' : 'Request Jadwal Guru'); ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_schedule_request">
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <?php if ($fixedTeacherId): ?>
                <input type="hidden" name="teacher_id" value="<?= e($fixedTeacherId) ?>">
                <label class="span-2">Guru <input readonly value="<?= e($teacher['name'] ?? '-') ?>"></label>
            <?php else: ?>
                <label class="span-2">Guru <select name="teacher_id" required><?= options($teacherOptions, $edit['teacher_id'] ?? '') ?></select></label>
            <?php endif; ?>
            <label>Tipe <select name="request_type"><?= options(schedule_request_type_options(), $edit['request_type'] ?? 'prefer') ?></select></label>
            <label>Hari <select name="day_of_week"><?= options(schedule_days(), $edit['day_of_week'] ?? 1) ?></select></label>
            <label>Jam Awal <select name="start_period"><?= options(schedule_period_options(), $edit['start_period'] ?? 1) ?></select></label>
            <label>Jam Akhir <select name="end_period"><?= options(schedule_period_options(), $edit['end_period'] ?? ($edit['start_period'] ?? 1)) ?></select></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <label class="wide">Catatan <textarea name="note" placeholder="Contoh: mohon tidak jam pertama karena piket gerbang"><?= e($edit['note'] ?? '') ?></textarea></label>
            <div class="wide actions"><button class="button primary">Simpan Request</button><a class="button" href="<?= e(route_url('lesson-schedule')) ?>">Reset</a></div>
        </form>
    </section>
    <?php
}

function render_lesson_schedule_table(array $rows, bool $adminActions): void
{
    $headers = ['Hari', 'Jam', 'Waktu', 'Kelas', 'Mapel', 'Guru', 'Status', 'Catatan'];
    if ($adminActions) {
        $headers[] = 'Aksi';
    }

    table_panel('Daftar Jadwal Pelajaran', $headers, $rows, function (array $row) use ($adminActions): void { ?>
        <td><?= e(schedule_days()[(int)$row['day_of_week']] ?? '-') ?></td>
        <td><?= e('Jam ke-' . (int)$row['period_no']) ?></td>
        <td><?= e(substr((string)$row['start_time'], 0, 5)) ?> - <?= e(substr((string)$row['end_time'], 0, 5)) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['subject_name']) ?></td>
        <td><?= e($row['teacher_name']) ?></td>
        <td><?= schedule_lock_badge((int)$row['locked']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['note'], 0, 80, '...')) ?></td>
        <?php if ($adminActions): ?>
            <td><?= lesson_schedule_actions((int)$row['id']) ?></td>
        <?php endif; ?>
    <?php });
}

function render_schedule_request_table(array $rows, bool $allowActions): void
{
    $headers = ['Guru', 'Tipe', 'Hari', 'Rentang Jam', 'Catatan', 'Status'];
    if ($allowActions) {
        $headers[] = 'Aksi';
    }

    table_panel('Request Jadwal Guru', $headers, $rows, function (array $row) use ($allowActions): void { ?>
        <td><?= e($row['teacher_name']) ?></td>
        <td><?= e(schedule_request_type_options()[$row['request_type']] ?? $row['request_type']) ?></td>
        <td><?= e(schedule_days()[(int)$row['day_of_week']] ?? '-') ?></td>
        <td><?= e('Jam ke-' . (int)$row['start_period'] . ' s/d ' . (int)$row['end_period']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['note'], 0, 90, '...')) ?></td>
        <td><?= status_badge((int)$row['active']) ?></td>
        <?php if ($allowActions): ?>
            <td><?= schedule_request_actions((int)$row['id']) ?></td>
        <?php endif; ?>
    <?php });
}

function schedule_lock_badge(int $locked): string
{
    return $locked === 1 ? '<span class="badge ok">Dikunci</span>' : '<span class="badge off">Fleksibel</span>';
}

function lesson_schedule_actions(int $id): string
{
    $html = '<div class="row-actions">';
    if (is_admin()) {
        $html .= '<a class="button small" href="' . e(route_url('lesson-schedule', ['edit_schedule' => $id])) . '">Edit</a>';
    }
    $html .= '<form method="post" data-confirm="Hapus jadwal ini? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus jadwal?" data-confirm-label="Ya, hapus" data-confirm-tone="danger">' . csrf_field()
        . '<input type="hidden" name="action" value="delete_lesson_schedule"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
    return $html;
}

function schedule_request_actions(int $id): string
{
    return '<div class="row-actions"><a class="button small" href="' . e(route_url('lesson-schedule', ['edit_request' => $id])) . '">Edit</a>'
        . '<form method="post" data-confirm="Hapus permintaan jadwal ini? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus permintaan?" data-confirm-label="Ya, hapus" data-confirm-tone="danger">' . csrf_field()
        . '<input type="hidden" name="action" value="delete_schedule_request"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function render_schedule_grid_form(int $maxPeriod = 8): void
{
    $classes = schedule_class_options();
    $filterClassId = (int)($_GET['grid_class_id'] ?? 0);
    $filterDay = (int)($_GET['grid_day'] ?? 0);
    $days = schedule_days();
    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);

    // Default to active day (1 = Senin .. 6 = Sabtu)
    if ($filterDay < 1 || $filterDay > 6) {
        $curDow = (int)date('N');
        $filterDay = ($curDow >= 1 && $curDow <= 6) ? $curDow : 1;
    }

    // Hitung slot per hari untuk badge di tab
    $dayCounts = [];
    foreach (fetch_all('SELECT day_of_week, COUNT(*) AS cnt FROM lesson_schedules GROUP BY day_of_week') as $r) {
        $dayCounts[(int)$r['day_of_week']] = (int)$r['cnt'];
    }

    $orderClause = function_exists('class_order_by_clause') ? class_order_by_clause() : 'grade, name';
    $hasDisplayOrder = function_exists('table_column_exists') && table_column_exists('classes', 'display_order');
    $displayOrderCol = $hasDisplayOrder ? ', display_order' : ', 0 AS display_order';
    $hasLevel = function_exists('table_column_exists') && table_column_exists('classes', 'level');
    $levelCol = $hasLevel ? ', level' : ", '' AS level";
    $hasPhysicalGroup = function_exists('table_column_exists') && table_column_exists('classes', 'physical_group');
    $physicalGroupCol = $hasPhysicalGroup ? ', physical_group' : ", '' AS physical_group";
    $allClasses = fetch_all('SELECT id, name, grade' . $levelCol . $physicalGroupCol . $displayOrderCol . ' FROM classes WHERE active = 1 ORDER BY ' . $orderClause);

    ?>
    <style>
    .schedule-day-tabs {
        display: flex;
        gap: 6px;
        padding: 12px 16px;
        background: var(--surface-secondary, #f8fafc);
        border-bottom: 1px solid var(--border, #e2e8f0);
        overflow-x: auto;
        align-items: center;
    }
    .schedule-day-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--text-muted, #64748b);
        background: var(--surface, #ffffff);
        border: 1px solid var(--border, #cbd5e1);
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .schedule-day-tab:hover {
        border-color: #0284c7;
        color: #0284c7;
    }
    .schedule-day-tab.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    }
    .schedule-day-tab .tab-badge {
        background: rgba(0, 0, 0, 0.08);
        color: inherit;
        font-size: 0.75rem;
        padding: 2px 7px;
        border-radius: 999px;
        font-weight: 500;
    }
    .schedule-day-tab.active .tab-badge {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }
    .schedule-matrix-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: var(--surface, #ffffff);
        border-bottom: 1px solid var(--border, #e2e8f0);
        flex-wrap: wrap;
    }
    .schedule-matrix-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text, #1e293b);
    }
    .schedule-matrix-scroll {
        overflow-x: auto;
        max-height: 75vh;
        position: relative;
        background: var(--surface, #ffffff);
    }
    .schedule-matrix-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }
    .schedule-matrix-table th,
    .schedule-matrix-table td {
        border-right: 1px solid var(--border, #e2e8f0);
        border-bottom: 1px solid var(--border, #e2e8f0);
        padding: 6px;
        vertical-align: middle;
    }
    .schedule-matrix-table thead th {
        position: sticky;
        top: 0;
        background: #0f172a;
        color: #f8fafc;
        font-weight: 600;
        font-size: 0.82rem;
        text-align: center;
        padding: 10px 8px;
        z-index: 10;
        white-space: nowrap;
    }
    .schedule-matrix-sticky-col {
        position: sticky;
        left: 0;
        background: var(--surface-secondary, #f8fafc) !important;
        z-index: 5;
        min-width: 172px;
        width: 172px;
        text-align: center;
        font-weight: 600;
        padding: 8px 6px;
        box-sizing: border-box;
    }
    .schedule-matrix-table thead th.schedule-matrix-sticky-col {
        z-index: 15;
        background: #0f172a !important;
        min-width: 172px;
        width: 172px;
    }
    .schedule-matrix-time-box {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        margin-top: 4px;
    }
    .schedule-matrix-time-input {
        width: 74px;
        min-width: 74px;
        height: 28px;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 2px 4px;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        text-align: center;
        background: #ffffff;
        color: #1e293b;
        box-sizing: border-box;
    }
    .schedule-matrix-time-input:focus {
        border-color: #0284c7;
        outline: none;
        box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2);
    }
    .schedule-matrix-time-input::-webkit-calendar-picker-indicator {
        padding: 0;
        margin: 0;
        cursor: pointer;
        opacity: 0.6;
        transform: scale(0.85);
    }
    .schedule-matrix-cell {
        min-width: 180px;
        max-width: 230px;
        padding: 4px !important;
        background: #ffffff;
        transition: background 0.15s ease, border-color 0.15s ease;
    }
    .schedule-matrix-cell:hover {
        background: #f8fafc;
    }
    .schedule-matrix-cell.cell-has-conflict {
        background: #fef2f2 !important;
        border: 2px solid #ef4444 !important;
        position: relative;
    }
    .matrix-slot-select {
        width: 100%;
        font-size: 0.78rem;
        padding: 5px 6px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #ffffff;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .matrix-slot-select:focus {
        border-color: #0284c7;
        outline: none;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
    }
    .matrix-slot-select.is-filled {
        border-color: #0284c7;
        background: #f0f9ff;
        font-weight: 600;
        color: #0369a1;
    }
    .matrix-activity-select {
        width: 100%;
        font-size: 0.74rem;
        padding: 5px 6px;
        border-radius: 6px;
        cursor: pointer;
        box-sizing: border-box;
    }
    .matrix-activity-select:focus {
        border-color: #0284c7;
        outline: none;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.25);
    }
    .matrix-slot-select.is-conflict {
        border-color: #ef4444 !important;
        background: #fee2e2 !important;
        color: #991b1b !important;
        font-weight: 700;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.35) !important;
    }
    .matrix-slot-select.is-group-sync {
        border-color: #0284c7 !important;
        background: #e0f2fe !important;
        color: #0369a1 !important;
    }
    .matrix-conflict-msg {
        display: none;
        font-size: 0.7rem;
        color: #dc2626;
        font-weight: 600;
        margin-top: 3px;
        line-height: 1.15;
    }
    .matrix-group-msg {
        display: none;
        font-size: 0.68rem;
        color: #0284c7;
        font-weight: 600;
        margin-top: 2px;
        line-height: 1.1;
    }
    .conflict-item-card {
        background: #ffffff;
        border: 1px solid #fca5a5;
        border-left: 4px solid #ef4444;
        border-radius: 6px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .conflict-item-period {
        display: inline-block;
        background: #fee2e2;
        color: #991b1b;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        margin-right: 6px;
    }
    .conflict-class-pill {
        display: inline-block;
        background: #f1f5f9;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
    }
    @keyframes pulseTarget {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        50% { transform: scale(1.04); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.4); }
    }
    .cell-pulse-target {
        animation: pulseTarget 0.8s ease-in-out 3;
        z-index: 100 !important;
        position: relative;
    }
    .schedule-matrix-break-row td {
        background: #fef3c7 !important;
        color: #92400e;
        font-weight: 600;
        text-align: center;
        padding: 6px !important;
        font-size: 0.78rem;
    }
    </style>
    <section class="panel" style="overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--surface);flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">
                    <i data-lucide="layout-grid" style="color:#0284c7;"></i> Input Cepat Jadwal Harian
                </span>
                <span style="font-size:0.8rem;background:rgba(2,132,199,0.1);color:#0284c7;padding:2px 8px;border-radius:999px;font-weight:600;">
                    Semua Kelas Sekaligus
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <?php if ($filterClassId > 0): ?>
                    <a href="<?= e(route_url('lesson-schedule', ['grid_day' => $filterDay])) ?>" class="button" style="font-size:0.8rem;padding:5px 12px;">
                        ⬅ Kembali ke Tampilan Semua Kelas
                    </a>
                <?php else: ?>
                    <div style="font-size:0.8rem;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                        <span>Fokus per kelas:</span>
                        <select onchange="if(this.value) window.location.href='<?= e(route_url('lesson-schedule')) ?>&grid_day=<?= $filterDay ?>&grid_class_id='+this.value;" style="font-size:0.8rem;padding:3px 6px;">
                            <option value="">-- Pilih 1 Kelas Saja --</option>
                            <?php foreach ($classes as $cid => $cname): ?>
                                <option value="<?= e($cid) ?>"><?= e($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Navigasi Hari -->
        <div class="schedule-day-tabs">
            <span style="font-weight:700;font-size:0.85rem;color:var(--text);margin-right:6px;display:inline-flex;align-items:center;gap:6px;">
                <i data-lucide="calendar"></i> Hari:
            </span>
            <?php foreach ($days as $dNum => $dLabel): ?>
                <?php
                $isShort = schedule_is_short_day($dNum);
                $cnt = $dayCounts[$dNum] ?? 0;
                $tabUrl = route_url('lesson-schedule', array_filter([
                    'grid_day' => $dNum,
                    'grid_class_id' => $filterClassId > 0 ? $filterClassId : null
                ]));
                ?>
                <a href="<?= e($tabUrl) ?>" class="schedule-day-tab <?= $filterDay === $dNum ? 'active' : '' ?>">
                    <span><?= e($dLabel) ?><?= $isShort ? ' (Pendek)' : '' ?></span>
                    <span class="tab-badge"><?= $cnt ?> slot</span>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(route_url('school')) ?>#section-jam-istirahat" class="button small secondary" style="margin-left:auto;font-size:0.78rem;padding:4px 10px;display:inline-flex;align-items:center;gap:5px;font-weight:600;" title="Ubah letak jam istirahat dan durasi di Profil Sekolah">
                <i data-lucide="coffee" style="width:14px;height:14px;"></i> Atur Jam Istirahat
            </a>
        </div>

    <?php if ($filterClassId > 0): ?>
        <!-- ── Mode Per Kelas Tunggal ── -->
        <?php
        $assignmentsBySubject = [];
        foreach (fetch_all(
            'SELECT ta.id, t.name AS teacher_name, s.name AS subject_name
             FROM teaching_assignments ta
             JOIN teachers t ON t.id = ta.teacher_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.active = 1 AND ta.class_id = ?
             ORDER BY s.name, t.name',
            [$filterClassId]
        ) as $a) {
            $assignmentsBySubject[$a['subject_name']][] = [
                'id' => $a['id'],
                'teacher' => $a['teacher_name'],
            ];
        }

        $existing = [];
        foreach (fetch_all(
            'SELECT period_no, assignment_id, start_time, end_time FROM lesson_schedules WHERE class_id = ? AND day_of_week = ?',
            [$filterClassId, $filterDay]
        ) as $row) {
            $existing[(int)$row['period_no']] = $row;
        }

        $siblingClasses = function_exists('sibling_classes_for_class') ? sibling_classes_for_class($filterClassId) : [];
        $curClassGroup = function_exists('class_physical_group') ? class_physical_group($filterClassId) : null;
        if ($curClassGroup && !empty($siblingClasses)):
        ?>
            <div style="background: rgba(14, 165, 233, 0.08); border-left: 4px solid #0284c7; border-radius: 8px; padding: 10px 14px; margin: 12px 16px; display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: #0369a1;">
                <i data-lucide="link-2" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                <div>
                    <strong>Kelas Gabungan KBM: <?= e($curClassGroup) ?></strong>
                    <div style="font-size: 0.8rem; color: #0284c7; margin-top: 2px;">
                        Jadwal yang Anda atur pada kelas ini otomatis disinkronkan ke rombel pasangannya: <strong><?= e(implode(', ', array_map(fn($c) => $c['name'], $siblingClasses))) ?></strong>.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($assignmentsBySubject)): ?>
            <form method="post" class="schedule-grid-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_schedule_grid">
                <input type="hidden" name="grid_class_id" value="<?= e($filterClassId) ?>">
                <input type="hidden" name="grid_day" value="<?= e($filterDay) ?>">
                <div class="schedule-grid-scroll">
                    <table class="schedule-grid schedule-grid-compact">
                        <tbody>
                            <?php for ($p = 1; $p <= $maxPeriods; $p++): ?>
                                <?php [$startTime, $endTime] = schedule_time_for_period($p, $filterDay); ?>
                                <?php $cur = $existing[$p] ?? null; ?>
                                <tr>
                                    <td class="schedule-grid-period">
                                        <strong>Jam <?= $p ?></strong>
                                        <small><?= e($startTime) ?> - <?= e($endTime) ?></small>
                                    </td>
                                    <td>
                                        <div class="schedule-grid-time-inputs">
                                            <input type="time" name="slot_time[<?= $filterDay ?>][<?= $p ?>][start]" value="<?= e(substr((string)($cur['start_time'] ?? $startTime), 0, 5)) ?>" class="schedule-grid-time-input">
                                            <span>-</span>
                                            <input type="time" name="slot_time[<?= $filterDay ?>][<?= $p ?>][end]" value="<?= e(substr((string)($cur['end_time'] ?? $endTime), 0, 5)) ?>" class="schedule-grid-time-input">
                                        </div>
                                    </td>
                                    <td class="schedule-grid-select-cell">
                                        <select name="slot[<?= $filterDay ?>][<?= $p ?>]" class="schedule-grid-select">
                                            <option value="">- Kosong -</option>
                                            <?php foreach ($assignmentsBySubject as $subjectName => $teachers): ?>
                                                <optgroup label="<?= e($subjectName) ?>">
                                                    <?php foreach ($teachers as $t): ?>
                                                        <option value="<?= e($t['id']) ?>" <?= ($cur && (int)$cur['assignment_id'] === (int)$t['id']) ? 'selected' : '' ?>><?= e($subjectName) ?> — <?= e($t['teacher']) ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="schedule-grid-actions">
                    <button class="button primary">Simpan Jadwal Kelas Ini</button>
                    <a class="button" href="<?= e(route_url('lesson-schedule', ['grid_day' => $filterDay])) ?>">Batal</a>
                </div>
            </form>
        <?php else: ?>
            <p style="padding:16px;color:var(--text-muted)">Belum ada pembelajaran terdaftar untuk kelas ini.</p>
        <?php endif; ?>

    <?php else: ?>
        <!-- ── Mode Matriks Harian (Semua Kelas Sekaligus) ── -->
        <?php
        // Ambil pembelajaran semua kelas
        $assignmentsByClass = [];
        foreach (fetch_all(
            'SELECT ta.id, ta.class_id, ta.teacher_id, ta.subject_id, t.name AS teacher_name, s.name AS subject_name, s.short_name
             FROM teaching_assignments ta
             JOIN teachers t ON t.id = ta.teacher_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.active = 1
             ORDER BY s.name, t.name'
        ) as $a) {
            $cid = (int)$a['class_id'];
            $assignmentsByClass[$cid][$a['subject_name']][] = [
                'id' => (int)$a['id'],
                'teacher_id' => (int)$a['teacher_id'],
                'subject_id' => (int)$a['subject_id'],
                'teacher_name' => $a['teacher_name'],
                'short_name' => $a['short_name'] ?: $a['subject_name'],
            ];
        }

        // Ambil jadwal yang sudah tersimpan di hari ini
        $existingSchedules = [];
        foreach (fetch_all(
            'SELECT ls.id, ls.class_id, ls.period_no, ls.assignment_id, ls.teacher_id, ls.start_time, ls.end_time, ls.locked
             FROM lesson_schedules ls
             WHERE ls.day_of_week = ?',
            [$filterDay]
        ) as $s) {
            $existingSchedules[(int)$s['class_id']][(int)$s['period_no']] = $s;
        }
        ?>

        <form method="post" id="form-daily-matrix">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_schedule_daily_matrix">
            <input type="hidden" name="grid_day" value="<?= e($filterDay) ?>">

            <div class="schedule-matrix-toolbar">
                <div class="schedule-matrix-title">
                    <span>Jadwal Hari <strong><?= e($days[$filterDay]) ?></strong></span>
                    <span style="font-size:0.8rem;font-weight:normal;color:var(--text-muted);">
                        (<?= count($allClasses) ?> Kelas &bull; <?= $maxPeriods ?> Jam Pelajaran)
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="button small secondary" onclick="document.getElementById('modal-reorder-classes-schedule').style.display='flex';" style="font-weight:600;display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;" title="Atur urutan kolom kelas di jadwal pelajaran">
                        <i data-lucide="arrow-up-down" style="width:14px;height:14px;"></i> Urutan Kelas
                    </button>
                    <a href="<?= e(route_url('school')) ?>#section-jam-istirahat" class="button small secondary" style="font-weight:600;display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;" title="Geser letak jam istirahat atau durasi">
                        <i data-lucide="coffee" style="width:14px;height:14px;"></i> Atur Jam Istirahat
                    </a>
                    <span id="conflict-badge-summary" style="display:none;background:#fef2f2;color:#b91c1c;padding:5px 12px;border-radius:6px;font-size:0.8rem;font-weight:700;border:1px solid #f87171;cursor:pointer;" title="Klik untuk melompat ke daftar guru tabrakan">
                        ⚠️ Ada Bentrok Guru!
                    </span>
                    <button type="submit" class="button primary" style="font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="save"></i> Simpan Jadwal Hari <?= e($days[$filterDay]) ?>
                    </button>
                </div>
            </div>

            <!-- Panel Rincian Bentrok / Tabrakan Guru (Bisa Melompat Langsung ke Kotak yang Bentrok) -->
            <div id="conflict-panel" style="display:none;background:#fef2f2;border:1px solid #f87171;border-radius:8px;padding:12px 16px;margin:12px 16px 0 16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
                    <div style="display:flex;align-items:center;gap:8px;color:#991b1b;font-weight:700;font-size:0.88rem;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,0.3);"></span>
                        <span id="conflict-panel-title">⚠️ Ditemukan Bentrok Jadwal Guru</span>
                    </div>
                    <div style="font-size:0.78rem;color:#7f1d1d;">
                        Klik tombol <strong>"👉 Lihat di Tabel"</strong> pada kartu di bawah untuk langsung mengarahkan tampilan ke kotak yang tabrakan:
                    </div>
                </div>
                <div id="conflict-list" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(310px, 1fr));gap:8px;margin-top:8px;">
                </div>
            </div>

            <div class="schedule-matrix-scroll">
                <table class="schedule-matrix-table">
                    <thead>
                        <tr>
                            <th class="schedule-matrix-sticky-col">Jam Ke &amp; Waktu</th>
                            <?php foreach ($allClasses as $cls): ?>
                                <th class="schedule-matrix-col-header" data-class-col="<?= (int)$cls['id'] ?>">
                                    <div style="font-size:0.88rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:4px;">
                                        <span><?= e($cls['name']) ?></span>
                                        <span class="col-conflict-badge" data-class-badge="<?= (int)$cls['id'] ?>" style="display:none;background:#ef4444;color:#fff;font-size:0.65rem;padding:1px 5px;border-radius:10px;font-weight:700;" title="Kelas ini memiliki guru yang bentrok">⚠️</span>
                                    </div>
                                    <?php if (!empty($cls['physical_group'])): ?>
                                        <div style="font-size:0.7rem;font-weight:normal;color:#38bdf8;">(<?= e($cls['physical_group']) ?>)</div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dayActivities = schedule_get_period_activities()[(string)$filterDay] ?? [];
                        $activityOptions = schedule_activity_options();
                        for ($p = 1; $p <= $maxPeriods; $p++): ?>
                            <?php
                            [$defaultStart, $defaultEnd] = schedule_time_for_period($p, $filterDay);
                            // Cek jika ada custom time yang tersimpan di baris ini
                            foreach ($allClasses as $cls) {
                                if (!empty($existingSchedules[(int)$cls['id']][$p]['start_time'])) {
                                    $defaultStart = substr((string)$existingSchedules[(int)$cls['id']][$p]['start_time'], 0, 5);
                                    $defaultEnd = substr((string)$existingSchedules[(int)$cls['id']][$p]['end_time'], 0, 5);
                                    break;
                                }
                            }
                            $curAct = $dayActivities[(string)$p] ?? null;
                            $actIcon = '🚩';
                            if ($curAct) {
                                if (stripos($curAct, 'Sehat') !== false || stripos($curAct, 'Senam') !== false) $actIcon = '🏃';
                                elseif (stripos($curAct, 'Bersih') !== false) $actIcon = '🧹';
                                elseif (stripos($curAct, 'Dhuha') !== false || stripos($curAct, 'Pengajian') !== false || stripos($curAct, 'Sholat') !== false) $actIcon = '🕌';
                                elseif (stripos($curAct, 'Literasi') !== false || stripos($curAct, 'Apel') !== false) $actIcon = '📖';
                                elseif (stripos($curAct, 'Upacara') !== false) $actIcon = '🚩';
                                elseif (stripos($curAct, 'Ekstrakurikuler') !== false || stripos($curAct, 'Ekskul') !== false) $actIcon = '🏆';
                                else $actIcon = '⭐';
                            }
                            ?>
                            <tr>
                                <td class="schedule-matrix-sticky-col" data-period-row="<?= $p ?>">
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                                        <div style="font-size:0.85rem;font-weight:700;color:var(--text);">Jam <?= $p ?></div>
                                        <span class="row-conflict-badge" data-period-badge="<?= $p ?>" style="display:none;background:#ef4444;color:#fff;font-size:0.65rem;padding:1px 5px;border-radius:4px;font-weight:700;" title="Ada guru bentrok di jam ini!">Bentrok!</span>
                                    </div>
                                    <div class="schedule-matrix-time-box">
                                        <input type="time" name="slot_time[<?= $p ?>][start]" value="<?= e($defaultStart) ?>" class="schedule-matrix-time-input" title="Jam Mulai">
                                        <span style="font-size:0.75rem;color:var(--text-muted);">-</span>
                                        <input type="time" name="slot_time[<?= $p ?>][end]" value="<?= e($defaultEnd) ?>" class="schedule-matrix-time-input" title="Jam Selesai">
                                    </div>
                                    <div style="margin-top:6px;">
                                        <select name="period_activity[<?= $p ?>]"
                                                class="matrix-activity-select no-search-select"
                                                data-no-search="true"
                                                data-period="<?= $p ?>"
                                                onchange="matrixActivityChanged(<?= $p ?>, this.value)"
                                                style="width:100%;font-size:0.75rem;padding:5px 6px;border-radius:6px;border:1px solid <?= $curAct ? '#3b82f6' : '#cbd5e1' ?>;background:<?= $curAct ? '#eff6ff' : '#ffffff' ?>;color:<?= $curAct ? '#1e40af' : '#475569' ?>;font-weight:<?= $curAct ? '700' : '600' ?>;cursor:pointer;"
                                                title="Pilih jenis kegiatan pada jam ini (Pelajaran atau Kegiatan Sekolah seperti Upacara, Jum'at Sehat, Ekstrakurikuler, dll)">
                                            <?php foreach ($activityOptions as $actKey => $actLabel):
                                                $isActSel = ($curAct ? ($actKey === $curAct || (!isset($activityOptions[$curAct]) && $actKey === 'custom')) : ($actKey === 'pelajaran'));
                                            ?>
                                                <option value="<?= e($actKey) ?>" <?= $isActSel ? 'selected' : '' ?>><?= e($actLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text"
                                               name="period_activity_custom[<?= $p ?>]"
                                               id="activity-custom-input-<?= $p ?>"
                                               value="<?= (!isset($activityOptions[$curAct]) && $curAct) ? e($curAct) : '' ?>"
                                               placeholder="Ketik kegiatan..."
                                               oninput="matrixCustomActivityTyped(<?= $p ?>, this.value)"
                                               style="display:<?= (!isset($activityOptions[$curAct]) && $curAct) ? 'block' : 'none' ?>;width:100%;font-size:0.72rem;padding:3px 5px;margin-top:3px;border-radius:4px;border:1px solid #93c5fd;box-sizing:border-box;">
                                    </div>
                                </td>
                                <td colspan="<?= count($allClasses) ?>"
                                    class="matrix-activity-banner"
                                    id="activity-banner-<?= $p ?>"
                                    style="display:<?= $curAct ? '' : 'none' ?>;background:linear-gradient(90deg, #eff6ff 0%, #f0fdf4 100%);border-left:4px solid #3b82f6;text-align:center;padding:12px 16px !important;color:#1e40af;font-weight:700;font-size:0.88rem;">
                                    <div style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;">
                                        <span style="font-size:1.2rem;" class="act-banner-icon"><?= e($actIcon) ?></span>
                                        <span style="font-size:0.95rem;font-weight:800;color:#1e3a8a;" class="act-banner-name"><?= e($curAct ?: 'Kegiatan Bersama') ?></span>
                                        <span style="font-size:0.72rem;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:999px;font-weight:600;border:1px solid #bfdbfe;">
                                            Kegiatan Bersama Seluruh Kelas
                                        </span>
                                        <span style="font-size:0.72rem;color:#64748b;font-weight:normal;">
                                            (Pilih "Pelajaran" di dropdown sebelah kiri jika ingin mengisi mapel per kelas)
                                        </span>
                                    </div>
                                </td>
                                <?php foreach ($allClasses as $cls): ?>
                                    <?php
                                    $cid = (int)$cls['id'];
                                    $cur = $existingSchedules[$cid][$p] ?? null;
                                    $curAsgnId = $cur ? (int)$cur['assignment_id'] : 0;
                                    $clsAssignments = $assignmentsByClass[$cid] ?? [];
                                    ?>
                                    <td class="schedule-matrix-cell" data-class-id="<?= $cid ?>" data-period="<?= $p ?>" style="<?= $curAct ? 'display:none;' : '' ?>">
                                        <select name="matrix_slot[<?= $cid ?>][<?= $p ?>]"
                                                class="matrix-slot-select <?= $curAsgnId > 0 ? 'is-filled' : '' ?>"
                                                data-period="<?= $p ?>"
                                                data-class-id="<?= $cid ?>"
                                                data-class-name="<?= e($cls['name']) ?>"
                                                data-physical-group="<?= e($cls['physical_group'] ?? '') ?>"
                                                onchange="matrixSelectChanged(this)">
                                            <option value="">— Kosong —</option>
                                            <?php foreach ($clsAssignments as $subjectName => $teachers): ?>
                                                <optgroup label="<?= e($subjectName) ?>">
                                                    <?php foreach ($teachers as $t): ?>
                                                        <option value="<?= e($t['id']) ?>"
                                                                data-teacher-id="<?= e($t['teacher_id']) ?>"
                                                                data-teacher-name="<?= e($t['teacher_name']) ?>"
                                                                data-subject-id="<?= e($t['subject_id'] ?? '') ?>"
                                                                data-subject-name="<?= e($subjectName) ?>"
                                                                <?= $curAsgnId === (int)$t['id'] ? 'selected' : '' ?>>
                                                            <?= e($subjectName) ?> — <?= e($t['teacher_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="matrix-conflict-msg">⚠️ Bentrok guru di jam ini!</div>
                                        <div class="matrix-group-msg">👥 Kelas Gabungan</div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php
                            $breakMins = schedule_break_minutes_for_after($p, $filterDay);
                            if ($breakMins > 0):
                                [, $bEnd] = schedule_time_for_period($p, $filterDay);
                                [$bNext] = schedule_time_for_period($p + 1, $filterDay);
                            ?>
                            <tr class="schedule-matrix-break-row">
                                <td class="schedule-matrix-sticky-col" style="background:#fef3c7 !important;font-weight:700;">
                                    ☕ ISTIRAHAT
                                </td>
                                <td colspan="<?= count($allClasses) ?>" style="text-align:center;">
                                    ☕ Istirahat (<?= $breakMins ?> Menit: <?= e($bEnd) ?> - <?= e($bNext) ?>)
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="schedule-matrix-toolbar" style="border-top:1px solid var(--border);border-bottom:none;">
                <span style="font-size:0.82rem;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                    💡 <strong>Tips Cepat</strong>: Pilih mapel &amp; guru pada setiap sel kelas. Jika guru bentrok mengajar di 2 kelas di jam yang sama, kotak otomatis menyala merah.
                </span>
                <button type="submit" class="button primary" style="font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                    <i data-lucide="save"></i> Simpan Jadwal Hari <?= e($days[$filterDay]) ?>
                </button>
            </div>
        </form>

        <!-- Modal Atur Urutan Kolom Kelas di Jadwal -->
        <div id="modal-reorder-classes-schedule" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#fff;border-radius:10px;max-width:650px;width:100%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h3 style="margin:0;font-size:1.05rem;font-weight:700;display:flex;align-items:center;gap:8px;">
                            <i data-lucide="arrow-up-down" style="color:#0284c7;"></i> Atur Urutan Kolom Kelas di Jadwal
                        </h3>
                        <p style="margin:2px 0 0 0;font-size:0.78rem;color:var(--text-muted);">
                            Ubah angka urutan untuk menentukan urutan kelas dari kiri ke kanan pada tabel jadwal.
                        </p>
                    </div>
                    <button type="button" onclick="document.getElementById('modal-reorder-classes-schedule').style.display='none';" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">&times;</button>
                </div>
                <form method="post" action="<?= e(route_url('classes')) ?>" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reorder_classes">
                    <input type="hidden" name="redirect_back" value="lesson-schedule">
                    <div style="padding:10px 18px;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <span style="font-size:0.8rem;color:var(--text-muted);">Isi nomor urut (1, 2, 3...) sesuai urutan yang Anda inginkan:</span>
                        <button type="button" class="button small" onclick="autoSortClassInputs('modal-reorder-classes-schedule')" style="font-size:0.75rem;padding:3px 8px;">
                            🪄 Urutkan Otomatis (7-12)
                        </button>
                    </div>
                    <div style="overflow-y:auto;padding:14px 18px;flex:1;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <thead>
                                <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                    <th style="padding:6px;">Kelas</th>
                                    <th style="padding:6px;">Tingkat</th>
                                    <th style="padding:6px;">Jenjang</th>
                                    <th style="padding:6px;width:100px;text-align:center;">Urutan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allClasses as $c): ?>
                                    <tr style="border-bottom:1px solid var(--border);" data-grade="<?= e($c['grade'] ?? '') ?>" data-name="<?= e($c['name']) ?>">
                                        <td style="padding:8px 6px;"><strong><?= e($c['name']) ?></strong></td>
                                        <td style="padding:8px 6px;"><?= e($c['grade'] ?? '-') ?></td>
                                        <td style="padding:8px 6px;"><?= e($c['level'] ?? '-') ?></td>
                                        <td style="padding:8px 6px;text-align:center;">
                                            <input type="number" name="class_order[<?= (int)$c['id'] ?>]" value="<?= (int)($c['display_order'] ?? 0) ?>" min="1" class="class-order-field" style="width:70px;text-align:center;padding:4px;border:1px solid #cbd5e1;border-radius:4px;font-weight:700;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding:12px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;background:var(--surface);">
                        <button type="button" class="button" onclick="document.getElementById('modal-reorder-classes-schedule').style.display='none';">Batal</button>
                        <button type="submit" class="button primary" style="font-weight:600;"><i data-lucide="save"></i> Simpan Urutan Kolom</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function autoSortClassInputs(modalId) {
            var modal = document.getElementById(modalId);
            if (!modal) return;
            var rows = Array.from(modal.querySelectorAll('tbody tr'));
            rows.sort(function(a, b) {
                var gradeA = a.getAttribute('data-grade') || '';
                var gradeB = b.getAttribute('data-grade') || '';
                var mapRoman = { 'vii': 7, 'viii': 8, 'ix': 9, 'x': 10, 'xi': 11, 'xii': 12 };
                var numA = mapRoman[gradeA.toLowerCase()] || parseInt(gradeA, 10) || 99;
                var numB = mapRoman[gradeB.toLowerCase()] || parseInt(gradeB, 10) || 99;
                if (numA !== numB) return numA - numB;
                var nameA = a.getAttribute('data-name') || '';
                var nameB = b.getAttribute('data-name') || '';
                return nameA.localeCompare(nameB);
            });
            rows.forEach(function(row, idx) {
                var input = row.querySelector('.class-order-field');
                if (input) input.value = idx + 1;
            });
        }
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }

        function jumpToConflict(period, classIds) {
            var targets = [];
            classIds.forEach(function(cid) {
                var sel = document.querySelector('.matrix-slot-select[data-period="' + period + '"][data-class-id="' + cid + '"]');
                if (sel) {
                    targets.push(sel);
                    var cell = sel.closest('.schedule-matrix-cell');
                    if (cell) {
                        cell.classList.add('cell-pulse-target');
                        setTimeout(function() {
                            cell.classList.remove('cell-pulse-target');
                        }, 3000);
                    }
                }
            });

            if (targets.length > 0) {
                targets[0].scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                targets[0].focus();
            }
        }

        function matrixActivityChanged(period, val) {
            var customInput = document.getElementById('activity-custom-input-' + period);
            if (val === 'custom') {
                if (customInput) customInput.style.display = 'block';
            } else {
                if (customInput) customInput.style.display = 'none';
            }

            var banner = document.getElementById('activity-banner-' + period);
            var cells = document.querySelectorAll('td.schedule-matrix-cell[data-period="' + period + '"]');
            var selectEl = document.querySelector('select[name="period_activity[' + period + ']"]');

            if (val !== 'pelajaran') {
                var actName = val;
                var icon = '🚩';
                if (val.indexOf('Sehat') !== -1 || val.indexOf('Senam') !== -1) icon = '🏃';
                else if (val.indexOf('Bersih') !== -1) icon = '🧹';
                else if (val.indexOf('Dhuha') !== -1 || val.indexOf('Pengajian') !== -1 || val.indexOf('Sholat') !== -1) icon = '🕌';
                else if (val.indexOf('Literasi') !== -1 || val.indexOf('Apel') !== -1) icon = '📖';
                else if (val.indexOf('Ekstrakurikuler') !== -1 || val.indexOf('Ekskul') !== -1) icon = '🏆';

                if (val === 'custom') {
                    actName = (customInput && customInput.value.trim()) ? customInput.value.trim() : 'Kegiatan Khusus';
                    icon = '⭐';
                }

                if (selectEl) {
                    selectEl.style.background = '#eff6ff';
                    selectEl.style.borderColor = '#3b82f6';
                    selectEl.style.color = '#1e40af';
                    selectEl.style.fontWeight = '700';
                }

                if (banner) {
                    banner.style.display = '';
                    var nameEl = banner.querySelector('.act-banner-name');
                    var iconEl = banner.querySelector('.act-banner-icon');
                    if (nameEl) nameEl.textContent = actName;
                    if (iconEl) iconEl.textContent = icon;
                }
                cells.forEach(function(c) { c.style.display = 'none'; });
            } else {
                if (selectEl) {
                    selectEl.style.background = '#ffffff';
                    selectEl.style.borderColor = '#cbd5e1';
                    selectEl.style.color = '#475569';
                    selectEl.style.fontWeight = '600';
                }
                if (banner) banner.style.display = 'none';
                cells.forEach(function(c) { c.style.display = ''; });
            }
            checkMatrixConflicts();
        }

        function matrixCustomActivityTyped(period, text) {
            var banner = document.getElementById('activity-banner-' + period);
            if (banner) {
                var nameEl = banner.querySelector('.act-banner-name');
                if (nameEl) nameEl.textContent = text.trim() || 'Kegiatan Khusus';
            }
        }

        function checkMatrixConflicts() {
            var selects = document.querySelectorAll('.matrix-slot-select');
            var byPeriod = {};
            selects.forEach(function(sel) {
                var p = sel.getAttribute('data-period');
                if (!byPeriod[p]) byPeriod[p] = [];
                byPeriod[p].push(sel);
            });

            // Reset semua indikator
            document.querySelectorAll('.row-conflict-badge').forEach(function(b) { b.style.display = 'none'; });
            document.querySelectorAll('.col-conflict-badge').forEach(function(b) { b.style.display = 'none'; });
            document.querySelectorAll('.schedule-matrix-cell').forEach(function(c) { c.classList.remove('cell-has-conflict'); });

            var conflictCards = [];
            var totalConflicts = 0;

            Object.keys(byPeriod).forEach(function(p) {
                var actSel = document.querySelector('select[name="period_activity[' + p + ']"]');
                if (actSel && actSel.value !== 'pelajaran') {
                    // Jam ini adalah kegiatan sekolah (Upacara, Jum'at Sehat, dll), lewati cek bentrok KBM
                    return;
                }
                var periodSelects = byPeriod[p];
                var teacherCounts = {};
                periodSelects.forEach(function(sel) {
                    var opt = sel.options[sel.selectedIndex];
                    var tid = opt ? opt.getAttribute('data-teacher-id') : null;
                    if (tid && tid !== '0' && sel.value !== '') {
                        if (!teacherCounts[tid]) teacherCounts[tid] = [];
                        teacherCounts[tid].push({
                            sel: sel,
                            opt: opt,
                            className: sel.getAttribute('data-class-name'),
                            classId: sel.getAttribute('data-class-id'),
                            physicalGroup: (sel.getAttribute('data-physical-group') || '').trim().toLowerCase(),
                            subjectId: opt.getAttribute('data-subject-id') || '',
                            teacherName: opt.getAttribute('data-teacher-name') || 'Guru'
                        });
                    }
                });

                Object.keys(teacherCounts).forEach(function(tid) {
                    var items = teacherCounts[tid];
                    if (items.length <= 1) return;

                    // Periksa apakah ini rombel gabungan (physical_group sama atau mata pelajaran sama)
                    var firstGroup = items[0].physicalGroup;
                    var firstSubject = items[0].subjectId;
                    var isGroupSync = (firstGroup !== '' && firstSubject !== '');
                    if (isGroupSync) {
                        for (var i = 1; i < items.length; i++) {
                            if (items[i].physicalGroup !== firstGroup || items[i].subjectId !== firstSubject) {
                                isGroupSync = false;
                                break;
                            }
                        }
                    }
                    var isSameSubjectJoint = (firstSubject !== '');
                    if (isSameSubjectJoint) {
                        for (var i = 1; i < items.length; i++) {
                            if (items[i].subjectId !== firstSubject) {
                                isSameSubjectJoint = false;
                                break;
                            }
                        }
                    }

                    if (isGroupSync || isSameSubjectJoint) {
                        // Ini rombel gabungan yang sah
                        items.forEach(function(item) {
                            item.sel.classList.add('is-group-sync');
                            item.sel.classList.remove('is-conflict');
                            var cell = item.sel.closest('.schedule-matrix-cell');
                            if (cell) cell.classList.remove('cell-has-conflict');
                            var msg = item.sel.parentElement.querySelector('.matrix-conflict-msg');
                            if (msg) msg.style.display = 'none';
                            var grpMsg = item.sel.parentElement.querySelector('.matrix-group-msg');
                            if (grpMsg) {
                                grpMsg.textContent = '👥 Gabungan: ' + items[0].opt.getAttribute('data-subject-name');
                                grpMsg.style.display = 'block';
                            }
                        });
                    } else {
                        // BENTROK NYATA
                        totalConflicts++;
                        var teacherName = items[0].teacherName;
                        var classNames = items.map(function(x) { return x.className; });
                        var classIds = items.map(function(x) { return x.classId; });

                        // Beri tanda merah pada baris Jam ke-p
                        var rowBadge = document.querySelector('.row-conflict-badge[data-period-badge="' + p + '"]');
                        if (rowBadge) rowBadge.style.display = 'inline-block';

                        // Beri tanda merah pada kolom kelas yang bersangkutan
                        classIds.forEach(function(cid) {
                            var colBadge = document.querySelector('.col-conflict-badge[data-class-badge="' + cid + '"]');
                            if (colBadge) colBadge.style.display = 'inline-block';
                        });

                        items.forEach(function(item) {
                            item.sel.classList.add('is-conflict');
                            item.sel.classList.remove('is-group-sync');
                            var cell = item.sel.closest('.schedule-matrix-cell');
                            if (cell) cell.classList.add('cell-has-conflict');
                            var msg = item.sel.parentElement.querySelector('.matrix-conflict-msg');
                            if (msg) {
                                var otherNames = items.filter(function(x) { return x.sel !== item.sel; }).map(function(x) { return x.className; });
                                msg.textContent = '⚠️ Bentrok: ' + teacherName + ' juga di ' + otherNames.join(', ');
                                msg.style.display = 'block';
                            }
                            var grpMsg = item.sel.parentElement.querySelector('.matrix-group-msg');
                            if (grpMsg) grpMsg.style.display = 'none';
                        });

                        conflictCards.push({
                            period: p,
                            teacherName: teacherName,
                            classNames: classNames,
                            classIds: classIds
                        });
                    }
                });

                // Bersihkan sel yang tidak ada bentrok
                periodSelects.forEach(function(sel) {
                    var opt = sel.options[sel.selectedIndex];
                    var tid = opt ? opt.getAttribute('data-teacher-id') : null;
                    if (!tid || !teacherCounts[tid] || teacherCounts[tid].length <= 1) {
                        sel.classList.remove('is-conflict');
                        sel.classList.remove('is-group-sync');
                        var cell = sel.closest('.schedule-matrix-cell');
                        if (cell) cell.classList.remove('cell-has-conflict');
                        var msg = sel.parentElement.querySelector('.matrix-conflict-msg');
                        if (msg) msg.style.display = 'none';
                        var grpMsg = sel.parentElement.querySelector('.matrix-group-msg');
                        if (grpMsg) grpMsg.style.display = 'none';
                    }
                    if (sel.value !== '') {
                        sel.classList.add('is-filled');
                    } else {
                        sel.classList.remove('is-filled');
                    }
                });
            });

            // Tampilkan Panel Rincian Bentrok & Badge
            var badge = document.getElementById('conflict-badge-summary');
            var panel = document.getElementById('conflict-panel');
            var list = document.getElementById('conflict-list');
            var panelTitle = document.getElementById('conflict-panel-title');

            if (totalConflicts > 0) {
                if (badge) {
                    badge.style.display = 'inline-flex';
                    badge.innerHTML = '⚠️ <strong>' + totalConflicts + ' Guru Tabrakan!</strong> (Klik lihat)';
                    badge.onclick = function() {
                        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    };
                }
                if (panel && list) {
                    panel.style.display = 'block';
                    if (panelTitle) {
                        panelTitle.innerHTML = '⚠️ Ditemukan ' + totalConflicts + ' Guru Tabrakan / Bentrok Jam:';
                    }
                    var html = '';
                    conflictCards.forEach(function(c) {
                        html += '<div class="conflict-item-card">' +
                                    '<div style="overflow:hidden;">' +
                                        '<div style="font-weight:700;color:#991b1b;font-size:0.83rem;margin-bottom:2px;display:flex;align-items:center;">' +
                                            '<span class="conflict-item-period">Jam ke-' + c.period + '</span>' +
                                            '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(c.teacherName) + '</span>' +
                                        '</div>' +
                                        '<div style="font-size:0.75rem;color:#b91c1c;">' +
                                            'Tabrakan di: ' + c.classNames.map(function(cn) { return '<span class="conflict-class-pill">' + escapeHtml(cn) + '</span>'; }).join(' &bull; ') +
                                        '</div>' +
                                    '</div>' +
                                    '<button type="button" class="button small danger" style="padding:4px 8px;font-size:0.72rem;white-space:nowrap;font-weight:700;" onclick="jumpToConflict(' + c.period + ', [' + c.classIds.join(',') + '])">' +
                                        '👉 Lihat di Tabel' +
                                    '</button>' +
                                '</div>';
                    });
                    list.innerHTML = html;
                }
            } else {
                if (badge) badge.style.display = 'none';
                if (panel) panel.style.display = 'none';
            }
        }

        function matrixSelectChanged(sel) {
            if (sel.value !== '') {
                sel.classList.add('is-filled');
            } else {
                sel.classList.remove('is-filled');
            }
            checkMatrixConflicts();
        }

        document.addEventListener('DOMContentLoaded', function() {
            checkMatrixConflicts();
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        </script>
    <?php endif; ?>
    </section>
    <?php
}

function action_save_schedule_daily_matrix(): void
{
    require_role(['admin']);
    schedule_require_tables();

    $day = (int)($_POST['grid_day'] ?? 1);
    if ($day < 1 || $day > 6) {
        $day = 1;
    }
    $dayLabel = schedule_days()[$day] ?? '-';

    $matrixSlots = $_POST['matrix_slot'] ?? [];
    if (!is_array($matrixSlots)) {
        $matrixSlots = [];
    }

    $slotTimes = $_POST['slot_time'] ?? [];
    if (!is_array($slotTimes)) {
        $slotTimes = [];
    }

    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);

    // 1. Simpan Jenis Kegiatan per Jam Pelajaran untuk hari ini
    $periodActivities = schedule_get_period_activities();
    $dayActivities = $_POST['period_activity'] ?? [];
    $customActivities = $_POST['period_activity_custom'] ?? [];

    if (!isset($periodActivities[(string)$day])) {
        $periodActivities[(string)$day] = [];
    }

    $activeActivitiesToday = [];
    for ($p = 1; $p <= $maxPeriods; $p++) {
        $act = trim((string)($dayActivities[$p] ?? 'pelajaran'));
        if ($act === 'custom') {
            $act = trim((string)($customActivities[$p] ?? ''));
        }
        if ($act === '' || strtolower($act) === 'pelajaran' || strtolower($act) === 'normal') {
            unset($periodActivities[(string)$day][(string)$p]);
        } else {
            $periodActivities[(string)$day][(string)$p] = $act;
            $activeActivitiesToday[$p] = $act;
        }
    }
    if (empty($periodActivities[(string)$day])) {
        unset($periodActivities[(string)$day]);
    }
    schedule_save_period_activities($periodActivities);

    // 2. Simpan Custom Time per Jam Pelajaran untuk hari ini
    $allCustomTimes = schedule_get_custom_period_times();
    if (!isset($allCustomTimes[(string)$day])) {
        $allCustomTimes[(string)$day] = [];
    }
    for ($p = 1; $p <= $maxPeriods; $p++) {
        $cStart = schedule_normalize_time($slotTimes[$p]['start'] ?? '') ?? null;
        $cEnd = schedule_normalize_time($slotTimes[$p]['end'] ?? '') ?? null;
        if ($cStart !== null && $cEnd !== null) {
            $allCustomTimes[(string)$day][(string)$p] = [
                'start' => $cStart,
                'end' => $cEnd,
            ];
        }
    }
    schedule_save_custom_period_times($allCustomTimes);

    $existing = [];
    foreach (fetch_all('SELECT id, assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked FROM lesson_schedules WHERE day_of_week = ?', [$day]) as $row) {
        $existing[(int)$row['class_id']][(int)$row['period_no']] = $row;
    }

    $orderClause = function_exists('class_order_by_clause') ? class_order_by_clause() : 'grade, name';
    $hasDisplayOrder = function_exists('table_column_exists') && table_column_exists('classes', 'display_order');
    $displayOrderCol = $hasDisplayOrder ? ', display_order' : ', 0 AS display_order';
    $hasPhysicalGroup = function_exists('table_column_exists') && table_column_exists('classes', 'physical_group');
    $physicalGroupCol = $hasPhysicalGroup ? ', physical_group' : ", '' AS physical_group";
    $allClasses = fetch_all('SELECT id, name, grade' . $physicalGroupCol . $displayOrderCol . ' FROM classes WHERE active = 1 ORDER BY ' . $orderClause);
    $saved = 0;
    $deleted = 0;
    $errors = [];

    db()->beginTransaction();
    try {
        foreach ($allClasses as $class) {
            $classId = (int)$class['id'];
            for ($p = 1; $p <= $maxPeriods; $p++) {
                $assignmentId = (int)($matrixSlots[$classId][$p] ?? 0);
                $customStart = schedule_normalize_time($slotTimes[$p]['start'] ?? '') ?? null;
                $customEnd = schedule_normalize_time($slotTimes[$p]['end'] ?? '') ?? null;
                [$defaultStart, $defaultEnd] = schedule_time_for_period($p, $day);
                $startTime = $customStart ?? $defaultStart;
                $endTime = $customEnd ?? $defaultEnd;

                $current = $existing[$classId][$p] ?? null;
                $currentAssignmentId = $current ? (int)$current['assignment_id'] : 0;

                // Jika jam ini diset sebagai kegiatan bersama sekolah (Upacara, Jum'at Sehat, dll)
                if (isset($activeActivitiesToday[$p])) {
                    if ($current && (int)$current['locked'] === 0) {
                        execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                        unset($existing[$classId][$p]);
                        $deleted++;
                    }
                    continue;
                }

                if ($assignmentId === 0 && !$current) {
                    continue;
                }

                if ($assignmentId === 0 && $current) {
                    if ((int)$current['locked'] === 1) {
                        $errors[] = "{$class['name']} Jam ke-{$p} terkunci.";
                        continue;
                    }
                    $curAsgn = schedule_assignment_by_id((int)$current['assignment_id']);
                    if ($curAsgn && function_exists('sibling_assignments_for_assignment')) {
                        $siblings = sibling_assignments_for_assignment($curAsgn);
                        foreach ($siblings as $sib) {
                            $sibCId = (int)$sib['class_id'];
                            execute_sql('DELETE FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND locked = 0', [$sibCId, $day, $p]);
                            unset($existing[$sibCId][$p]);
                        }
                    }
                    execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                    unset($existing[$classId][$p]);
                    $deleted++;
                    continue;
                }

                if ($assignmentId === $currentAssignmentId) {
                    if ($current && ($customStart !== null || $customEnd !== null)) {
                        $dbStart = $customStart ?? (string)$current['start_time'];
                        $dbEnd = $customEnd ?? (string)$current['end_time'];
                        if (substr($dbStart, 0, 5) !== substr((string)$current['start_time'], 0, 5) || substr($dbEnd, 0, 5) !== substr((string)$current['end_time'], 0, 5)) {
                            execute_sql('UPDATE lesson_schedules SET start_time = ?, end_time = ?, updated_at = ? WHERE id = ?', [$dbStart, $dbEnd, now_string(), (int)$current['id']]);
                            if (isset($existing[$classId][$p])) {
                                $existing[$classId][$p]['start_time'] = $dbStart;
                                $existing[$classId][$p]['end_time'] = $dbEnd;
                            }
                        }
                    }
                    continue;
                }

                if ($assignmentId > 0) {
                    $assignment = schedule_assignment_by_id($assignmentId);
                    if (!$assignment) {
                        $errors[] = "Pembelajaran tidak valid untuk {$class['name']} jam ke-{$p}.";
                        continue;
                    }
                    if ($current && (int)$current['locked'] === 1) {
                        $errors[] = "{$class['name']} Jam ke-{$p} terkunci.";
                        continue;
                    }

                    // Check teacher conflict across other classes at same day & period
                    $conflictTeacher = fetch_one(
                        'SELECT ls.id, ls.class_id, ls.subject_id, c.name AS class_name, c.physical_group
                         FROM lesson_schedules ls
                         JOIN classes c ON c.id = ls.class_id
                         WHERE ls.teacher_id = ? AND ls.day_of_week = ? AND ls.period_no = ? AND ls.class_id <> ?',
                        [(int)$assignment['teacher_id'], $day, $p, $classId]
                    );
                    if ($conflictTeacher) {
                        $curClassInfo = fetch_one('SELECT name, physical_group FROM classes WHERE id = ?', [$classId]);
                        $isPhysicalGroupMatch = !empty($curClassInfo['physical_group'])
                            && !empty($conflictTeacher['physical_group'])
                            && strcasecmp(trim((string)$curClassInfo['physical_group']), trim((string)$conflictTeacher['physical_group'])) === 0
                            && (int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id'];
                        $isSameSubjectJoint = ((int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id']);
                        if (!$isPhysicalGroupMatch && !$isSameSubjectJoint) {
                            $tName = $assignment['teacher_name'] ?? ('Guru ID ' . $assignment['teacher_id']);
                            $errors[] = "Bentrok Jam ke-{$p}: {$tName} sudah mengajar di {$conflictTeacher['class_name']}.";
                            continue;
                        }
                    }

                    if ($current) {
                        execute_sql(
                            'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                            [(int)$assignment['id'], (int)$assignment['teacher_id'], $classId, (int)$assignment['subject_id'], $startTime, $endTime, now_string(), (int)$current['id']]
                        );
                        $existing[$classId][$p] = [
                            'id' => (int)$current['id'],
                            'assignment_id' => (int)$assignment['id'],
                            'teacher_id' => (int)$assignment['teacher_id'],
                            'class_id' => $classId,
                            'subject_id' => (int)$assignment['subject_id'],
                            'locked' => (int)$current['locked'],
                            'start_time' => $startTime,
                            'end_time' => $endTime
                        ];
                    } else {
                        execute_sql(
                            'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                            [(int)$assignment['id'], (int)$assignment['teacher_id'], $classId, (int)$assignment['subject_id'], $day, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                        );
                        $newId = (int)db()->lastInsertId();
                        $existing[$classId][$p] = [
                            'id' => $newId,
                            'assignment_id' => (int)$assignment['id'],
                            'teacher_id' => (int)$assignment['teacher_id'],
                            'class_id' => $classId,
                            'subject_id' => (int)$assignment['subject_id'],
                            'locked' => 0,
                            'start_time' => $startTime,
                            'end_time' => $endTime
                        ];
                    }

                    if (function_exists('sibling_assignments_for_assignment')) {
                        $siblings = sibling_assignments_for_assignment($assignment);
                        foreach ($siblings as $sib) {
                            $sibClassId = (int)$sib['class_id'];
                            $sibCurrent = fetch_one(
                                'SELECT id, locked FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ?',
                                [$sibClassId, $day, $p]
                            );
                            if ($sibCurrent) {
                                if ((int)$sibCurrent['locked'] !== 1) {
                                    execute_sql(
                                        'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                                        [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $startTime, $endTime, now_string(), (int)$sibCurrent['id']]
                                    );
                                    $existing[$sibClassId][$p] = [
                                        'id' => (int)$sibCurrent['id'],
                                        'assignment_id' => (int)$sib['id'],
                                        'teacher_id' => (int)$sib['teacher_id'],
                                        'class_id' => $sibClassId,
                                        'subject_id' => (int)$sib['subject_id'],
                                        'locked' => (int)$sibCurrent['locked'],
                                        'start_time' => $startTime,
                                        'end_time' => $endTime
                                    ];
                                }
                            } else {
                                execute_sql(
                                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                                    [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $day, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                                );
                                $sibNewId = (int)db()->lastInsertId();
                                $existing[$sibClassId][$p] = [
                                    'id' => $sibNewId,
                                    'assignment_id' => (int)$sib['id'],
                                    'teacher_id' => (int)$sib['teacher_id'],
                                    'class_id' => $sibClassId,
                                    'subject_id' => (int)$sib['subject_id'],
                                    'locked' => 0,
                                    'start_time' => $startTime,
                                    'end_time' => $endTime
                                ];
                            }
                        }
                    }
                    $saved++;
                }
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
            if (str_contains($msg, 'teacher_id') || preg_match("/Duplicate entry '(\d+)-(\d+)-(\d+)'/", $msg, $m)) {
                $tId = isset($m[1]) ? (int)$m[1] : 0;
                $tName = $tId > 0 ? (fetch_one('SELECT name FROM teachers WHERE id = ?', [$tId])['name'] ?? "Guru #{$tId}") : 'Guru';
                $pNo = $m[3] ?? '?';
                flash('danger', "⚠️ Bentrok Jadwal: <strong>" . htmlspecialchars($tName, ENT_QUOTES, 'UTF-8') . "</strong> sudah dijadwalkan pada Jam ke-{$pNo}. Satu guru tidak dapat dijadwalkan mengajar di dua kelas berbeda pada jam yang sama (kecuali kelas gabungan rombel paralel).");
            } else {
                flash('danger', '⚠️ Terjadi bentrok jadwal pada kelas dan jam pelajaran yang dipilih.');
            }
        } else {
            flash('danger', 'Gagal menyimpan jadwal: ' . $msg);
        }
        redirect_to('lesson-schedule', ['grid_day' => $day]);
        return;
    }

    if ($errors) {
        flash('warning', "Jadwal {$dayLabel} tersimpan ({$saved} diperbarui, {$deleted} dihapus), ada catatan: " . implode(' ', $errors));
    } else {
        flash('success', "✅ Jadwal hari {$dayLabel} untuk semua kelas berhasil disimpan! ({$saved} slot diperbarui, {$deleted} dihapus).");
    }
    redirect_to('lesson-schedule', ['grid_day' => $day]);
}

function action_save_teacher_schedule_matrix(): void
{
    require_role(['admin', 'guru']);
    schedule_require_tables();

    $isGuru = user_role() === 'guru';
    $teacherId = $isGuru ? schedule_current_teacher_id() : (int)($_POST['teacher_id'] ?? 0);
    $redirectParams = $isGuru ? [] : ['teacher_id' => $teacherId, 'view' => 'teacher_matrix'];

    if ($teacherId <= 0) {
        flash('danger', 'Data guru tidak valid.');
        redirect_to('lesson-schedule');
    }

    $teacher = fetch_one('SELECT * FROM teachers WHERE id = ?', [$teacherId]);
    if (!$teacher) {
        flash('danger', 'Data guru tidak ditemukan.');
        redirect_to('lesson-schedule');
    }

    $teacherSlots = $_POST['teacher_slot'] ?? [];
    if (!is_array($teacherSlots)) {
        $teacherSlots = [];
    }

    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);
    $days = schedule_days();

    // Fetch valid active assignments for this teacher
    $validAssignments = fetch_all(
        'SELECT ta.id, ta.class_id, ta.subject_id, c.name AS class_name, c.physical_group, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.teacher_id = ? AND ta.active = 1',
        [$teacherId]
    );
    $assignmentMap = [];
    foreach ($validAssignments as $va) {
        $assignmentMap[(int)$va['id']] = $va;
    }

    // Existing schedules for this teacher
    $existing = [];
    foreach (fetch_all('SELECT id, assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked FROM lesson_schedules WHERE teacher_id = ?', [$teacherId]) as $row) {
        $existing[(int)$row['day_of_week']][(int)$row['period_no']] = $row;
    }

    $saved = 0;
    $deleted = 0;
    $errors = [];

    db()->beginTransaction();
    try {
        foreach ($days as $day => $dayName) {
            for ($p = 1; $p <= $maxPeriods; $p++) {
                $assignmentId = (int)($teacherSlots[$day][$p] ?? 0);
                [$defaultStart, $defaultEnd] = schedule_time_for_period($p, $day);
                $startTime = $defaultStart;
                $endTime = $defaultEnd;

                $current = $existing[$day][$p] ?? null;
                $currentAssignmentId = $current ? (int)$current['assignment_id'] : 0;

                $actForPeriod = schedule_get_activity_for_period($day, $p);
                if ($actForPeriod !== null) {
                    if ($assignmentId > 0) {
                        $errors[] = "Hari {$dayName} Jam ke-{$p} adalah Kegiatan Bersama ({$actForPeriod}), tidak dapat diisi jam pelajaran.";
                    }
                    if ($current && (int)$current['locked'] === 0) {
                        execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                        unset($existing[$day][$p]);
                        $deleted++;
                    }
                    continue;
                }

                // 1. Both empty
                if ($assignmentId === 0 && !$current) {
                    continue;
                }

                // 2. Slot cleared by user
                if ($assignmentId === 0 && $current) {
                    if ((int)$current['locked'] === 1) {
                        $errors[] = "Hari {$dayName} Jam ke-{$p} dikunci admin.";
                        continue;
                    }
                    $curAsgn = schedule_assignment_by_id((int)$current['assignment_id']);
                    if ($curAsgn && function_exists('sibling_assignments_for_assignment')) {
                        $siblings = sibling_assignments_for_assignment($curAsgn);
                        foreach ($siblings as $sib) {
                            $sibCId = (int)$sib['class_id'];
                            execute_sql('DELETE FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND locked = 0', [$sibCId, $day, $p]);
                        }
                    }
                    execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                    unset($existing[$day][$p]);
                    $deleted++;
                    continue;
                }

                // 3. Same assignment unchanged
                if ($assignmentId === $currentAssignmentId) {
                    continue;
                }

                // 4. Assigned slot
                if ($assignmentId > 0) {
                    $assignment = $assignmentMap[$assignmentId] ?? null;
                    if (!$assignment) {
                        $errors[] = "Penugasan tidak valid pada Hari {$dayName} Jam ke-{$p}.";
                        continue;
                    }
                    if ($current && (int)$current['locked'] === 1) {
                        $errors[] = "Hari {$dayName} Jam ke-{$p} dikunci admin.";
                        continue;
                    }

                    $classId = (int)$assignment['class_id'];

                    // Check if class is already occupied by another teacher at day & period
                    $conflictClass = fetch_one(
                        'SELECT ls.id, ls.teacher_id, t.name AS teacher_name, c.name AS class_name, c.physical_group, ls.subject_id
                         FROM lesson_schedules ls
                         JOIN teachers t ON t.id = ls.teacher_id
                         JOIN classes c ON c.id = ls.class_id
                         WHERE ls.class_id = ? AND ls.day_of_week = ? AND ls.period_no = ? AND ls.teacher_id <> ?',
                        [$classId, $day, $p, $teacherId]
                    );
                    if ($conflictClass) {
                        $isGroupMatch = !empty($assignment['physical_group'])
                            && !empty($conflictClass['physical_group'])
                            && strcasecmp(trim((string)$assignment['physical_group']), trim((string)$conflictClass['physical_group'])) === 0
                            && (int)$conflictClass['subject_id'] === (int)$assignment['subject_id'];
                        if (!$isGroupMatch) {
                            $errors[] = "Hari {$dayName} Jam ke-{$p}: Kelas {$conflictClass['class_name']} sudah diisi oleh {$conflictClass['teacher_name']}.";
                            continue;
                        }
                    }

                    if ($current) {
                        execute_sql(
                            'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                            [(int)$assignment['id'], $teacherId, $classId, (int)$assignment['subject_id'], $startTime, $endTime, now_string(), (int)$current['id']]
                        );
                    } else {
                        execute_sql(
                            'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                            [(int)$assignment['id'], $teacherId, $classId, (int)$assignment['subject_id'], $day, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                        );
                    }
                    $saved++;

                    // Sync sibling assignments (e.g. parallel classes sharing physical_group)
                    $fullAsgn = schedule_assignment_by_id((int)$assignment['id']);
                    if ($fullAsgn && function_exists('sibling_assignments_for_assignment')) {
                        $siblings = sibling_assignments_for_assignment($fullAsgn);
                        foreach ($siblings as $sib) {
                            $sibClassId = (int)$sib['class_id'];
                            $sibCurrent = fetch_one(
                                'SELECT id, locked FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ?',
                                [$sibClassId, $day, $p]
                            );
                            if ($sibCurrent) {
                                if ((int)$sibCurrent['locked'] !== 1) {
                                    execute_sql(
                                        'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                                        [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $startTime, $endTime, now_string(), (int)$sibCurrent['id']]
                                    );
                                }
                            } else {
                                execute_sql(
                                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                                    [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $day, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                                );
                            }
                        }
                    }
                }
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
            flash('danger', '⚠️ Terjadi bentrok jadwal saat menyimpan ke database.');
        } else {
            flash('danger', 'Gagal menyimpan jadwal: ' . $msg);
        }
        redirect_to('lesson-schedule', $redirectParams);
        return;
    }

    if ($errors) {
        flash('warning', "Jadwal mengajar berhasil diperbarui ({$saved} slot disimpan, {$deleted} slot dikosongkan), terdapat catatan: " . implode('<br>', array_slice($errors, 0, 5)));
    } else {
        flash('success', "✅ Jadwal mengajar mingguan berhasil disimpan! ({$saved} slot diperbarui, {$deleted} slot dikosongkan).");
    }
    redirect_to('lesson-schedule', $redirectParams);
}

function action_save_schedule_grid(): void
{
    require_role(['admin']);
    schedule_require_tables();

    $slots = $_POST['slot'] ?? [];
    if (!is_array($slots)) {
        $slots = [];
    }
    $slotTimes = $_POST['slot_time'] ?? [];
    if (!is_array($slotTimes)) {
        $slotTimes = [];
    }

    $filterClassId = (int)($_POST['grid_class_id'] ?? 0);
    $filterDay = (int)($_POST['grid_day'] ?? 0);
    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);
    $dayLabel = schedule_days()[$filterDay] ?? '-';
    $saved = 0;
    $deleted = 0;
    $errors = [];

    $currentMap = [];
    if ($filterClassId > 0 && $filterDay > 0) {
        foreach (fetch_all('SELECT id, assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked FROM lesson_schedules WHERE class_id = ? AND day_of_week = ?', [$filterClassId, $filterDay]) as $row) {
            $currentMap[(int)$row['period_no']] = $row;
        }
    }

    db()->beginTransaction();
    try {
        for ($p = 1; $p <= $maxPeriods; $p++) {
            $assignmentId = (int)($slots[$filterDay][$p] ?? 0);
            $customStart = schedule_normalize_time($slotTimes[$filterDay][$p]['start'] ?? '') ?? null;
            $customEnd = schedule_normalize_time($slotTimes[$filterDay][$p]['end'] ?? '') ?? null;
            $current = $currentMap[$p] ?? null;
            $currentAssignmentId = $current ? (int)$current['assignment_id'] : 0;

            if ($assignmentId === 0 && !$current) {
                continue;
            }

            if ($assignmentId === $currentAssignmentId) {
                if ($current && ($customStart !== null || $customEnd !== null)) {
                    $dbStart = $customStart ?? (string)$current['start_time'];
                    $dbEnd = $customEnd ?? (string)$current['end_time'];
                    if (substr($dbStart, 0, 5) !== substr((string)$current['start_time'], 0, 5) || substr($dbEnd, 0, 5) !== substr((string)$current['end_time'], 0, 5)) {
                        execute_sql('UPDATE lesson_schedules SET start_time = ?, end_time = ?, updated_at = ? WHERE id = ?', [$dbStart, $dbEnd, now_string(), (int)$current['id']]);
                        $curAsgn = schedule_assignment_by_id((int)$current['assignment_id']);
                        if ($curAsgn && function_exists('sibling_assignments_for_assignment')) {
                            $siblings = sibling_assignments_for_assignment($curAsgn);
                            foreach ($siblings as $sib) {
                                execute_sql('UPDATE lesson_schedules SET start_time = ?, end_time = ?, updated_at = ? WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND locked = 0', [$dbStart, $dbEnd, now_string(), (int)$sib['class_id'], $filterDay, $p]);
                            }
                        }
                    }
                }
                continue;
            }

            if ($current && $assignmentId === 0) {
                if ((int)$current['locked'] === 1) {
                    $errors[] = "Jam ke-{$p} terkunci, tidak bisa dihapus.";
                    continue;
                }
                $curAsgn = schedule_assignment_by_id((int)$current['assignment_id']);
                if ($curAsgn && function_exists('sibling_assignments_for_assignment')) {
                    $siblings = sibling_assignments_for_assignment($curAsgn);
                    foreach ($siblings as $sib) {
                        execute_sql('DELETE FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND locked = 0', [(int)$sib['class_id'], $filterDay, $p]);
                    }
                }
                execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                $deleted++;
                continue;
            }

            if ($assignmentId > 0) {
                $assignment = schedule_assignment_by_id($assignmentId);
                if (!$assignment) {
                    $errors[] = "Pembelajaran tidak valid di jam ke-{$p}.";
                    continue;
                }

                if ($current && (int)$current['locked'] === 1) {
                    $errors[] = "Jam ke-{$p} terkunci, tidak bisa diubah.";
                    continue;
                }

                [$defaultStart, $defaultEnd] = schedule_time_for_period($p, $filterDay);
                $startTime = $customStart ?? $defaultStart;
                $endTime = $customEnd ?? $defaultEnd;

                $conflictClass = fetch_one(
                    'SELECT id FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND id <> ?',
                    [(int)$assignment['class_id'], $filterDay, $p, $current ? (int)$current['id'] : 0]
                );
                if ($conflictClass) {
                    $errors[] = "Kelas sudah ada jadwal di jam ke-{$p}.";
                    continue;
                }

                $conflictTeacher = fetch_one(
                    'SELECT ls.id, ls.class_id, ls.subject_id, c.physical_group
                     FROM lesson_schedules ls
                     JOIN classes c ON c.id = ls.class_id
                     WHERE ls.teacher_id = ? AND ls.day_of_week = ? AND ls.period_no = ? AND ls.id <> ?',
                    [(int)$assignment['teacher_id'], $filterDay, $p, $current ? (int)$current['id'] : 0]
                );
                if ($conflictTeacher) {
                    $curClass = fetch_one('SELECT physical_group FROM classes WHERE id = ?', [(int)$assignment['class_id']]);
                    $isPhysicalGroupMatch = !empty($curClass['physical_group'])
                        && !empty($conflictTeacher['physical_group'])
                        && strcasecmp(trim((string)$curClass['physical_group']), trim((string)$conflictTeacher['physical_group'])) === 0
                        && (int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id'];
                    $isSameSubjectJoint = ((int)$conflictTeacher['subject_id'] === (int)$assignment['subject_id']);
                    if (!$isPhysicalGroupMatch && !$isSameSubjectJoint) {
                        $errors[] = "Guru sudah ada jadwal di jam ke-{$p}.";
                        continue;
                    }
                }

                if ($current) {
                    execute_sql(
                        'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                        [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $startTime, $endTime, now_string(), (int)$current['id']]
                    );
                } else {
                    execute_sql(
                        'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                        [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $filterDay, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                    );
                }

                // Auto-sync sibling classes in physical group (Option 1)
                if (function_exists('sibling_assignments_for_assignment')) {
                    $siblings = sibling_assignments_for_assignment($assignment);
                    foreach ($siblings as $sib) {
                        $sibClassId = (int)$sib['class_id'];
                        $sibCurrent = fetch_one(
                            'SELECT id, locked FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ?',
                            [$sibClassId, $filterDay, $p]
                        );
                        if ($sibCurrent) {
                            if ((int)$sibCurrent['locked'] !== 1) {
                                execute_sql(
                                    'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                                    [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $startTime, $endTime, now_string(), (int)$sibCurrent['id']]
                                );
                            }
                        } else {
                            execute_sql(
                                'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                                [(int)$sib['id'], (int)$sib['teacher_id'], $sibClassId, (int)$sib['subject_id'], $filterDay, $p, $startTime, $endTime, (int)current_user()['id'], now_string(), now_string()]
                            );
                        }
                    }
                }
                $saved++;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
            if (str_contains($msg, 'teacher_id') || preg_match("/Duplicate entry '(\d+)-(\d+)-(\d+)'/", $msg, $m)) {
                $tId = isset($m[1]) ? (int)$m[1] : 0;
                $tName = $tId > 0 ? (fetch_one('SELECT name FROM teachers WHERE id = ?', [$tId])['name'] ?? "Guru #{$tId}") : 'Guru';
                $pNo = $m[3] ?? '?';
                flash('danger', "⚠️ Bentrok Jadwal: <strong>" . htmlspecialchars($tName, ENT_QUOTES, 'UTF-8') . "</strong> sudah dijadwalkan pada Jam ke-{$pNo}.");
            } else {
                flash('danger', '⚠️ Terjadi bentrok jadwal pada kelas dan jam pelajaran yang dipilih.');
            }
        } else {
            flash('danger', 'Gagal menyimpan jadwal: ' . $msg);
        }
        redirect_to('lesson-schedule', ['grid_class_id' => $filterClassId, 'grid_day' => $filterDay]);
        return;
    }

    if ($errors) {
        flash('warning', 'Tersimpan, tapi ada konflik: ' . implode(' ', $errors));
    } else {
        flash('success', "Jadwal {$dayLabel} tersimpan. {$saved} slot diperbarui, {$deleted} dihapus.");
    }
    redirect_to('lesson-schedule', ['grid_class_id' => $filterClassId, 'grid_day' => $filterDay]);
}
