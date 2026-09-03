<?php declare(strict_types=1);

function action_delete(string $table, string $returnPage): void
{
    require_role(['admin']);
    $allowed = ['teachers', 'classes', 'students', 'subjects', 'teaching_assignments', 'users', 'daily_journals', 'student_violations'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabel tidak valid.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($table === 'users' && $id === (int)current_user()['id']) {
        throw new RuntimeException('Akun yang sedang login tidak bisa dihapus.');
    }
    execute_sql("DELETE FROM $table WHERE id = ?", [$id]);
    flash('success', 'Data dihapus.');
    redirect_to($returnPage);
}

function action_save_school(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $lat = trim((string)($_POST['location_lat'] ?? ''));
    $lng = trim((string)($_POST['location_lng'] ?? ''));
    $radius = trim((string)($_POST['attendance_radius_meters'] ?? ''));
    $regularMinutes = trim((string)($_POST['regular_period_minutes'] ?? ''));
    $shortMinutes = trim((string)($_POST['short_period_minutes'] ?? ''));
    $shortDays = trim((string)($_POST['short_days'] ?? ''));
    $maxPeriods = trim((string)($_POST['max_periods'] ?? ''));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $break1After = trim((string)($_POST['break1_after'] ?? ''));
    $break1Minutes = trim((string)($_POST['break1_minutes'] ?? ''));
    $break2After = trim((string)($_POST['break2_after'] ?? ''));
    $break2Minutes = trim((string)($_POST['break2_minutes'] ?? ''));
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['npsn'] ?? '')),
        trim((string)($_POST['address'] ?? '')),
        trim((string)($_POST['principal_name'] ?? '')),
        trim((string)($_POST['principal_nip'] ?? '')),
        trim((string)($_POST['academic_year'] ?? current_academic_year())),
        trim((string)($_POST['semester'] ?? current_semester())),
        $lat !== '' ? (float)$lat : null,
        $lng !== '' ? (float)$lng : null,
        $radius !== '' ? (int)$radius : null,
        $regularMinutes !== '' ? (int)$regularMinutes : 35,
        $shortMinutes !== '' ? (int)$shortMinutes : 25,
        $shortDays !== '' ? $shortDays : null,
        $maxPeriods !== '' ? (int)$maxPeriods : 10,
        $startTime !== '' ? $startTime : '07:00',
        $break1After !== '' ? (int)$break1After : 3,
        $break1Minutes !== '' ? (int)$break1Minutes : 15,
        $break2After !== '' ? (int)$break2After : 6,
        $break2Minutes !== '' ? (int)$break2Minutes : 15,
        trim((string)($_POST['village'] ?? '')),
        trim((string)($_POST['district'] ?? '')),
        trim((string)($_POST['regency'] ?? '')),
        trim((string)($_POST['province'] ?? '')),
        now_string(),
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE school_profile SET name = ?, npsn = ?, address = ?, principal_name = ?, principal_nip = ?, academic_year = ?, semester = ?, location_lat = ?, location_lng = ?, attendance_radius_meters = ?, regular_period_minutes = ?, short_period_minutes = ?, short_days = ?, max_periods = ?, start_time = ?, break1_after = ?, break1_minutes = ?, break2_after = ?, break2_minutes = ?, village = ?, district = ?, regency = ?, province = ?, updated_at = ? WHERE id = ?',
            array_slice($data, 0, 24, true) + [$id]
        );
    } else {
        $sql = 'INSERT INTO school_profile (name, npsn, address, principal_name, principal_nip, academic_year, semester, location_lat, location_lng, attendance_radius_meters, regular_period_minutes, short_period_minutes, short_days, max_periods, start_time, break1_after, break1_minutes, break2_after, break2_minutes, village, district, regency, province, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        execute_sql($sql, $data);
    }
    if ($id === 0) {
        set_app_setting('promotion.enabled', !empty($_POST['promotion_enabled']) ? '1' : '0');
    }
    flash('success', 'Data sekolah tersimpan.');
    redirect_to('schools');
}

function action_delete_school(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'ID sekolah tidak valid.');
        redirect_to('schools');
        return;
    }
    $used = (int)(fetch_one('SELECT COUNT(*) AS c FROM classes WHERE school_id = ?', [$id])['c'] ?? 0);
    if ($used > 0) {
        flash('error', 'Sekolah masih dipakai ' . $used . ' kelas. Pindahkan kelas terlebih dahulu.');
        redirect_to('schools');
        return;
    }
    execute_sql('DELETE FROM school_profile WHERE id = ?', [$id]);
    flash('success', 'Sekolah dihapus.');
    redirect_to('schools');
}

function action_save_teacher(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['nip'] ?? '')),
        trim((string)($_POST['nuptk'] ?? '')),
        (string)($_POST['gender'] ?? ''),
        trim((string)($_POST['phone'] ?? '')),
        trim((string)($_POST['email'] ?? '')),
        trim((string)($_POST['position'] ?? '')),
        trim((string)($_POST['telegram_chat_id'] ?? '')),
        isset($_POST['is_bk']) ? 1 : 0,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE teachers SET name = ?, nip = ?, nuptk = ?, gender = ?, phone = ?, email = ?, position = ?, telegram_chat_id = ?, is_bk = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
        $teacherId = $id;
    } else {
        execute_sql(
            'INSERT INTO teachers (name, nip, nuptk, gender, phone, email, position, telegram_chat_id, is_bk, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
        $teacherId = (int)db()->lastInsertId();
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $existingUser = fetch_one('SELECT id FROM users WHERE teacher_id = ?', [$teacherId]);
    if ($existingUser) {
        if ($password !== '') {
            execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$existingUser['id']]);
            flash('success', 'Password guru berhasil diubah.');
        }
    } else {
        if ($username === '') {
            $username = unique_username(generate_username_from_name(trim((string)$_POST['name'])));
        }
        if ($password === '') {
            $password = config('default_teacher_password', 'guru123');
        }
        $taken = fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
        if ($taken) {
            flash('danger', 'Username "' . $username . '" sudah dipakai user lain.');
            redirect_to('teachers');
        }
        execute_sql(
            'INSERT INTO users (username, password_hash, name, role, teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), trim((string)$_POST['name']), 'guru', $teacherId, 1, now_string(), now_string()]
        );
        flash('success', 'Data guru & akun login tersimpan. Username: ' . $username . ' | Password: ' . $password);
    }

    flash('success', 'Data guru tersimpan.');
    redirect_to('teachers');
}

function action_save_class(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $schoolId = (int)($_POST['school_id'] ?? 0);
    if ($schoolId <= 0) {
        flash('error', 'Pilih sekolah terlebih dahulu.');
        redirect_to('classes');
        return;
    }
    $data = [
        trim((string)$_POST['name']),
        trim((string)$_POST['grade']),
        strtoupper(trim((string)($_POST['level'] ?? ''))),
        trim((string)($_POST['major'] ?? '')),
        $schoolId,
        (int)($_POST['homeroom_teacher_id'] ?? 0) ?: null,
        trim((string)$_POST['academic_year']),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE classes SET name = ?, grade = ?, level = ?, major = ?, school_id = ?, homeroom_teacher_id = ?, academic_year = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO classes (name, grade, level, major, school_id, homeroom_teacher_id, academic_year, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Data kelas tersimpan.');
    redirect_to('classes');
}

function action_save_student(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)($_POST['nis'] ?? '')),
        trim((string)($_POST['nisn'] ?? '')),
        trim((string)$_POST['name']),
        (string)($_POST['gender'] ?? ''),
        trim((string)($_POST['birth_place'] ?? '')),
        $_POST['birth_date'] ?: null,
        trim((string)($_POST['religion'] ?? '')),
        trim((string)($_POST['address'] ?? '')),
        trim((string)($_POST['phone'] ?? '')),
        trim((string)($_POST['father_name'] ?? '')),
        trim((string)($_POST['father_occupation'] ?? '')),
        trim((string)($_POST['mother_name'] ?? '')),
        trim((string)($_POST['mother_occupation'] ?? '')),
        trim((string)($_POST['guardian_name'] ?? '')),
        (int)($_POST['class_id'] ?? 0) ?: null,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE students SET nis = ?, nisn = ?, name = ?, gender = ?, birth_place = ?, birth_date = ?, religion = ?, address = NULLIF(?, \'\'), phone = NULLIF(?, \'\'), father_name = NULLIF(?, \'\'), father_occupation = NULLIF(?, \'\'), mother_name = NULLIF(?, \'\'), mother_occupation = NULLIF(?, \'\'), guardian_name = NULLIF(?, \'\'), class_id = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
        $studentId = $id;
    } else {
        execute_sql(
            'INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, religion, address, phone, father_name, father_occupation, mother_name, mother_occupation, guardian_name, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?, ?)',
            $data
        );
        $studentId = (int)db()->lastInsertId();
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $existingUser = fetch_one('SELECT id FROM users WHERE student_id = ?', [$studentId]);
    if ($existingUser) {
        if ($password !== '') {
            execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$existingUser['id']]);
            flash('success', 'Password siswa berhasil diubah.');
        }
    } elseif ($username !== '' && $password !== '') {
        $taken = fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
        if ($taken) {
            flash('danger', 'Username "' . $username . '" sudah dipakai user lain.');
            redirect_to('students');
        }
        execute_sql(
            'INSERT INTO users (username, password_hash, name, role, student_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), trim((string)$_POST['name']), 'siswa', $studentId, 1, now_string(), now_string()]
        );
        flash('success', 'Data siswa & akun login tersimpan. Username: ' . $username);
    }

    flash('success', 'Data siswa tersimpan.');
    redirect_to('students');
}

function action_save_subject(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)$_POST['name']);
    if ($name === '') {
        flash('danger', 'Nama mapel wajib diisi.');
        redirect_to('subjects');
    }
    $levels = array_values(array_intersect((array)($_POST['levels'] ?? []), school_levels()));
    if (!$levels) {
        flash('danger', 'Pilih minimal 1 jenjang untuk mapel (sesuai jenjang kelas yang ada).');
        redirect_to('subjects');
    }
    $levelCsv = implode(',', $levels);
    $data = [
        $name,
        trim((string)($_POST['short_name'] ?? '')),
        trim((string)($_POST['group_name'] ?? '')),
        $levelCsv,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE subjects SET name = ?, short_name = ?, group_name = ?, level = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO subjects (name, short_name, group_name, level, active, updated_at) VALUES (?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data mapel tersimpan.');
    redirect_to('subjects');
}

function action_save_assignment(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    if ($teacherId <= 0 || $classId <= 0 || $subjectId <= 0) {
        flash('danger', 'Guru, kelas, dan mapel wajib dipilih.');
        redirect_to('assignments');
    }
    $academicYear = trim((string)($_POST['academic_year'] ?? ''));
    $semester = trim((string)($_POST['semester'] ?? ''));
    if ($academicYear === '' || $semester === '') {
        flash('danger', 'Tahun ajaran dan semester wajib diisi.');
        redirect_to('assignments');
    }
    $classRow = fetch_one('SELECT name, level FROM classes WHERE id = ?', [$classId]);
    $subjectRow = fetch_one('SELECT name, level FROM subjects WHERE id = ?', [$subjectId]);
    $teacherRow = fetch_one('SELECT id FROM teachers WHERE id = ?', [$teacherId]);
    if (!$classRow || !$subjectRow || !$teacherRow) {
        flash('danger', 'Guru, kelas, atau mapel tidak ditemukan.');
        redirect_to('assignments');
    }
    if (!subject_has_level($subjectRow['level'] ?? null, (string)($classRow['level'] ?? ''))) {
        flash('danger', 'Mapel "' . $subjectRow['name'] . '" tidak berlaku untuk jenjang kelas "' . $classRow['name'] . '".');
        redirect_to('assignments');
    }
    $data = [
        $teacherId,
        $classId,
        $subjectId,
        $academicYear,
        $semester,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE teaching_assignments SET teacher_id = ?, class_id = ?, subject_id = ?, academic_year = ?, semester = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data pembelajaran tersimpan.');
    redirect_to('assignments');
}

function action_save_user(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $role = (string)$_POST['role'];
    if (!in_array($role, ['admin', 'guru', 'siswa'], true)) {
        throw new RuntimeException('Role pengguna tidak valid.');
    }
    $teacherId = $role === 'siswa' ? null : ((int)($_POST['teacher_id'] ?? 0) ?: null);
    $studentId = $role === 'siswa' ? ((int)($_POST['student_id'] ?? 0) ?: null) : null;
    $telegramChatId = $role === 'siswa' ? '' : trim((string)($_POST['telegram_chat_id'] ?? ''));
    if ($role === 'siswa' && !$studentId) {
        throw new RuntimeException('Siswa terkait wajib dipilih untuk akun siswa.');
    }
    $username = trim((string)$_POST['username']);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
        throw new RuntimeException('Username hanya boleh huruf, angka, titik, garis bawah, atau strip, minimal 3 karakter.');
    }
    if ($password !== '') {
        validate_password_strength($password);
    }
    $base = [
        $username,
        trim((string)$_POST['name']),
        trim((string)($_POST['email'] ?? '')),
        $role,
        $teacherId,
        $studentId,
        $telegramChatId,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];

    if ($id > 0) {
        if ($password !== '') {
            execute_sql(
                'UPDATE users SET username = ?, name = ?, email = ?, role = ?, teacher_id = ?, student_id = ?, telegram_chat_id = ?, active = ?, updated_at = ?, password_hash = ? WHERE id = ?',
                array_merge($base, [password_hash($password, PASSWORD_DEFAULT), $id])
            );
        } else {
            execute_sql(
                'UPDATE users SET username = ?, name = ?, email = ?, role = ?, teacher_id = ?, student_id = ?, telegram_chat_id = ?, active = ?, updated_at = ? WHERE id = ?',
                array_merge($base, [$id])
            );
        }
    } else {
        if ($password === '') {
            throw new RuntimeException('Password wajib diisi untuk pengguna baru.');
        }
        execute_sql(
            'INSERT INTO users (username, name, email, role, teacher_id, student_id, telegram_chat_id, active, updated_at, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($base, [password_hash($password, PASSWORD_DEFAULT)])
        );
    }

    flash('success', 'Data pengguna tersimpan.');
    redirect_to('users');
}

function action_save_violation(): void
{
    require_bk();
    $id = (int)($_POST['id'] ?? 0);
    $ruleId = (int)($_POST['rule_id'] ?? 0) ?: null;

    $type = trim((string)($_POST['type'] ?? ''));
    $points = (int)($_POST['points'] ?? 0);

    if ($ruleId) {
        $rule = fetch_one('SELECT * FROM violation_rules WHERE id = ?', [$ruleId]);
        if ($rule) {
            $type = $type ?: (string)$rule['description'];
            $points = $points ?: (int)$rule['points'];
        }
    }

    $data = [
        (int)$_POST['student_id'],
        date_ymd((string)($_POST['date'] ?? date('Y-m-d'))),
        $type,
        trim((string)($_POST['description'] ?? '')),
        $points,
        trim((string)($_POST['action_taken'] ?? '')),
        (int)current_user()['id'],
        now_string(),
    ];
    $violationId = $id;
    if ($id > 0) {
        execute_sql('UPDATE student_violations SET student_id = ?, date = ?, type = ?, description = ?, points = ?, action_taken = ?, created_by = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO student_violations (student_id, date, type, description, points, action_taken, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $data);
        $violationId = (int)db()->lastInsertId();
    }
    if (!empty($_POST['queue_whatsapp']) && function_exists('whatsapp_enqueue_violation_notice') && $violationId > 0) {
        whatsapp_enqueue_violation_notice($violationId, (int)current_user()['id']);
    }
    flash('success', 'Data pelanggaran tersimpan.');
    redirect_to('violations');
}

function action_save_profile(): void
{
    $user = current_user();
    $password = (string)($_POST['password'] ?? '');
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['email'] ?? '')),
        now_string(),
        (int)$user['id'],
    ];
    execute_sql('UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?', $data);
    if ($password !== '') {
        validate_password_strength($password);
        execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$user['id']]);
    }
    flash('success', 'Profil tersimpan.');
    redirect_to('profile');
}

function action_import_bulk(): void
{
    require_role(['admin']);
    $type = (string)($_POST['data_type'] ?? '');
    if (!in_array($type, ['guru', 'siswa'], true)) {
        flash('danger', 'Jenis data tidak valid.');
        redirect_to('import-bulk');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'File CSV tidak berhasil diupload.');
        redirect_to('import-bulk');
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('danger', 'Gagal membaca file CSV.');
        redirect_to('import-bulk');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        flash('danger', 'File CSV kosong atau format tidak valid.');
        redirect_to('import-bulk');
    }

    $header = array_map('strtolower', array_map('trim', $header));
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $defaultPassword = $type === 'guru'
        ? (string)config('default_teacher_password', 'guru123')
        : (string)config('default_student_password', 'siswa123');

    // ── Jadwal pelajaran: handle separately ──
    if ($type === 'jadwal') {
        $dayMap = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'jum\'at' => 5, 'sabtu' => 6];
        // Pre-load lookups
        $allClasses = [];
        foreach (fetch_all('SELECT id, name FROM classes WHERE active = 1') as $c) {
            $allClasses[strtolower(trim($c['name']))] = (int)$c['id'];
        }
        $allSubjects = [];
        foreach (fetch_all('SELECT id, name, short_name FROM subjects WHERE active = 1') as $s) {
            $allSubjects[strtolower(trim($s['name']))] = (int)$s['id'];
            if (!empty($s['short_name'])) {
                $allSubjects[strtolower(trim($s['short_name']))] = (int)$s['id'];
            }
        }
        $allTeachers = [];
        foreach (fetch_all('SELECT id, name FROM teachers WHERE active = 1') as $t) {
            $norm = strtolower(preg_replace('/[^a-z0-9\s]/', '', (string)preg_replace('/\s*,\s*/', ' ', $t['name'])));
            $allTeachers[$norm] = (int)$t['id'];
        }
        $academicYear = (string)current_academic_year();
        $semester = (string)current_semester();

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = array_combine($header, $row);
            if (!$data) { $skipped++; continue; }

            $hari = strtolower(trim((string)($data['hari'] ?? '')));
            $day = $dayMap[$hari] ?? 0;
            if ($day === 0) {
                $errors[] = "Baris $rowNum: hari '$hari' tidak valid, dilewati.";
                $skipped++; continue;
            }
            $periodNo = (int)($data['jam_ke'] ?? 0);
            if ($periodNo < 1 || $periodNo > 12) {
                $errors[] = "Baris $rowNum: jam_ke '$periodNo' tidak valid, dilewati.";
                $skipped++; continue;
            }
            $className = strtolower(trim((string)($data['kelas'] ?? '')));
            $subjectName = strtolower(trim((string)($data['mapel'] ?? '')));
            $teacherName = strtolower(trim((string)($data['guru'] ?? '')));
            $startTime = trim((string)($data['jam_mulai'] ?? '')) ?: null;
            $endTime = trim((string)($data['jam_selesai'] ?? '')) ?: null;

            // Resolve class
            $classId = $allClasses[$className] ?? null;
            if ($classId === null) {
                $errors[] = "Baris $rowNum: kelas '$className' tidak ditemukan, dilewati.";
                $skipped++; continue;
            }
            // Resolve subject
            $subjectId = $allSubjects[$subjectName] ?? null;
            if ($subjectId === null) {
                $errors[] = "Baris $rowNum: mapel '$subjectName' tidak ditemukan, dilewati.";
                $skipped++; continue;
            }
            // Validate subject level matches class level
            $classRow = fetch_one('SELECT name, level FROM classes WHERE id = ?', [$classId]);
            $subjectRow = fetch_one('SELECT name, level FROM subjects WHERE id = ?', [$subjectId]);
            if (!subject_has_level($subjectRow['level'] ?? null, (string)($classRow['level'] ?? ''))) {
                $errors[] = "Baris $rowNum: mapel '{$subjectRow['name']}' tidak berlaku untuk jenjang kelas '{$classRow['name']}', dilewati.";
                $skipped++; continue;
            }
            // Resolve teacher
            $teacherId = null;
            foreach ($allTeachers as $normName => $tid) {
                $tokens = preg_split('/\s+/', $normName) ?: [];
                $searchTokens = preg_split('/\s+/', $teacherName) ?: [];
                if ($tokens && $searchTokens && count(array_intersect($searchTokens, $tokens)) >= min(count($tokens), count($searchTokens))) {
                    $teacherId = $tid;
                    break;
                }
            }
            if ($teacherId === null) {
                $errors[] = "Baris $rowNum: guru '$teacherName' tidak ditemukan, dilewati.";
                $skipped++; continue;
            }

            // Find or create teaching assignment
            $assignment = fetch_one(
                'SELECT id FROM teaching_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND academic_year = ? AND semester = ? ORDER BY id LIMIT 1',
                [$teacherId, $classId, $subjectId, $academicYear, $semester]
            );
            if ($assignment) {
                $assignmentId = (int)$assignment['id'];
            } else {
                execute_sql(
                    'INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
                    [$teacherId, $classId, $subjectId, $academicYear, $semester, now_string()]
                );
                $assignmentId = (int)db()->lastInsertId();
            }

            // Insert schedule
            try {
                execute_sql(
                    'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?)',
                    [$assignmentId, $teacherId, $classId, $subjectId, $day, $periodNo, $startTime, $endTime, now_string(), now_string()]
                );
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = "Baris $rowNum: kelas '$className' jam $periodNo sudah terisi ($hari).";
            }
        }
        fclose($handle);
        $msg = "Import jadwal selesai: $imported berhasil, $skipped dilewati.";
        if ($errors) {
            $msg .= ' Detail: ' . implode(' ', array_slice($errors, 0, 5));
            if (count($errors) > 5) { $msg .= ' ... dan ' . (count($errors) - 5) . ' error lainnya.'; }
        }
        flash($imported > 0 ? 'success' : 'warning', $msg);
        redirect_to('import-bulk');
    }

    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        $data = array_combine($header, $row);
        if (!$data) {
            $skipped++;
            continue;
        }

        $username = trim((string)($data['username'] ?? ''));
        $password = trim((string)($data['password'] ?? '')) ?: $defaultPassword;
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            $errors[] = "Baris $rowNum: nama kosong, dilewati.";
            $skipped++;
            continue;
        }
        if ($username === '') {
            $errors[] = "Baris $rowNum: username kosong, dilewati.";
            $skipped++;
            continue;
        }

        $taken = fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
        if ($taken) {
            $errors[] = "Baris $rowNum: username '$username' sudah ada, dilewati.";
            $skipped++;
            continue;
        }

        if ($type === 'guru') {
            $gender = trim((string)($data['gender'] ?? ''));
            $nip = trim((string)($data['nip'] ?? ''));
            $nuptk = trim((string)($data['nuptk'] ?? ''));
            $phone = trim((string)($data['phone'] ?? ''));
            $email = trim((string)($data['email'] ?? ''));
            $position = trim((string)($data['position'] ?? ''));
            $telegramChatId = trim((string)($data['telegram_chat_id'] ?? ''));

            execute_sql(
                'INSERT INTO teachers (name, nip, nuptk, gender, phone, email, position, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
                [$name, $nip, $nuptk, $gender, $phone, $email, $position, $telegramChatId, now_string()]
            );
            $teacherId = (int)db()->lastInsertId();

            execute_sql(
                'INSERT INTO users (username, password_hash, name, role, teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $name, 'guru', $teacherId, now_string(), now_string()]
            );
        } else {
            $nis = trim((string)($data['nis'] ?? ''));
            $nisn = trim((string)($data['nisn'] ?? ''));
            $gender = trim((string)($data['gender'] ?? ''));
            $birthPlace = trim((string)($data['birth_place'] ?? ''));
            $birthDate = trim((string)($data['birth_date'] ?? '')) ?: null;
            $religion = trim((string)($data['religion'] ?? ''));
            $address = trim((string)($data['address'] ?? ''));
            $phone = trim((string)($data['phone'] ?? ''));
            $fatherName = trim((string)($data['father_name'] ?? ''));
            $fatherOccupation = trim((string)($data['father_occupation'] ?? ''));
            $motherName = trim((string)($data['mother_name'] ?? ''));
            $motherOccupation = trim((string)($data['mother_occupation'] ?? ''));
            $guardianName = trim((string)($data['guardian_name'] ?? ''));
            $className = trim((string)($data['class_name'] ?? ''));

            $classId = null;
            if ($className !== '') {
                $class = fetch_one('SELECT id FROM classes WHERE name = ?', [$className]);
                $classId = $class ? (int)$class['id'] : null;
            }

            execute_sql(
                'INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, religion, address, phone, father_name, father_occupation, mother_name, mother_occupation, guardian_name, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), ?, 1, ?)',
                [$nis, $nisn, $name, $gender, $birthPlace, $birthDate, $religion, $address, $phone, $fatherName, $fatherOccupation, $motherName, $motherOccupation, $guardianName, $classId, now_string()]
            );
            $studentId = (int)db()->lastInsertId();

            execute_sql(
                'INSERT INTO users (username, password_hash, name, role, student_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $name, 'siswa', $studentId, now_string(), now_string()]
            );
        }
        $imported++;
    }
    fclose($handle);

    $msg = "Import selesai: $imported berhasil, $skipped dilewati.";
    if ($errors) {
        $msg .= ' Detail: ' . implode(' ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $msg .= ' ... dan ' . (count($errors) - 5) . ' error lainnya.';
        }
    }
    flash($imported > 0 ? 'success' : 'warning', $msg);
    redirect_to('import-bulk');
}

function action_import_bulk_validate(): void
{
    require_role(['admin']);
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'File CSV tidak berhasil diupload.');
        redirect_to('import-bulk');
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('danger', 'Gagal membaca file CSV.');
        redirect_to('import-bulk');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        flash('danger', 'File CSV kosong atau format tidak valid.');
        redirect_to('import-bulk');
    }
    $header = array_map('strtolower', array_map('trim', $header));

    // Pre-load lookups
    $allClasses = [];
    foreach (fetch_all('SELECT id, name FROM classes WHERE active = 1') as $c) {
        $allClasses[strtolower(trim($c['name']))] = (int)$c['id'];
    }
    $allSubjects = [];
    foreach (fetch_all('SELECT id, name, short_name FROM subjects WHERE active = 1') as $s) {
        $allSubjects[strtolower(trim($s['name']))] = (int)$s['id'];
        if (!empty($s['short_name'])) {
            $allSubjects[strtolower(trim($s['short_name']))] = (int)$s['id'];
        }
    }
    $allTeachers = [];
    foreach (fetch_all('SELECT id, name FROM teachers WHERE active = 1') as $t) {
        $norm = strtolower(preg_replace('/[^a-z0-9\s]/', '', (string)preg_replace('/\s*,\s*/', ' ', $t['name'])));
        $allTeachers[$norm] = (int)$t['id'];
    }
    $dayMap = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'jum\'at' => 5, 'sabtu' => 6];

    $rows = [];
    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;
        $data = array_combine($header, $row);
        if (!$data) { continue; }

        $hari = strtolower(trim((string)($data['hari'] ?? '')));
        $day = $dayMap[$hari] ?? 0;
        $periodNo = (int)($data['jam_ke'] ?? 0);
        $className = strtolower(trim((string)($data['kelas'] ?? '')));
        $subjectName = strtolower(trim((string)($data['mapel'] ?? '')));
        $teacherName = strtolower(trim((string)($data['guru'] ?? '')));
        $startTime = trim((string)($data['jam_mulai'] ?? '')) ?: null;
        $endTime = trim((string)($data['jam_selesai'] ?? '')) ?: null;

        $r = [
            'hari' => $hari, 'jam_ke' => $periodNo, 'kelas' => $className,
            'mapel' => $subjectName, 'guru' => $teacherName,
            'jam_mulai' => $startTime, 'jam_selesai' => $endTime,
            'class_id' => null, 'subject_id' => null, 'teacher_id' => null,
            'valid' => false, 'error' => '',
        ];

        if ($day === 0) { $r['error'] = 'Hari tidak valid'; $rows[] = $r; continue; }
        if ($periodNo < 1 || $periodNo > 12) { $r['error'] = 'Jam ke tidak valid'; $rows[] = $r; continue; }

        $classId = $allClasses[$className] ?? null;
        if ($classId === null) { $r['error'] = 'Kelas tidak ditemukan'; $rows[] = $r; continue; }
        $r['class_id'] = $classId;

        $subjectId = $allSubjects[$subjectName] ?? null;
        if ($subjectId === null) { $r['error'] = 'Mapel tidak ditemukan'; $rows[] = $r; continue; }
        $r['subject_id'] = $subjectId;

        $classRow = fetch_one('SELECT name, level FROM classes WHERE id = ?', [$classId]);
        $subjectRow = fetch_one('SELECT name, level FROM subjects WHERE id = ?', [$subjectId]);
        if (!subject_has_level($subjectRow['level'] ?? null, (string)($classRow['level'] ?? ''))) {
            $r['error'] = 'Mapel "' . $subjectRow['name'] . '" tidak berlaku untuk jenjang kelas "' . $classRow['name'] . '"';
            $rows[] = $r; continue;
        }

        $teacherId = null;
        foreach ($allTeachers as $normName => $tid) {
            $tokens = preg_split('/\s+/', $normName) ?: [];
            $searchTokens = preg_split('/\s+/', $teacherName) ?: [];
            if ($tokens && $searchTokens && count(array_intersect($searchTokens, $tokens)) >= min(count($tokens), count($searchTokens))) {
                $teacherId = $tid;
                break;
            }
        }
        if ($teacherId === null) { $r['error'] = 'Guru tidak ditemukan'; $rows[] = $r; continue; }
        $r['teacher_id'] = $teacherId;

        $r['valid'] = true;
        $rows[] = $r;
    }
    fclose($handle);

    $_SESSION['import_bulk_pending'] = ['type' => 'jadwal', 'rows' => $rows];
    redirect_to('import-bulk');
}

function action_import_bulk_confirm(): void
{
    require_role(['admin']);
    $pending = $_SESSION['import_bulk_pending'] ?? null;
    if (!$pending || $pending['type'] !== 'jadwal') {
        flash('danger', 'Tidak ada data jadwal yang pending.');
        redirect_to('import-bulk');
    }

    $rows = $pending['rows'];
    unset($_SESSION['import_bulk_pending']);

    $dayMap = ['senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'jum\'at' => 5, 'sabtu' => 6];
    $academicYear = (string)current_academic_year();
    $semester = (string)current_semester();
    $imported = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $r) {
        if (!$r['valid']) { $skipped++; continue; }
        $classId = (int)$r['class_id'];
        $subjectId = (int)$r['subject_id'];
        $teacherId = (int)$r['teacher_id'];
        $day = $dayMap[strtolower($r['hari'])] ?? 0;
        $periodNo = (int)$r['jam_ke'];

        // Find or create assignment
        $assignment = fetch_one(
            'SELECT id FROM teaching_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND academic_year = ? AND semester = ? ORDER BY id LIMIT 1',
            [$teacherId, $classId, $subjectId, $academicYear, $semester]
        );
        if ($assignment) {
            $assignmentId = (int)$assignment['id'];
        } else {
            execute_sql(
                'INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
                [$teacherId, $classId, $subjectId, $academicYear, $semester, now_string()]
            );
            $assignmentId = (int)db()->lastInsertId();
        }

        try {
            execute_sql(
                'INSERT INTO lesson_schedules (assignment_id, teacher_id, class_id, subject_id, day_of_week, period_no, start_time, end_time, locked, generated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?)',
                [$assignmentId, $teacherId, $classId, $subjectId, $day, $periodNo, $r['jam_mulai'], $r['jam_selesai'], now_string(), now_string()]
            );
            $imported++;
        } catch (Throwable $e) {
            $skipped++;
            $errors[] = "Baris " . ($i + 2) . ": " . $r['kelas'] . " jam " . $periodNo . " sudah terisi (" . $r['hari'] . ").";
        }
    }

    $msg = "Import jadwal selesai: $imported berhasil, $skipped dilewati.";
    if ($errors) {
        $msg .= ' Detail: ' . implode(' ', array_slice($errors, 0, 5));
        if (count($errors) > 5) { $msg .= ' ... dan ' . (count($errors) - 5) . ' error lainnya.'; }
    }
    flash($imported > 0 ? 'success' : 'warning', $msg);
    redirect_to('import-bulk');
}
