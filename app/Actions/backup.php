<?php declare(strict_types=1);

function backup_tables(): array
{
    return [
        'school_profile', 'teachers', 'classes', 'students', 'subjects', 'teaching_assignments', 'users',
        'learning_objectives', 'grades', 'student_attendance_sessions', 'student_attendance_entries',
        'teacher_attendance', 'teacher_teaching_attendance', 'student_violations', 'whatsapp_guardians', 'whatsapp_templates',
        'whatsapp_queue', 'whatsapp_logs', 'daily_journals', 'extracurriculars', 'extracurricular_members', 'extracurricular_scores',
        'subject_groups', 'merged_subjects',
        'report_mappings', 'signatures', 'report_dates', 'student_photos', 'cocurricular_themes',
        'cocurricular_activities', 'cocurricular_groups', 'cocurricular_members', 'graduations',
        'final_scores', 'exam_scores', 'app_settings',
    ];
}

function backup_file_prefixes(): array
{
    return [
        'storage/uploads',
        'storage/reports',
    ];
}

function backup_max_upload_bytes(): int
{
    return max(1, (int)config('security.max_backup_upload_bytes', 128 * 1024 * 1024));
}

function backup_relative_files(): array
{
    $files = [];
    $root = rtrim(str_replace('\\', '/', app_root()), '/') . '/';

    foreach (backup_file_prefixes() as $prefix) {
        $dir = app_root() . '/' . $prefix;
        if (!is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $item->getPathname());
            if (!str_starts_with($path, $root)) {
                continue;
            }
            $relative = substr($path, strlen($root));
            safe_relative_path($relative, backup_file_prefixes());
            $files[] = $relative;
        }
    }

    sort($files);
    return array_values(array_unique($files));
}

function backup_file_payloads(): array
{
    $files = [];
    foreach (backup_relative_files() as $relative) {
        $path = app_file_path($relative, backup_file_prefixes());
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('File backup tidak bisa dibaca: ' . $relative);
        }

        $files[] = [
            'path' => $relative,
            'size' => strlen($data),
            'sha256' => hash('sha256', $data),
            'encoding' => 'base64',
            'data' => base64_encode($data),
        ];
    }

    return $files;
}

function create_backup_payload(): array
{
    $backup = [
        'format' => 'eraport-kumerbot-backup',
        'version' => 2,
        'created_at' => now_string(),
        'tables' => [],
        'files' => backup_file_payloads(),
    ];
    foreach (backup_tables() as $table) {
        if (table_exists($table)) {
            $backup['tables'][$table] = fetch_all('SELECT * FROM ' . db_identifier($table));
        }
    }

    return $backup;
}

function save_backup_payload(array $backup, int $createdBy): string
{
    $dir = dirname(__DIR__) . '/storage/backups';
    ensure_directory($dir);
    $file = 'backup-' . date('Ymd-His') . '.json';
    $path = $dir . '/' . $file;
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('Payload backup tidak bisa dibuat.');
    }
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('File backup tidak bisa ditulis.');
    }
    execute_sql('INSERT INTO backups (file_name, file_path, status, created_by) VALUES (?, ?, ?, ?)', [$file, 'storage/backups/' . $file, 'ready', $createdBy]);

    return $file;
}

function action_create_backup(): void
{
    require_role(['admin']);
    $file = save_backup_payload(create_backup_payload(), (int)current_user()['id']);
    flash('success', 'Backup dibuat: ' . $file);
    redirect_to('backup-restore');
}

function restore_backup_files(array $payload): int
{
    if (empty($payload['files']) || !is_array($payload['files'])) {
        return 0;
    }

    $restored = 0;
    foreach ($payload['files'] as $file) {
        if (!is_array($file)) {
            continue;
        }

        $relative = safe_relative_path((string)($file['path'] ?? ''), backup_file_prefixes());
        $encoding = (string)($file['encoding'] ?? 'base64');
        if ($encoding !== 'base64' || !is_string($file['data'] ?? null)) {
            throw new RuntimeException('Format file backup tidak valid: ' . $relative);
        }

        $data = base64_decode((string)$file['data'], true);
        if ($data === false) {
            throw new RuntimeException('Data file backup rusak: ' . $relative);
        }

        if (isset($file['size']) && strlen($data) !== (int)$file['size']) {
            throw new RuntimeException('Ukuran file backup tidak cocok: ' . $relative);
        }

        if (!empty($file['sha256']) && !hash_equals((string)$file['sha256'], hash('sha256', $data))) {
            throw new RuntimeException('Checksum file backup tidak cocok: ' . $relative);
        }

        $path = app_root() . '/' . $relative;
        ensure_directory(dirname($path));
        if (file_put_contents($path, $data, LOCK_EX) === false) {
            throw new RuntimeException('File restore tidak bisa ditulis: ' . $relative);
        }
        $restored++;
    }

    return $restored;
}

function action_restore_backup(): void
{
    require_role(['admin']);
    $fileData = uploaded_file('userfile', true, backup_max_upload_bytes());
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
    $restoredFiles = restore_backup_files($payload);
    $message = 'Restore backup selesai.';
    if ($restoredFiles > 0) {
        $message .= ' File rapor/upload dipulihkan: ' . $restoredFiles . '.';
    }
    flash('success', $message);
    redirect_to('backup-restore');
}
