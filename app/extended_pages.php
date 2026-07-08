<?php

declare(strict_types=1);

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
        'extracurriculars', 'subject_groups', 'merged_subjects', 'report_mappings',
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
    $data = [(int)$_POST['subject_id'], trim((string)$_POST['grade']), trim((string)$_POST['description']), isset($_POST['active']) ? 1 : 0, now_string()];
    if ($id > 0) {
        execute_sql('UPDATE learning_objectives SET subject_id = ?, grade = ?, description = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO learning_objectives (subject_id, grade, description, active, updated_at) VALUES (?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Tujuan pembelajaran tersimpan.');
    redirect_to('data-tp');
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

function backup_tables(): array
{
    return [
        'school_profile', 'teachers', 'classes', 'students', 'subjects', 'teaching_assignments', 'users',
        'learning_objectives', 'grades', 'student_attendance_sessions', 'student_attendance_entries',
        'teacher_attendance', 'student_violations', 'whatsapp_guardians', 'whatsapp_templates',
        'whatsapp_queue', 'whatsapp_logs', 'daily_journals', 'extracurriculars', 'subject_groups', 'merged_subjects',
        'report_mappings', 'signatures', 'report_dates', 'student_photos', 'cocurricular_themes',
        'cocurricular_activities', 'cocurricular_groups', 'cocurricular_members', 'graduations',
        'final_scores', 'app_settings',
    ];
}

function action_create_backup(): void
{
    require_role(['admin']);
    $backup = ['created_at' => now_string(), 'tables' => []];
    foreach (backup_tables() as $table) {
        if (table_exists($table)) {
            $backup['tables'][$table] = fetch_all("SELECT * FROM $table");
        }
    }
    $dir = dirname(__DIR__) . '/storage/backups';
    ensure_directory($dir);
    $file = 'backup-' . date('Ymd-His') . '.json';
    $path = $dir . '/' . $file;
    if (file_put_contents($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('File backup tidak bisa ditulis.');
    }
    execute_sql('INSERT INTO backups (file_name, file_path, status, created_by) VALUES (?, ?, ?, ?)', [$file, 'storage/backups/' . $file, 'ready', (int)current_user()['id']]);
    flash('success', 'Backup dibuat: ' . $file);
    redirect_to('backup-restore');
}

function action_restore_backup(): void
{
    require_role(['admin']);
    $fileData = uploaded_file('userfile');
    $ext = strtolower(pathinfo((string)$fileData['name'], PATHINFO_EXTENSION));
    if ($ext !== 'json') {
        throw new RuntimeException('File backup harus JSON.');
    }
    $payload = json_decode((string)file_get_contents((string)$fileData['tmp_name']), true);
    if (!is_array($payload) || empty($payload['tables']) || !is_array($payload['tables'])) {
        throw new RuntimeException('Format backup tidak valid.');
    }
    db()->beginTransaction();
    try {
        foreach (array_reverse(backup_tables()) as $table) {
            if (isset($payload['tables'][$table]) && table_exists($table)) {
                db()->exec('DELETE FROM ' . db_identifier($table));
            }
        }
        foreach (backup_tables() as $table) {
            if (empty($payload['tables'][$table]) || !table_exists($table)) {
                continue;
            }
            $allowedColumns = table_columns($table);
            foreach ($payload['tables'][$table] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $filtered = [];
                foreach ($row as $column => $value) {
                    if (in_array((string)$column, $allowedColumns, true)) {
                        $filtered[(string)$column] = $value;
                    }
                }
                if (!$filtered) {
                    continue;
                }
                $columns = array_keys($filtered);
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $sql = 'INSERT INTO ' . db_identifier($table) . ' (' . implode(',', array_map('db_identifier', $columns)) . ') VALUES (' . $placeholders . ')';
                execute_sql($sql, array_values($filtered));
            }
        }
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
    flash('success', 'Restore backup selesai.');
    redirect_to('backup-restore');
}

function dapodik_helper_defaults(string $dapodikUrl, string $dapodikToken, string $npsn, string $type = 'all'): array
{
    return [
        'bridge_url' => app_url(''),
        'bridge_token' => '',
        'dapodik_url' => $dapodikUrl !== '' ? $dapodikUrl : 'http://127.0.0.1:5774',
        'dapodik_token' => $dapodikToken,
        'npsn' => $npsn,
        'type' => $type,
    ];
}

function dapodik_helper_download_url(string $download, array $defaults): string
{
    return app_url('dapodik_local_helper.php') . '?' . http_build_query(['download' => $download] + $defaults);
}

function action_save_dapodik_settings(): void
{
    require_role(['admin']);
    $dapodikUrl = normalize_http_url((string)($_POST['url'] ?? ''));
    $dapodikToken = trim((string)($_POST['token'] ?? ''));
    $npsn = trim((string)($_POST['npsn'] ?? ''));
    set_app_setting('dapodik_url', $dapodikUrl);
    set_app_setting('dapodik_token', $dapodikToken);
    set_app_setting('dapodik_npsn', $npsn);
    if (array_key_exists('bridge_token', $_POST)) {
        $bridgeToken = trim((string)($_POST['bridge_token'] ?? ''));
        set_app_setting('dapodik_bridge_token', $bridgeToken !== '' ? $bridgeToken : bin2hex(random_bytes(12)));
    } elseif (get_app_setting('dapodik_bridge_token', '') === '') {
        set_app_setting('dapodik_bridge_token', bin2hex(random_bytes(12)));
    }
    $downloadAfterSave = (string)($_POST['download_after_save'] ?? '');
    if (in_array($downloadAfterSave, ['portable', 'config'], true)) {
        header('Location: ' . dapodik_helper_download_url($downloadAfterSave, dapodik_helper_defaults($dapodikUrl, $dapodikToken, $npsn)));
        exit;
    }
    flash('success', 'Konfigurasi Dapodik tersimpan.');
    redirect_to((string)($_POST['return_page'] ?? 'update-data'));
}

function action_generate_dapodik_bridge_token(): void
{
    require_role(['admin']);
    set_app_setting('dapodik_bridge_token', 'bridge-' . bin2hex(random_bytes(16)));
    flash('success', 'Token bridge baru dibuat.');
    redirect_to('update-data');
}

function dapodik_data_types(bool $includeAll = false): array
{
    $types = [
        'sekolah' => 'Sekolah',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
        'mapel' => 'Mapel',
        'rombel' => 'Rombel',
        'pembelajaran' => 'Pembelajaran',
    ];

    return $includeAll ? ['all' => 'Semua Data Dasar'] + $types : $types;
}

function dapodik_default_sync_types(): array
{
    return ['sekolah', 'guru', 'siswa', 'mapel', 'rombel'];
}

function dapodik_validate_type(string $type, bool $allowAll = false): string
{
    $type = trim($type);
    if (!array_key_exists($type, dapodik_data_types($allowAll))) {
        throw new RuntimeException('Jenis data Dapodik tidak valid.');
    }
    return $type;
}

function dapodik_import_items(array $items): array
{
    $summary = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = dapodik_validate_type((string)($item['type'] ?? ''), false);
        $records = isset($item['data']) && is_array($item['data']) ? $item['data'] : $item;
        unset($records['type'], $records['token'], $records['npsn']);
        $summary[$type] = ($summary[$type] ?? 0) + dapodik_import($type, $records);
    }

    if (!$summary) {
        throw new RuntimeException('Paket semua data Dapodik kosong atau tidak valid.');
    }

    return $summary;
}

function dapodik_summary_text(array $summary): string
{
    $parts = [];
    foreach ($summary as $type => $count) {
        $parts[] = $type . ': ' . (int)$count;
    }
    return implode(', ', $parts);
}

function action_import_dapodik_offline(): void
{
    require_role(['admin']);
    $type = dapodik_validate_type((string)($_POST['data_type'] ?? 'sekolah'), true);
    $jsonText = trim((string)($_POST['json_payload'] ?? ''));
    if (!empty($_FILES['json_file']['tmp_name'])) {
        $fileData = uploaded_file('json_file');
        $ext = strtolower(pathinfo((string)$fileData['name'], PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            throw new RuntimeException('File Dapodik harus JSON.');
        }
        $jsonText = (string)file_get_contents((string)$fileData['tmp_name']);
    }
    if ($jsonText === '') {
        throw new RuntimeException('Pilih file JSON atau tempel payload JSON Dapodik.');
    }
    $payload = json_decode($jsonText, true);
    if (!is_array($payload)) {
        throw new RuntimeException('JSON offline tidak valid.');
    }
    if (isset($payload['type'])) {
        $type = dapodik_validate_type((string)$payload['type'], true);
    }

    if ($type === 'all') {
        $items = isset($payload['items']) && is_array($payload['items'])
            ? $payload['items']
            : (isset($payload['payloads']) && is_array($payload['payloads']) ? $payload['payloads'] : []);
        $summary = dapodik_import_items($items);
        $message = 'Import offline semua data selesai. ' . dapodik_summary_text($summary) . '.';
        execute_sql(
            'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            ['offline-import', 'all', 'manual-json', 'success', $message, (int)current_user()['id']]
        );
        flash('success', $message);
        redirect_to('update-data');
    }

    $records = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    $count = dapodik_import($type, $records);
    execute_sql(
        'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        ['offline-import', $type, 'manual-json', 'success', "Import offline $type selesai. Data diproses: $count.", (int)current_user()['id']]
    );
    flash('success', "Import offline $type selesai. Data diproses: $count.");
    redirect_to('update-data');
}

function action_sync_dapodik(): void
{
    require_role(['admin']);
    $type = dapodik_validate_type((string)($_POST['data_type'] ?? 'sekolah'), true);
    if ($type === 'all') {
        $summary = [];
        $hasError = false;
        foreach (dapodik_default_sync_types() as $singleType) {
            $result = dapodik_fetch($singleType);
            $hasError = $hasError || $result['status'] !== 'success';
            $summary[] = $result['message'];
            execute_sql(
                'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                ['pull', $singleType, $result['endpoint'], $result['status'], $result['message'], (int)current_user()['id']]
            );
        }
        flash($hasError ? 'danger' : 'success', 'Sinkron semua selesai. ' . implode(' ', $summary));
        redirect_to('update-data');
    }

    $result = dapodik_fetch($type);
    execute_sql(
        'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        ['pull', $type, $result['endpoint'], $result['status'], $result['message'], (int)current_user()['id']]
    );
    flash($result['status'] === 'success' ? 'success' : 'danger', $result['message']);
    redirect_to('update-data');
}

function action_send_dapodik(): void
{
    require_role(['admin']);
    $kind = (string)($_POST['kind'] ?? 'nilai');
    if (!in_array($kind, ['matev', 'nilai'], true)) {
        throw new RuntimeException('Jenis payload Dapodik tidak valid.');
    }
    $payload = dapodik_payload($kind);
    $url = normalize_http_url((string)get_app_setting('dapodik_url', ''));
    $endpoint = $url ? rtrim($url, '/') . '/erapor/' . rawurlencode($kind) : '';
    $message = $endpoint ? dapodik_post_payload($endpoint, $payload) : 'Payload ' . $kind . ' disiapkan. Isi URL Dapodik untuk mengirim online.';
    execute_sql('INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)', ['push', $kind, $endpoint, 'queued', $message, (int)current_user()['id']]);
    flash('success', $message);
    redirect_to('kirim-data-dapodik');
}

function dapodik_endpoint_name(string $type): string
{
    $map = [
        'sekolah' => 'getSekolah',
        'guru' => 'getGtk',
        'siswa' => 'getPesertaDidik',
        'rombel' => 'getRombonganBelajar',
        'mapel' => 'getMataPelajaran',
        'pembelajaran' => 'getPembelajaran',
    ];

    return $map[$type] ?? $type;
}

function dapodik_endpoint_url(string $baseUrl, string $type, string $npsn, ?string $token = null): string
{
    $params = ['npsn' => $npsn];
    if ($token !== null && $token !== '') {
        $params['token'] = $token;
    }

    return rtrim($baseUrl, '/') . '/WebService/' . dapodik_endpoint_name($type) . '?' . http_build_query($params);
}

function dapodik_fetch_json(string $endpoint, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
    }

    $ch = curl_init($endpoint);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
        ], $headers),
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $curlOptions);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $error) {
        throw new RuntimeException('Gagal menghubungi Dapodik: ' . $error);
    }
    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        $preview = mb_strimwidth(trim((string)$body), 0, 160, '...');
        throw new RuntimeException('Dapodik merespons HTTP ' . $code . ', tetapi bukan JSON valid' . ($preview !== '' ? ': ' . $preview : '.'));
    }

    return $json;
}

function dapodik_fetch(string $type): array
{
    $url = normalize_http_url((string)get_app_setting('dapodik_url', ''));
    $token = trim((string)get_app_setting('dapodik_token', ''));
    $npsn = trim((string)get_app_setting('dapodik_npsn', ''));
    if ($url === '' || $token === '' || $npsn === '') {
        return ['status' => 'error', 'endpoint' => '', 'message' => 'URL, token, dan NPSN Dapodik wajib diisi.'];
    }

    $endpoint = dapodik_endpoint_url($url, $type, $npsn);
    try {
        $json = dapodik_fetch_json($endpoint, ['Authorization: Bearer ' . $token]);
        $authLabel = 'Authorization Bearer';
    } catch (RuntimeException $bearerException) {
        $fallbackEndpoint = dapodik_endpoint_url($url, $type, $npsn, $token);
        try {
            $json = dapodik_fetch_json($fallbackEndpoint);
            $endpoint = $fallbackEndpoint;
            $authLabel = 'query token';
        } catch (RuntimeException $queryException) {
            return [
                'status' => 'error',
                'endpoint' => $endpoint,
                'message' => 'Dapodik menolak akses dengan Authorization Bearer dan query token. Bearer: '
                    . $bearerException->getMessage() . ' Query: ' . $queryException->getMessage(),
            ];
        }
    }

    $count = dapodik_import($type, $json);
    return ['status' => 'success', 'endpoint' => $endpoint . ' [' . $authLabel . ']', 'message' => "Sinkron $type selesai. Data diproses: $count."];
}

function dapodik_records(array $json): array
{
    foreach (['data', 'rows', 'result'] as $key) {
        if (isset($json[$key]) && is_array($json[$key])) {
            return $json[$key];
        }
    }
    return array_is_list($json) ? $json : [$json];
}

function dapodik_import(string $type, array $json): int
{
    $rows = dapodik_records($json);
    $count = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($type === 'sekolah') {
            $school = get_school_profile();
            execute_sql(
                'UPDATE school_profile SET name = ?, npsn = ?, address = ?, principal_name = ?, updated_at = ? WHERE id = ?',
                [$row['nama'] ?? $row['nama_sekolah'] ?? $school['name'], $row['npsn'] ?? '', $row['alamat'] ?? '', $row['nama_kepala_sekolah'] ?? $school['principal_name'] ?? '', now_string(), (int)$school['id']]
            );
            $count++;
        } elseif ($type === 'guru') {
            $name = trim((string)($row['nama'] ?? $row['nama_ptk'] ?? ''));
            if ($name === '') {
                continue;
            }
            execute_sql('INSERT INTO teachers (name, nip, nuptk, gender, position, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$name, $row['nip'] ?? '', $row['nuptk'] ?? '', $row['jenis_kelamin'] ?? '', $row['jenis_ptk'] ?? '', now_string()]);
            $count++;
        } elseif ($type === 'siswa') {
            $name = trim((string)($row['nama'] ?? $row['nama_siswa'] ?? ''));
            if ($name === '') {
                continue;
            }
            execute_sql('INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$row['nis'] ?? '', $row['nisn'] ?? '', $name, $row['jenis_kelamin'] ?? '', $row['tempat_lahir'] ?? '', $row['tanggal_lahir'] ?? null, now_string()]);
            $count++;
        } elseif ($type === 'mapel') {
            $name = trim((string)($row['nama'] ?? $row['nama_mata_pelajaran'] ?? ''));
            if ($name === '') {
                continue;
            }
            execute_sql('INSERT INTO subjects (name, short_name, updated_at) VALUES (?, ?, ?)', [$name, $row['kode'] ?? '', now_string()]);
            $count++;
        } else {
            $count++;
        }
    }
    return $count;
}

function dapodik_payload(string $kind): array
{
    if ($kind === 'matev') {
        return [
            'npsn' => get_app_setting('dapodik_npsn', ''),
            'learning_objectives' => fetch_all('SELECT lo.*, s.name AS subject_name FROM learning_objectives lo JOIN subjects s ON s.id = lo.subject_id'),
        ];
    }
    return [
        'npsn' => get_app_setting('dapodik_npsn', ''),
        'grades' => fetch_all('SELECT g.*, st.nisn, st.nis, st.name AS student_name, sub.name AS subject_name FROM grades g JOIN students st ON st.id = g.student_id JOIN teaching_assignments ta ON ta.id = g.assignment_id JOIN subjects sub ON sub.id = ta.subject_id'),
        'final_scores' => fetch_all('SELECT fs.*, st.nisn, st.name AS student_name, sub.name AS subject_name FROM final_scores fs JOIN students st ON st.id = fs.student_id JOIN subjects sub ON sub.id = fs.subject_id'),
    ];
}

function dapodik_post_payload(string $endpoint, array $payload): string
{
    $endpoint = normalize_http_url($endpoint);
    $ch = curl_init($endpoint);
    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $curlOptions);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $error) {
        return 'Pengiriman gagal: ' . $error;
    }
    return 'Pengiriman diproses. HTTP ' . $code . '. Respons: ' . mb_strimwidth((string)$body, 0, 180, '...');
}

function page_data_ekskul(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('extracurriculars');
    $teachers = map_options('teachers', 'name');
    $rows = fetch_all('SELECT e.*, t.name AS teacher_name FROM extracurriculars e LEFT JOIN teachers t ON t.id = e.teacher_id ORDER BY e.name');
    render_header('Data Ekskul');
    ext_simple_form_start('save_extracurricular', $edit, 'Data Ekskul');
    ?>
        <label>Nama Rombel Ekskul <input name="class_name" required value="<?= e($edit['class_name'] ?? '') ?>"></label>
        <label>Jenis Ekskul <input name="type" value="<?= e($edit['type'] ?? '') ?>"></label>
        <label>Nama Ekskul <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Pembina <select name="teacher_id"><option value="">-</option><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
    <?php ext_simple_form_end('data-ekskul'); ?>
    <?php table_panel('Daftar Ekskul', ['Rombel', 'Jenis', 'Nama', 'Pembina', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['class_name']) ?></td><td><?= e($row['type']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('extracurriculars', 'data-ekskul', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function ext_simple_form_start(string $action, array $edit, string $title, string $class = 'grid four'): void
{
    $isOpen = (bool)$edit || isset($_GET['add']);
    $button = '<button type="button" class="button primary input-panel-toggle" data-toggle-label="' . e('Tambah ' . $title) . '" data-close-label="Tutup Form">' . e($isOpen ? 'Tutup Form' : 'Tambah ' . $title) . '</button>';
    echo '<section class="panel input-panel ' . ($isOpen ? 'is-open' : '') . '" id="form-data">';
    panel_title(($edit ? 'Edit ' : 'Input ') . $title, '', $button);
    echo '<div class="input-panel-body"><form method="post" enctype="multipart/form-data" class="' . e($class) . '">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . e($edit['id'] ?? 0) . '">';
}

function ext_simple_form_end(string $page): void
{
    echo '<div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="' . e(route_url($page)) . '">Reset</a></div></form></div></section>';
}

function page_data_kelompok(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('subject_groups');
    $rows = fetch_all('SELECT * FROM subject_groups ORDER BY display_order, code');
    render_header('Data Kelompok Mapel');
    ext_simple_form_start('save_subject_group', $edit, 'Kelompok Mapel');
    ?>
        <label>Kode <input name="code" required value="<?= e($edit['code'] ?? '') ?>"></label>
        <label>Nama Kelompok <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Status <select name="status"><?= options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], $edit['status'] ?? 'aktif') ?></select></label>
        <label>Urutan <input type="number" name="display_order" value="<?= e($edit['display_order'] ?? 0) ?>"></label>
    <?php ext_simple_form_end('data-kelompok'); ?>
    <?php table_panel('Kelompok Mapel Rapor', ['Kode', 'Nama', 'Status', 'Urutan', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['code']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['display_order']) ?></td><td><?= ext_delete_button('subject_groups', 'data-kelompok', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_gabung_mapel(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('merged_subjects');
    $subjects = map_options('subjects', 'name');
    $rows = fetch_all('SELECT m.*, a.name AS source_name, b.name AS target_name FROM merged_subjects m JOIN subjects a ON a.id = m.source_subject_id JOIN subjects b ON b.id = m.target_subject_id ORDER BY m.grade, a.name');
    render_header('Gabung Mapel');
    ext_simple_form_start('save_merged_subject', $edit, 'Gabung Mapel');
    ?>
        <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Mapel Asal <select name="source_subject_id"><?= options($subjects, $edit['source_subject_id'] ?? '') ?></select></label>
        <label>Digabung ke Mapel <select name="target_subject_id"><?= options($subjects, $edit['target_subject_id'] ?? '') ?></select></label>
    <?php ext_simple_form_end('gabung-mapel'); ?>
    <?php table_panel('Daftar Gabung Mapel', ['Tingkat', 'Mapel Asal', 'Mapel Gabung', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['source_name']) ?></td><td><?= e($row['target_name']) ?></td><td><?= ext_delete_button('merged_subjects', 'gabung-mapel', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_mapping(string $title = 'Data Mapping Rapor', string $page = 'data-mapping'): void
{
    require_role(['admin']);
    $edit = ext_edit_row('report_mappings');
    $subjects = map_options('subjects', 'name');
    $groups = array_column_map(fetch_all("SELECT id, name FROM subject_groups WHERE status = 'aktif' ORDER BY display_order, name"), 'id', 'name');
    $rows = fetch_all('SELECT rm.*, s.name AS subject_name, g.name AS group_name FROM report_mappings rm JOIN subjects s ON s.id = rm.subject_id LEFT JOIN subject_groups g ON g.id = rm.group_id ORDER BY rm.grade, rm.display_order');
    render_header($title);
    ext_simple_form_start($page === 'mapping-mapel-skl' ? 'save_skl_mapping' : 'save_report_mapping', $edit, $title);
    ?>
        <label>Kurikulum <input name="curriculum" required value="<?= e($edit['curriculum'] ?? 'Kurikulum Merdeka') ?>"></label>
        <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Mapel <select name="subject_id"><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
        <label>Kelompok <select name="group_id"><option value="">-</option><?= options($groups, $edit['group_id'] ?? '') ?></select></label>
        <label>Urutan <input type="number" name="display_order" value="<?= e($edit['display_order'] ?? 0) ?>"></label>
        <label class="check"><input type="checkbox" name="include_in_report" <?= checked($edit['include_in_report'] ?? 1) ?>> Tampil</label>
    <?php ext_simple_form_end($page); ?>
    <?php table_panel($title, ['Kurikulum', 'Tingkat', 'Mapel', 'Kelompok', 'Urutan', 'Tampil', 'Aksi'], $rows, function ($row) use ($page) { ?>
        <td><?= e($row['curriculum']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= e($row['display_order']) ?></td><td><?= status_badge((int)$row['include_in_report']) ?></td><td><?= ext_delete_button('report_mappings', $page, (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_logo_ttd(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('signatures');
    $users = array_column_map(fetch_all('SELECT id, name FROM users ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT s.*, u.name AS user_name FROM signatures s LEFT JOIN users u ON u.id = s.user_id ORDER BY s.type, s.id DESC');
    $types = [
        'logo' => 'Logo Sekolah',
        'logo_dinas' => 'Logo Dinas',
        'ttd_kepsek' => 'TTD Kepala Sekolah',
        'ttd_wali' => 'TTD Wali Kelas',
        'stempel' => 'Stempel',
    ];
    render_header('Data Logo dan TTD');
    ext_simple_form_start('save_signature', $edit, 'Logo/TTD');
    ?>
        <label>Tipe <select name="type"><?= options($types, $edit['type'] ?? 'logo') ?></select></label>
        <label>User/Guru <select name="user_id"><option value="">-</option><?= options($users, $edit['user_id'] ?? '') ?></select></label>
        <label>Jabatan <input name="title" value="<?= e($edit['title'] ?? '') ?>"></label>
        <label>Nama <input name="person_name" value="<?= e($edit['person_name'] ?? '') ?>"></label>
        <label>NIP <input name="nip" value="<?= e($edit['nip'] ?? '') ?>"></label>
        <label>File <input type="file" name="userfile" accept="image/*"></label>
    <?php ext_simple_form_end('data-logo-ttd'); ?>
    <?php table_panel('Daftar Logo/TTD', ['Tipe', 'Preview', 'User', 'Nama', 'NIP', 'File', 'Aksi'], $rows, function ($row) use ($types) { ?>
        <td><?= e($types[$row['type']] ?? $row['type']) ?></td>
        <td>
            <?php $previewUrl = signature_media_url((string)$row['type'], (int)$row['id']); ?>
            <?= $previewUrl !== '' ? '<img class="signature-preview" src="' . e($previewUrl) . '" alt="' . e($row['type']) . '">' : '<span class="hint">-</span>' ?>
        </td>
        <td><?= e($row['user_name']) ?></td><td><?= e($row['person_name']) ?></td><td><?= e($row['nip']) ?></td><td><?= e($row['file_path']) ?></td><td><?= ext_delete_button('signatures', 'data-logo-ttd', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_tanggal_rapor(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('report_dates');
    $rows = fetch_all('SELECT * FROM report_dates ORDER BY grade, report_date DESC');
    render_header('Data Tanggal Rapor');
    ext_simple_form_start('save_report_date', $edit, 'Tanggal Rapor');
    ?>
        <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Tanggal Rapor <input type="date" name="report_date" required value="<?= e($edit['report_date'] ?? date('Y-m-d')) ?>"></label>
        <label>Tempat TTD Wali <input name="homeroom_place" value="<?= e($edit['homeroom_place'] ?? '') ?>"></label>
        <label>Tempat TTD Kepsek <input name="principal_place" value="<?= e($edit['principal_place'] ?? '') ?>"></label>
        <label class="span-2">Catatan <input name="note" value="<?= e($edit['note'] ?? '') ?>"></label>
    <?php ext_simple_form_end('tanggal-rapor'); ?>
    <?php table_panel('Tanggal Rapor', ['Tingkat', 'Tanggal', 'TTD Wali', 'TTD Kepsek', 'Catatan', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['report_date']) ?></td><td><?= e($row['homeroom_place']) ?></td><td><?= e($row['principal_place']) ?></td><td><?= e($row['note']) ?></td><td><?= ext_delete_button('report_dates', 'tanggal-rapor', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_foto_siswa(): void
{
    require_role(['admin']);
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT p.*, s.name AS student_name, s.nisn FROM student_photos p JOIN students s ON s.id = p.student_id ORDER BY s.name');
    render_header('Foto Siswa');
    echo '<section class="panel"><h3>Upload Foto Siswa</h3><form method="post" enctype="multipart/form-data" class="grid four">' . csrf_field() . '<input type="hidden" name="action" value="save_student_photo">';
    ?>
        <label>Siswa <select name="student_id"><?= options($students, '') ?></select></label>
        <label>Foto <input type="file" name="userfile" accept="image/*" required></label>
        <div class="actions"><button class="button primary">Upload</button></div>
    </form></section>
    <?php table_panel('Foto Siswa', ['Nama', 'NISN', 'File', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['student_name']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['file_path']) ?></td><td><?= ext_delete_button('student_photos', 'foto-siswa', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_tema_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_themes');
    $rows = fetch_all('SELECT * FROM cocurricular_themes ORDER BY name');
    render_header('Daftar Tema Kokurikuler');
    ext_simple_form_start('save_cocurricular_theme', $edit, 'Tema');
    ?>
        <label class="span-2">Nama Tema <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Status <select name="status"><?= options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], $edit['status'] ?? 'aktif') ?></select></label>
    <?php ext_simple_form_end('tema-kokurikuler'); ?>
    <?php table_panel('Tema Kokurikuler', ['Tema', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['status']) ?></td><td><?= ext_delete_button('cocurricular_themes', 'tema-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_kegiatan_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_activities');
    $themes = array_column_map(fetch_all('SELECT id, name FROM cocurricular_themes ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT a.*, t.name AS theme_name FROM cocurricular_activities a JOIN cocurricular_themes t ON t.id = a.theme_id ORDER BY t.name, a.phase, a.title');
    render_header('Kegiatan Kokurikuler');
    ext_simple_form_start('save_cocurricular_activity', $edit, 'Kegiatan Kokurikuler', 'grid two');
    ?>
        <label>Tema <select name="theme_id"><?= options($themes, $edit['theme_id'] ?? '') ?></select></label>
        <label>Fase <input name="phase" required value="<?= e($edit['phase'] ?? '') ?>"></label>
        <label class="wide">Judul Kegiatan <input name="title" required value="<?= e($edit['title'] ?? '') ?>"></label>
        <label class="wide">Deskripsi <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="wide">Tujuan <textarea name="objective"><?= e($edit['objective'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
    <?php ext_simple_form_end('kegiatan-kokurikuler'); ?>
    <?php table_panel('Kegiatan', ['Tema', 'Fase', 'Judul', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['theme_name']) ?></td><td><?= e($row['phase']) ?></td><td><?= e($row['title']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('cocurricular_activities', 'kegiatan-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_kelompok_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_groups');
    $themes = array_column_map(fetch_all('SELECT id, name FROM cocurricular_themes ORDER BY name'), 'id', 'name');
    $teachers = map_options('teachers', 'name');
    $students = fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name');
    $members = $edit ? array_map('intval', array_column(fetch_all('SELECT student_id FROM cocurricular_members WHERE group_id = ?', [(int)$edit['id']]), 'student_id')) : [];
    $rows = fetch_all('SELECT g.*, t.name AS theme_name, p.name AS teacher_name, COUNT(m.id) AS member_count FROM cocurricular_groups g LEFT JOIN cocurricular_themes t ON t.id = g.theme_id LEFT JOIN teachers p ON p.id = g.coordinator_teacher_id LEFT JOIN cocurricular_members m ON m.group_id = g.id GROUP BY g.id ORDER BY g.grade, g.name');
    render_header('Kelompok Kokurikuler');
    ext_simple_form_start('save_cocurricular_group', $edit, 'Kelompok Kokurikuler', 'grid two');
    ?>
        <label>Nama Kelompok <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Fase <input name="phase" required value="<?= e($edit['phase'] ?? '') ?>"></label>
        <label>Tema <select name="theme_id"><option value="">-</option><?= options($themes, $edit['theme_id'] ?? '') ?></select></label>
        <label>Koordinator <select name="coordinator_teacher_id"><option value="">-</option><?= options($teachers, $edit['coordinator_teacher_id'] ?? '') ?></select></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
        <label class="wide">Anggota
            <select name="members[]" multiple size="8">
                <?php foreach ($students as $student): ?>
                    <option value="<?= e($student['id']) ?>" <?= in_array((int)$student['id'], $members, true) ? 'selected' : '' ?>><?= e($student['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php ext_simple_form_end('kelompok-kokurikuler'); ?>
    <?php table_panel('Kelompok Kokurikuler', ['Nama', 'Tingkat', 'Fase', 'Tema', 'Koordinator', 'Anggota', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['phase']) ?></td><td><?= e($row['theme_name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['member_count']) ?></td><td><?= ext_delete_button('cocurricular_groups', 'kelompok-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_tp(): void
{
    require_role(['admin', 'guru']);
    $edit = ext_edit_row('learning_objectives');
    $subjects = map_options('subjects', 'name');
    $rows = fetch_all('SELECT lo.*, s.name AS subject_name FROM learning_objectives lo JOIN subjects s ON s.id = lo.subject_id ORDER BY lo.grade, s.name, lo.id DESC');
    render_header('Tujuan Pembelajaran');
    ext_simple_form_start('save_learning_objective', $edit, 'Tujuan Pembelajaran', 'grid two');
    ?>
        <label>Mapel <select name="subject_id"><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
        <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label class="wide">Tujuan Pembelajaran <textarea name="description" required><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
    <?php ext_simple_form_end('data-tp'); ?>
    <?php table_panel('Daftar TP', ['Tingkat', 'Mapel', 'Tujuan', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e(mb_strimwidth((string)$row['description'], 0, 120, '...')) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('learning_objectives', 'data-tp', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_status_penilaian(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }
    $rows = $classId ? fetch_all(
        'SELECT ta.*, t.name AS teacher_name, s.name AS subject_name, c.name AS class_name
         FROM teaching_assignments ta JOIN teachers t ON t.id = ta.teacher_id JOIN subjects s ON s.id = ta.subject_id JOIN classes c ON c.id = ta.class_id
         WHERE ta.class_id = ? ORDER BY s.name',
        [$classId]
    ) : [];
    render_header('Status Penilaian');
    echo '<section class="panel"><form method="get" class="grid four"><input type="hidden" name="page" value="status-penilaian"><label>Kelas <select name="class_id">' . options(array_column_map($classes, 'id', 'name'), $classId) . '</select></label><div class="actions"><button class="button">Tampilkan</button></div></form></section>';
    table_panel('Status Nilai Guru Mapel', ['Kelas', 'Mapel', 'Guru', 'Siswa', 'Nilai Masuk', 'Progress'], $rows, function ($row) {
        $studentCount = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ? AND active = 1', [(int)$row['class_id']])['c'];
        $gradeCount = (int)fetch_one('SELECT COUNT(*) AS c FROM grades WHERE assignment_id = ? AND score IS NOT NULL', [(int)$row['id']])['c'];
        $percent = $studentCount > 0 ? round(($gradeCount / $studentCount) * 100) : 0;
        ?><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($studentCount) ?></td><td><?= e($gradeCount) ?></td><td><span class="badge <?= $percent >= 100 ? 'ok' : 'off' ?>"><?= e($percent) ?>%</span></td><?php
    });
    render_footer();
}

function page_print_document(string $type): void
{
    require_role(['admin', 'guru']);

    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }
    $class = $classId ? fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]) : null;
    $students = $class ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    $titles = [
        'cetak-pelengkap-rapor' => 'Cetak Biodata/Pelengkap Rapor',
        'cetak-nilai-rapor' => 'Cetak Nilai Rapor',
        'cetak-leger-rapor' => 'Leger Rapor',
        'cetak-leger-pts' => 'Leger PTS',
        'cetak-nilai-rapor-pts' => 'Rapor PTS',
        'cetak-buku-induk' => 'Buku Induk',
        'cetak-skl' => 'Cetak SKL',
        'cetak-transkrip-ijazah' => 'Cetak Transkrip Ijazah',
    ];
    render_header($titles[$type] ?? 'Cetak');
    ?>
    <section class="panel no-print">
        <?php panel_title(($titles[$type] ?? 'Cetak') . ' Siswa'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="<?= e($type) ?>">
            <label>Pilih Kelas <select name="class_id"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <label>Posisi Tanda Tangan KS <select name="posisittdks"><?= options(['sejajar' => 'Sejajar Wali Kelas', 'bawah' => 'Di Bawah Wali Kelas'], $_GET['posisittdks'] ?? 'sejajar') ?></select></label>
            <label>Posisi Tanda Tangan <select name="isittd"><?= options(['tanpa' => 'Tanpa Tanda Tangan', 'dengan' => 'Dengan Tanda Tangan'], $_GET['isittd'] ?? 'tanpa') ?></select></label>
            <label>Ukuran Kertas <select name="kertas"><?= options(['A4' => 'A4', 'F4' => 'F4'], $_GET['kertas'] ?? 'A4') ?></select></label>
            <label>Batas Kiri (mm) <input type="number" name="kiri" value="<?= e($_GET['kiri'] ?? 20) ?>"></label>
            <label>Batas Kanan (mm) <input type="number" name="kanan" value="<?= e($_GET['kanan'] ?? 20) ?>"></label>
            <label>Batas Atas (mm) <input type="number" name="atas" value="<?= e($_GET['atas'] ?? 20) ?>"></label>
            <label>Batas Bawah (mm) <input type="number" name="bawah" value="<?= e($_GET['bawah'] ?? 10) ?>"></label>
            <div class="actions wide"><button class="button primary">Tampilkan</button></div>
        </form>
    </section>
    <section class="panel print-panel">
        <?php panel_title($titles[$type] ?? 'Cetak', '', '<button type="button" class="button warning" onclick="window.print()">Generate ' . e($titles[$type] ?? 'Cetak') . '</button><button type="button" class="button success" onclick="window.print()">Download/Cetak</button>'); ?>
        <h2><?= e($titles[$type] ?? 'Cetak') ?> <?= e($class['name'] ?? '') ?></h2>
        <?php if (in_array($type, ['cetak-leger-rapor', 'cetak-leger-pts'], true)): ?>
            <?php render_leger_table($students); ?>
        <?php elseif ($type === 'cetak-buku-induk' || $type === 'cetak-pelengkap-rapor'): ?>
            <?php render_biodata_table($students); ?>
        <?php elseif (in_array($type, ['cetak-skl', 'cetak-transkrip-ijazah'], true)): ?>
            <?php render_skl_table($students); ?>
        <?php else: ?>
            <?php foreach ($students as $student): render_student_report_card($student, $type); endforeach; ?>
        <?php endif; ?>
    </section>
    <?php render_footer();
}

function render_biodata_table(array $students): void
{
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th><th>NIS</th><th>NISN</th><th>JK</th><th>TTL</th><th>Agama</th></tr></thead><tbody>';
    foreach ($students as $s) {
        echo '<tr><td>' . e($s['name']) . '</td><td>' . e($s['nis']) . '</td><td>' . e($s['nisn']) . '</td><td>' . e($s['gender']) . '</td><td>' . e(trim(($s['birth_place'] ?? '') . ', ' . ($s['birth_date'] ?? ''), ', ')) . '</td><td>' . e($s['religion']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_leger_table(array $students): void
{
    $subjects = fetch_all('SELECT * FROM subjects WHERE active = 1 ORDER BY name');
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th>';
    foreach ($subjects as $subject) {
        echo '<th>' . e($subject['short_name'] ?: $subject['name']) . '</th>';
    }
    echo '<th>Rata-rata</th></tr></thead><tbody>';
    foreach ($students as $student) {
        echo '<tr><td>' . e($student['name']) . '</td>';
        $scores = [];
        foreach ($subjects as $subject) {
            $score = fetch_one('SELECT AVG(g.score) AS score FROM grades g JOIN teaching_assignments ta ON ta.id = g.assignment_id WHERE g.student_id = ? AND ta.subject_id = ?', [(int)$student['id'], (int)$subject['id']]);
            $value = $score && $score['score'] !== null ? (float)$score['score'] : null;
            if ($value !== null) {
                $scores[] = $value;
            }
            echo '<td>' . e($value !== null ? number_format($value, 0) : '-') . '</td>';
        }
        echo '<td>' . e($scores ? number_format(array_sum($scores) / count($scores), 2) : '-') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_skl_table(array $students): void
{
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th><th>NISN</th><th>Status</th><th>No Ijazah</th><th>No Transkrip</th><th>Tgl Lulus</th></tr></thead><tbody>';
    foreach ($students as $student) {
        $g = fetch_one('SELECT * FROM graduations WHERE student_id = ?', [(int)$student['id']]) ?: [];
        echo '<tr><td>' . e($student['name']) . '</td><td>' . e($student['nisn']) . '</td><td>' . e($g['status'] ?? '-') . '</td><td>' . e($g['certificate_no'] ?? '-') . '</td><td>' . e($g['transcript_no'] ?? '-') . '</td><td>' . e($g['graduation_date'] ?? '-') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_student_report_card(array $student, string $type): void
{
    echo '<article class="report-card"><h3>' . e($student['name']) . '</h3><p>NIS/NISN: ' . e($student['nis'] . ' / ' . $student['nisn']) . '</p>';
    $rows = fetch_all('SELECT sub.name, g.score, g.description FROM grades g JOIN teaching_assignments ta ON ta.id = g.assignment_id JOIN subjects sub ON sub.id = ta.subject_id WHERE g.student_id = ? ORDER BY sub.name', [(int)$student['id']]);
    if (!$rows) {
        echo '<p class="empty">Belum ada nilai.</p></article>';
        return;
    }
    echo '<table><thead><tr><th>Mapel</th><th>Nilai</th><th>Deskripsi</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['score']) . '</td><td>' . e($row['description']) . '</td></tr>';
    }
    echo '</tbody></table></article>';
}

function page_input_kelulusan(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT s.*, c.name AS class_name, g.status, g.certificate_no, g.transcript_no, g.graduation_date, g.notes FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN graduations g ON g.student_id = s.id ORDER BY c.grade, c.name, s.name');
    render_header('Input Kelulusan');
    table_panel('Data Kelulusan Siswa', ['Nama', 'Kelas', 'Status', 'No Ijazah', 'No Transkrip', 'Tanggal', 'Simpan'], $rows, function ($row) { ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_graduation"><input type="hidden" name="student_id" value="<?= e($row['id']) ?>">
        <td><?= e($row['name']) ?></td><td><?= e($row['class_name']) ?></td><td><select name="status"><?= options(['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', 'naik' => 'Naik Kelas', 'tinggal' => 'Tinggal Kelas'], $row['status'] ?? 'lulus') ?></select></td><td><input name="certificate_no" value="<?= e($row['certificate_no']) ?>"></td><td><input name="transcript_no" value="<?= e($row['transcript_no']) ?>"></td><td><input type="date" name="graduation_date" value="<?= e($row['graduation_date']) ?>"></td><td><button class="button small primary">Simpan</button></td></form>
    <?php }); render_footer();
}

function page_import_nomor_ijazah(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT s.name, s.nisn, c.name AS class_name, g.certificate_no, g.transcript_no, g.graduation_date FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN graduations g ON g.student_id = s.id ORDER BY c.grade, c.name, s.name');
    render_header('Import Nomor Ijazah');
    ?>
    <section class="panel">
        <form method="post" enctype="multipart/form-data" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_certificate_numbers">
            <label>CSV: nisn, nomor_ijazah, nomor_transkrip, tgl_lulus <input type="file" name="userfile" accept=".csv,text/csv" required></label>
            <div class="actions"><button class="button primary">Import Nomor Ijazah</button></div>
        </form>
    </section>
    <?php table_panel('Nomor Ijazah', ['Nama', 'NISN', 'Kelas', 'Nomor Ijazah', 'Nomor Transkrip', 'Tgl Lulus'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['certificate_no']) ?></td><td><?= e($row['transcript_no']) ?></td><td><?= e($row['graduation_date']) ?></td>
    <?php }); render_footer();
}

function page_setting_transkrip(): void
{
    page_settings_form('Setting Transkrip', 'setting-transkrip', 'save_transcript_settings', ['setnamasiswa', 'desimal_nilai', 'ada_ratarata', 'desimal_ratarata', 'ket_tempat_ttd', 'nama_kepsek', 'nip_kepsek', 'ada_ttd']);
}

function page_setting_skl(): void
{
    page_settings_form('Setting SKL', 'setting-skl', 'save_skl_settings', ['ada_kop', 'judul_1', 'nomor_skl', 'isi_text1', 'setnamasiswa', 'isi_text2', 'statuslulus', 'ada_nilai', 'judul_nilai', 'desimal_nilai', 'ada_ratarata', 'ket_tempat_ttd', 'nama_kepsek', 'nip_kepsek', 'ada_foto', 'ada_ttd']);
}

function page_settings_form(string $title, string $page, string $action, array $fields): void
{
    require_role(['admin']);
    render_header($title);
    echo '<section class="panel"><form method="post" class="grid two">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $field) {
        $value = get_app_setting($page . '.' . $field, '');
        if (str_starts_with($field, 'isi_')) {
            echo '<label class="wide">' . e($field) . '<textarea name="' . e($field) . '">' . e($value) . '</textarea></label>';
        } else {
            echo '<label>' . e($field) . '<input name="' . e($field) . '" value="' . e($value) . '"></label>';
        }
    }
    echo '<div class="wide actions"><button class="button primary">Simpan Data</button></div></form></section>';
    render_footer();
}

function page_input_nilai_skl(): void
{
    require_role(['admin']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = fetch_all('SELECT * FROM classes WHERE active = 1 ORDER BY grade, name');
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    $students = $classId ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    $subjects = fetch_all('SELECT * FROM subjects WHERE active = 1 ORDER BY name');
    render_header('Input Nilai SKL');
    echo '<section class="panel"><form method="get" class="grid four"><input type="hidden" name="page" value="input-nilai-skl"><label>Kelas <select name="class_id">' . options(array_column_map($classes, 'id', 'name'), $classId) . '</select></label><div class="actions"><button class="button">Tampilkan</button></div></form></section>';
    echo '<section class="panel"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="save_final_scores"><input type="hidden" name="class_id" value="' . e($classId) . '"><div class="table-wrap"><table><thead><tr><th>Nama</th>';
    foreach ($subjects as $subject) {
        echo '<th>' . e($subject['short_name'] ?: $subject['name']) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($students as $student) {
        echo '<tr><td>' . e($student['name']) . '</td>';
        foreach ($subjects as $subject) {
            $score = fetch_one('SELECT score FROM final_scores WHERE student_id = ? AND subject_id = ?', [(int)$student['id'], (int)$subject['id']]);
            echo '<td><input class="small-input" type="number" min="0" max="100" step="0.01" name="score[' . e($student['id']) . '][' . e($subject['id']) . ']" value="' . e($score['score'] ?? '') . '"></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div><div class="actions"><button class="button primary">Simpan Nilai Akhir</button></div></form></section>';
    render_footer();
}

function page_backup_restore(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT * FROM backups ORDER BY id DESC');
    render_header('Backup dan Restore');
    ?>
    <section class="panel">
        <div class="grid two">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create_backup"><button class="button primary">Backup Data</button></form>
            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore akan mengganti data saat ini. Lanjut?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="restore_backup">
                <label>File Backup JSON <input type="file" name="userfile" accept=".json,application/json" required></label>
                <div class="actions"><button class="button danger">Restore Data</button></div>
            </form>
        </div>
    </section>
    <?php table_panel('File Backup', ['File', 'Created at', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['file_name']) ?></td><td><?= e($row['created_at']) ?></td><td><?= e($row['status']) ?></td><td><a class="button small" href="<?= e(route_url('backup-download', ['id' => (int)$row['id']])) ?>">Download</a></td>
    <?php }); render_footer();
}

function page_backup_download(): void
{
    require_role(['admin']);
    $row = fetch_one('SELECT * FROM backups WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    if (!$row) {
        http_response_code(404);
        exit('Backup tidak ditemukan.');
    }
    $path = app_file_path((string)$row['file_path'], ['storage/backups']);
    if (!is_file($path)) {
        http_response_code(404);
        exit('File backup tidak ada.');
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

function page_update_data(): void
{
    require_role(['admin']);
    $logs = fetch_all('SELECT * FROM dapodik_sync_logs ORDER BY id DESC LIMIT 25');
    $reportUrl = app_url('');
    render_header('Update Data E Rapor');
    page_dapodik_settings_form('update-data');
    ?>
    <section class="panel">
        <h3>Mode Update dari Dapodik</h3>
        <div>
            <p>Isi Link Dapodik Lokal, Token / Key Webservice, dan NPSN di atas, lalu gunakan tombol Simpan & Unduh agar konfigurasi server tersimpan sebelum helper portable dibuat.</p>
            <p class="hint">Link Web Raport: <code><?= e($reportUrl) ?></code></p>
        </div>
    </section>
    <section class="panel">
        <h3>Import JSON Offline dari Helper</h3>
        <form method="post" enctype="multipart/form-data" class="grid three">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_dapodik_offline">
            <label>Jenis Data <select name="data_type"><?= options(dapodik_data_types(true), 'all') ?></select></label>
            <label>File JSON <input type="file" name="json_file" accept=".json,application/json"></label>
            <label class="wide">Tempel JSON <textarea name="json_payload" placeholder='{"type":"all","items":[{"type":"guru","data":[...]}]}'></textarea></label>
            <div class="actions"><button class="button primary">Import Offline</button></div>
        </form>
    </section>
    <?php table_panel('Log Sinkronisasi', ['Waktu', 'Mode', 'Jenis', 'Status', 'Pesan'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['mode']) ?></td><td><?= e($row['data_type']) ?></td><td><?= e($row['status']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 140, '...')) ?></td>
    <?php }); render_footer();
}

function page_kirim_data_dapodik(): void
{
    require_role(['admin']);
    $logs = fetch_all("SELECT * FROM dapodik_sync_logs WHERE mode = 'push' ORDER BY id DESC LIMIT 25");
    render_header('Kirim Data ke Dapodik');
    page_dapodik_settings_form('kirim-data-dapodik');
    ?>
    <section class="panel">
        <h3>Upload Nilai ke Dapodik</h3>
        <div class="grid two">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_dapodik"><input type="hidden" name="kind" value="matev"><button class="button primary">Kirim Data Matev</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_dapodik"><input type="hidden" name="kind" value="nilai"><button class="button primary">Kirim Data Nilai</button></form>
        </div>
        <p class="hint">Endpoint Dapodik resmi bisa berbeda antar instalasi. Aplikasi menyiapkan payload dan mencatat respons HTTP agar mudah disesuaikan.</p>
    </section>
    <?php table_panel('Log Kirim Dapodik', ['Waktu', 'Jenis', 'Endpoint', 'Status', 'Pesan'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['data_type']) ?></td><td><?= e($row['endpoint']) ?></td><td><?= e($row['status']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 130, '...')) ?></td>
    <?php }); render_footer();
}

function page_dapodik_settings_form(string $returnPage): void
{
    $showDownloadButtons = $returnPage === 'update-data';
    ?>
    <section class="panel">
        <h3>Konfigurasi Web Service Dapodik</h3>
        <form method="post" class="grid three">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_dapodik_settings"><input type="hidden" name="return_page" value="<?= e($returnPage) ?>">
            <label>Link Dapodik Lokal <input name="url" placeholder="http://127.0.0.1:5774" value="<?= e(get_app_setting('dapodik_url', '')) ?>"></label>
            <label>Token / Key Webservice <input name="token" value="<?= e(get_app_setting('dapodik_token', '')) ?>"></label>
            <label>NPSN <input name="npsn" value="<?= e(get_app_setting('dapodik_npsn', '')) ?>"></label>
            <div class="actions wide">
                <button class="button primary">Simpan</button>
                <?php if ($showDownloadButtons): ?>
                    <button class="button success" name="download_after_save" value="portable">Simpan & Unduh Portable ZIP</button>
                    <button class="button" name="download_after_save" value="config">Simpan & Unduh Config Portable</button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php
}
