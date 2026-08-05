<?php declare(strict_types=1);

function handle_extended_post_action(): bool
{
    $action = (string)($_POST['action'] ?? '');
    if (!app_has_post_action($action)) {
        return false;
    }

    app_handle_post_request();
    return true;
}

function ext_done(mixed $ignored = null): bool
{
    return true;
}

function ext_edit_row(string $table): array
{
    $id = (int)($_GET['edit'] ?? 0);
    return $id > 0 ? (fetch_one("SELECT * FROM $table WHERE id = ?", [$id]) ?: []) : [];
}

function ext_delete_button(string $table, string $page, int $id): string
{
    if (!is_admin()) {
        return '<span class="hint">-</span>';
    }
    return '<div class="row-actions"><a class="button small" href="' . e(route_url($page, ['edit' => $id])) . '">Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus data ini?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="delete_extended">'
        . '<input type="hidden" name="table" value="' . e($table) . '">'
        . '<input type="hidden" name="return_page" value="' . e($page) . '">'
        . '<input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function action_delete_extended(): void
{
    require_role(['admin']);
    $allowed = [
        'extracurriculars', 'extracurricular_members', 'extracurricular_scores',
        'subject_groups', 'merged_subjects', 'report_mappings',
        'signatures', 'report_dates', 'student_photos', 'cocurricular_themes',
        'cocurricular_activities', 'cocurricular_groups', 'learning_objectives',
        'graduations', 'backups',
    ];
    $table = (string)$_POST['table'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Tabel tidak valid.');
    }
    execute_sql("DELETE FROM $table WHERE id = ?", [(int)$_POST['id']]);
    flash('success', 'Data dihapus.');
    redirect_to((string)$_POST['return_page']);
}

function ext_upload(string $field, string $dir): ?string
{
    $fileData = uploaded_file($field, false);
    if (!$fileData) {
        return null;
    }

    $allowed = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];
    $ext = strtolower(pathinfo((string)$fileData['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowed)) {
        throw new RuntimeException('Format file harus JPG, PNG, WEBP, atau GIF.');
    }
    if ((int)($fileData['size'] ?? 0) > (int)config('security.max_image_upload_bytes', 2 * 1024 * 1024)) {
        throw new RuntimeException('Ukuran gambar melebihi batas.');
    }

    $tmpName = (string)$fileData['tmp_name'];
    $mime = detected_mime_type($tmpName);
    if (!in_array($mime, $allowed[$ext], true)) {
        throw new RuntimeException('Isi file tidak sesuai dengan format gambar.');
    }
    if (@getimagesize($tmpName) === false) {
        throw new RuntimeException('File gambar tidak valid.');
    }

    $safeDir = safe_relative_path($dir);
    $root = storage_path('uploads/' . $safeDir);
    ensure_directory($root);
    $file = date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $root . '/' . $file;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Upload file gagal.');
    }
    return 'storage/uploads/' . $safeDir . '/' . $file;
}

function action_save_extracurricular(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['class_name']),
        trim((string)($_POST['type'] ?? '')),
        trim((string)$_POST['name']),
        (int)($_POST['teacher_id'] ?? 0) ?: null,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE extracurriculars SET class_name = ?, type = ?, name = ?, teacher_id = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO extracurriculars (class_name, type, name, teacher_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data ekskul tersimpan.');
    redirect_to('data-ekskul');
}

function action_save_extracurricular_scores(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_POST['class_id'] ?? 0);
    $scores = $_POST['scores'] ?? [];
    foreach ($scores as $studentId => $ekskulScores) {
        foreach ($ekskulScores as $ekskulId => $score) {
            $studentId = (int)$studentId;
            $ekskulId = (int)$ekskulId;
            $scoreVal = trim((string)$score);
            if ($studentId > 0 && $ekskulId > 0) {
                $existing = fetch_one('SELECT id FROM extracurricular_scores WHERE student_id = ? AND extracurricular_id = ?', [$studentId, $ekskulId]);
                if ($existing) {
                    execute_sql('UPDATE extracurricular_scores SET score = ?, updated_at = ? WHERE id = ?', [$scoreVal, now_string(), (int)$existing['id']]);
                } else {
                    execute_sql('INSERT INTO extracurricular_scores (student_id, extracurricular_id, score, updated_at) VALUES (?, ?, ?, ?)', [$studentId, $ekskulId, $scoreVal, now_string()]);
                }
            }
        }
    }
    flash('success', 'Nilai ekstrakurikuler tersimpan.');
    redirect_to('input-nilai-ekskul');
}

function action_save_subject_group(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [trim((string)$_POST['code']), trim((string)$_POST['name']), (string)$_POST['status'], (int)($_POST['display_order'] ?? 0), now_string()];
    if ($id > 0) {
        execute_sql('UPDATE subject_groups SET code = ?, name = ?, status = ?, display_order = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO subject_groups (code, name, status, display_order, updated_at) VALUES (?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Kelompok mapel tersimpan.');
    redirect_to('data-kelompok');
}

function action_save_merged_subject(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [trim((string)$_POST['grade']), (int)$_POST['source_subject_id'], (int)$_POST['target_subject_id'], now_string()];
    if ($id > 0) {
        execute_sql('UPDATE merged_subjects SET grade = ?, source_subject_id = ?, target_subject_id = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO merged_subjects (grade, source_subject_id, target_subject_id, updated_at) VALUES (?, ?, ?, ?)', $data);
    }
    flash('success', 'Gabung mapel tersimpan.');
    redirect_to('gabung-mapel');
}

function action_save_report_mapping(string $page = 'data-mapping'): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['curriculum']),
        trim((string)$_POST['grade']),
        (int)$_POST['subject_id'],
        (int)($_POST['group_id'] ?? 0) ?: null,
        (int)($_POST['display_order'] ?? 0),
        isset($_POST['include_in_report']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE report_mappings SET curriculum = ?, grade = ?, subject_id = ?, group_id = ?, display_order = ?, include_in_report = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO report_mappings (curriculum, grade, subject_id, group_id, display_order, include_in_report, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Mapping mapel tersimpan.');
    redirect_to($page);
}

function action_save_signature(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $file = ext_upload('userfile', 'signatures');
    $type = (string)$_POST['type'];
    if (!in_array($type, ['logo', 'logo_dinas', 'ttd_kepsek', 'ttd_wali', 'stempel'], true)) {
        throw new RuntimeException('Tipe logo/TTD tidak valid.');
    }
    $data = [
        $type,
        (int)($_POST['user_id'] ?? 0) ?: null,
        trim((string)($_POST['title'] ?? '')),
        trim((string)($_POST['person_name'] ?? '')),
        trim((string)($_POST['nip'] ?? '')),
        now_string(),
    ];
    if ($id > 0) {
        $sql = $file
            ? 'UPDATE signatures SET type = ?, user_id = ?, title = ?, person_name = ?, nip = ?, updated_at = ?, file_path = ? WHERE id = ?'
            : 'UPDATE signatures SET type = ?, user_id = ?, title = ?, person_name = ?, nip = ?, updated_at = ? WHERE id = ?';
        execute_sql($sql, $file ? array_merge($data, [$file, $id]) : array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO signatures (type, user_id, title, person_name, nip, updated_at, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($data, [$file]));
    }
    flash('success', 'Logo/TTD tersimpan.');
    redirect_to('data-logo-ttd');
}

function action_save_report_date(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['grade']),
        date_ymd((string)$_POST['report_date']),
        trim((string)($_POST['homeroom_place'] ?? '')),
        trim((string)($_POST['principal_place'] ?? '')),
        trim((string)($_POST['note'] ?? '')),
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE report_dates SET grade = ?, report_date = ?, homeroom_place = ?, principal_place = ?, note = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO report_dates (grade, report_date, homeroom_place, principal_place, note, updated_at) VALUES (?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Tanggal rapor tersimpan.');
    redirect_to('tanggal-rapor');
}

function action_save_student_photo(): void
{
    require_role(['admin']);
    $studentId = (int)$_POST['student_id'];
    $file = ext_upload('userfile', 'student-photos');
    if (!$file) {
        throw new RuntimeException('Pilih file foto siswa.');
    }
    $existing = fetch_one('SELECT id FROM student_photos WHERE student_id = ?', [$studentId]);
    if ($existing) {
        execute_sql('UPDATE student_photos SET file_path = ?, updated_at = ? WHERE student_id = ?', [$file, now_string(), $studentId]);
    } else {
        execute_sql('INSERT INTO student_photos (student_id, file_path, updated_at) VALUES (?, ?, ?)', [$studentId, $file, now_string()]);
    }
    flash('success', 'Foto siswa tersimpan.');
    redirect_to('foto-siswa');
}

function action_save_cocurricular_theme(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [trim((string)$_POST['name']), (string)$_POST['status'], now_string()];
    if ($id > 0) {
        execute_sql('UPDATE cocurricular_themes SET name = ?, status = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO cocurricular_themes (name, status, updated_at) VALUES (?, ?, ?)', $data);
    }
    flash('success', 'Tema kokurikuler tersimpan.');
    redirect_to('tema-kokurikuler');
}

function action_save_cocurricular_activity(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [(int)$_POST['theme_id'], trim((string)$_POST['phase']), trim((string)$_POST['title']), trim((string)($_POST['description'] ?? '')), trim((string)($_POST['objective'] ?? '')), isset($_POST['active']) ? 1 : 0, now_string()];
    if ($id > 0) {
        execute_sql('UPDATE cocurricular_activities SET theme_id = ?, phase = ?, title = ?, description = ?, objective = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO cocurricular_activities (theme_id, phase, title, description, objective, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Kegiatan kokurikuler tersimpan.');
    redirect_to('kegiatan-kokurikuler');
}

function action_save_cocurricular_group(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [trim((string)$_POST['name']), trim((string)$_POST['grade']), trim((string)$_POST['phase']), (int)($_POST['theme_id'] ?? 0) ?: null, (int)($_POST['coordinator_teacher_id'] ?? 0) ?: null, isset($_POST['active']) ? 1 : 0, now_string()];
    if ($id > 0) {
        execute_sql('UPDATE cocurricular_groups SET name = ?, grade = ?, phase = ?, theme_id = ?, coordinator_teacher_id = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
        $groupId = $id;
    } else {
        execute_sql('INSERT INTO cocurricular_groups (name, grade, phase, theme_id, coordinator_teacher_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
        $groupId = (int)db()->lastInsertId();
    }
    execute_sql('DELETE FROM cocurricular_members WHERE group_id = ?', [$groupId]);
    foreach ((array)($_POST['members'] ?? []) as $studentId) {
        execute_sql('INSERT INTO cocurricular_members (group_id, student_id) VALUES (?, ?)', [$groupId, (int)$studentId]);
    }
    flash('success', 'Kelompok kokurikuler tersimpan.');
    redirect_to('kelompok-kokurikuler');
}

function action_save_learning_objective(): void
{
    require_role(['admin', 'guru']);
    $id = (int)($_POST['id'] ?? 0);
    $className = trim((string)($_POST['grade'] ?? ''));
    if ($className === '' || !array_key_exists($className, learning_objective_class_options())) {
        throw new RuntimeException('Kelas tidak valid.');
    }
    $data = [(int)$_POST['subject_id'], $className, trim((string)$_POST['description']), isset($_POST['active']) ? 1 : 0, now_string()];
    if ($id > 0) {
        execute_sql('UPDATE learning_objectives SET subject_id = ?, grade = ?, description = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO learning_objectives (subject_id, grade, description, active, updated_at) VALUES (?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Tujuan pembelajaran tersimpan.');
    redirect_to('data-tp');
}

function learning_objective_class_options(): array
{
    return array_column_map(classes_for_current_user(), 'name', 'name');
}

function action_save_graduation(): void
{
    require_role(['admin']);
    $studentId = (int)$_POST['student_id'];
    $data = [(string)$_POST['status'], trim((string)($_POST['certificate_no'] ?? '')), trim((string)($_POST['transcript_no'] ?? '')), $_POST['graduation_date'] ?: null, trim((string)($_POST['notes'] ?? '')), now_string()];
    $existing = fetch_one('SELECT id FROM graduations WHERE student_id = ?', [$studentId]);
    if ($existing) {
        execute_sql('UPDATE graduations SET status = ?, certificate_no = ?, transcript_no = ?, graduation_date = ?, notes = ?, updated_at = ? WHERE student_id = ?', array_merge($data, [$studentId]));
    } else {
        execute_sql('INSERT INTO graduations (status, certificate_no, transcript_no, graduation_date, notes, updated_at, student_id) VALUES (?, ?, ?, ?, ?, ?, ?)', array_merge($data, [$studentId]));
    }
    flash('success', 'Data kelulusan tersimpan.');
    redirect_to('input-kelulusan');
}

function action_import_certificate_numbers(): void
{
    require_role(['admin']);
    $fileData = uploaded_file('userfile');
    $ext = strtolower(pathinfo((string)$fileData['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        throw new RuntimeException('File import harus CSV.');
    }
    $handle = fopen((string)$fileData['tmp_name'], 'r');
    if (!$handle) {
        throw new RuntimeException('File tidak bisa dibaca.');
    }
    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 4 || strtolower((string)$row[0]) === 'nisn') {
            continue;
        }
        $student = fetch_one('SELECT id FROM students WHERE nisn = ? OR nis = ?', [trim((string)$row[0]), trim((string)$row[0])]);
        if (!$student) {
            continue;
        }
        $_POST['student_id'] = $student['id'];
        $existing = fetch_one('SELECT id FROM graduations WHERE student_id = ?', [(int)$student['id']]);
        $params = [trim((string)($row[1] ?? '')), trim((string)($row[2] ?? '')), date_ymd((string)($row[3] ?? date('Y-m-d'))), now_string(), (int)$student['id']];
        if ($existing) {
            execute_sql('UPDATE graduations SET certificate_no = ?, transcript_no = ?, graduation_date = ?, updated_at = ? WHERE student_id = ?', $params);
        } else {
            execute_sql('INSERT INTO graduations (certificate_no, transcript_no, graduation_date, updated_at, student_id) VALUES (?, ?, ?, ?, ?)', $params);
        }
        $count++;
    }
    fclose($handle);
    flash('success', "Import nomor ijazah selesai: $count siswa.");
    redirect_to('import-nomor-ijazah');
}

function action_save_settings(string $returnPage): void
{
    require_role(['admin']);
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['_csrf', 'action'], true)) {
            continue;
        }
        set_app_setting($returnPage . '.' . $key, is_array($value) ? json_encode($value) : (string)$value);
    }
    flash('success', 'Setting tersimpan.');
    redirect_to($returnPage);
}

function action_save_final_scores(): void
{
    require_role(['admin']);
    foreach ((array)($_POST['score'] ?? []) as $studentId => $score) {
        foreach ((array)$score as $subjectId => $value) {
            $existing = fetch_one('SELECT id FROM final_scores WHERE student_id = ? AND subject_id = ?', [(int)$studentId, (int)$subjectId]);
            $scoreValue = trim((string)$value) === '' ? null : (float)$value;
            if ($existing) {
                execute_sql('UPDATE final_scores SET score = ?, updated_at = ? WHERE id = ?', [$scoreValue, now_string(), (int)$existing['id']]);
            } else {
                execute_sql('INSERT INTO final_scores (student_id, subject_id, score, updated_at) VALUES (?, ?, ?, ?)', [(int)$studentId, (int)$subjectId, $scoreValue, now_string()]);
            }
        }
    }
    flash('success', 'Nilai akhir SKL tersimpan.');
    redirect_to('input-nilai-skl', ['class_id' => (int)($_POST['class_id'] ?? 0)]);
}

function action_save_promotion(): void
{
    require_role(['admin', 'guru']);
    if (semester_number() !== '2') {
        flash('danger', 'Fitur naik kelas hanya tersedia di semester genap.');
        redirect_to('naik-kelas');
    }
    $classId = (int)($_GET['class_id'] ?? 0);
    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($classId <= 0) {
        $homeroom = fetch_one('SELECT id FROM classes WHERE homeroom_teacher_id = ? AND active = 1', [$teacherId]);
        $classId = $homeroom ? (int)$homeroom['id'] : 0;
    }
    if ($classId <= 0) {
        flash('danger', 'Anda tidak memiliki akses sebagai wali kelas.');
        redirect_to('naik-kelas');
    }
    require_class_access($classId);
    foreach ((array)($_POST['status'] ?? []) as $studentId => $status) {
        $studentId = (int)$studentId;
        $status = in_array((string)$status, ['naik', 'tinggal'], true) ? (string)$status : 'naik';
        $notes = trim((string)(($_POST['notes'][$studentId] ?? '')));
        $existing = fetch_one('SELECT id FROM graduations WHERE student_id = ?', [$studentId]);
        if ($existing) {
            execute_sql('UPDATE graduations SET status = ?, notes = ?, updated_at = ? WHERE student_id = ?', [$status, $notes, now_string(), $studentId]);
        } else {
            execute_sql('INSERT INTO graduations (student_id, status, notes, updated_at) VALUES (?, ?, ?, ?)', [$studentId, $status, $notes, now_string()]);
        }
    }
    flash('success', 'Status kenaikan kelas tersimpan.');
    redirect_to('naik-kelas', ['class_id' => $classId]);
}
