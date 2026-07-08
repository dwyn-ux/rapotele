<?php

declare(strict_types=1);

function telegram_send_message(string $chatId, string $text): void
{
    $token = (string)config('telegram.bot_token', '');
    if ($token === '') {
        return;
    }

    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ]);

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function telegram_log(string $chatId, ?string $username, string $message, string $response): void
{
    if (!table_exists('telegram_logs')) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO telegram_logs (chat_id, username, message, response) VALUES (?, ?, ?, ?)');
    $stmt->execute([$chatId, $username, $message, $response]);
}

function telegram_user_by_chat(string $chatId): ?array
{
    return fetch_one('SELECT * FROM users WHERE telegram_chat_id = ? AND active = 1', [$chatId]);
}

function telegram_app_base_url(): string
{
    $configured = rtrim((string)config('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    $scriptDir = trim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $path = $scriptDir === '' || $scriptDir === '.' ? '' : '/' . $scriptDir;

    return (is_https_request() ? 'https' : 'http') . '://' . $host . $path;
}

function telegram_web_route_url(string $page, array $params = []): string
{
    $base = telegram_app_base_url();
    if ($base === '') {
        return '';
    }

    $params = array_merge(['page' => $page], $params);
    return $base . '/index.php?' . http_build_query($params);
}

function telegram_web_login_url(string $page): string
{
    return telegram_web_route_url('login', ['next' => $page]);
}

function telegram_web_link(string $label, string $page): string
{
    $url = telegram_web_login_url($page);
    if ($url === '') {
        return '- ' . $label;
    }

    return '- <a href="' . e($url) . '">' . e($label) . '</a>';
}

function telegram_web_menu(array $user): string
{
    $role = (string)($user['role'] ?? '');
    $items = [
        ['Dashboard', 'dashboard'],
        ['Jadwal Pelajaran', 'lesson-schedule'],
        ['Input Nilai', 'grades'],
        ['Absensi Siswa/Mapel', 'student-attendance'],
        ['Absensi Guru Harian', 'teacher-attendance'],
        ['Jurnal Harian', 'journals'],
        ['Profil Pengguna', 'profile'],
    ];

    if ($role === 'admin') {
        $items = array_merge(
            [
                ['Dashboard', 'dashboard'],
                ['Jadwal Pelajaran', 'lesson-schedule'],
                ['Data Pembelajaran', 'assignments'],
                ['Data Guru', 'teachers'],
                ['Data Siswa', 'students'],
            ],
            array_slice($items, 2),
            [['Bot Telegram', 'telegram']]
        );
    }

    $lines = ['Menu web mini E-Raport:'];
    foreach ($items as [$label, $page]) {
        $lines[] = telegram_web_link($label, $page);
    }

    if (telegram_app_base_url() === '') {
        $lines[] = '';
        $lines[] = 'Catatan: isi APP_URL/base_url agar link Telegram menjadi alamat web lengkap.';
    } else {
        $lines[] = '';
        $lines[] = 'Kalau diminta login, pakai akun web yang sama.';
    }

    return implode("\n", $lines);
}

function telegram_web_page_hint(string $title, string $page, string $hint): string
{
    return implode("\n", [
        $title,
        telegram_web_link('Buka halaman ' . $title, $page),
        $hint,
    ]);
}

function telegram_help(): string
{
    return implode("\n", [
        'Perintah E-Raport KumerBot:',
        '/daftar Nama Guru Mapel',
        '/daftar Nama Guru | Mapel | Kelas',
        '/login username password',
        '/profil',
        '/menu',
        '/web',
        '/jadwal',
        '/requestjadwal',
        '/kelas',
        '/hadir ID_PEMBELAJARAN YYYY-MM-DD [PERTEMUAN] [topik]',
        '/absen ID_PEMBELAJARAN YYYY-MM-DD NIS status [catatan]',
        '/jurnal ID_PEMBELAJARAN YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut',
        '/logout',
        '',
        'Contoh daftar: /daftar Fahmi Dwi Payana, S.H Fiqih',
        'Jika mapel lebih dari satu kata: /daftar Fahmi Dwi Payana, S.H | Bahasa Indonesia | 1A',
        'Status absensi: hadir, sakit, izin, alpa, terlambat.',
    ]);
}

function telegram_slug(string $text, string $fallback = 'guru'): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $slug = strtolower((string)$ascii);
    $slug = preg_replace('/[^a-z0-9]+/', '.', $slug) ?: '';
    $slug = trim($slug, '.');
    return substr($slug !== '' ? $slug : $fallback, 0, 60);
}

function telegram_unique_username(string $name): string
{
    $base = telegram_slug($name, 'guru');
    $candidate = $base;
    $suffix = 1;
    while (fetch_one('SELECT id FROM users WHERE username = ?', [$candidate])) {
        $tail = '.' . $suffix++;
        $candidate = substr($base, 0, max(1, 60 - strlen($tail))) . $tail;
    }
    return $candidate;
}

function telegram_subject_short_name(string $subject): string
{
    $words = preg_split('/\s+/', trim($subject)) ?: [];
    if (count($words) > 1) {
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 12);
    }
    return substr($subject, 0, 12);
}

function telegram_known_subjects(): array
{
    $subjects = array_column(fetch_all('SELECT name FROM subjects WHERE active = 1 ORDER BY LENGTH(name) DESC, name'), 'name');
    $common = [
        'Pendidikan Pancasila', 'Bahasa Indonesia', 'Bahasa Inggris', 'Matematika',
        'Ilmu Pengetahuan Alam', 'Ilmu Pengetahuan Sosial', 'Pendidikan Agama Islam',
        'Fiqih', 'Akidah Akhlak', 'Al Quran Hadis', 'Sejarah Kebudayaan Islam',
        'PJOK', 'Seni Budaya', 'Prakarya', 'Informatika',
    ];
    return array_values(array_unique(array_merge($subjects, $common)));
}

function telegram_parse_registration(string $payload): array
{
    $payload = trim(preg_replace('/\s+/', ' ', $payload) ?: '');
    if ($payload === '') {
        throw new RuntimeException('Format: /daftar Nama Guru Mapel');
    }

    if (str_contains($payload, '|')) {
        $segments = array_map('trim', explode('|', $payload));
        $name = (string)($segments[0] ?? '');
        $subject = (string)($segments[1] ?? '');
        $className = (string)($segments[2] ?? '');
        if ($name === '' || $subject === '') {
            throw new RuntimeException('Format: /daftar Nama Guru | Mapel | Kelas');
        }
        return [$name, $subject, $className];
    }

    foreach (telegram_known_subjects() as $knownSubject) {
        if (preg_match('/\s' . preg_quote($knownSubject, '/') . '$/iu', $payload)) {
            $name = trim(substr($payload, 0, -strlen($knownSubject)));
            if ($name !== '') {
                return [$name, $knownSubject, ''];
            }
        }
    }

    $parts = preg_split('/\s+/', $payload) ?: [];
    if (count($parts) < 2) {
        throw new RuntimeException('Tulis nama guru dan mapel. Contoh: /daftar Fahmi Dwi Payana, S.H Fiqih');
    }
    $subject = array_pop($parts);
    $name = trim(implode(' ', $parts));
    return [$name, (string)$subject, ''];
}

function telegram_find_or_create_teacher(string $chatId, string $name): int
{
    $linkedUser = telegram_user_by_chat($chatId);
    if ($linkedUser && !empty($linkedUser['teacher_id'])) {
        return (int)$linkedUser['teacher_id'];
    }

    $teacher = fetch_one('SELECT id FROM teachers WHERE telegram_chat_id = ? ORDER BY id LIMIT 1', [$chatId]);
    if ($teacher) {
        return (int)$teacher['id'];
    }

    $teacher = fetch_one(
        "SELECT id FROM teachers WHERE name = ? AND (telegram_chat_id IS NULL OR telegram_chat_id = '' OR telegram_chat_id = ?) ORDER BY id LIMIT 1",
        [$name, $chatId]
    );
    if ($teacher) {
        execute_sql('UPDATE teachers SET telegram_chat_id = ?, active = 1, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$teacher['id']]);
        return (int)$teacher['id'];
    }

    execute_sql(
        'INSERT INTO teachers (name, position, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$name, 'Guru Mapel', $chatId, now_string()]
    );
    return (int)db()->lastInsertId();
}

function telegram_find_or_create_subject(string $subjectName): int
{
    $subject = fetch_one('SELECT id FROM subjects WHERE lower(name) = lower(?) ORDER BY id LIMIT 1', [$subjectName]);
    if ($subject) {
        execute_sql('UPDATE subjects SET active = 1, updated_at = ? WHERE id = ?', [now_string(), (int)$subject['id']]);
        return (int)$subject['id'];
    }

    execute_sql(
        'INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$subjectName, telegram_subject_short_name($subjectName), 'Wajib', now_string()]
    );
    return (int)db()->lastInsertId();
}

function telegram_find_or_create_user_for_teacher(string $chatId, ?string $fromUsername, string $name, int $teacherId): array
{
    $existing = telegram_user_by_chat($chatId);
    if ($existing) {
        execute_sql('UPDATE users SET teacher_id = COALESCE(teacher_id, ?), telegram_chat_id = ?, active = 1, updated_at = ? WHERE id = ?', [$teacherId, $chatId, now_string(), (int)$existing['id']]);
        return ['user' => fetch_one('SELECT * FROM users WHERE id = ?', [(int)$existing['id']]), 'password' => null, 'created' => false];
    }

    $existing = fetch_one('SELECT * FROM users WHERE teacher_id = ? AND active = 1 ORDER BY id LIMIT 1', [$teacherId]);
    if ($existing) {
        execute_sql('UPDATE users SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$existing['id']]);
        return ['user' => fetch_one('SELECT * FROM users WHERE id = ?', [(int)$existing['id']]), 'password' => null, 'created' => false];
    }

    $baseName = $fromUsername ? telegram_slug($fromUsername, 'guru') : $name;
    $username = telegram_unique_username($baseName);
    $password = 'tg' . substr(bin2hex(random_bytes(4)), 0, 8);
    execute_sql(
        'INSERT INTO users (username, password_hash, name, email, role, teacher_id, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), $name, $username . '@telegram.local', 'guru', $teacherId, $chatId, now_string()]
    );
    return ['user' => fetch_one('SELECT * FROM users WHERE username = ?', [$username]), 'password' => $password, 'created' => true];
}

function telegram_create_optional_assignment(int $teacherId, int $subjectId, string $className): ?array
{
    $className = trim($className);
    if ($className === '') {
        return null;
    }
    $class = fetch_one('SELECT * FROM classes WHERE name = ? AND active = 1 ORDER BY id LIMIT 1', [$className]);
    if (!$class) {
        return ['ok' => false, 'message' => 'Kelas ' . $className . ' belum ada, jadi pembelajaran belum dibuat.'];
    }
    $assignment = fetch_one(
        'SELECT id FROM teaching_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? ORDER BY id LIMIT 1',
        [$teacherId, (int)$class['id'], $subjectId]
    );
    if ($assignment) {
        return ['ok' => true, 'message' => 'Pembelajaran sudah ada: ' . $class['name'] . '.'];
    }
    execute_sql(
        'INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
        [$teacherId, (int)$class['id'], $subjectId, (string)config('school.academic_year', '2025/2026'), (string)config('school.semester', 'Genap'), now_string()]
    );

    $grade = (string)($class['grade'] ?? '');
    if ($grade !== '' && !fetch_one('SELECT id FROM report_mappings WHERE grade = ? AND subject_id = ?', [$grade, $subjectId])) {
        $maxOrder = fetch_one('SELECT MAX(display_order) AS max_order FROM report_mappings WHERE grade = ?', [$grade]);
        execute_sql(
            'INSERT INTO report_mappings (curriculum, grade, subject_id, display_order, include_in_report, updated_at) VALUES (?, ?, ?, ?, 1, ?)',
            ['Kurikulum Merdeka', $grade, $subjectId, (int)($maxOrder['max_order'] ?? 0) + 1, now_string()]
        );
    }

    return ['ok' => true, 'message' => 'Pembelajaran dibuat untuk kelas ' . $class['name'] . '.'];
}

function telegram_register_teacher(string $chatId, ?string $fromUsername, string $text): string
{
    try {
        [$name, $subjectName, $className] = telegram_parse_registration(trim(substr($text, strlen('/daftar'))));
        $teacherId = telegram_find_or_create_teacher($chatId, $name);
        $subjectId = telegram_find_or_create_subject($subjectName);
        execute_sql('UPDATE teachers SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), $teacherId]);
        $userResult = telegram_find_or_create_user_for_teacher($chatId, $fromUsername, $name, $teacherId);
        $assignmentResult = telegram_create_optional_assignment($teacherId, $subjectId, $className);

        $user = $userResult['user'] ?? [];
        $lines = [
            'Pendaftaran guru berhasil.',
            'Nama: ' . $name,
            'Mapel: ' . $subjectName,
            'Telegram ID: ' . $chatId,
            'Username web: ' . ($user['username'] ?? '-'),
        ];
        if (!empty($userResult['password'])) {
            $lines[] = 'Password web: ' . $userResult['password'];
        } else {
            $lines[] = 'Password web: tetap memakai password akun yang sudah ada.';
        }
        if ($assignmentResult) {
            $lines[] = $assignmentResult['message'];
        } else {
            $lines[] = 'Pembelajaran kelas belum dibuat. Admin bisa mapping di Data Pembelajaran, atau daftar dengan format: /daftar Nama | Mapel | Kelas';
        }
        $lines[] = 'Ketik /profil untuk cek akun atau /kelas untuk melihat pembelajaran.';
        return implode("\n", $lines);
    } catch (Throwable $exception) {
        log_exception($exception);
        return app_debug()
            ? 'Pendaftaran gagal: ' . $exception->getMessage()
            : 'Pendaftaran gagal. Cek format perintah atau hubungi admin.';
    }
}

function telegram_assignment_for_user(int $assignmentId, array $user): ?array
{
    $params = [$assignmentId];
    $where = 'ta.id = ? AND ta.active = 1';
    if (($user['role'] ?? '') !== 'admin') {
        $where .= ' AND ta.teacher_id = ?';
        $params[] = (int)$user['teacher_id'];
    }

    return fetch_one(
        "SELECT ta.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE $where",
        $params
    );
}

function telegram_handle_command(string $chatId, ?string $fromUsername, string $text): string
{
    $parts = preg_split('/\s+/', trim($text));
    $command = strtolower((string)($parts[0] ?? ''));

    if ($command === '/start' || $command === '/help') {
        return telegram_help();
    }

    if ($command === '/daftar') {
        return telegram_register_teacher($chatId, $fromUsername, $text);
    }

    if ($command === '/login') {
        if (count($parts) < 3) {
            return 'Format: /login username password';
        }
        $user = fetch_one('SELECT * FROM users WHERE username = ? AND active = 1', [$parts[1]]);
        if (!$user || !password_verify($parts[2], (string)$user['password_hash'])) {
            return 'Login gagal. Periksa username dan password.';
        }
        if (!in_array($user['role'], ['admin', 'guru'], true)) {
            return 'Akun ini belum diizinkan memakai bot.';
        }

        execute_sql('UPDATE users SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$user['id']]);
        if (!empty($user['teacher_id'])) {
            execute_sql('UPDATE teachers SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$user['teacher_id']]);
        }

        return 'Login berhasil sebagai ' . $user['name'] . '. Ketik /kelas untuk melihat pembelajaran.';
    }

    $user = telegram_user_by_chat($chatId);
    if (!$user) {
        return 'Silakan login dulu: /login username password';
    }

    if ($command === '/logout') {
        execute_sql('UPDATE users SET telegram_chat_id = NULL, updated_at = ? WHERE id = ?', [now_string(), (int)$user['id']]);
        if (!empty($user['teacher_id'])) {
            execute_sql('UPDATE teachers SET telegram_chat_id = NULL, updated_at = ? WHERE id = ?', [now_string(), (int)$user['teacher_id']]);
        }
        return 'Logout bot berhasil.';
    }

    if ($command === '/profil') {
        return 'Login sebagai ' . $user['name'] . ' (' . $user['role'] . '). Chat ID: ' . $chatId;
    }

    if (in_array($command, ['/menu', '/web'], true)) {
        return telegram_web_menu($user);
    }

    if ($command === '/jadwal') {
        return telegram_web_page_hint(
            'Jadwal Pelajaran',
            'lesson-schedule',
            'Di halaman ini guru bisa melihat jadwal mengajar. Admin bisa filter, input, dan generate jadwal.'
        );
    }

    if ($command === '/requestjadwal') {
        return telegram_web_page_hint(
            'Request Jadwal Guru',
            'lesson-schedule',
            'Buka halaman Jadwal Pelajaran, lalu gunakan form Request Jadwal Guru untuk memilih tipe Utamakan/Hindari, hari, dan rentang jam.'
        );
    }

    if ($command === '/kelas') {
        $params = [];
        $where = 'ta.active = 1';
        if ($user['role'] !== 'admin') {
            $where .= ' AND ta.teacher_id = ?';
            $params[] = (int)$user['teacher_id'];
        }
        $rows = fetch_all(
            "SELECT ta.id, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name
             FROM teaching_assignments ta
             JOIN classes c ON c.id = ta.class_id
             JOIN subjects s ON s.id = ta.subject_id
             JOIN teachers t ON t.id = ta.teacher_id
             WHERE $where
             ORDER BY c.grade, c.name, s.name",
            $params
        );
        if (!$rows) {
            return 'Belum ada pembelajaran untuk akun ini.';
        }
        $lines = ['Daftar pembelajaran:'];
        foreach ($rows as $row) {
            $lines[] = $row['id'] . '. ' . $row['class_name'] . ' - ' . $row['subject_name'] . ' (' . $row['teacher_name'] . ')';
        }
        return implode("\n", $lines);
    }

    if ($command === '/hadir') {
        if (count($parts) < 3) {
            return 'Format: /hadir ID_PEMBELAJARAN YYYY-MM-DD [PERTEMUAN] [topik]';
        }
        $assignment = telegram_assignment_for_user((int)$parts[1], $user);
        if (!$assignment) {
            return 'Pembelajaran tidak ditemukan atau bukan milik akun ini.';
        }
        $date = date_ymd($parts[2]);
        $meetingNo = isset($parts[3]) && ctype_digit($parts[3]) ? (int)$parts[3] : 1;
        $topicStart = isset($parts[3]) && ctype_digit($parts[3]) ? 4 : 3;
        $topic = trim(implode(' ', array_slice($parts, $topicStart)));
        $topic = $topic !== '' ? $topic : 'Pembelajaran ' . $assignment['subject_name'];

        $sessionId = save_attendance_session((int)$assignment['id'], $date, $meetingNo, $topic, (int)$user['id']);
        $students = fetch_all('SELECT id FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [(int)$assignment['class_id']]);
        foreach ($students as $student) {
            save_student_attendance_entry($sessionId, (int)$student['id'], 'hadir', '');
        }

        return 'Absensi hadir tersimpan untuk ' . count($students) . ' siswa di ' . $assignment['class_name'] . ' - ' . $assignment['subject_name'] . '.';
    }

    if ($command === '/absen') {
        if (count($parts) < 5) {
            return 'Format: /absen ID_PEMBELAJARAN YYYY-MM-DD NIS status [catatan]';
        }
        $assignment = telegram_assignment_for_user((int)$parts[1], $user);
        if (!$assignment) {
            return 'Pembelajaran tidak ditemukan atau bukan milik akun ini.';
        }
        $status = strtolower($parts[4]);
        if (!array_key_exists($status, allowed_statuses())) {
            return 'Status tidak valid. Pakai: ' . implode(', ', array_keys(allowed_statuses()));
        }
        $student = fetch_one(
            'SELECT * FROM students WHERE class_id = ? AND active = 1 AND (nis = ? OR nisn = ?)',
            [(int)$assignment['class_id'], $parts[3], $parts[3]]
        );
        if (!$student) {
            return 'Siswa dengan NIS/NISN ' . $parts[3] . ' tidak ditemukan di kelas pembelajaran.';
        }

        $date = date_ymd($parts[2]);
        $notes = trim(implode(' ', array_slice($parts, 5)));
        $sessionId = save_attendance_session((int)$assignment['id'], $date, 1, 'Absensi via Telegram', (int)$user['id']);
        save_student_attendance_entry($sessionId, (int)$student['id'], $status, $notes);

        return 'Absensi ' . $student['name'] . ' tersimpan: ' . allowed_statuses()[$status] . '.';
    }

    if ($command === '/jurnal') {
        $payload = trim(substr($text, strlen('/jurnal')));
        $segments = array_map('trim', explode('|', $payload));
        $first = preg_split('/\s+/', (string)($segments[0] ?? ''));
        if (count($first) < 2 || count($segments) < 3) {
            return 'Format: /jurnal ID_PEMBELAJARAN YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut';
        }
        $assignment = telegram_assignment_for_user((int)$first[0], $user);
        if (!$assignment) {
            return 'Pembelajaran tidak ditemukan atau bukan milik akun ini.';
        }
        $date = date_ymd($first[1]);
        $topic = $segments[1] ?? 'Jurnal harian';
        $activities = $segments[2] ?? '-';
        $materials = $segments[3] ?? '';
        $obstacles = $segments[4] ?? '';
        $followUp = $segments[5] ?? '';

        $stmt = db()->prepare(
            'INSERT INTO daily_journals
             (assignment_id, teacher_id, class_id, subject_id, date, meeting_no, topic, activities, materials, obstacles, follow_up, created_by, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$assignment['id'],
            (int)$assignment['teacher_id'],
            (int)$assignment['class_id'],
            (int)$assignment['subject_id'],
            $date,
            $topic,
            $activities,
            $materials,
            $obstacles,
            $followUp,
            (int)$user['id'],
            now_string(),
        ]);

        return 'Jurnal tersimpan untuk ' . $assignment['class_name'] . ' - ' . $assignment['subject_name'] . '.';
    }

    return 'Perintah belum dikenal.' . "\n\n" . telegram_help();
}

function handle_telegram_webhook(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        return;
    }

    $secret = (string)config('telegram.webhook_secret', '');
    if ($secret !== '') {
        $header = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if (!hash_equals($secret, $header)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
    }

    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > max_upload_bytes()) {
        http_response_code(413);
        echo 'Payload Too Large';
        return;
    }
    $update = json_decode($raw, true);
    $message = $update['message'] ?? $update['edited_message'] ?? null;
    if (!is_array($message)) {
        echo 'OK';
        return;
    }

    $chatId = (string)($message['chat']['id'] ?? '');
    $text = trim((string)($message['text'] ?? ''));
    $username = $message['from']['username'] ?? null;

    if ($chatId === '' || $text === '') {
        echo 'OK';
        return;
    }

    try {
        $response = telegram_handle_command($chatId, $username, $text);
    } catch (Throwable $exception) {
        log_exception($exception);
        $response = app_debug()
            ? 'Maaf, perintah gagal diproses: ' . $exception->getMessage()
            : 'Maaf, perintah gagal diproses. Coba ulangi atau hubungi admin.';
    }

    telegram_log($chatId, $username, $text, $response);
    telegram_send_message($chatId, $response);
    echo 'OK';
}

function save_attendance_session(int $assignmentId, string $date, int $meetingNo, string $topic, int $userId): int
{
    $existing = fetch_one(
        'SELECT id FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [$assignmentId, $date, $meetingNo]
    );
    if ($existing) {
        execute_sql(
            'UPDATE student_attendance_sessions SET topic = ?, created_by = ?, updated_at = ? WHERE id = ?',
            [$topic, $userId, now_string(), (int)$existing['id']]
        );
        return (int)$existing['id'];
    }

    $stmt = db()->prepare(
        'INSERT INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, created_by, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$assignmentId, $date, $meetingNo, $topic, $userId, now_string()]);
    return (int)db()->lastInsertId();
}

function save_student_attendance_entry(int $sessionId, int $studentId, string $status, string $notes): void
{
    $existing = fetch_one(
        'SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?',
        [$sessionId, $studentId]
    );
    if ($existing) {
        execute_sql(
            'UPDATE student_attendance_entries SET status = ?, notes = ?, updated_at = ? WHERE id = ?',
            [$status, $notes, now_string(), (int)$existing['id']]
        );
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO student_attendance_entries (session_id, student_id, status, notes, updated_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$sessionId, $studentId, $status, $notes, now_string()]);
}
