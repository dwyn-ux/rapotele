<?php declare(strict_types=1);

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
        'anggota_rombel' => 'Anggota Rombel',
        'mapel' => 'Mapel',
        'rombel' => 'Rombel',
        'pembelajaran' => 'Pembelajaran',
    ];

    return $includeAll ? ['all' => 'Semua Data Dasar'] + $types : $types;
}

function dapodik_default_sync_types(): array
{
    return ['sekolah', 'guru', 'rombel', 'siswa', 'anggota_rombel', 'pembelajaran'];
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
    $endpoint = $url ? rtrim($url, '/') . '/WebService/' . ($kind === 'nilai' ? 'simpanNilai' : 'simpanMatev') : '';
    $message = $url ? dapodik_post_payload($url, $kind, $payload) : 'Payload ' . $kind . ' disiapkan. Isi URL Dapodik untuk mengirim online.';
    execute_sql('INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)', ['push', $kind, $endpoint, 'queued', $message, (int)current_user()['id']]);
    flash('success', $message);
    redirect_to('kirim-data-dapodik');
}

function dapodik_endpoint_names(string $type): array
{
    $map = [
        'sekolah' => ['getSekolah'],
        'guru' => ['getGtk'],
        'siswa' => ['getPesertaDidik'],
        'rombel' => ['getRombonganBelajar'],
        'anggota_rombel' => ['getAnggotaRombel', 'getAnggotaRombonganBelajar', 'getPesertaDidikRombel'],
        'mapel' => ['getMataPelajaran'],
        'pembelajaran' => ['getPembelajaran', 'getPembelajaranGuru', 'getDataPembelajaran'],
    ];

    return $map[$type] ?? [$type];
}

function dapodik_endpoint_name(string $type): string
{
    return dapodik_endpoint_names($type)[0];
}

function dapodik_endpoint_url(string $baseUrl, string $type, string $npsn, ?string $token = null, ?string $endpointName = null): string
{
    $params = ['npsn' => $npsn];
    if ($token !== null && $token !== '') {
        $params['token'] = $token;
    }

    return rtrim($baseUrl, '/') . '/WebService/' . ($endpointName ?? dapodik_endpoint_name($type)) . '?' . http_build_query($params);
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

    $errors = [];
    foreach (dapodik_endpoint_names($type) as $endpointName) {
        $endpoint = dapodik_endpoint_url($url, $type, $npsn, null, $endpointName);
        try {
            $json = dapodik_fetch_json($endpoint, ['Authorization: Bearer ' . $token]);
            $count = dapodik_import($type, $json);
            return ['status' => 'success', 'endpoint' => $endpoint . ' [Authorization Bearer]', 'message' => "Sinkron $type selesai. Data diproses: $count."];
        } catch (RuntimeException $queryException) {
            $bearerException = $queryException;
        }

        $fallbackEndpoint = dapodik_endpoint_url($url, $type, $npsn, $token, $endpointName);
        try {
            $json = dapodik_fetch_json($fallbackEndpoint);
            $count = dapodik_import($type, $json);
            return ['status' => 'success', 'endpoint' => $fallbackEndpoint . ' [query token]', 'message' => "Sinkron $type selesai. Data diproses: $count."];
        } catch (RuntimeException $queryException) {
            $errors[] = $endpointName . ' Bearer: ' . $bearerException->getMessage() . ' Query: ' . $queryException->getMessage();
        }
    }

    return [
        'status' => 'error',
        'endpoint' => dapodik_endpoint_url($url, $type, $npsn),
        'message' => 'Dapodik menolak semua endpoint kandidat. ' . implode('; ', $errors),
    ];
}

function dapodik_records(array $json): array
{
    foreach (['data', 'rows', 'result'] as $key) {
        if (isset($json[$key]) && is_array($json[$key])) {
            $inner = $json[$key];
            return array_is_list($inner) ? $inner : [$inner];
        }
    }
    return array_is_list($json) ? $json : [$json];
}

function dapodik_row_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            continue;
        }
        if (is_array($row[$key]) || is_object($row[$key])) {
            continue;
        }
        $value = trim((string)$row[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

function dapodik_limit(?string $value, int $max): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return mb_strimwidth($value, 0, $max, '');
}

function dapodik_nullable_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function dapodik_add_column_if_missing(string $table, string $column, string $definition): void
{
    if (!table_exists($table) || table_column_exists($table, $column)) {
        return;
    }

    try {
        execute_sql('ALTER TABLE ' . db_identifier($table) . ' ADD COLUMN ' . db_identifier($column) . ' ' . $definition);
    } catch (PDOException $exception) {
        if (db_driver() === 'mysql' && str_contains($exception->getMessage(), 'Duplicate column name')) {
            return;
        }
        throw $exception;
    }

    if (!table_column_exists($table, $column)) {
        throw new RuntimeException('Kolom ' . $table . '.' . $column . ' belum berhasil dibuat. Jalankan install.php sekali atau beri user database izin ALTER TABLE.');
    }
}

function dapodik_ensure_tracking_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    run_migrations();
    dapodik_add_column_if_missing('teachers', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_add_column_if_missing('classes', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_add_column_if_missing('students', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_add_column_if_missing('subjects', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_add_column_if_missing('teaching_assignments', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_add_column_if_missing('extracurriculars', 'dapodik_id', 'VARCHAR(64) NULL');
    dapodik_ensure_dapodik_tables();
    $done = true;
}

function dapodik_ensure_dapodik_tables(): void
{
    $pk = db_driver() === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine = db_driver() === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    execute_sql("CREATE TABLE IF NOT EXISTS dapodik_rombel_cache (
        id $pk,
        dapodik_id VARCHAR(64) NULL,
        name VARCHAR(160) NOT NULL,
        kind VARCHAR(80) NULL,
        grade VARCHAR(16) NULL,
        major VARCHAR(80) NULL,
        academic_year VARCHAR(32) NULL,
        teacher_id INT NULL,
        is_regular INT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (dapodik_id)
    )$engine");
    execute_sql("CREATE TABLE IF NOT EXISTS extracurricular_members (
        id $pk,
        extracurricular_id INT NOT NULL,
        student_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (extracurricular_id, student_id)
    )$engine");
}

function dapodik_external_id(array $row, array $keys): string
{
    return dapodik_row_value($row, $keys);
}

function dapodik_academic_year_from_row(array $row): string
{
    $year = dapodik_row_value($row, ['tahun_ajaran', 'tahun_pelajaran', 'academic_year']);
    if (preg_match('/(20\d{2})\D+(20\d{2})/', $year, $match)) {
        return $match[1] . '/' . $match[2];
    }

    $semester = dapodik_row_value($row, ['semester_id', 'id_semester']);
    if (preg_match('/^(20\d{2})[12]$/', $semester, $match)) {
        return $match[1] . '/' . ((int)$match[1] + 1);
    }

    return (string)config('school.academic_year', '2025/2026');
}

function dapodik_class_name_map(string $rawName): string
{
    $name = trim($rawName);
    if ($name === '' || mb_strlen($name) > 30) {
        return $name;
    }

    $grade = dapodik_grade_from_rombel([], $name);
    if ($grade === '') {
        return $name;
    }

    $romanMap = ['7' => 'VII', '8' => 'VIII', '9' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII'];
    $roman = $romanMap[$grade] ?? $grade;

    $upper = strtoupper($name);

    $hasPutri = str_contains($upper, 'PUTRI') || str_contains($upper, 'WANITA') || str_contains($upper, 'PEREMPUAN');
    $hasPutra = str_contains($upper, 'PUTRA') || str_contains($upper, 'LAKI') || str_contains($upper, 'LAKI-LAKI');

    if ($hasPutri) {
        return $roman . ' B';
    }
    if ($hasPutra) {
        return $roman . ' A';
    }

    if (preg_match('/^(\d+)\s*([AB])$/i', $name, $m)) {
        return $roman . ' ' . strtoupper($m[2]);
    }

    if (preg_match('/^(\d+)$/', $name)) {
        if (in_array((int)$grade, [10, 11], true)) {
            return $roman;
        }
        return $roman . ' A';
    }

    return $name;
}

function dapodik_grade_from_rombel(array $row, string $name): string
{
    $value = dapodik_row_value($row, ['tingkat_pendidikan_id', 'tingkat_pendidikan', 'grade', 'kelas_id']);
    if (preg_match('/\d+/', $value, $match)) {
        $grade = (int)$match[0];
        return $grade >= 1 && $grade <= 13 ? (string)$grade : '';
    }

    $upperName = strtoupper($name);
    $romanGrades = [
        'XII' => '12',
        'XI' => '11',
        'X' => '10',
        'IX' => '9',
        'VIII' => '8',
        'VII' => '7',
        'VI' => '6',
        'V' => '5',
        'IV' => '4',
        'III' => '3',
        'II' => '2',
        'I' => '1',
    ];
    foreach ($romanGrades as $roman => $grade) {
        if (preg_match('/(^|[^A-Z])' . $roman . '([^A-Z]|$)/', $upperName)) {
            return $grade;
        }
    }

    if (preg_match('/^\s*([1-9]|1[0-2])(?=\D|$)/', $name, $match) || preg_match('/\b([1-9]|1[0-2])\b/', $name, $match)) {
        return $match[1];
    }

    return '';
}

function dapodik_is_regular_rombel(array $row, string $name, string $grade): bool
{
    $kind = strtolower(dapodik_row_value($row, ['jenis_rombel', 'jenis_rombel_nama', 'jenis_rombel_id_str', 'jenis']));
    foreach (['ekskul', 'ekstra', 'extra', 'ekstrakurikuler'] as $needle) {
        if ($kind !== '' && str_contains($kind, $needle)) {
            return false;
        }
    }

    if (in_array($kind, ['kelas', 'reguler', 'regular'], true)) {
        return $grade !== '';
    }

    return $grade !== '';
}

function dapodik_homeroom_teacher_id(array $row): ?int
{
    $nuptk = dapodik_row_value($row, ['nuptk_wali_kelas', 'wali_nuptk', 'nuptk']);
    if ($nuptk !== '') {
        $teacher = fetch_one('SELECT id FROM teachers WHERE nuptk = ? ORDER BY id LIMIT 1', [$nuptk]);
        if ($teacher) {
            return (int)$teacher['id'];
        }
    }

    $nip = dapodik_row_value($row, ['nip_wali_kelas', 'wali_nip', 'nip']);
    if ($nip !== '') {
        $teacher = fetch_one('SELECT id FROM teachers WHERE nip = ? ORDER BY id LIMIT 1', [$nip]);
        if ($teacher) {
            return (int)$teacher['id'];
        }
    }

    $name = dapodik_row_value($row, ['nama_wali_kelas', 'wali_kelas', 'nama_wali', 'nama_ptk', 'nama_guru', 'guru']);
    if ($name !== '') {
        $teacher = fetch_one('SELECT id FROM teachers WHERE name = ? ORDER BY id LIMIT 1', [$name]);
        if ($teacher) {
            return (int)$teacher['id'];
        }
    }

    return null;
}

function dapodik_teacher_id_from_row(array $row): ?int
{
    dapodik_ensure_tracking_columns();
    $dapodikId = dapodik_external_id($row, ['ptk_id', 'guru_id', 'id_ptk', 'id_guru']);
    if ($dapodikId !== '') {
        $teacher = fetch_one('SELECT id FROM teachers WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$dapodikId]);
        if ($teacher) {
            return (int)$teacher['id'];
        }
    }

    foreach ([['nuptk'], ['nip']] as $keys) {
        $value = dapodik_row_value($row, $keys);
        if ($value === '') {
            continue;
        }
        $teacher = fetch_one('SELECT id FROM teachers WHERE ' . $keys[0] . ' = ? ORDER BY id LIMIT 1', [$value]);
        if ($teacher) {
            return (int)$teacher['id'];
        }
    }

    $name = dapodik_row_value($row, ['nama_ptk', 'nama_guru', 'nama']);
    if ($name !== '') {
        $matches = fetch_all('SELECT id FROM teachers WHERE name = ? ORDER BY id', [$name]);
        if (count($matches) === 1) {
            return (int)$matches[0]['id'];
        }
    }

    return null;
}

function dapodik_import_teacher(array $row): bool
{
    dapodik_ensure_tracking_columns();
    $name = dapodik_limit(dapodik_row_value($row, ['nama', 'nama_ptk', 'nama_guru']), 160);
    if ($name === '') {
        return false;
    }

    $dapodikId = dapodik_limit(dapodik_external_id($row, ['ptk_id', 'guru_id', 'id_ptk', 'id_guru']), 64);
    $nip = dapodik_limit(dapodik_row_value($row, ['nip']), 64);
    $nuptk = dapodik_limit(dapodik_row_value($row, ['nuptk']), 64);
    $gender = dapodik_limit(dapodik_row_value($row, ['jenis_kelamin', 'jk']), 16);
    $position = dapodik_limit(dapodik_row_value($row, ['jenis_ptk', 'jabatan', 'tugas_tambahan']), 120);
    $existingId = dapodik_teacher_id_from_row($row);

    if ($existingId) {
        execute_sql(
            'UPDATE teachers SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), name = ?, nip = ?, nuptk = ?, gender = ?, position = ?, active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $name, $nip, $nuptk, $gender, $position, now_string(), $existingId]
        );
        return true;
    }

    execute_sql(
        'INSERT INTO teachers (dapodik_id, name, nip, nuptk, gender, position, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$dapodikId, $name, $nip, $nuptk, $gender, $position, now_string()]
    );
    return true;
}

function dapodik_class_id_from_row(array $row): ?int
{
    dapodik_ensure_tracking_columns();
    $rombelId = dapodik_limit(dapodik_external_id($row, ['rombongan_belajar_id', 'rombel_id', 'id_rombel', 'id_rombongan_belajar', 'rombongan_belajar_id_str']), 64);
    if ($rombelId !== '') {
        $class = fetch_one('SELECT id FROM classes WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$rombelId]);
        if ($class) {
            return (int)$class['id'];
        }
    }

    $name = dapodik_row_value($row, ['nama_rombel', 'rombongan_belajar', 'rombel', 'nama_kelas', 'kelas']);
    if ($name === '') {
        return null;
    }

    $name = dapodik_class_name_map($name);
    $academicYear = dapodik_academic_year_from_row($row);
    $class = fetch_one('SELECT id FROM classes WHERE name = ? AND academic_year = ? ORDER BY id LIMIT 1', [$name, $academicYear])
        ?: fetch_one('SELECT id FROM classes WHERE name = ? ORDER BY id LIMIT 1', [$name]);

    return $class ? (int)$class['id'] : null;
}

function dapodik_subject_name_from_row(array $row): string
{
    return dapodik_row_value($row, ['nama_mata_pelajaran', 'mata_pelajaran', 'nama_mapel', 'mapel', 'nama']);
}

function dapodik_subject_short_from_row(array $row, string $name): string
{
    $short = dapodik_row_value($row, ['kode', 'kode_mata_pelajaran', 'kode_mapel', 'short_name']);
    return $short !== '' ? $short : mb_strimwidth($name, 0, 40, '');
}

function dapodik_subject_id_from_row(array $row): ?int
{
    dapodik_ensure_tracking_columns();
    $dapodikId = dapodik_external_id($row, ['mata_pelajaran_id', 'mapel_id', 'id_mapel', 'id_mata_pelajaran']);
    if ($dapodikId !== '') {
        $subject = fetch_one('SELECT id FROM subjects WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$dapodikId]);
        if ($subject) {
            return (int)$subject['id'];
        }
    }

    $name = dapodik_limit(dapodik_subject_name_from_row($row), 160);
    if ($name !== '') {
        $subject = fetch_one('SELECT id FROM subjects WHERE name = ? ORDER BY id LIMIT 1', [$name]);
        if ($subject) {
            return (int)$subject['id'];
        }
    }

    return null;
}

function dapodik_import_subject(array $row, bool $requireTeacher = true): ?int
{
    dapodik_ensure_tracking_columns();
    if ($requireTeacher && !dapodik_teacher_id_from_row($row)) {
        return null;
    }

    $name = dapodik_subject_name_from_row($row);
    if ($name === '') {
        return null;
    }

    $dapodikId = dapodik_limit(dapodik_external_id($row, ['mata_pelajaran_id', 'mapel_id', 'id_mapel', 'id_mata_pelajaran']), 64);
    $short = dapodik_limit(dapodik_subject_short_from_row($row, $name), 40);
    $group = dapodik_limit(dapodik_row_value($row, ['kelompok', 'kelompok_mapel', 'group_name']), 80);
    $existingId = dapodik_subject_id_from_row($row);

    if ($existingId) {
        execute_sql(
            'UPDATE subjects SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), name = ?, short_name = ?, group_name = COALESCE(NULLIF(?, \'\'), group_name), active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $name, $short, $group, now_string(), $existingId]
        );
        return $existingId;
    }

    execute_sql(
        'INSERT INTO subjects (dapodik_id, name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)',
        [$dapodikId, $name, $short, $group, now_string()]
    );
    return (int)db()->lastInsertId();
}

function dapodik_student_id_from_row(array $row): ?int
{
    dapodik_ensure_tracking_columns();
    $studentDapodikId = dapodik_external_id($row, ['peserta_didik_id', 'pd_id', 'id_pd', 'id_peserta_didik']);
    if ($studentDapodikId !== '') {
        $student = fetch_one('SELECT id FROM students WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$studentDapodikId]);
        if ($student) {
            return (int)$student['id'];
        }
    }

    $nisn = dapodik_row_value($row, ['nisn']);
    if ($nisn !== '') {
        $student = fetch_one('SELECT id FROM students WHERE nisn = ? ORDER BY id LIMIT 1', [$nisn]);
        if ($student) {
            return (int)$student['id'];
        }
    }

    $nis = dapodik_row_value($row, ['nis', 'nipd', 'nomor_induk']);
    if ($nis !== '') {
        $student = fetch_one('SELECT id FROM students WHERE nis = ? ORDER BY id LIMIT 1', [$nis]);
        if ($student) {
            return (int)$student['id'];
        }
    }

    $name = dapodik_row_value($row, ['nama', 'nama_siswa', 'nama_peserta_didik']);
    if ($name !== '') {
        $matches = fetch_all('SELECT id FROM students WHERE name = ? ORDER BY id', [$name]);
        if (count($matches) === 1) {
            return (int)$matches[0]['id'];
        }
    }

    return null;
}

function dapodik_cleanup_nonclass_rombel(array $row, string $name): void
{
    $rows = fetch_all('SELECT id FROM classes WHERE name = ?', [$name]);
    foreach ($rows as $class) {
        $classId = (int)$class['id'];
        $studentCount = (int)(fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ?', [$classId])['c'] ?? 0);
        $assignmentCount = table_exists('teaching_assignments')
            ? (int)(fetch_one('SELECT COUNT(*) AS c FROM teaching_assignments WHERE class_id = ?', [$classId])['c'] ?? 0)
            : 0;
        if ($studentCount === 0 && $assignmentCount === 0) {
            execute_sql('DELETE FROM classes WHERE id = ?', [$classId]);
        }
    }
}

function dapodik_cache_rombel(array $row, bool $isRegular, string $name, string $grade, string $major, string $academicYear, ?int $teacherId): void
{
    dapodik_ensure_tracking_columns();
    $dapodikId = dapodik_external_id($row, ['rombongan_belajar_id', 'rombel_id', 'id_rombel', 'id_rombongan_belajar', 'rombongan_belajar_id_str']);
    if ($dapodikId === '') {
        return;
    }

    $kind = dapodik_row_value($row, ['jenis_rombel', 'jenis_rombel_nama', 'jenis_rombel_id_str', 'jenis']);
    $existing = fetch_one('SELECT id FROM dapodik_rombel_cache WHERE dapodik_id = ?', [$dapodikId]);
    if ($existing) {
        execute_sql(
            'UPDATE dapodik_rombel_cache SET name = ?, kind = ?, grade = ?, major = ?, academic_year = ?, teacher_id = ?, is_regular = ?, updated_at = ? WHERE id = ?',
            [$name, $kind, $grade, $major, $academicYear, $teacherId, $isRegular ? 1 : 0, now_string(), (int)$existing['id']]
        );
        return;
    }

    execute_sql(
        'INSERT INTO dapodik_rombel_cache (dapodik_id, name, kind, grade, major, academic_year, teacher_id, is_regular, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$dapodikId, $name, $kind, $grade, $major, $academicYear, $teacherId, $isRegular ? 1 : 0, now_string()]
    );
}

function dapodik_extracurricular_id_from_cache(array $cache): int
{
    dapodik_ensure_tracking_columns();
    $dapodikId = (string)($cache['dapodik_id'] ?? '');
    $name = (string)($cache['name'] ?? '');
    $type = (string)($cache['kind'] ?? 'Ekstrakurikuler');
    $className = $name;
    $teacherId = !empty($cache['teacher_id']) ? (int)$cache['teacher_id'] : null;

    $existing = $dapodikId !== ''
        ? fetch_one('SELECT id FROM extracurriculars WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$dapodikId])
        : null;
    if (!$existing && $name !== '') {
        $existing = fetch_one('SELECT id FROM extracurriculars WHERE name = ? ORDER BY id LIMIT 1', [$name]);
    }

    if ($existing) {
        execute_sql(
            'UPDATE extracurriculars SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), class_name = ?, type = ?, name = ?, teacher_id = COALESCE(?, teacher_id), active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $className, $type, $name, $teacherId, now_string(), (int)$existing['id']]
        );
        return (int)$existing['id'];
    }

    execute_sql(
        'INSERT INTO extracurriculars (dapodik_id, class_name, type, name, teacher_id, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
        [$dapodikId, $className, $type, $name, $teacherId, now_string()]
    );
    return (int)db()->lastInsertId();
}

function dapodik_add_extracurricular_member(int $extracurricularId, int $studentId): void
{
    $existing = fetch_one('SELECT id FROM extracurricular_members WHERE extracurricular_id = ? AND student_id = ?', [$extracurricularId, $studentId]);
    if ($existing) {
        execute_sql('UPDATE extracurricular_members SET updated_at = ? WHERE id = ?', [now_string(), (int)$existing['id']]);
        return;
    }

    execute_sql(
        'INSERT INTO extracurricular_members (extracurricular_id, student_id, updated_at) VALUES (?, ?, ?)',
        [$extracurricularId, $studentId, now_string()]
    );
}

function dapodik_cleanup_key(array $row, array $fields): string
{
    foreach ($fields as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value !== '') {
            return $field . ':' . mb_strtolower($value);
        }
    }
    return '';
}

function dapodik_merge_duplicate_rows(string $table, array $rows, array $keyFields, array $references): void
{
    if (!table_exists($table)) {
        return;
    }

    $groups = [];
    foreach ($rows as $row) {
        $key = dapodik_cleanup_key($row, $keyFields);
        if ($key === '') {
            continue;
        }
        $groups[$key][] = $row;
    }

    foreach ($groups as $groupRows) {
        if (count($groupRows) < 2) {
            continue;
        }
        usort($groupRows, fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
        $keepId = (int)$groupRows[0]['id'];
        foreach (array_slice($groupRows, 1) as $duplicate) {
            $duplicateId = (int)$duplicate['id'];
            $ok = true;
            foreach ($references as $refTable => $columns) {
                if (!table_exists($refTable)) {
                    continue;
                }
                foreach ((array)$columns as $column) {
                    try {
                        execute_sql('UPDATE ' . db_identifier($refTable) . ' SET ' . db_identifier($column) . ' = ? WHERE ' . db_identifier($column) . ' = ?', [$keepId, $duplicateId]);
                    } catch (Throwable) {
                        $ok = false;
                    }
                }
            }
            if ($ok) {
                try {
                    execute_sql('DELETE FROM ' . db_identifier($table) . ' WHERE id = ?', [$duplicateId]);
                } catch (Throwable) {
                    // Keep the duplicate if the database refuses deletion.
                }
            }
        }
    }
}

function dapodik_cleanup_duplicates(string $type): void
{
    if ($type === 'guru') {
        $rows = fetch_all('SELECT * FROM teachers ORDER BY id');
        dapodik_merge_duplicate_rows('teachers', $rows, ['dapodik_id', 'nuptk', 'nip', 'name'], [
            'classes' => ['homeroom_teacher_id'],
            'teaching_assignments' => ['teacher_id'],
            'teacher_schedule_requests' => ['teacher_id'],
            'lesson_schedules' => ['teacher_id'],
            'lesson_schedule_reminder_logs' => ['teacher_id'],
            'users' => ['teacher_id'],
            'teacher_attendance' => ['teacher_id'],
            'teacher_teaching_attendance' => ['teacher_id'],
            'daily_journals' => ['teacher_id'],
            'extracurriculars' => ['teacher_id'],
        ]);
    } elseif ($type === 'siswa' || $type === 'anggota_rombel') {
        $rows = fetch_all('SELECT * FROM students ORDER BY id');
        dapodik_merge_duplicate_rows('students', $rows, ['dapodik_id', 'nisn', 'nis', 'name'], [
            'users' => ['student_id'],
            'grades' => ['student_id'],
            'student_attendance_entries' => ['student_id'],
            'student_violations' => ['student_id'],
            'whatsapp_guardians' => ['student_id'],
            'whatsapp_queue' => ['student_id'],
            'whatsapp_logs' => ['student_id'],
            'final_scores' => ['student_id'],
            'graduations' => ['student_id'],
            'kokurikuler_group_members' => ['student_id'],
            'extracurricular_members' => ['student_id'],
        ]);
    } elseif ($type === 'rombel') {
        $rows = fetch_all('SELECT * FROM classes ORDER BY id');
        foreach ($rows as &$row) {
            $row['class_key'] = (string)($row['name'] ?? '') . ':' . (string)($row['academic_year'] ?? '');
        }
        unset($row);
        dapodik_merge_duplicate_rows('classes', $rows, ['dapodik_id', 'class_key'], [
            'students' => ['class_id'],
            'teaching_assignments' => ['class_id'],
            'lesson_schedules' => ['class_id'],
            'teacher_teaching_attendance' => ['class_id'],
            'daily_journals' => ['class_id'],
        ]);
    } elseif ($type === 'mapel' || $type === 'pembelajaran') {
        $rows = fetch_all('SELECT * FROM subjects ORDER BY id');
        dapodik_merge_duplicate_rows('subjects', $rows, ['dapodik_id', 'name'], [
            'teaching_assignments' => ['subject_id'],
            'lesson_schedules' => ['subject_id'],
            'learning_objectives' => ['subject_id'],
            'report_mappings' => ['subject_id'],
            'final_scores' => ['subject_id'],
            'merged_subjects' => ['source_subject_id', 'target_subject_id'],
            'teacher_teaching_attendance' => ['subject_id'],
            'daily_journals' => ['subject_id'],
        ]);
        $assignments = fetch_all('SELECT * FROM teaching_assignments ORDER BY id');
        foreach ($assignments as &$assignment) {
            $assignment['assignment_key'] = implode(':', [
                (string)($assignment['teacher_id'] ?? ''),
                (string)($assignment['class_id'] ?? ''),
                (string)($assignment['subject_id'] ?? ''),
                (string)($assignment['academic_year'] ?? ''),
                (string)($assignment['semester'] ?? ''),
            ]);
        }
        unset($assignment);
        dapodik_merge_duplicate_rows('teaching_assignments', $assignments, ['dapodik_id', 'assignment_key'], [
            'grades' => ['assignment_id'],
            'student_attendance_sessions' => ['assignment_id'],
            'lesson_schedules' => ['assignment_id'],
            'teacher_teaching_attendance' => ['assignment_id'],
            'daily_journals' => ['assignment_id'],
        ]);
    }

    if (($type === 'rombel' || $type === 'anggota_rombel') && table_exists('extracurriculars')) {
        $rows = fetch_all('SELECT * FROM extracurriculars ORDER BY id');
        dapodik_merge_duplicate_rows('extracurriculars', $rows, ['dapodik_id', 'name'], [
            'extracurricular_members' => ['extracurricular_id'],
        ]);
    }
}

function dapodik_import_rombel(array $row): bool
{
    dapodik_ensure_tracking_columns();
    $name = dapodik_limit(dapodik_row_value($row, ['nama', 'nama_rombel', 'rombongan_belajar', 'rombel', 'nama_kelas', 'kelas']), 80);
    if ($name === '') {
        return false;
    }

    $name = dapodik_class_name_map($name);
    $grade = dapodik_grade_from_rombel($row, $name);
    $dapodikId = dapodik_limit(dapodik_external_id($row, ['rombongan_belajar_id', 'rombel_id', 'id_rombel', 'id_rombongan_belajar', 'rombongan_belajar_id_str']), 64);
    $major = dapodik_limit(dapodik_row_value($row, ['nama_jurusan_sp', 'jurusan', 'program_keahlian', 'kompetensi_keahlian', 'major']), 80);
    $academicYear = dapodik_academic_year_from_row($row);
    $homeroomTeacherId = dapodik_homeroom_teacher_id($row);
    $isRegular = dapodik_is_regular_rombel($row, $name, $grade);
    dapodik_cache_rombel($row, $isRegular, $name, $grade, $major, $academicYear, $homeroomTeacherId);
    if (!$isRegular) {
        dapodik_cleanup_nonclass_rombel($row, $name);
        return false;
    }

    $existing = ($dapodikId !== '' ? fetch_one('SELECT id FROM classes WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$dapodikId]) : null)
        ?: fetch_one('SELECT id FROM classes WHERE name = ? AND academic_year = ? ORDER BY id LIMIT 1', [$name, $academicYear])
        ?: fetch_one('SELECT id FROM classes WHERE name = ? ORDER BY id LIMIT 1', [$name]);

    if ($existing) {
        execute_sql(
            'UPDATE classes SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), name = ?, grade = ?, major = ?, homeroom_teacher_id = COALESCE(?, homeroom_teacher_id), academic_year = ?, active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $name, $grade, $major, $homeroomTeacherId, $academicYear, now_string(), (int)$existing['id']]
        );
        return true;
    }

    execute_sql(
        'INSERT INTO classes (dapodik_id, name, grade, major, homeroom_teacher_id, academic_year, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$dapodikId, $name, $grade, $major, $homeroomTeacherId, $academicYear, now_string()]
    );

    return true;
}

function dapodik_import_student(array $row): bool
{
    dapodik_ensure_tracking_columns();
    $name = dapodik_limit(dapodik_row_value($row, ['nama', 'nama_siswa', 'nama_peserta_didik']), 160);
    if ($name === '') {
        return false;
    }

    $dapodikId = dapodik_limit(dapodik_external_id($row, ['peserta_didik_id', 'pd_id', 'id_pd', 'id_peserta_didik']), 64);
    $nis = dapodik_limit(dapodik_row_value($row, ['nis', 'nipd', 'nomor_induk']), 64);
    $nisn = dapodik_limit(dapodik_row_value($row, ['nisn']), 64);
    $gender = dapodik_limit(dapodik_row_value($row, ['jenis_kelamin', 'jk']), 16);
    $birthPlace = dapodik_limit(dapodik_row_value($row, ['tempat_lahir']), 80);
    $birthDate = dapodik_nullable_date(dapodik_row_value($row, ['tanggal_lahir', 'tgl_lahir']));
    $religion = dapodik_limit(dapodik_row_value($row, ['agama', 'agama_id_str']), 64);
    $classId = dapodik_class_id_from_row($row);
    $existingId = dapodik_student_id_from_row($row);

    if ($existingId) {
        execute_sql(
            'UPDATE students SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), nis = ?, nisn = ?, name = ?, gender = ?, birth_place = ?, birth_date = ?, religion = ?, class_id = COALESCE(?, class_id), active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $nis, $nisn, $name, $gender, $birthPlace, $birthDate, $religion, $classId, now_string(), $existingId]
        );
        return true;
    }

    execute_sql(
        'INSERT INTO students (dapodik_id, nis, nisn, name, gender, birth_place, birth_date, religion, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$dapodikId, $nis, $nisn, $name, $gender, $birthPlace, $birthDate, $religion, $classId, now_string()]
    );

    return true;
}

function dapodik_import_anggota_rombel(array $row): bool
{
    dapodik_ensure_tracking_columns();
    $classId = dapodik_class_id_from_row($row);
    $studentId = dapodik_student_id_from_row($row);
    if (!$studentId) {
        return false;
    }

    if ($classId) {
        execute_sql('UPDATE students SET class_id = ?, active = 1, updated_at = ? WHERE id = ?', [$classId, now_string(), $studentId]);
        return true;
    }

    $rombelId = dapodik_external_id($row, ['rombongan_belajar_id', 'rombel_id', 'id_rombel', 'id_rombongan_belajar', 'rombongan_belajar_id_str']);
    if ($rombelId === '') {
        return false;
    }

    $cache = fetch_one('SELECT * FROM dapodik_rombel_cache WHERE dapodik_id = ? AND is_regular = 0', [$rombelId]);
    if (!$cache) {
        return false;
    }

    $extracurricularId = dapodik_extracurricular_id_from_cache($cache);
    dapodik_add_extracurricular_member($extracurricularId, $studentId);
    return true;
}

function dapodik_semester_from_row(array $row): string
{
    $semester = dapodik_row_value($row, ['semester', 'nama_semester', 'semester_id']);
    if ($semester !== '') {
        if (str_ends_with($semester, '1')) {
            return 'Ganjil';
        }
        if (str_ends_with($semester, '2')) {
            return 'Genap';
        }
        return $semester;
    }

    return (string)config('school.semester', 'Genap');
}

function dapodik_import_pembelajaran(array $row): bool
{
    dapodik_ensure_tracking_columns();
    $teacherId = dapodik_teacher_id_from_row($row);
    $classId = dapodik_class_id_from_row($row);
    if (!$teacherId || !$classId) {
        return false;
    }

    $subjectId = dapodik_import_subject($row, false);
    if (!$subjectId) {
        return false;
    }

    $dapodikId = dapodik_limit(dapodik_external_id($row, ['pembelajaran_id', 'id_pembelajaran']), 64);
    $academicYear = dapodik_academic_year_from_row($row);
    $semester = dapodik_semester_from_row($row);
    $existing = $dapodikId !== ''
        ? fetch_one('SELECT id FROM teaching_assignments WHERE dapodik_id = ? ORDER BY id LIMIT 1', [$dapodikId])
        : null;
    if (!$existing) {
        $existing = fetch_one(
            'SELECT id FROM teaching_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND academic_year = ? AND semester = ? ORDER BY id LIMIT 1',
            [$teacherId, $classId, $subjectId, $academicYear, $semester]
        );
    }

    if ($existing) {
        execute_sql(
            'UPDATE teaching_assignments SET dapodik_id = COALESCE(NULLIF(?, \'\'), dapodik_id), teacher_id = ?, class_id = ?, subject_id = ?, academic_year = ?, semester = ?, active = 1, updated_at = ? WHERE id = ?',
            [$dapodikId, $teacherId, $classId, $subjectId, $academicYear, $semester, now_string(), (int)$existing['id']]
        );
        return true;
    }

    execute_sql(
        'INSERT INTO teaching_assignments (dapodik_id, teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$dapodikId, $teacherId, $classId, $subjectId, $academicYear, $semester, now_string()]
    );
    return true;
}

function dapodik_import_set_warnings(string $type, array $warnings): void
{
    $GLOBALS['dapodik_import_warnings'][$type] = $warnings;
}

function dapodik_import_warnings(string $type): array
{
    $warnings = $GLOBALS['dapodik_import_warnings'][$type] ?? [];
    return is_array($warnings) ? $warnings : [];
}

function dapodik_import_warning_payload(string $type): array
{
    $warnings = dapodik_import_warnings($type);
    if (!$warnings) {
        return [];
    }

    return [
        'warning_count' => count($warnings),
        'warnings' => array_slice($warnings, 0, 5),
    ];
}

function dapodik_import(string $type, array $json): int
{
    dapodik_ensure_tracking_columns();
    $rows = dapodik_records($json);
    $count = 0;
    $warnings = [];
    $rowNumber = 0;
    foreach ($rows as $row) {
        $rowNumber++;
        if (!is_array($row)) {
            continue;
        }
        try {
            if ($type === 'sekolah') {
                $school = get_school_profile();
                if (!isset($school['id'])) {
                    execute_sql(
                        'INSERT INTO school_profile (name, npsn, address, principal_name, academic_year, semester, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            $row['nama'] ?? $row['nama_sekolah'] ?? $school['name'] ?? '',
                            $row['npsn'] ?? '',
                            $row['alamat'] ?? $row['alamat_jalan'] ?? '',
                            $row['nama_kepala_sekolah'] ?? $row['kepala_sekolah'] ?? '',
                            (string)config('school.academic_year', '2025/2026'),
                            (string)config('school.semester', 'Genap'),
                            now_string(),
                        ]
                    );
                } else {
                    execute_sql(
                        'UPDATE school_profile SET name = ?, npsn = ?, address = ?, principal_name = ?, updated_at = ? WHERE id = ?',
                        [
                            $row['nama'] ?? $row['nama_sekolah'] ?? $school['name'],
                            $row['npsn'] ?? '',
                            $row['alamat'] ?? $row['alamat_jalan'] ?? '',
                            $row['nama_kepala_sekolah'] ?? $row['kepala_sekolah'] ?? $school['principal_name'] ?? '',
                            now_string(),
                            (int)$school['id'],
                        ]
                    );
                }
                $count++;
            } elseif ($type === 'guru') {
                if (dapodik_import_teacher($row)) {
                    $count++;
                }
            } elseif ($type === 'siswa') {
                if (dapodik_import_student($row)) {
                    $count++;
                }
            } elseif ($type === 'mapel') {
                if (dapodik_import_subject($row, true)) {
                    $count++;
                }
            } elseif ($type === 'rombel') {
                if (dapodik_import_rombel($row)) {
                    $count++;
                }
            } elseif ($type === 'anggota_rombel') {
                if (dapodik_import_anggota_rombel($row)) {
                    $count++;
                }
            } elseif ($type === 'pembelajaran') {
                if (dapodik_import_pembelajaran($row)) {
                    $count++;
                }
            } else {
                $count++;
            }
        } catch (Throwable $exception) {
            log_exception($exception);
            $warnings[] = 'Baris ' . $rowNumber . ': ' . $exception->getMessage();
        }
    }
    try {
        dapodik_cleanup_duplicates($type);
    } catch (Throwable $exception) {
        log_exception($exception);
        $warnings[] = 'Cleanup duplikat: ' . $exception->getMessage();
    }
    dapodik_import_set_warnings($type, $warnings);
    if ($count === 0 && $warnings) {
        throw new RuntimeException('Import ' . $type . ' gagal di semua baris. Contoh error: ' . $warnings[0]);
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

function dapodik_push_endpoint_candidates(string $baseUrl, string $kind, string $token): array
{
    $baseUrl = rtrim(normalize_http_url($baseUrl), '/');
    $names = $kind === 'matev'
        ? ['simpanMatev', 'saveMatev', 'kirimMatev']
        : ['simpanNilai', 'saveNilai', 'kirimNilai'];
    $endpoints = [];
    foreach ($names as $name) {
        $endpoints[] = $baseUrl . '/WebService/' . $name;
        if ($token !== '') {
            $endpoints[] = $baseUrl . '/WebService/' . $name . '?' . http_build_query(['token' => $token]);
        }
    }
    $endpoints[] = $baseUrl . '/erapor/' . rawurlencode($kind);
    return array_values(array_unique($endpoints));
}

function dapodik_post_json_once(string $endpoint, array $payload, string $token): array
{
    $ch = curl_init($endpoint);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
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
    return ['endpoint' => $endpoint, 'code' => $code, 'body' => (string)$body, 'error' => (string)$error];
}

function dapodik_post_payload(string $baseUrl, string $kind, array $payload): string
{
    if (!function_exists('curl_init')) {
        return 'Pengiriman gagal: ekstensi PHP cURL belum aktif.';
    }

    $token = trim((string)get_app_setting('dapodik_token', ''));
    $npsn = trim((string)get_app_setting('dapodik_npsn', ''));
    $payload['token'] = $token;
    $payload['npsn'] = $npsn;
    $attempts = [];
    foreach (dapodik_push_endpoint_candidates($baseUrl, $kind, $token) as $endpoint) {
        $result = dapodik_post_json_once($endpoint, $payload, $token);
        $attempts[] = basename((string)parse_url($endpoint, PHP_URL_PATH)) . ': HTTP ' . $result['code'] . ($result['error'] !== '' ? ' ' . $result['error'] : '');
        if ($result['error'] === '' && $result['code'] >= 200 && $result['code'] < 300) {
            return 'Pengiriman ' . $kind . ' diproses via ' . $endpoint . '. HTTP ' . $result['code'] . '. Respons: ' . mb_strimwidth(trim($result['body']), 0, 180, '...');
        }
    }

    return 'Pengiriman ' . $kind . ' belum diterima Dapodik. Percobaan: ' . implode('; ', $attempts) . '. Cek endpoint write Dapodik/e-Rapor pada instalasi lokal.';
}
