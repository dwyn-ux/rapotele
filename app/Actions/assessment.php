<?php declare(strict_types=1);

function action_save_grades(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    foreach ((array)($_POST['score'] ?? []) as $studentId => $score) {
        $studentId = (int)$studentId;
        require_student_in_assignment_class($studentId, $assignment);
        $scoreValue = trim((string)$score) === '' ? null : (float)$score;
        $description = trim((string)(($_POST['description'][$studentId] ?? '')));
        $existing = fetch_one('SELECT id FROM grades WHERE assignment_id = ? AND student_id = ?', [(int)$assignment['id'], $studentId]);
        if ($existing) {
            execute_sql('UPDATE grades SET score = ?, description = ?, created_by = ?, updated_at = ? WHERE id = ?', [$scoreValue, $description, (int)current_user()['id'], now_string(), (int)$existing['id']]);
        } else {
            execute_sql('INSERT INTO grades (assignment_id, student_id, score, description, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [(int)$assignment['id'], $studentId, $scoreValue, $description, (int)current_user()['id'], now_string()]);
        }
    }
    flash('success', 'Nilai tersimpan.');
    redirect_to('grades', ['assignment_id' => (int)$assignment['id']]);
}

function action_save_student_attendance(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $date = date_ymd((string)$_POST['date']);
    $meetingNo = max(1, (int)($_POST['meeting_no'] ?? 1));
    $topic = trim((string)($_POST['topic'] ?? 'Absensi'));
    $sessionId = save_attendance_session((int)$assignment['id'], $date, $meetingNo, $topic, (int)current_user()['id']);
    $queueWhatsapp = !empty($_POST['queue_whatsapp_absence']) && function_exists('whatsapp_enqueue_attendance_notice');

    foreach ((array)($_POST['status'] ?? []) as $studentId => $status) {
        $studentId = (int)$studentId;
        require_student_in_assignment_class($studentId, $assignment);
        $status = strtolower((string)$status);
        if (!array_key_exists($status, allowed_statuses())) {
            $status = 'hadir';
        }
        $notes = trim((string)(($_POST['notes'][$studentId] ?? '')));
        save_student_attendance_entry($sessionId, $studentId, $status, $notes);
        if ($queueWhatsapp && $status !== 'hadir') {
            $entry = fetch_one('SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?', [$sessionId, $studentId]);
            if ($entry) {
                whatsapp_enqueue_attendance_notice((int)$entry['id'], (int)current_user()['id']);
            }
        }
    }

    flash('success', 'Absensi siswa tersimpan.');
    redirect_to('student-attendance', ['assignment_id' => (int)$assignment['id'], 'date' => $date, 'meeting_no' => $meetingNo]);
}

function action_save_teacher_attendance(): void
{
    require_role(['admin', 'guru']);
    teacher_teaching_attendance_ensure_schema();
    $date = date_ymd((string)$_POST['date']);
    foreach ((array)($_POST['status'] ?? []) as $scheduleId => $status) {
        $schedule = teacher_teaching_schedule_by_id((int)$scheduleId);
        if (!$schedule) {
            continue;
        }
        if ((int)$schedule['day_of_week'] !== teacher_teaching_day_for_date($date)) {
            continue;
        }
        $status = strtolower((string)$status);
        if (!array_key_exists($status, teacher_attendance_statuses())) {
            $status = 'hadir';
        }
        $timeIn = trim((string)(($_POST['time_in'][$schedule['schedule_id']] ?? ''))) ?: null;
        $timeOut = trim((string)(($_POST['time_out'][$schedule['schedule_id']] ?? ''))) ?: null;
        $notes = trim((string)(($_POST['notes'][$schedule['schedule_id']] ?? '')));
        $base = [
            (int)$schedule['schedule_id'],
            (int)$schedule['assignment_id'],
            (int)$schedule['teacher_id'],
            (int)$schedule['class_id'],
            (int)$schedule['subject_id'],
            $date,
            $status,
            $timeIn,
            $timeOut,
            $notes,
            (int)current_user()['id'],
            now_string(),
        ];
        $existing = fetch_one('SELECT id FROM teacher_teaching_attendance WHERE schedule_id = ? AND date = ?', [(int)$schedule['schedule_id'], $date]);
        if ($existing) {
            execute_sql(
                'UPDATE teacher_teaching_attendance SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, status = ?, time_in = ?, time_out = ?, notes = ?, recorded_by = ?, updated_at = ? WHERE id = ?',
                [(int)$schedule['assignment_id'], (int)$schedule['teacher_id'], (int)$schedule['class_id'], (int)$schedule['subject_id'], $status, $timeIn, $timeOut, $notes, (int)current_user()['id'], now_string(), (int)$existing['id']]
            );
        } else {
            execute_sql(
                'INSERT INTO teacher_teaching_attendance (schedule_id, assignment_id, teacher_id, class_id, subject_id, date, status, time_in, time_out, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $base
            );
        }
    }
    flash('success', 'Absensi mengajar guru tersimpan.');
    redirect_to('teacher-attendance', ['date' => $date]);
}

function action_save_teacher_attendance_self(): void
{
    require_role(['guru']);
    $user = current_user();
    $teacherId = (int)($user['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        throw new RuntimeException('Akun ini belum terhubung dengan data guru.');
    }
    $date = date('Y-m-d');
    $type = (string)($_POST['type'] ?? 'checkin');
    $time = date('H:i:s');
    $lat = trim((string)($_POST['lat'] ?? ''));
    $lng = trim((string)($_POST['lng'] ?? ''));
    $school = get_school_profile();
    $schoolLat = (float)($school['location_lat'] ?? 0);
    $schoolLng = (float)($school['location_lng'] ?? 0);
    $radius = (int)($school['attendance_radius_meters'] ?? 500);

    if ($schoolLat === 0.0 || $schoolLng === 0.0) {
        flash('danger', 'Lokasi sekolah belum diatur oleh admin.');
        redirect_to('teacher-attendance-self');
    }
    if ($lat === '' || $lng === '') {
        flash('danger', 'Lokasi tidak terdeteksi. Izinkan akses lokasi di browser.');
        redirect_to('teacher-attendance-self');
    }
    if (!is_within_radius((float)$lat, (float)$lng, $schoolLat, $schoolLng, $radius)) {
        $distance = haversine_distance((float)$lat, (float)$lng, $schoolLat, $schoolLng);
        flash('danger', 'Anda berada di luar radius absensi (' . $radius . ' m). Jarak Anda: ' . round($distance) . ' m dari sekolah.');
        redirect_to('teacher-attendance-self');
    }
    $existing = fetch_one('SELECT id, time_in, time_out FROM teacher_attendance WHERE teacher_id = ? AND date = ?', [$teacherId, $date]);
    if ($existing) {
        if ($type === 'checkin' && $existing['time_in']) {
            flash('info', 'Anda sudah checkin hari ini.');
            redirect_to('teacher-attendance-self');
        }
        if ($type === 'checkout') {
            execute_sql(
                'UPDATE teacher_attendance SET time_out = ?, location_lat = ?, location_lng = ?, updated_at = ? WHERE id = ?',
                [$time, $lat, $lng, now_string(), (int)$existing['id']]
            );
            flash('success', 'Checkout berhasil pada ' . $time . '.');
            redirect_to('teacher-attendance-self');
        }
        execute_sql(
            'UPDATE teacher_attendance SET time_in = ?, location_lat = ?, location_lng = ?, updated_at = ? WHERE id = ?',
            [$time, $lat, $lng, now_string(), (int)$existing['id']]
        );
    } else {
        execute_sql(
            'INSERT INTO teacher_attendance (teacher_id, date, status, time_in, location_lat, location_lng, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$teacherId, $date, 'hadir', $time, $lat, $lng, 'Absensi mandiri via web', (int)$user['id'], now_string()]
        );
    }
    flash('success', 'Checkin berhasil pada ' . $time . '.');
    redirect_to('teacher-attendance-self');
}

function action_save_journal(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $existing = fetch_one('SELECT assignment_id FROM daily_journals WHERE id = ?', [$id]);
        if (!$existing) {
            throw new RuntimeException('Jurnal tidak ditemukan.');
        }
        require_assignment_access((int)$existing['assignment_id']);
    }
    $data = [
        (int)$assignment['id'],
        (int)$assignment['teacher_id'],
        (int)$assignment['class_id'],
        (int)$assignment['subject_id'],
        date_ymd((string)$_POST['date']),
        max(1, (int)($_POST['meeting_no'] ?? 1)),
        trim((string)$_POST['topic']),
        trim((string)$_POST['activities']),
        trim((string)($_POST['materials'] ?? '')),
        trim((string)($_POST['obstacles'] ?? '')),
        trim((string)($_POST['follow_up'] ?? '')),
        (int)current_user()['id'],
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE daily_journals SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, date = ?, meeting_no = ?, topic = ?, activities = ?, materials = ?, obstacles = ?, follow_up = ?, created_by = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO daily_journals (assignment_id, teacher_id, class_id, subject_id, date, meeting_no, topic, activities, materials, obstacles, follow_up, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Jurnal tersimpan.');
    redirect_to('journals');
}
