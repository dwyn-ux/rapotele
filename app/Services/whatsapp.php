<?php

declare(strict_types=1);

function whatsapp_tables_ready(): bool
{
    return table_exists('whatsapp_guardians')
        && table_exists('whatsapp_templates')
        && table_exists('whatsapp_queue')
        && table_exists('whatsapp_logs');
}

function whatsapp_require_tables(): void
{
    if (!whatsapp_tables_ready()) {
        run_migrations();
    }
    if (!whatsapp_tables_ready()) {
        throw new RuntimeException('Tabel WhatsApp belum tersedia. Jalankan php tools/install.php sekali.');
    }
}

function whatsapp_modes(): array
{
    return [
        'simulate' => 'Simulasi / Log Saja',
        'cloud_api' => 'WhatsApp Cloud API',
        'fonnte' => 'Fonnte Gateway',
    ];
}

function whatsapp_delivery_modes(): array
{
    return [
        'text' => 'Text Message',
        'template' => 'Template Message',
    ];
}

function whatsapp_status_labels(): array
{
    return [
        'pending' => 'Pending',
        'sent' => 'Terkirim',
        'failed' => 'Gagal',
        'cancelled' => 'Dibatalkan',
    ];
}

function whatsapp_setting(string $key, mixed $default = ''): mixed
{
    return get_app_setting('whatsapp_' . $key, $default);
}

function whatsapp_set_setting(string $key, mixed $value): void
{
    set_app_setting('whatsapp_' . $key, $value);
}

function whatsapp_cron_secret(): string
{
    return trim((string)whatsapp_setting('cron_secret', ''));
}

function whatsapp_cron_url(): string
{
    $secret = whatsapp_cron_secret();
    $query = $secret !== '' ? '?' . http_build_query(['secret' => $secret]) : '';
    return app_url('whatsapp_weekly.php') . $query;
}

function whatsapp_current_week_range(?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $start = $now->modify('monday this week')->format('Y-m-d');
    $end = $now->modify('sunday this week')->format('Y-m-d');
    return [$start, $end];
}

function whatsapp_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '8')) {
        $digits = '62' . $digits;
    }

    return $digits;
}

function whatsapp_validate_phone(string $phone): string
{
    $normalized = whatsapp_normalize_phone($phone);
    if (!preg_match('/^[1-9][0-9]{9,15}$/', $normalized)) {
        throw new RuntimeException('Nomor WhatsApp tidak valid. Gunakan format 628xxxxxxxxxx.');
    }
    return $normalized;
}

function whatsapp_template_by_code(string $code): ?array
{
    whatsapp_require_tables();
    return fetch_one('SELECT * FROM whatsapp_templates WHERE code = ? AND active = 1', [$code]);
}

function whatsapp_render_template(string $body, array $variables): string
{
    $replace = [];
    foreach ($variables as $key => $value) {
        $replace['{' . $key . '}'] = (string)$value;
    }
    return strtr($body, $replace);
}

function whatsapp_template_variable_json(array $variables): string
{
    return json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function whatsapp_student_row(int $studentId): array
{
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name, c.grade, sp.name AS school_name
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN school_profile sp ON sp.id = (SELECT id FROM school_profile ORDER BY id LIMIT 1)
         WHERE s.id = ?',
        [$studentId]
    );
    if (!$student) {
        throw new RuntimeException('Siswa tidak ditemukan.');
    }
    return $student;
}

function whatsapp_guardians_for_student(int $studentId): array
{
    whatsapp_require_tables();
    return fetch_all(
        'SELECT * FROM whatsapp_guardians
         WHERE student_id = ? AND active = 1 AND whatsapp_enabled = 1
         ORDER BY id',
        [$studentId]
    );
}

function whatsapp_attendance_summary(int $studentId, string $startDate, string $endDate): array
{
    $summary = array_fill_keys(array_keys(allowed_statuses()), 0);
    $rows = fetch_all(
        'SELECT e.status, COUNT(*) AS total
         FROM student_attendance_entries e
         JOIN student_attendance_sessions ses ON ses.id = e.session_id
         WHERE e.student_id = ? AND ses.date BETWEEN ? AND ?
         GROUP BY e.status',
        [$studentId, $startDate, $endDate]
    );
    foreach ($rows as $row) {
        $summary[(string)$row['status']] = (int)$row['total'];
    }
    return $summary;
}

function whatsapp_violation_rows(int $studentId, string $startDate, string $endDate): array
{
    return fetch_all(
        'SELECT * FROM student_violations
         WHERE student_id = ? AND date BETWEEN ? AND ?
         ORDER BY date DESC, id DESC',
        [$studentId, $startDate, $endDate]
    );
}

function whatsapp_violation_summary_text(array $violations): string
{
    if (!$violations) {
        return 'Tidak ada catatan pelanggaran pada pekan ini.';
    }

    $items = [];
    foreach (array_slice($violations, 0, 3) as $row) {
        $items[] = (string)$row['date'] . ' - ' . (string)$row['type'] . ' (' . (int)$row['points'] . ' poin)';
    }
    if (count($violations) > 3) {
        $items[] = 'Dan ' . (count($violations) - 3) . ' catatan lain.';
    }
    return implode("\n", $items);
}

function whatsapp_weekly_variables(array $student, array $guardian, string $startDate, string $endDate): array
{
    $attendance = whatsapp_attendance_summary((int)$student['id'], $startDate, $endDate);
    $violations = whatsapp_violation_rows((int)$student['id'], $startDate, $endDate);
    return [
        'school' => (string)($student['school_name'] ?? config('school.name', 'Sekolah')),
        'guardian_name' => (string)$guardian['name'],
        'student_name' => (string)$student['name'],
        'class_name' => (string)($student['class_name'] ?? '-'),
        'start_date' => whatsapp_format_date($startDate),
        'end_date' => whatsapp_format_date($endDate),
        'hadir' => (string)($attendance['hadir'] ?? 0),
        'sakit' => (string)($attendance['sakit'] ?? 0),
        'izin' => (string)($attendance['izin'] ?? 0),
        'alpa' => (string)($attendance['alpa'] ?? 0),
        'terlambat' => (string)($attendance['terlambat'] ?? 0),
        'violation_count' => (string)count($violations),
        'violation_points' => (string)array_sum(array_map(fn (array $row): int => (int)$row['points'], $violations)),
        'violation_summary' => whatsapp_violation_summary_text($violations),
    ];
}

function whatsapp_format_date(string $date): string
{
    if (function_exists('format_indonesian_date')) {
        return format_indonesian_date($date);
    }
    return date('d-m-Y', strtotime($date) ?: time());
}

function whatsapp_enqueue_template(
    int $studentId,
    int $guardianId,
    string $templateCode,
    array $variables,
    string $messageType,
    string $contextKey,
    ?string $reportStart = null,
    ?string $reportEnd = null,
    ?int $createdBy = null
): bool {
    whatsapp_require_tables();
    $student = whatsapp_student_row($studentId);
    $guardian = fetch_one('SELECT * FROM whatsapp_guardians WHERE id = ? AND student_id = ?', [$guardianId, $studentId]);
    if (!$guardian || !(int)$guardian['active'] || !(int)$guardian['whatsapp_enabled']) {
        return false;
    }
    $template = whatsapp_template_by_code($templateCode);
    if (!$template) {
        throw new RuntimeException('Template WhatsApp tidak ditemukan: ' . $templateCode);
    }

    $phone = whatsapp_validate_phone((string)$guardian['phone']);
    $variables = ['school' => (string)($student['school_name'] ?? config('school.name', 'Sekolah'))] + $variables;
    $message = whatsapp_render_template((string)$template['body'], $variables);

    $existing = fetch_one(
        "SELECT id FROM whatsapp_queue
         WHERE guardian_id = ? AND student_id = ? AND message_type = ? AND context_key = ?
           AND status IN ('pending', 'sent')
         LIMIT 1",
        [$guardianId, $studentId, $messageType, $contextKey]
    );
    if ($existing) {
        return false;
    }

    execute_sql(
        'INSERT INTO whatsapp_queue
            (student_id, guardian_id, template_id, message_type, context_key, report_start, report_end, phone, message, template_variables, status, scheduled_at, created_by, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $studentId,
            $guardianId,
            (int)$template['id'],
            $messageType,
            $contextKey,
            $reportStart,
            $reportEnd,
            $phone,
            $message,
            whatsapp_template_variable_json($variables),
            'pending',
            now_string(),
            $createdBy,
            now_string(),
        ]
    );
    return true;
}

function whatsapp_enqueue_weekly_reports(string $startDate, string $endDate, int $classId = 0, ?int $createdBy = null): array
{
    whatsapp_require_tables();
    $params = [];
    $where = 's.active = 1';
    if ($classId > 0) {
        $where .= ' AND s.class_id = ?';
        $params[] = $classId;
    }
    $students = fetch_all(
        "SELECT s.*, c.name AS class_name, sp.name AS school_name
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN school_profile sp ON sp.id = (SELECT id FROM school_profile ORDER BY id LIMIT 1)
         WHERE $where
         ORDER BY c.grade, c.name, s.name",
        $params
    );
    $result = ['students' => count($students), 'queued' => 0, 'skipped_no_guardian' => 0, 'skipped_duplicate' => 0];
    foreach ($students as $student) {
        $guardians = whatsapp_guardians_for_student((int)$student['id']);
        if (!$guardians) {
            $result['skipped_no_guardian']++;
            continue;
        }
        foreach ($guardians as $guardian) {
            $variables = whatsapp_weekly_variables($student, $guardian, $startDate, $endDate);
            $queued = whatsapp_enqueue_template(
                (int)$student['id'],
                (int)$guardian['id'],
                'weekly_report',
                $variables,
                'weekly',
                'weekly:' . $startDate . ':' . $endDate,
                $startDate,
                $endDate,
                $createdBy
            );
            $queued ? $result['queued']++ : $result['skipped_duplicate']++;
        }
    }
    return $result;
}

function whatsapp_enqueue_violation_notice(int $violationId, ?int $createdBy = null): array
{
    whatsapp_require_tables();
    $row = fetch_one(
        'SELECT v.*, s.name AS student_name, c.name AS class_name, sp.name AS school_name
         FROM student_violations v
         JOIN students s ON s.id = v.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN school_profile sp ON sp.id = (SELECT id FROM school_profile ORDER BY id LIMIT 1)
         WHERE v.id = ?',
        [$violationId]
    );
    if (!$row) {
        throw new RuntimeException('Data pelanggaran tidak ditemukan.');
    }

    $guardians = whatsapp_guardians_for_student((int)$row['student_id']);
    $result = ['guardians' => count($guardians), 'queued' => 0, 'skipped_duplicate' => 0];
    foreach ($guardians as $guardian) {
        $variables = [
            'guardian_name' => (string)$guardian['name'],
            'student_name' => (string)$row['student_name'],
            'class_name' => (string)($row['class_name'] ?? '-'),
            'date' => whatsapp_format_date((string)$row['date']),
            'type' => (string)$row['type'],
            'points' => (string)$row['points'],
            'description' => (string)($row['description'] ?? '-'),
            'action_taken' => (string)($row['action_taken'] ?? '-'),
            'school' => (string)($row['school_name'] ?? config('school.name', 'Sekolah')),
        ];
        $queued = whatsapp_enqueue_template(
            (int)$row['student_id'],
            (int)$guardian['id'],
            'violation_notice',
            $variables,
            'violation',
            'violation:' . $violationId,
            null,
            null,
            $createdBy
        );
        $queued ? $result['queued']++ : $result['skipped_duplicate']++;
    }
    return $result;
}

function whatsapp_enqueue_attendance_notice(int $entryId, ?int $createdBy = null): array
{
    whatsapp_require_tables();
    $row = fetch_one(
        'SELECT e.*, ses.date, sub.name AS subject_name, s.name AS student_name, c.name AS class_name, sp.name AS school_name
         FROM student_attendance_entries e
         JOIN student_attendance_sessions ses ON ses.id = e.session_id
         JOIN teaching_assignments ta ON ta.id = ses.assignment_id
         JOIN subjects sub ON sub.id = ta.subject_id
         JOIN students s ON s.id = e.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN school_profile sp ON sp.id = (SELECT id FROM school_profile ORDER BY id LIMIT 1)
         WHERE e.id = ?',
        [$entryId]
    );
    if (!$row) {
        throw new RuntimeException('Data absensi tidak ditemukan.');
    }

    $guardians = whatsapp_guardians_for_student((int)$row['student_id']);
    $result = ['guardians' => count($guardians), 'queued' => 0, 'skipped_duplicate' => 0];
    foreach ($guardians as $guardian) {
        $variables = [
            'guardian_name' => (string)$guardian['name'],
            'student_name' => (string)$row['student_name'],
            'class_name' => (string)($row['class_name'] ?? '-'),
            'date' => whatsapp_format_date((string)$row['date']),
            'status' => (string)(allowed_statuses()[$row['status']] ?? $row['status']),
            'subject_name' => (string)$row['subject_name'],
            'notes' => (string)($row['notes'] ?: '-'),
            'school' => (string)($row['school_name'] ?? config('school.name', 'Sekolah')),
        ];
        $queued = whatsapp_enqueue_template(
            (int)$row['student_id'],
            (int)$guardian['id'],
            'attendance_notice',
            $variables,
            'attendance',
            'attendance:' . $entryId,
            null,
            null,
            $createdBy
        );
        $queued ? $result['queued']++ : $result['skipped_duplicate']++;
    }
    return $result;
}

function whatsapp_enqueue_manual_notice(int $studentId, string $message, ?int $createdBy = null): array
{
    whatsapp_require_tables();
    $student = whatsapp_student_row($studentId);
    $guardians = whatsapp_guardians_for_student($studentId);
    $result = ['guardians' => count($guardians), 'queued' => 0, 'skipped_duplicate' => 0];
    $context = 'manual:' . sha1($studentId . '|' . $message . '|' . date('Y-m-d H:i'));
    foreach ($guardians as $guardian) {
        $variables = [
            'guardian_name' => (string)$guardian['name'],
            'student_name' => (string)$student['name'],
            'class_name' => (string)($student['class_name'] ?? '-'),
            'message' => $message,
            'school' => (string)($student['school_name'] ?? config('school.name', 'Sekolah')),
        ];
        $queued = whatsapp_enqueue_template($studentId, (int)$guardian['id'], 'manual_notice', $variables, 'manual', $context, null, null, $createdBy);
        $queued ? $result['queued']++ : $result['skipped_duplicate']++;
    }
    return $result;
}

function whatsapp_send_pending_queue(int $limit = 50): array
{
    whatsapp_require_tables();
    $limit = max(1, min(200, $limit));
    $rows = fetch_all(
        'SELECT q.*, wt.cloud_template_name, wt.language_code, wt.parameter_keys
         FROM whatsapp_queue q
         LEFT JOIN whatsapp_templates wt ON wt.id = q.template_id
         WHERE q.status = ? AND (q.scheduled_at IS NULL OR q.scheduled_at <= ?)
         ORDER BY q.id
         LIMIT ' . $limit,
        ['pending', now_string()]
    );
    $result = ['checked' => count($rows), 'sent' => 0, 'failed' => 0, 'mode' => (string)whatsapp_setting('mode', 'simulate')];
    foreach ($rows as $row) {
        $sent = whatsapp_send_queue_row($row);
        $sent ? $result['sent']++ : $result['failed']++;
    }
    return $result;
}

function whatsapp_send_queue_row(array $row): bool
{
    $mode = (string)whatsapp_setting('mode', 'simulate');
    $attempts = (int)$row['attempts'] + 1;
    if ($mode === 'simulate') {
        execute_sql('UPDATE whatsapp_queue SET status = ?, attempts = ?, sent_at = ?, updated_at = ? WHERE id = ?', ['sent', $attempts, now_string(), now_string(), (int)$row['id']]);
        whatsapp_log_send($row, 'simulated', 'simulate-' . (int)$row['id'], 200, '{"ok":true,"mode":"simulate"}', null);
        return true;
    }

    try {
        $response = $mode === 'fonnte' ? whatsapp_fonnte_send($row) : whatsapp_cloud_api_send($row);
        execute_sql('UPDATE whatsapp_queue SET status = ?, attempts = ?, sent_at = ?, last_error = NULL, updated_at = ? WHERE id = ?', ['sent', $attempts, now_string(), now_string(), (int)$row['id']]);
        whatsapp_log_send($row, 'sent', (string)($response['message_id'] ?? ''), (int)$response['http_code'], (string)$response['body'], null);
        return true;
    } catch (Throwable $exception) {
        $error = friendly_error($exception);
        execute_sql('UPDATE whatsapp_queue SET status = ?, attempts = ?, last_error = ?, updated_at = ? WHERE id = ?', ['failed', $attempts, $error, now_string(), (int)$row['id']]);
        whatsapp_log_send($row, 'failed', '', null, '', $error);
        return false;
    }
}

function whatsapp_fonnte_send(array $row): array
{
    $token = trim((string)whatsapp_setting('fonnte_token', ''));
    if ($token === '') {
        throw new RuntimeException('Token Fonnte wajib diisi.');
    }

    $payload = [
        'target' => (string)$row['phone'],
        'message' => (string)$row['message'],
        'countryCode' => trim((string)whatsapp_setting('fonnte_country_code', '62')) ?: '62',
    ];
    $ch = curl_init('https://api.fonnte.com/send');
    if ($ch === false) {
        throw new RuntimeException('cURL tidak tersedia.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
        CURLOPT_POSTFIELDS => http_build_query($payload),
    ]);
    $body = (string)curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Gagal menghubungi Fonnte: ' . $error);
    }
    $decoded = json_decode($body, true);
    if ($httpCode < 200 || $httpCode >= 300 || (is_array($decoded) && array_key_exists('status', $decoded) && !$decoded['status'])) {
        $message = is_array($decoded) ? (string)($decoded['reason'] ?? $decoded['message'] ?? $body) : $body;
        throw new RuntimeException('Fonnte gagal (' . $httpCode . '): ' . $message);
    }

    $messageId = '';
    if (is_array($decoded)) {
        $messageId = (string)($decoded['id'][0] ?? $decoded['id'] ?? $decoded['detail'][0]['id'] ?? '');
    }
    return ['http_code' => $httpCode, 'body' => $body, 'message_id' => $messageId];
}

function whatsapp_cloud_api_send(array $row): array
{
    $token = trim((string)whatsapp_setting('access_token', ''));
    $phoneNumberId = trim((string)whatsapp_setting('phone_number_id', ''));
    $graphVersion = trim((string)whatsapp_setting('graph_version', 'v23.0')) ?: 'v23.0';
    if ($token === '' || $phoneNumberId === '') {
        throw new RuntimeException('Access token dan Phone Number ID WhatsApp Cloud API wajib diisi.');
    }

    $url = 'https://graph.facebook.com/' . rawurlencode($graphVersion) . '/' . rawurlencode($phoneNumberId) . '/messages';
    $payload = whatsapp_cloud_payload($row);
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('cURL tidak tersedia.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = (string)curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Gagal menghubungi WhatsApp Cloud API: ' . $error);
    }
    $decoded = json_decode($body, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? $body) : $body;
        throw new RuntimeException('WhatsApp Cloud API gagal (' . $httpCode . '): ' . $message);
    }

    $messageId = '';
    if (is_array($decoded) && isset($decoded['messages'][0]['id'])) {
        $messageId = (string)$decoded['messages'][0]['id'];
    }
    return ['http_code' => $httpCode, 'body' => $body, 'message_id' => $messageId];
}

function whatsapp_cloud_payload(array $row): array
{
    $delivery = (string)whatsapp_setting('cloud_delivery', 'text');
    $templateName = trim((string)($row['cloud_template_name'] ?? ''));
    if ($delivery === 'template' && $templateName !== '') {
        $variables = json_decode((string)($row['template_variables'] ?? '{}'), true);
        if (!is_array($variables)) {
            $variables = [];
        }
        $keys = array_filter(array_map('trim', explode(',', (string)($row['parameter_keys'] ?? ''))));
        $parameters = [];
        foreach ($keys as $key) {
            $parameters[] = ['type' => 'text', 'text' => (string)($variables[$key] ?? '')];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => (string)$row['phone'],
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => (string)($row['language_code'] ?: 'id')],
            ],
        ];
        if ($parameters) {
            $payload['template']['components'] = [['type' => 'body', 'parameters' => $parameters]];
        }
        return $payload;
    }

    return [
        'messaging_product' => 'whatsapp',
        'to' => (string)$row['phone'],
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => (string)$row['message'],
        ],
    ];
}

function whatsapp_log_send(array $row, string $status, ?string $providerMessageId, ?int $responseCode, string $responseBody, ?string $error): void
{
    execute_sql(
        'INSERT INTO whatsapp_logs (queue_id, student_id, guardian_id, phone, status, provider_message_id, response_code, response_body, error_message, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int)$row['id'],
            $row['student_id'] !== null ? (int)$row['student_id'] : null,
            $row['guardian_id'] !== null ? (int)$row['guardian_id'] : null,
            (string)$row['phone'],
            $status,
            $providerMessageId,
            $responseCode,
            $responseBody,
            $error,
            now_string(),
        ]
    );
}

function whatsapp_badge(string $status): string
{
    $class = match ($status) {
        'sent', 'simulated' => 'ok',
        'failed', 'cancelled' => 'off',
        default => '',
    };
    return '<span class="badge ' . e($class) . '">' . e(whatsapp_status_labels()[$status] ?? $status) . '</span>';
}

function action_save_whatsapp_settings(): void
{
    require_role(['admin']);
    $mode = (string)($_POST['mode'] ?? 'simulate');
    if (!array_key_exists($mode, whatsapp_modes())) {
        $mode = 'simulate';
    }
    $delivery = (string)($_POST['cloud_delivery'] ?? 'text');
    if (!array_key_exists($delivery, whatsapp_delivery_modes())) {
        $delivery = 'text';
    }
    whatsapp_set_setting('mode', $mode);
    whatsapp_set_setting('phone_number_id', trim((string)($_POST['phone_number_id'] ?? '')));
    whatsapp_set_setting('waba_id', trim((string)($_POST['waba_id'] ?? '')));
    whatsapp_set_setting('graph_version', trim((string)($_POST['graph_version'] ?? 'v23.0')) ?: 'v23.0');
    whatsapp_set_setting('cloud_delivery', $delivery);
    whatsapp_set_setting('fonnte_country_code', preg_replace('/\D+/', '', (string)($_POST['fonnte_country_code'] ?? '62')) ?: '62');
    whatsapp_set_setting('cron_secret', trim((string)($_POST['cron_secret'] ?? '')) ?: bin2hex(random_bytes(12)));
    $token = trim((string)($_POST['access_token'] ?? ''));
    if ($token !== '') {
        whatsapp_set_setting('access_token', $token);
    }
    $fonnteToken = trim((string)($_POST['fonnte_token'] ?? ''));
    if ($fonnteToken !== '') {
        whatsapp_set_setting('fonnte_token', $fonnteToken);
    }
    if (!empty($_POST['clear_access_token'])) {
        whatsapp_set_setting('access_token', '');
    }
    if (!empty($_POST['clear_fonnte_token'])) {
        whatsapp_set_setting('fonnte_token', '');
    }
    flash('success', 'Pengaturan WhatsApp tersimpan.');
    redirect_to('whatsapp');
}

function action_save_whatsapp_guardian(): void
{
    require_role(['admin']);
    whatsapp_require_tables();
    $id = (int)($_POST['id'] ?? 0);
    $phone = whatsapp_validate_phone((string)$_POST['phone']);
    $data = [
        (int)$_POST['student_id'],
        trim((string)$_POST['name']),
        trim((string)($_POST['relationship'] ?? '')),
        $phone,
        isset($_POST['whatsapp_enabled']) ? 1 : 0,
        isset($_POST['active']) ? 1 : 0,
        trim((string)($_POST['notes'] ?? '')),
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE whatsapp_guardians SET student_id = ?, name = ?, relationship = ?, phone = ?, whatsapp_enabled = ?, active = ?, notes = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO whatsapp_guardians (student_id, name, relationship, phone, whatsapp_enabled, active, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data wali santri tersimpan.');
    redirect_to('whatsapp');
}

function action_save_whatsapp_template(): void
{
    require_role(['admin']);
    whatsapp_require_tables();
    $id = (int)($_POST['id'] ?? 0);
    $code = trim((string)$_POST['code']);
    if (!preg_match('/^[a-z0-9_\\-]{3,80}$/', $code)) {
        throw new RuntimeException('Kode template hanya boleh huruf kecil, angka, strip, atau garis bawah.');
    }
    $data = [
        $code,
        trim((string)$_POST['name']),
        trim((string)($_POST['category'] ?? 'utility')),
        trim((string)$_POST['body']),
        trim((string)($_POST['cloud_template_name'] ?? '')),
        trim((string)($_POST['language_code'] ?? 'id')) ?: 'id',
        trim((string)($_POST['parameter_keys'] ?? '')),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE whatsapp_templates SET code = ?, name = ?, category = ?, body = ?, cloud_template_name = ?, language_code = ?, parameter_keys = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO whatsapp_templates (code, name, category, body, cloud_template_name, language_code, parameter_keys, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Template WhatsApp tersimpan.');
    redirect_to('whatsapp');
}

function action_queue_whatsapp_weekly(): void
{
    require_role(['admin']);
    [$defaultStart, $defaultEnd] = whatsapp_current_week_range();
    $start = date_ymd((string)($_POST['start_date'] ?? $defaultStart));
    $end = date_ymd((string)($_POST['end_date'] ?? $defaultEnd));
    $classId = (int)($_POST['class_id'] ?? 0);
    $result = whatsapp_enqueue_weekly_reports($start, $end, $classId, (int)current_user()['id']);
    flash('success', 'Antrian weekly dibuat: ' . $result['queued'] . ' pesan. Duplikat dilewati: ' . $result['skipped_duplicate'] . '.');
    redirect_to('whatsapp', ['start_date' => $start, 'end_date' => $end, 'class_id' => $classId]);
}

function action_send_whatsapp_queue(): void
{
    require_role(['admin']);
    $limit = max(1, min(200, (int)($_POST['limit'] ?? 50)));
    $result = whatsapp_send_pending_queue($limit);
    flash('success', 'Pengiriman WA selesai. Terkirim: ' . $result['sent'] . ', gagal: ' . $result['failed'] . '.');
    redirect_to('whatsapp');
}

function action_cancel_whatsapp_queue(): void
{
    require_role(['admin']);
    whatsapp_require_tables();
    $id = (int)($_POST['id'] ?? 0);
    execute_sql('UPDATE whatsapp_queue SET status = ?, updated_at = ? WHERE id = ? AND status = ?', ['cancelled', now_string(), $id, 'pending']);
    flash('success', 'Antrian WhatsApp dibatalkan.');
    redirect_to('whatsapp');
}

function action_queue_whatsapp_violation(): void
{
    require_role(['admin']);
    $result = whatsapp_enqueue_violation_notice((int)$_POST['violation_id'], (int)current_user()['id']);
    flash('success', 'Antrian pemberitahuan pelanggaran: ' . $result['queued'] . ' pesan.');
    redirect_to((string)($_POST['return_page'] ?? 'violations'));
}

function action_queue_whatsapp_manual(): void
{
    require_role(['admin']);
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        throw new RuntimeException('Isi pesan manual wajib diisi.');
    }
    $result = whatsapp_enqueue_manual_notice((int)$_POST['student_id'], $message, (int)current_user()['id']);
    flash('success', 'Antrian pesan manual: ' . $result['queued'] . ' pesan.');
    redirect_to('whatsapp');
}

function page_whatsapp(): void
{
    require_role(['admin']);
    whatsapp_require_tables();
    [$defaultStart, $defaultEnd] = whatsapp_current_week_range();
    $start = date_ymd((string)($_GET['start_date'] ?? $defaultStart));
    $end = date_ymd((string)($_GET['end_date'] ?? $defaultEnd));
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = ['0' => 'Semua Kelas'] + array_column_map(fetch_all('SELECT id, name FROM classes WHERE active = 1 ORDER BY grade, name'), 'id', 'name');
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $guardianEditId = (int)($_GET['edit_guardian'] ?? 0);
    $templateEditId = (int)($_GET['edit_template'] ?? 0);
    $guardianEdit = $guardianEditId > 0 ? (fetch_one('SELECT * FROM whatsapp_guardians WHERE id = ?', [$guardianEditId]) ?: []) : [];
    $templateEdit = $templateEditId > 0 ? (fetch_one('SELECT * FROM whatsapp_templates WHERE id = ?', [$templateEditId]) ?: []) : [];
    $guardians = fetch_all(
        'SELECT wg.*, s.name AS student_name, c.name AS class_name
         FROM whatsapp_guardians wg
         JOIN students s ON s.id = wg.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         ORDER BY c.grade, c.name, s.name, wg.name'
    );
    $templates = fetch_all('SELECT * FROM whatsapp_templates ORDER BY code');
    $queueRows = fetch_all(
        'SELECT q.*, s.name AS student_name, wg.name AS guardian_name
         FROM whatsapp_queue q
         LEFT JOIN students s ON s.id = q.student_id
         LEFT JOIN whatsapp_guardians wg ON wg.id = q.guardian_id
         ORDER BY q.id DESC
         LIMIT 40'
    );
    $logs = fetch_all(
        'SELECT wl.*, s.name AS student_name, wg.name AS guardian_name
         FROM whatsapp_logs wl
         LEFT JOIN students s ON s.id = wl.student_id
         LEFT JOIN whatsapp_guardians wg ON wg.id = wl.guardian_id
         ORDER BY wl.id DESC
         LIMIT 40'
    );
    $hasToken = trim((string)whatsapp_setting('access_token', '')) !== '';
    $hasFonnteToken = trim((string)whatsapp_setting('fonnte_token', '')) !== '';

    render_header('WhatsApp Report');
    ?>
    <section class="panel">
        <?php panel_title('Pengaturan WhatsApp Cloud API'); ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_whatsapp_settings">
            <label>Mode <select name="mode"><?= options(whatsapp_modes(), whatsapp_setting('mode', 'simulate')) ?></select></label>
            <label>Jenis Kirim Cloud <select name="cloud_delivery"><?= options(whatsapp_delivery_modes(), whatsapp_setting('cloud_delivery', 'text')) ?></select></label>
            <label>Graph Version <input type="text" name="graph_version" value="<?= e(whatsapp_setting('graph_version', 'v23.0')) ?>"></label>
            <label>Phone Number ID <input type="text" name="phone_number_id" value="<?= e(whatsapp_setting('phone_number_id', '')) ?>"></label>
            <label>WABA ID <input type="text" name="waba_id" value="<?= e(whatsapp_setting('waba_id', '')) ?>"></label>
            <label>Access Token <input type="password" name="access_token" placeholder="<?= e($hasToken ? 'Sudah terisi, kosongkan jika tidak diganti' : 'Isi token Cloud API') ?>"></label>
            <label>Token Fonnte <input type="password" name="fonnte_token" placeholder="<?= e($hasFonnteToken ? 'Sudah terisi, kosongkan jika tidak diganti' : 'Isi token device Fonnte') ?>"></label>
            <label>Country Code Fonnte <input type="text" name="fonnte_country_code" value="<?= e(whatsapp_setting('fonnte_country_code', '62')) ?>"></label>
            <label>Cron Secret <input type="text" name="cron_secret" value="<?= e(whatsapp_cron_secret()) ?>"></label>
            <label class="checkbox"><input type="checkbox" name="clear_access_token" value="1"> Hapus token tersimpan</label>
            <label class="checkbox"><input type="checkbox" name="clear_fonnte_token" value="1"> Hapus token Fonnte</label>
            <div class="wide actions"><button class="button primary">Simpan Pengaturan</button></div>
        </form>
        <div class="grid two">
            <div>
                <p>URL cron weekly:</p>
                <code class="block"><?= e(whatsapp_cron_url()) ?></code>
            </div>
            <div>
                <p>CLI cron:</p>
                <code class="block">php tools/whatsapp_weekly.php</code>
            </div>
        </div>
    </section>

    <section class="panel">
        <?php panel_title('Weekly Report'); ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="queue_whatsapp_weekly">
            <label>Mulai <input type="date" name="start_date" value="<?= e($start) ?>"></label>
            <label>Sampai <input type="date" name="end_date" value="<?= e($end) ?>"></label>
            <label>Kelas <select name="class_id"><?= options($classes, $classId) ?></select></label>
            <div class="actions"><button class="button primary">Buat Antrian Weekly</button></div>
        </form>
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="send_whatsapp_queue">
            <input type="hidden" name="limit" value="50">
            <button class="button success">Kirim 50 Antrian Pending</button>
        </form>
    </section>

    <section class="panel">
        <?php panel_title('Kirim Kondisional Manual'); ?>
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="queue_whatsapp_manual">
            <label>Siswa <select name="student_id" required><?= options($students, '') ?></select></label>
            <label class="wide">Pesan <textarea name="message" required placeholder="Tulis pesan khusus untuk wali santri"></textarea></label>
            <div class="wide actions"><button class="button primary">Tambahkan ke Antrian</button></div>
        </form>
    </section>

    <?php input_panel_start($guardianEdit ? 'Edit Wali Santri' : 'Data Wali Santri', 'Tambah Wali Santri', (bool)$guardianEdit || isset($_GET['add_guardian'])); ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_whatsapp_guardian"><input type="hidden" name="id" value="<?= e($guardianEdit['id'] ?? 0) ?>">
            <label>Siswa <select name="student_id" required><?= options($students, $guardianEdit['student_id'] ?? '') ?></select></label>
            <label>Nama Wali <input type="text" name="name" required value="<?= e($guardianEdit['name'] ?? '') ?>"></label>
            <label>Relasi <input type="text" name="relationship" value="<?= e($guardianEdit['relationship'] ?? 'Orang Tua') ?>"></label>
            <label>No WhatsApp <input type="text" name="phone" required placeholder="62812..." value="<?= e($guardianEdit['phone'] ?? '') ?>"></label>
            <label class="checkbox"><input type="checkbox" name="whatsapp_enabled" value="1" <?= checked($guardianEdit['whatsapp_enabled'] ?? 1) ?>> WA Aktif</label>
            <label class="checkbox"><input type="checkbox" name="active" value="1" <?= checked($guardianEdit['active'] ?? 1) ?>> Data Aktif</label>
            <label class="wide">Catatan <textarea name="notes"><?= e($guardianEdit['notes'] ?? '') ?></textarea></label>
            <div class="wide actions"><button class="button primary">Simpan Wali</button><a class="button" href="<?= e(route_url('whatsapp')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>

    <?php table_panel('Daftar Wali Santri', ['Siswa', 'Kelas', 'Wali', 'Relasi', 'WA', 'Status', 'Aksi'], $guardians, function ($row) { ?>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['name']) ?></td>
        <td><?= e($row['relationship']) ?></td>
        <td><?= e($row['phone']) ?></td>
        <td><?= (int)$row['active'] && (int)$row['whatsapp_enabled'] ? '<span class="badge ok">Aktif</span>' : '<span class="badge off">Nonaktif</span>' ?></td>
        <td><a class="button small" href="<?= e(route_url('whatsapp', ['edit_guardian' => (int)$row['id']])) ?>">Edit</a></td>
    <?php }); ?>

    <?php input_panel_start($templateEdit ? 'Edit Template WhatsApp' : 'Template WhatsApp', 'Tambah Template', (bool)$templateEdit || isset($_GET['add_template'])); ?>
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_whatsapp_template"><input type="hidden" name="id" value="<?= e($templateEdit['id'] ?? 0) ?>">
            <label>Kode <input type="text" name="code" required value="<?= e($templateEdit['code'] ?? '') ?>"></label>
            <label>Nama <input type="text" name="name" required value="<?= e($templateEdit['name'] ?? '') ?>"></label>
            <label>Kategori <input type="text" name="category" value="<?= e($templateEdit['category'] ?? 'utility') ?>"></label>
            <label>Nama Template Cloud <input type="text" name="cloud_template_name" value="<?= e($templateEdit['cloud_template_name'] ?? '') ?>"></label>
            <label>Language Code <input type="text" name="language_code" value="<?= e($templateEdit['language_code'] ?? 'id') ?>"></label>
            <label>Urutan Parameter <input type="text" name="parameter_keys" value="<?= e($templateEdit['parameter_keys'] ?? '') ?>"></label>
            <label class="wide">Isi Pesan <textarea name="body" rows="7" required><?= e($templateEdit['body'] ?? '') ?></textarea></label>
            <label class="checkbox"><input type="checkbox" name="active" value="1" <?= checked($templateEdit['active'] ?? 1) ?>> Aktif</label>
            <div class="wide actions"><button class="button primary">Simpan Template</button><a class="button" href="<?= e(route_url('whatsapp')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>

    <?php table_panel('Template Pesan', ['Kode', 'Nama', 'Mode Cloud', 'Parameter', 'Status', 'Aksi'], $templates, function ($row) { ?>
        <td><?= e($row['code']) ?></td>
        <td><?= e($row['name']) ?></td>
        <td><?= e($row['cloud_template_name'] ?: '-') ?></td>
        <td><?= e(mb_strimwidth((string)$row['parameter_keys'], 0, 70, '...')) ?></td>
        <td><?= status_badge((int)$row['active']) ?></td>
        <td><a class="button small" href="<?= e(route_url('whatsapp', ['edit_template' => (int)$row['id']])) ?>">Edit</a></td>
    <?php }); ?>

    <?php table_panel('Antrian WhatsApp Terbaru', ['Waktu', 'Siswa', 'Wali', 'Tipe', 'No WA', 'Status', 'Pesan', 'Aksi'], $queueRows, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['guardian_name']) ?></td>
        <td><?= e($row['message_type']) ?></td>
        <td><?= e($row['phone']) ?></td>
        <td><?= whatsapp_badge((string)$row['status']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['message'], 0, 90, '...')) ?></td>
        <td>
            <?php if ($row['status'] === 'pending'): ?>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_whatsapp_queue"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="button small danger">Batal</button></form>
            <?php else: ?>
                <span class="hint">-</span>
            <?php endif; ?>
        </td>
    <?php }); ?>

    <?php table_panel('Log Pengiriman WhatsApp', ['Waktu', 'Siswa', 'Wali', 'No WA', 'Status', 'Kode', 'Error'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['guardian_name']) ?></td>
        <td><?= e($row['phone']) ?></td>
        <td><?= e($row['status']) ?></td>
        <td><?= e($row['response_code']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['error_message'], 0, 90, '...')) ?></td>
    <?php }); ?>
    <?php render_footer();
}

function whatsapp_run_weekly_cron(?string $startDate = null, ?string $endDate = null, int $classId = 0, int $sendLimit = 100): array
{
    [$defaultStart, $defaultEnd] = whatsapp_current_week_range();
    $startDate = date_ymd($startDate ?: $defaultStart);
    $endDate = date_ymd($endDate ?: $defaultEnd);
    $queued = whatsapp_enqueue_weekly_reports($startDate, $endDate, $classId, null);
    $sent = whatsapp_send_pending_queue($sendLimit);
    return ['start_date' => $startDate, 'end_date' => $endDate] + $queued + $sent;
}
