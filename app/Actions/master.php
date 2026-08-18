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
    $school = get_school_profile();
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
        trim((string)$_POST['academic_year']),
        trim((string)$_POST['semester']),
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
        now_string(),
    ];
    execute_sql(
        'UPDATE school_profile SET name = ?, npsn = ?, address = ?, principal_name = ?, principal_nip = ?, academic_year = ?, semester = ?, location_lat = ?, location_lng = ?, attendance_radius_meters = ?, regular_period_minutes = ?, short_period_minutes = ?, short_days = ?, max_periods = ?, start_time = ?, break1_after = ?, break1_minutes = ?, break2_after = ?, break2_minutes = ?, updated_at = ? WHERE id = ?',
        array_merge($data, [(int)$school['id']])
    );
    set_app_setting('promotion.enabled', !empty($_POST['promotion_enabled']) ? '1' : '0');
    flash('success', 'Data sekolah tersimpan.');
    redirect_to('school');
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
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE teachers SET name = ?, nip = ?, nuptk = ?, gender = ?, phone = ?, email = ?, position = ?, telegram_chat_id = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
        $teacherId = $id;
    } else {
        execute_sql(
            'INSERT INTO teachers (name, nip, nuptk, gender, phone, email, position, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
    $data = [
        trim((string)$_POST['name']),
        trim((string)$_POST['grade']),
        trim((string)($_POST['major'] ?? '')),
        (int)($_POST['homeroom_teacher_id'] ?? 0) ?: null,
        trim((string)$_POST['academic_year']),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE classes SET name = ?, grade = ?, major = ?, homeroom_teacher_id = ?, academic_year = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO classes (name, grade, major, homeroom_teacher_id, academic_year, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
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
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['short_name'] ?? '')),
        trim((string)($_POST['group_name'] ?? '')),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE subjects SET name = ?, short_name = ?, group_name = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data mapel tersimpan.');
    redirect_to('subjects');
}

function action_save_assignment(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        (int)$_POST['teacher_id'],
        (int)$_POST['class_id'],
        (int)$_POST['subject_id'],
        trim((string)$_POST['academic_year']),
        trim((string)$_POST['semester']),
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
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        (int)$_POST['student_id'],
        date_ymd((string)($_POST['date'] ?? date('Y-m-d'))),
        trim((string)$_POST['type']),
        trim((string)($_POST['description'] ?? '')),
        (int)($_POST['points'] ?? 0),
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
