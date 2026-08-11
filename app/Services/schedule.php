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

function schedule_time_for_period(int $period, ?int $dayOfWeek = null): array
{
    if ($dayOfWeek !== null && schedule_is_short_day($dayOfWeek)) {
        $duration = schedule_period_duration($dayOfWeek);
        $gap = 5;
        $startMinute = (7 * 60) + (($period - 1) * ($duration + $gap));
        $endMinute = $startMinute + $duration;
        return [
            sprintf('%02d:%02d', intdiv($startMinute, 60), $startMinute % 60),
            sprintf('%02d:%02d', intdiv($endMinute, 60), $endMinute % 60),
        ];
    }

    $times = schedule_period_times();
    if (isset($times[$period])) {
        return $times[$period];
    }

    $startMinute = (7 * 60) + (($period - 1) * 40);
    $endMinute = $startMinute + 35;
    return [
        sprintf('%02d:%02d', intdiv($startMinute, 60), $startMinute % 60),
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
    $remaining = $remainingMinutes > 0 ? 'sekitar ' . $remainingMinutes . ' menit lagi' : 'sekarang';
    $lines = [
        '<b>Pengingat Jadwal Pelajaran</b>',
        'Assalamu\'alaikum, ' . e($row['teacher_name']) . '.',
        e($row['subject_name']) . ' - Kelas ' . e($row['class_name']),
        e(schedule_days()[(int)$row['day_of_week']] ?? '-') . ', Jam ke-' . (int)$row['period_no'] . ' (' . e($start . ' - ' . $end) . ')',
        'Mulai ' . $remaining . '.',
    ];

    $url = telegram_web_login_url('lesson-schedule');
    if ($url !== '') {
        $lines[] = '<a href="' . e($url) . '">Buka Jadwal Pelajaran</a>';
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

        if (trim((string)($row['teacher_chat_id'] ?? '')) === '') {
            $result['skipped_no_chat']++;
            continue;
        }

        if (!$hasToken) {
            $result['skipped_no_token']++;
            continue;
        }

        $remainingMinutes = (int)ceil($diffSeconds / 60);
        $message = schedule_reminder_message($row, max(0, $remainingMinutes));
        telegram_send_message((string)$row['teacher_chat_id'], $message);
        schedule_log_reminder($row, $date, $startTime, $minutesBefore, $message);
        $result['sent']++;
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
    return array_column_map(fetch_all('SELECT id, name FROM classes WHERE active = 1 ORDER BY grade, name'), 'id', 'name');
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
    require_role(['admin']);
    schedule_require_tables();

    $id = (int)($_POST['id'] ?? 0);
    $assignment = schedule_assignment_by_id((int)($_POST['assignment_id'] ?? 0));
    $day = schedule_normalize_day($_POST['day_of_week'] ?? 1);
    $period = schedule_clamped_int($_POST['period_no'] ?? 1, 1, 12, 1);
    [$defaultStart, $defaultEnd] = schedule_time_for_period($period);
    $startTime = schedule_normalize_time($_POST['start_time'] ?? '') ?? $defaultStart;
    $endTime = schedule_normalize_time($_POST['end_time'] ?? '') ?? $defaultEnd;
    if ($endTime <= $startTime) {
        throw new RuntimeException('Jam selesai harus lebih besar dari jam mulai.');
    }
    $locked = isset($_POST['locked']) ? 1 : 0;
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
        'SELECT id FROM lesson_schedules WHERE teacher_id = ? AND day_of_week = ? AND period_no = ? AND id <> ?',
        [(int)$assignment['teacher_id'], $day, $period, $id]
    );
    if ($conflictTeacher) {
        throw new RuntimeException('Guru sudah memiliki jadwal pada hari dan jam tersebut.');
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

    flash('success', 'Jadwal pelajaran tersimpan.');
    redirect_to('lesson-schedule');
}

function action_delete_lesson_schedule(): void
{
    require_role(['admin']);
    schedule_require_tables();

    execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
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
        render_lesson_schedule_admin_page();
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
        'Request Aktif' => $activeRequestTotal,
        'Hasil Filter' => count($rows),
    ]);
    render_schedule_template_panel();
    render_schedule_generate_panel();
    render_schedule_grid_form();
    render_schedule_filter_panel($filterClassId, $filterTeacherId);
    render_lesson_schedule_form($editSchedule);
    render_lesson_schedule_table($rows, true);
    render_schedule_request_form($editRequest, schedule_teacher_options(), null);
    render_schedule_request_table($requests, true);
}

function render_lesson_schedule_teacher_page(): void
{
    $teacherId = schedule_current_teacher_id();
    $editRequest = [];
    if ((int)($_GET['edit_request'] ?? 0) > 0) {
        $editRequest = schedule_request_by_id((int)$_GET['edit_request']);
    }

    $rows = schedule_rows(['teacher_id' => $teacherId]);
    $requests = schedule_request_rows($teacherId);
    render_schedule_metrics([
        'Slot Mengajar' => count($rows),
        'Request Aktif' => count(array_filter($requests, fn (array $row): bool => (int)$row['active'] === 1)),
    ]);
    render_lesson_schedule_table($rows, false);
    render_schedule_request_form($editRequest, [], $teacherId);
    render_schedule_request_table($requests, true);
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
        <div class="schedule-period-strip">
            <?php foreach (schedule_period_times() as $period => $time): ?>
                <span><strong><?= e($period) ?></strong><?= e($time[0] . '-' . $time[1]) ?></span>
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
            <div class="actions"><button class="button">Tampilkan</button><a class="button" href="<?= e(route_url('lesson-schedule')) ?>">Reset</a></div>
        </form>
    </section>
    <?php
}

function render_lesson_schedule_form(array $edit): void
{
    $period = (int)($edit['period_no'] ?? 1);
    [$defaultStart, $defaultEnd] = schedule_time_for_period($period);
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
    return '<div class="row-actions"><a class="button small" href="' . e(route_url('lesson-schedule', ['edit_schedule' => $id])) . '">Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus jadwal ini?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="delete_lesson_schedule"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function schedule_request_actions(int $id): string
{
    return '<div class="row-actions"><a class="button small" href="' . e(route_url('lesson-schedule', ['edit_request' => $id])) . '">Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus request ini?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="delete_schedule_request"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function render_schedule_grid_form(int $maxPeriod = 8): void
{
    $classes = schedule_class_options();
    $filterClassId = (int)($_GET['grid_class_id'] ?? 0);

    $allAssignments = schedule_assignment_options();
    if ($filterClassId > 0) {
        $assignments = [];
        foreach (fetch_all(
            'SELECT ta.id, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
             FROM teaching_assignments ta
             JOIN teachers t ON t.id = ta.teacher_id
             JOIN classes c ON c.id = ta.class_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.active = 1 AND ta.class_id = ?
             ORDER BY s.name, t.name',
            [$filterClassId]
        ) as $a) {
            $assignments[(string)$a['id']] = $a['subject_name'] . ' - ' . $a['teacher_name'];
        }
    } else {
        $assignments = $allAssignments;
    }

    $days = schedule_days();
    $school = get_school_profile();
    $maxPeriods = (int)($school['max_periods'] ?? 10);

    $existing = [];
    $existingFilter = $filterClassId > 0 ? ['class_id' => $filterClassId] : [];
    $rows = schedule_rows($existingFilter);
    foreach ($rows as $row) {
        $existing[(int)$row['day_of_week']][(int)$row['period_no']] = (int)$row['assignment_id'];
    }
    ?>
    <section class="panel">
        <?php panel_title('Input Grid Jadwal'); ?>
        <form method="get" class="grid four" style="padding:0 16px 8px;">
            <input type="hidden" name="page" value="lesson-schedule">
            <label>Pilih Kelas <select name="grid_class_id" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $cid => $cname): ?>
                    <option value="<?= e($cid) ?>" <?= $filterClassId == (int)$cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select></label>
        </form>
        <form method="post" class="schedule-grid-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_schedule_grid">
            <input type="hidden" name="grid_class_id" value="<?= e($filterClassId) ?>">
            <?php if ($filterClassId > 0 && empty($assignments)): ?>
                <p style="padding:12px 16px;color:var(--muted)">Belum ada pembelajaran untuk kelas ini. Tambahkan di <a href="<?= e(route_url('assignments')) ?>">Data Pembelajaran</a> terlebih dulu.</p>
            <?php else: ?>
            <div class="schedule-grid-scroll">
                <table class="schedule-grid">
                    <thead>
                        <tr>
                            <th class="schedule-grid-period">Jam</th>
                            <?php foreach ($days as $dayNum => $dayLabel): ?>
                                <?php $isShort = schedule_is_short_day($dayNum); ?>
                                <th class="<?= $isShort ? 'schedule-grid-short-day' : '' ?>"><?= e($dayLabel) ?><?= $isShort ? ' *' : '' ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th class="schedule-grid-period"><small>Waktu</small></th>
                            <?php foreach ($days as $dayNum => $dayLabel): ?>
                                <th class="schedule-grid-time-header"></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($p = 1; $p <= $maxPeriods; $p++): ?>
                            <tr>
                                <td class="schedule-grid-period">
                                    <strong>Jam <?= $p ?></strong>
                                </td>
                                <?php foreach ($days as $dayNum => $dayLabel): ?>
                                    <?php [$start, $end] = schedule_time_for_period($p, $dayNum); ?>
                                    <td>
                                        <div class="schedule-grid-time"><?= e($start . '-' . $end) ?></div>
                                        <select name="slot[<?= $dayNum ?>][<?= $p ?>]" class="schedule-grid-select">
                                            <option value="">-</option>
                                            <?php foreach ($assignments as $id => $label): ?>
                                                <option value="<?= e($id) ?>" <?= ($existing[$dayNum][$p] ?? 0) == (int)$id ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php if (schedule_is_short_day(5) || schedule_is_short_day(6)): ?>
                <div style="padding:4px 16px"><small style="color:var(--muted)">* Hari pendek (jam pelajaran lebih singkat)</small></div>
            <?php endif; ?>
            <div class="schedule-grid-actions">
                <button class="button primary">Simpan Semua Jadwal</button>
                <a class="button" href="<?= e(route_url('lesson-schedule')) ?>">Reset</a>
                <span class="hint">Pilih mapel di tiap sel. Kosongkan untuk menghapus slot.</span>
            </div>
            <?php endif; ?>
        </form>
    </section>
    <?php
}

function action_save_schedule_grid(): void
{
    require_role(['admin']);
    schedule_require_tables();

    $slots = $_POST['slot'] ?? [];
    if (!is_array($slots)) {
        $slots = [];
    }

    $filterClassId = (int)($_POST['grid_class_id'] ?? 0);
    $days = schedule_days();
    $saved = 0;
    $deleted = 0;
    $errors = [];

    $whereClass = $filterClassId > 0 ? ' AND class_id = ' . $filterClassId : '';
    $allCurrent = fetch_all("SELECT id, assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked FROM lesson_schedules WHERE 1=1 $whereClass");
    $currentMap = [];
    foreach ($allCurrent as $row) {
        $currentMap[(int)$row['day_of_week']][(int)$row['period_no']] = $row;
    }

    $periodTimes = schedule_period_times();

    foreach ($days as $dayNum => $dayLabel) {
        for ($p = 1; $p <= 12; $p++) {
            $assignmentId = (int)($slots[$dayNum][$p] ?? 0);
            $current = $currentMap[$dayNum][$p] ?? null;
            $currentAssignmentId = $current ? (int)$current['assignment_id'] : 0;

            if ($assignmentId === $currentAssignmentId) {
                continue;
            }

            if ($current && $assignmentId === 0) {
                if ((int)$current['locked'] === 1) {
                    $errors[] = "Slot {$dayLabel} jam ke-{$p} terkunci, tidak bisa dihapus.";
                    continue;
                }
                execute_sql('DELETE FROM lesson_schedules WHERE id = ?', [(int)$current['id']]);
                $deleted++;
                continue;
            }

            if ($assignmentId > 0) {
                $assignment = schedule_assignment_by_id($assignmentId);
                if (!$assignment) {
                    $errors[] = "Pembelajaran tidak valid di {$dayLabel} jam ke-{$p}.";
                    continue;
                }

                if ($current && (int)$current['locked'] === 1) {
                    $errors[] = "Slot {$dayLabel} jam ke-{$p} terkunci, tidak bisa diubah.";
                    continue;
                }

                [$defaultStart, $defaultEnd] = schedule_time_for_period($p, $dayNum);

                $conflictClass = fetch_one(
                    'SELECT id FROM lesson_schedules WHERE class_id = ? AND day_of_week = ? AND period_no = ? AND id <> ?',
                    [(int)$assignment['class_id'], $dayNum, $p, $current ? (int)$current['id'] : 0]
                );
                if ($conflictClass) {
                    $errors[] = "Kelas sudah ada jadwal di {$dayLabel} jam ke-{$p}.";
                    continue;
                }

                $conflictTeacher = fetch_one(
                    'SELECT id FROM lesson_schedules WHERE teacher_id = ? AND day_of_week = ? AND period_no = ? AND id <> ?',
                    [(int)$assignment['teacher_id'], $dayNum, $p, $current ? (int)$current['id'] : 0]
                );
                if ($conflictTeacher) {
                    $errors[] = "Guru sudah ada jadwal di {$dayLabel} jam ke-{$p}.";
                    continue;
                }

                if ($current) {
                    execute_sql(
                        'UPDATE lesson_schedules SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, start_time = ?, end_time = ?, updated_at = ? WHERE id = ?',
                        [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $defaultStart, $defaultEnd, now_string(), (int)$current['id']]
                    );
                } else {
                    execute_sql(
                        'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                        [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $dayNum, $p, $defaultStart, $defaultEnd, (int)current_user()['id'], now_string(), now_string()]
                    );
                }
                $saved++;
            }
        }
    }

    if ($errors) {
        flash('warning', 'Tersimpan, tapi ada konflik: ' . implode(' ', $errors));
    } else {
        flash('success', "Jadwal tersimpan. {$saved} slot diperbarui, {$deleted} slot dihapus.");
    }
    $redirectParams = $filterClassId > 0 ? ['grid_class_id' => $filterClassId] : [];
    redirect_to('lesson-schedule', $redirectParams);
}
