<?php declare(strict_types=1);

function action_save_grades(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $assessmentValue = array_key_exists('assessment_type', $_POST) ? $_POST['assessment_type'] : 'UH';
    if (!is_string($assessmentValue)) {
        throw new InvalidArgumentException('Jenis penilaian tidak valid.');
    }
    $assessmentType = trim($assessmentValue);
    if (!in_array($assessmentType, array_keys(assessment_type_options()), true)) {
        throw new InvalidArgumentException('Jenis penilaian tidak valid.');
    }

    $objectiveValue = $_POST['learning_objective_id'] ?? null;
    $learningObjectiveId = null;
    if ($objectiveValue !== null && $objectiveValue !== '' && $objectiveValue !== 0 && $objectiveValue !== '0') {
        $learningObjectiveId = positive_int_value($objectiveValue, 'Tujuan pembelajaran tidak valid.');
        $objective = fetch_one(
            'SELECT lo.id FROM learning_objectives lo JOIN classes c ON c.id = ? WHERE lo.id = ? AND lo.subject_id = ? AND lo.grade = c.grade AND lo.active = 1',
            [(int)$assignment['class_id'], $learningObjectiveId, (int)$assignment['subject_id']]
        );
        if (!$objective) {
            throw new InvalidArgumentException('Tujuan pembelajaran tidak valid untuk pembelajaran ini.');
        }
    }

    $scoreInput = $_POST['score'] ?? [];
    if (!is_array($scoreInput)) {
        throw new InvalidArgumentException('Data nilai tidak valid.');
    }
    $rows = [];
    foreach ($scoreInput as $studentKey => $score) {
        $studentId = positive_int_value($studentKey, 'Data siswa tidak valid.');
        require_student_in_assignment_class($studentId, $assignment);
        $rows[] = [
            'student_id' => $studentId,
            'score' => grade_score_value($score),
        ];
    }

    $pdo = db();
    if (!$pdo->beginTransaction()) {
        throw new RuntimeException('Transaksi tidak dapat dimulai.');
    }
    try {
        $userId = (int)(current_user()['id'] ?? 0);
        $updatedAt = now_string();
        foreach ($rows as $row) {
            $studentId = (int)$row['student_id'];
            $params = [(int)$assignment['id'], $studentId, $assessmentType];
            $objectiveSql = 'learning_objective_id IS NULL';
            if ($learningObjectiveId !== null) {
                $objectiveSql = 'learning_objective_id = ?';
                $params[] = $learningObjectiveId;
            }
            $existing = fetch_one(
                'SELECT id FROM grades WHERE assignment_id = ? AND student_id = ? AND assessment_type = ? AND ' . $objectiveSql,
                $params
            );
            if ($existing) {
                execute_sql(
                    'UPDATE grades SET score = ?, created_by = ?, updated_at = ? WHERE id = ?',
                    [$row['score'], $userId, $updatedAt, (int)$existing['id']]
                );
            } else {
                execute_sql(
                    'INSERT INTO grades (assignment_id, student_id, assessment_type, learning_objective_id, score, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [(int)$assignment['id'], $studentId, $assessmentType, $learningObjectiveId, $row['score'], $userId, $updatedAt]
                );
            }
        }
        if (!$pdo->commit()) {
            throw new RuntimeException('Transaksi tidak berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    flash('success', 'Nilai ' . assessment_type_label($assessmentType) . ' tersimpan.');
    redirect_to('grades', ['assignment_id' => (int)$assignment['id'], 'type' => $assessmentType]);
}

function action_save_student_attendance(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $date = date_ymd($_POST['date'] ?? null);
    $meetingNo = positive_int_value(array_key_exists('meeting_no', $_POST) ? $_POST['meeting_no'] : 1, 'Nomor pertemuan tidak valid.');

    // Check if teacher attendance is confirmed for this session
    $session = fetch_one(
        'SELECT id, teacher_attended FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [(int)$assignment['id'], $date, $meetingNo]
    );
    if (!is_admin() && (!$session || empty($session['teacher_attended']))) {
        throw new RuntimeException('Guru pengampu wajib melakukan konfirmasi kehadiran di kelas terlebih dahulu sebelum mengisi absensi siswa.');
    }

    $topicValue = $_POST['topic'] ?? 'Absensi';
    if (!is_scalar($topicValue) && $topicValue !== null) {
        throw new InvalidArgumentException('Topik pertemuan tidak valid.');
    }
    $topic = trim((string)($topicValue ?? 'Absensi'));

    $statusInput = $_POST['status'] ?? [];
    $notesInput = $_POST['notes'] ?? [];
    if (!is_array($statusInput) || !is_array($notesInput)) {
        throw new InvalidArgumentException('Data absensi tidak valid.');
    }
    $rows = [];
    foreach ($statusInput as $studentKey => $status) {
        $studentId = positive_int_value($studentKey, 'Data siswa tidak valid.');
        require_student_in_assignment_class($studentId, $assignment);
        $noteValue = $notesInput[$studentKey] ?? '';
        if (!is_scalar($noteValue) && $noteValue !== null) {
            throw new InvalidArgumentException('Catatan absensi tidak valid.');
        }
        $rows[] = [
            'student_id' => $studentId,
            'status' => allowed_status_value($status, allowed_statuses(), 'Status absensi tidak valid.'),
            'notes' => trim((string)($noteValue ?? '')),
        ];
    }

    $queueWhatsapp = !empty($_POST['queue_whatsapp_absence']) && function_exists('whatsapp_enqueue_attendance_notice');
    $entryIds = [];
    $pdo = db();
    if (!$pdo->beginTransaction()) {
        throw new RuntimeException('Transaksi tidak dapat dimulai.');
    }
    try {
        $sessionId = save_attendance_session((int)$assignment['id'], $date, $meetingNo, $topic, (int)(current_user()['id'] ?? 0));
        // Ensure teacher_attended is confirmed when saving student attendance
        execute_sql('UPDATE student_attendance_sessions SET teacher_attended = 1 WHERE id = ? AND (teacher_attended IS NULL OR teacher_attended = 0)', [$sessionId]);

        foreach ($rows as $row) {
            $studentId = (int)$row['student_id'];
            save_student_attendance_entry($sessionId, $studentId, (string)$row['status'], (string)$row['notes']);
            if ($queueWhatsapp && $row['status'] !== 'hadir') {
                $entry = fetch_one('SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?', [$sessionId, $studentId]);
                if (!$entry) {
                    throw new RuntimeException('Data absensi tidak ditemukan.');
                }
                $entryIds[] = (int)$entry['id'];
            }
        }
        if (!$pdo->commit()) {
            throw new RuntimeException('Transaksi tidak berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    if ($queueWhatsapp) {
        $userId = (int)(current_user()['id'] ?? 0);
        foreach ($entryIds as $entryId) {
            whatsapp_enqueue_attendance_notice($entryId, $userId);
        }
    }

    flash('success', 'Absensi siswa tersimpan.');
    redirect_to('student-attendance', ['assignment_id' => (int)$assignment['id'], 'date' => $date, 'meeting_no' => $meetingNo]);
}

function action_checkin_teacher_classroom(): void
{
    require_role(['admin', 'guru']);
    $assignmentId = positive_int_value($_POST['assignment_id'] ?? 0, 'Pembelajaran tidak valid.');
    $assignment = require_assignment_access($assignmentId);
    $date = date_ymd($_POST['date'] ?? null);
    $meetingNo = positive_int_value($_POST['meeting_no'] ?? 1, 'Nomor pertemuan tidak valid.');
    $teacherStatus = allowed_status_value($_POST['teacher_status'] ?? 'hadir', ['hadir' => 'Hadir Mengajar', 'inval' => 'Guru Pengganti (Inval)'], 'Status guru tidak valid.');
    $timeIn = trim((string)($_POST['teacher_time_in'] ?? date('H:i')));
    $notes = trim((string)($_POST['teacher_notes'] ?? ''));
    $userId = (int)(current_user()['id'] ?? 0);
    $now = now_string();

    $existing = fetch_one(
        'SELECT id FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [(int)$assignment['id'], $date, $meetingNo]
    );

    if ($existing) {
        execute_sql(
            'UPDATE student_attendance_sessions SET teacher_attended = 1, teacher_status = ?, teacher_time_in = ?, teacher_notes = ?, teacher_attended_at = ?, updated_at = ? WHERE id = ?',
            [$teacherStatus, $timeIn, $notes, $now, $now, (int)$existing['id']]
        );
    } else {
        execute_sql(
            'INSERT INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, teacher_attended, teacher_status, teacher_time_in, teacher_notes, teacher_attended_at, created_by, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)',
            [(int)$assignment['id'], $date, $meetingNo, 'Pertemuan ' . $meetingNo, $teacherStatus, $timeIn, $notes, $now, $userId, $now]
        );
    }

    // Synchronize with lesson_schedules & teacher_teaching_attendance if schedule exists
    if (table_exists('lesson_schedules') && table_exists('teacher_teaching_attendance')) {
        try {
            $dayOfWeek = teacher_teaching_day_for_date($date);
            $schedule = fetch_one(
                'SELECT id FROM lesson_schedules WHERE assignment_id = ? AND day_of_week = ? ORDER BY period_no LIMIT 1',
                [(int)$assignment['id'], $dayOfWeek]
            );
            if ($schedule) {
                $existingTta = fetch_one(
                    'SELECT id FROM teacher_teaching_attendance WHERE schedule_id = ? AND date = ?',
                    [(int)$schedule['id'], $date]
                );
                if ($existingTta) {
                    execute_sql(
                        'UPDATE teacher_teaching_attendance SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, status = ?, time_in = ?, notes = ?, recorded_by = ?, updated_at = ? WHERE id = ?',
                        [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], 'hadir', $timeIn, $notes, $userId, $now, (int)$existingTta['id']]
                    );
                } else {
                    execute_sql(
                        'INSERT INTO teacher_teaching_attendance (schedule_id, assignment_id, teacher_id, class_id, subject_id, date, status, time_in, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [(int)$schedule['id'], (int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $date, 'hadir', $timeIn, $notes, $userId, $now]
                    );
                }
            }
        } catch (Throwable) {
            // Silently continue if schedule sync is not strictly applicable
        }
    }

    flash('success', 'Kehadiran guru di kelas berhasil dikonfirmasi. Form absensi siswa sekarang dapat diisi.');
    redirect_to('student-attendance', ['assignment_id' => (int)$assignment['id'], 'date' => $date, 'meeting_no' => $meetingNo]);
}

function action_cancel_teacher_classroom(): void
{
    require_role(['admin']);
    $sessionId = positive_int_value($_POST['session_id'] ?? 0, 'Sesi tidak valid.');
    $session = fetch_one('SELECT * FROM student_attendance_sessions WHERE id = ?', [$sessionId]);
    if (!$session) {
        throw new RuntimeException('Sesi absensi tidak ditemukan.');
    }
    execute_sql(
        'UPDATE student_attendance_sessions SET teacher_attended = 0, teacher_time_in = NULL, teacher_notes = NULL, teacher_attended_at = NULL, updated_at = ? WHERE id = ?',
        [now_string(), $sessionId]
    );
    flash('success', 'Status kehadiran guru berhasil direset.');
    redirect_to('student-attendance', ['assignment_id' => (int)$session['assignment_id'], 'date' => $session['date'], 'meeting_no' => (int)$session['meeting_no']]);
}

function action_save_teacher_attendance(): void
{
    require_role(['admin', 'guru']);
    $date = date_ymd($_POST['date'] ?? null);
    teacher_teaching_attendance_ensure_schema();
    $statusInput = $_POST['status'] ?? [];
    $timeInInput = $_POST['time_in'] ?? [];
    $timeOutInput = $_POST['time_out'] ?? [];
    $notesInput = $_POST['notes'] ?? [];
    if (!is_array($statusInput) || !is_array($timeInInput) || !is_array($timeOutInput) || !is_array($notesInput)) {
        throw new InvalidArgumentException('Data absensi guru tidak valid.');
    }

    $day = teacher_teaching_day_for_date($date);
    $rows = [];
    foreach ($statusInput as $scheduleKey => $status) {
        $scheduleId = positive_int_value($scheduleKey, 'Jadwal mengajar tidak valid.');
        $schedule = teacher_teaching_schedule_by_id($scheduleId);
        if (!$schedule || (int)$schedule['day_of_week'] !== $day) {
            throw new InvalidArgumentException('Jadwal mengajar tidak valid untuk tanggal tersebut.');
        }
        if (!fetch_one(
            'SELECT id FROM teaching_assignments WHERE id = ? AND teacher_id = ? AND class_id = ? AND subject_id = ? AND active = 1',
            [(int)$schedule['assignment_id'], (int)$schedule['teacher_id'], (int)$schedule['class_id'], (int)$schedule['subject_id']]
        )) {
            throw new InvalidArgumentException('Pembelajaran pada jadwal tidak valid.');
        }

        $timeInValue = $timeInInput[$scheduleKey] ?? '';
        $timeOutValue = $timeOutInput[$scheduleKey] ?? '';
        $notesValue = $notesInput[$scheduleKey] ?? '';
        if ((!is_scalar($timeInValue) && $timeInValue !== null)
            || (!is_scalar($timeOutValue) && $timeOutValue !== null)
            || (!is_scalar($notesValue) && $notesValue !== null)
        ) {
            throw new InvalidArgumentException('Data jadwal mengajar tidak valid.');
        }
        $rows[] = [
            'schedule' => $schedule,
            'status' => allowed_status_value($status, teacher_attendance_statuses(), 'Status absensi guru tidak valid.'),
            'time_in' => trim((string)($timeInValue ?? '')) ?: null,
            'time_out' => trim((string)($timeOutValue ?? '')) ?: null,
            'notes' => trim((string)($notesValue ?? '')),
        ];
    }

    $pdo = db();
    if (!$pdo->beginTransaction()) {
        throw new RuntimeException('Transaksi tidak dapat dimulai.');
    }
    try {
        $userId = (int)(current_user()['id'] ?? 0);
        $updatedAt = now_string();
        foreach ($rows as $row) {
            $schedule = $row['schedule'];
            $existing = fetch_one('SELECT id FROM teacher_teaching_attendance WHERE schedule_id = ? AND date = ?', [(int)$schedule['schedule_id'], $date]);
            if ($existing) {
                execute_sql(
                    'UPDATE teacher_teaching_attendance SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, status = ?, time_in = ?, time_out = ?, notes = ?, recorded_by = ?, updated_at = ? WHERE id = ?',
                    [(int)$schedule['assignment_id'], (int)$schedule['teacher_id'], (int)$schedule['class_id'], (int)$schedule['subject_id'], $row['status'], $row['time_in'], $row['time_out'], $row['notes'], $userId, $updatedAt, (int)$existing['id']]
                );
            } else {
                execute_sql(
                    'INSERT INTO teacher_teaching_attendance (schedule_id, assignment_id, teacher_id, class_id, subject_id, date, status, time_in, time_out, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int)$schedule['schedule_id'], (int)$schedule['assignment_id'], (int)$schedule['teacher_id'], (int)$schedule['class_id'], (int)$schedule['subject_id'], $date, $row['status'], $row['time_in'], $row['time_out'], $row['notes'], $userId, $updatedAt]
                );
            }
        }
        if (!$pdo->commit()) {
            throw new RuntimeException('Transaksi tidak berhasil disimpan.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    flash('success', 'Absensi mengajar guru tersimpan.');
    redirect_to('teacher-attendance', ['date' => $date]);
}

function action_save_teacher_attendance_self(): void
{
    require_role(['guru']);
    $user = current_user();
    $teacherId = (int)($user['teacher_id'] ?? 0);
    if ($teacherId <= 0 || !fetch_one('SELECT id FROM teachers WHERE id = ? AND active = 1', [$teacherId])) {
        throw new RuntimeException('Akun ini belum terhubung dengan data guru.');
    }
    $date = date('Y-m-d');
    $type = $_POST['type'] ?? 'checkin';
    if (!is_string($type) || !in_array($type, ['checkin', 'checkout'], true)) {
        throw new InvalidArgumentException('Jenis absensi tidak valid.');
    }
    if (array_key_exists('status', $_POST)) {
        allowed_status_value($_POST['status'], teacher_attendance_statuses(), 'Status absensi guru tidak valid.');
    }
    $time = date('H:i:s');
    $lat = coordinate_value($_POST['lat'] ?? null, -90, 90, 'Latitude tidak valid.');
    $lng = coordinate_value($_POST['lng'] ?? null, -180, 180, 'Longitude tidak valid.');
    $school = get_school_profile();
    $schoolLat = coordinate_value($school['location_lat'] ?? null, -90, 90, 'Latitude sekolah tidak valid.');
    $schoolLng = coordinate_value($school['location_lng'] ?? null, -180, 180, 'Longitude sekolah tidak valid.');
    $radius = (int)($school['attendance_radius_meters'] ?? 500);

    if ($schoolLat === null || $schoolLng === null || $schoolLat === 0.0 || $schoolLng === 0.0) {
        flash('danger', 'Lokasi sekolah belum diatur oleh admin.');
        redirect_to('teacher-attendance-self');
    }
    if ($lat === null || $lng === null) {
        flash('danger', 'Lokasi tidak terdeteksi. Izinkan akses lokasi di browser.');
        redirect_to('teacher-attendance-self');
    }
    if (!is_within_radius($lat, $lng, $schoolLat, $schoolLng, $radius)) {
        $distance = haversine_distance($lat, $lng, $schoolLat, $schoolLng);
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
        date_ymd($_POST['date'] ?? null),
        positive_int_value(array_key_exists('meeting_no', $_POST) ? $_POST['meeting_no'] : 1, 'Nomor pertemuan tidak valid.'),
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
