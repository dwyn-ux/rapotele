<?php

declare(strict_types=1);

function telegram_response_text(mixed $response): string
{
    if (is_array($response)) {
        return (string)($response['text'] ?? '');
    }

    return (string)$response;
}

function telegram_card_response(string $text, array $buttons, bool $homeMenu = false): array
{
    $response = [
        'text' => $text,
        'reply_markup' => [
            'inline_keyboard' => $buttons,
        ],
    ];
    if ($homeMenu) {
        $response['home_menu'] = true;
    }

    return $response;
}

function telegram_web_app_button(string $label, string $url): array
{
    if ($url === '') {
        return ['text' => $label, 'callback_data' => 'home:missing-url'];
    }

    return ['text' => $label, 'web_app' => ['url' => $url]];
}

function telegram_send_message(string $chatId, mixed $response): void
{
    $token = (string)config('telegram.bot_token', '');
    if ($token === '') {
        return;
    }

    $payloadData = [
        'chat_id' => $chatId,
        'text' => telegram_response_text($response),
        'parse_mode' => 'HTML',
    ];
    if (is_array($response) && !empty($response['reply_markup'])) {
        $payloadData['reply_markup'] = json_encode($response['reply_markup'], JSON_UNESCAPED_UNICODE);
    }

    $payload = http_build_query($payloadData);

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

function telegram_answer_callback(string $callbackQueryId, string $text = ''): void
{
    $token = (string)config('telegram.bot_token', '');
    if ($token === '' || $callbackQueryId === '') {
        return;
    }

    $payload = http_build_query([
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false,
    ]);

    $ch = curl_init('https://api.telegram.org/bot' . $token . '/answerCallbackQuery');
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
    $where = 'telegram_chat_id = ? AND active = 1';
    if (table_exists('users') && table_column_exists('users', 'telegram_login_active')) {
        $where .= ' AND telegram_login_active = 1';
    }

    return fetch_one("SELECT * FROM users WHERE $where", [$chatId]);
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

function telegram_registration_token_ttl(): int
{
    return max(300, (int)config('telegram.registration_token_ttl', 7200));
}

function telegram_web_login_token_ttl(): int
{
    return max(60, (int)config('telegram.web_login_token_ttl', 300));
}

function telegram_registration_ensure_schema(): void
{
    if (!table_exists('telegram_registration_tokens') || !table_column_exists('users', 'telegram_login_active')) {
        run_migrations();
    }
}

function telegram_web_login_ensure_schema(): void
{
    if (!table_exists('telegram_web_login_tokens')) {
        run_migrations();
    }
}

function telegram_clean_next_page(string $page): string
{
    $page = trim($page);
    return preg_match('/^[a-z0-9-]{1,80}$/', $page) ? $page : 'dashboard';
}

function telegram_registration_create_token(string $chatId, ?string $fromUsername): string
{
    telegram_registration_ensure_schema();

    execute_sql(
        'UPDATE telegram_registration_tokens SET used_at = ? WHERE chat_id = ? AND used_at IS NULL',
        [now_string(), $chatId]
    );

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + telegram_registration_token_ttl());
    execute_sql(
        'INSERT INTO telegram_registration_tokens (token, chat_id, from_username, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$token, $chatId, $fromUsername, $expiresAt, now_string()]
    );

    return $token;
}

function telegram_registration_get_or_create_token(string $chatId, ?string $fromUsername): string
{
    telegram_registration_ensure_schema();

    $row = fetch_one(
        'SELECT token FROM telegram_registration_tokens WHERE chat_id = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1',
        [$chatId, now_string()]
    );
    if ($row && !empty($row['token'])) {
        return (string)$row['token'];
    }

    return telegram_registration_create_token($chatId, $fromUsername);
}

function telegram_registration_url(string $token): string
{
    return telegram_web_route_url('telegram-register', ['token' => $token]);
}

function telegram_web_login_create_token(int $userId, string $nextPage): string
{
    telegram_web_login_ensure_schema();

    $nextPage = telegram_clean_next_page($nextPage);
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + telegram_web_login_token_ttl());
    execute_sql(
        'INSERT INTO telegram_web_login_tokens (token, user_id, next_page, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$token, $userId, $nextPage, $expiresAt, now_string()]
    );

    return $token;
}

function telegram_web_login_url_for_user(?array $user, string $page): string
{
    $page = telegram_clean_next_page($page);
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        return telegram_web_route_url($page);
    }

    return telegram_web_route_url('telegram-web-login', [
        'token' => telegram_web_login_create_token($userId, $page),
    ]);
}

function telegram_web_login_consume_token(string $token): ?array
{
    telegram_web_login_ensure_schema();

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $row = fetch_one(
        'SELECT t.*, u.id AS user_id, u.active AS user_active
         FROM telegram_web_login_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.used_at IS NULL AND t.expires_at > ?
         ORDER BY t.id DESC LIMIT 1',
        [$token, now_string()]
    );
    if (!$row || (int)$row['user_active'] !== 1) {
        return null;
    }

    execute_sql('UPDATE telegram_web_login_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [now_string(), (int)$row['id']]);
    return [
        'user_id' => (int)$row['user_id'],
        'next_page' => telegram_clean_next_page((string)$row['next_page']),
    ];
}

function telegram_registration_token_row(string $token): ?array
{
    telegram_registration_ensure_schema();

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    return fetch_one(
        'SELECT * FROM telegram_registration_tokens WHERE token = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1',
        [$token, now_string()]
    );
}

function telegram_login_menu_buttons(string $chatId, ?string $fromUsername): array
{
    $buttons = [
        [
            ['text' => 'Login Password', 'callback_data' => 'login:password'],
            ['text' => 'Login Username', 'callback_data' => 'login:username'],
        ],
    ];

    if (telegram_app_base_url() !== '') {
        $registerToken = telegram_registration_get_or_create_token($chatId, $fromUsername);
        $buttons[] = [
            telegram_web_app_button('Daftar Guru', telegram_registration_url($registerToken)),
            telegram_web_app_button('Login Web', telegram_web_route_url('login')),
        ];
    } else {
        $buttons[] = [
            ['text' => 'Cara Daftar Guru', 'callback_data' => 'login:register'],
        ];
    }

    return $buttons;
}

function telegram_guru_menu_buttons(?array $user = null): array
{
    return [
        [
            ['text' => 'Home', 'callback_data' => 'home:menu'],
            telegram_web_app_button('Dashboard', telegram_web_login_url_for_user($user, 'dashboard')),
        ],
        [
            telegram_web_app_button('Absensi Siswa', telegram_web_login_url_for_user($user, 'student-attendance')),
            telegram_web_app_button('Absensi Mengajar', telegram_web_login_url_for_user($user, 'teacher-attendance')),
        ],
        [
            telegram_web_app_button('Absensi Kehadiran', telegram_web_login_url_for_user($user, 'teacher-attendance-self')),
            telegram_web_app_button('Jadwal', telegram_web_login_url_for_user($user, 'lesson-schedule')),
        ],
        [
            telegram_web_app_button('Jurnal', telegram_web_login_url_for_user($user, 'journals')),
            ['text' => 'Kelas', 'callback_data' => 'home:kelas'],
        ],
        [
            ['text' => 'Profil', 'callback_data' => 'home:profil'],
            ['text' => 'Keluar', 'callback_data' => 'home:logout'],
        ],
    ];
}

function telegram_home_response(string $chatId, ?string $fromUsername): array
{
    $user = telegram_user_by_chat($chatId);
    if (!$user) {
        return telegram_login_card_response($chatId, $fromUsername);
    }

    return telegram_card_response(
        implode("\n", [
            '🏠 <b>Menu Guru</b>',
            '',
            '👤 Login sebagai <b>' . e($user['name']) . '</b> <i>(' . e($user['role']) . ')</i>.',
            'Pilih menu di bawah untuk membuka miniweb di Telegram.',
        ]),
        telegram_guru_menu_buttons($user),
        true
    );
}

function telegram_attach_home_menu(mixed $response, string $chatId, ?string $fromUsername): mixed
{
    if (!is_array($response)) {
        $response = ['text' => (string)$response];
    }

    if (!empty($response['home_menu'])) {
        unset($response['home_menu']);
        return $response;
    }

    $currentRows = $response['reply_markup']['inline_keyboard'] ?? [];
    $user = telegram_user_by_chat($chatId);
    $homeRows = $user ? telegram_guru_menu_buttons($user) : telegram_login_menu_buttons($chatId, $fromUsername);
    $response['reply_markup'] = [
        'inline_keyboard' => array_merge($currentRows, $homeRows),
    ];

    return $response;
}

function telegram_registration_reply(string $chatId, ?string $fromUsername): mixed
{
    $token = telegram_registration_create_token($chatId, $fromUsername);
    $url = telegram_registration_url($token);
    if ($url === '') {
        return '⚠️ <b>Link daftar belum bisa dibuat.</b> Isi APP_URL/base_url di config terlebih dulu.';
    }

    return telegram_card_response(
        implode("\n", [
            '📋 <b>Daftar Guru</b>',
            '',
            'Buka miniweb pendaftaran guru dari tombol di bawah.',
            '⏰ Link berlaku <b>' . (int)(telegram_registration_token_ttl() / 60) . ' menit</b> dan hanya bisa dipakai sekali.',
            '✅ Setelah form selesai, login bot dengan format: <code>/login password</code>',
        ]),
        [
            [telegram_web_app_button('Buka Form Daftar Guru', $url)],
            [
                ['text' => 'Login Password', 'callback_data' => 'login:password'],
                ['text' => 'Login Username', 'callback_data' => 'login:username'],
            ],
        ],
        true
    );
}

function telegram_login_card_response(string $chatId, ?string $fromUsername): array
{
    return telegram_card_response(
        implode("\n", [
            '🔐 <b>Pilih cara masuk</b>',
            '',
            'Kalau sudah daftar lewat Telegram, pilih <b>Login Password</b>.',
            'Tombol <b>Daftar Guru</b> dan <b>Login Web</b> terbuka sebagai miniweb di Telegram.',
        ]),
        telegram_login_menu_buttons($chatId, $fromUsername),
        true
    );
}

function telegram_login_callback_response(string $data): string
{
    return match ($data) {
        'login:password' => implode("\n", [
            '🔐 <b>Login Password</b>',
            '',
            'Ketik perintah berikut:',
            '<code>login password-anda</code>',
            '',
            '💡 <i>Contoh: <code>login rahasia123</code></i>',
        ]),
        'login:username' => implode("\n", [
            '🔑 <b>Login Username</b>',
            '',
            'Ketik perintah berikut:',
            '<code>login username password</code>',
            '',
            '📌 <i>Pakai cara ini untuk akun lama atau akun yang belum terhubung Telegram.</i>',
        ]),
        'login:register' => implode("\n", [
            '📋 <b>Daftar Guru</b>',
            '',
            'Ketik <code>daftar</code> untuk meminta link miniweb pendaftaran guru.',
            'Isi <code>APP_URL/base_url</code> agar tombol daftar bisa langsung berupa link.',
        ]),
        default => '⚠️ Pilihan tidak dikenal.',
    };
}

function telegram_user_set_login_state(int $userId, bool $loggedIn): void
{
    if (table_exists('users') && table_column_exists('users', 'telegram_login_active')) {
        execute_sql(
            'UPDATE users SET telegram_login_active = ?, updated_at = ? WHERE id = ?',
            [$loggedIn ? 1 : 0, now_string(), $userId]
        );
    }
}

function telegram_web_login_url(string $page): string
{
    return telegram_web_route_url('login', ['next' => $page]);
}

function telegram_web_menu(array $user): array
{
    $role = (string)($user['role'] ?? '');
    $rows = telegram_guru_menu_buttons($user);

    if ($role === 'admin') {
        $rows[] = [
            telegram_web_app_button('Data Guru', telegram_web_login_url_for_user($user, 'teachers')),
            telegram_web_app_button('Data Siswa', telegram_web_login_url_for_user($user, 'students')),
        ];
        $rows[] = [
            telegram_web_app_button('Data Pembelajaran', telegram_web_login_url_for_user($user, 'assignments')),
            telegram_web_app_button('Bot Telegram', telegram_web_login_url_for_user($user, 'telegram')),
        ];
    }

    return telegram_card_response(
        implode("\n", [
            ($role === 'admin' ? '👑' : '🏠') . ' <b>Menu ' . ($role === 'admin' ? 'Admin' : 'Guru') . '</b>',
            '',
            'Pilih tombol untuk membuka miniweb di Telegram.',
            telegram_app_base_url() === '' ? '⚠️ Catatan: isi APP_URL/base_url agar miniweb bisa dibuka.' : 'Kalau diminta login web, pakai akun yang sama.',
        ]),
        $rows,
        true
    );
}

function telegram_web_page_hint(string $title, string $page, string $hint, ?array $user = null): array
{
    $icon = match($page) {
        'lesson-schedule' => '📅',
        'student-attendance' => '✅',
        'teacher-attendance' => '📝',
        'teacher-attendance-self' => '📍',
        default => '📌',
    };
    return telegram_card_response(
        implode("\n", [
            $icon . ' <b>' . e($title) . '</b>',
            '',
            e($hint),
        ]),
        [
            [telegram_web_app_button('Buka ' . $title, telegram_web_login_url_for_user($user, $page))],
        ]
    );
}

function telegram_help(): string
{
    return implode("\n", [
        '📖 <b>Perintah E-Raport KumerBot</b>',
        '',
        '🔐 <b>Akun</b>',
        '  /daftar — Daftar guru via miniweb',
        '  /login password — Login dengan password',
        '  /login username password — Login dengan username',
        '  /profil — Lihat profil akun',
        '  /logout — Keluar dari bot',
        '',
        '📋 <b>Menu</b>',
        '  /menu — Buka menu utama',
        '  /web — Buka miniweb',
        '  /jadwal — Lihat jadwal pelajaran',
        '  /kelas — Lihat daftar pembelajaran',
        '',
        '✅ <b>Absensi</b>',
        '  /absensi — Absensi siswa via miniweb',
        '  /absensiguru — Absensi mengajar',
        '  /absensikehadiran — Kehadiran guru',
        '  /hadir ID YYYY-MM-DD [PERTEMUAN] [topik]',
        '  /absen ID YYYY-MM-DD NIS status [catatan]',
        '',
        '📝 <b>Jurnal</b>',
        '  /jurnal ID YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut',
        '',
        '💡 <i>Contoh: /daftar Fahmi Dwi Payana, S.H | Bahasa Indonesia | 1A</i>',
        '📌 <i>Status absensi: hadir, sakit, izin, alpa, terlambat.</i>',
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
        telegram_user_set_login_state((int)$existing['id'], true);
        return ['user' => fetch_one('SELECT * FROM users WHERE id = ?', [(int)$existing['id']]), 'password' => null, 'created' => false];
    }

    $existing = fetch_one('SELECT * FROM users WHERE teacher_id = ? AND active = 1 ORDER BY id LIMIT 1', [$teacherId]);
    if ($existing) {
        execute_sql('UPDATE users SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$existing['id']]);
        telegram_user_set_login_state((int)$existing['id'], true);
        return ['user' => fetch_one('SELECT * FROM users WHERE id = ?', [(int)$existing['id']]), 'password' => null, 'created' => false];
    }

    $baseName = $fromUsername ? telegram_slug($fromUsername, 'guru') : $name;
    $username = telegram_unique_username($baseName);
    $password = 'tg' . substr(bin2hex(random_bytes(4)), 0, 8);
    execute_sql(
        'INSERT INTO users (username, password_hash, name, email, role, teacher_id, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$username, password_hash($password, PASSWORD_DEFAULT), $name, $username . '@telegram.local', 'guru', $teacherId, $chatId, now_string()]
    );
    telegram_user_set_login_state((int)db()->lastInsertId(), true);
    return ['user' => fetch_one('SELECT * FROM users WHERE username = ?', [$username]), 'password' => $password, 'created' => true];
}

function telegram_complete_registration(array $registration, array $input): array
{
    telegram_registration_ensure_schema();

    $token = (string)($registration['token'] ?? '');
    $chatId = (string)($registration['chat_id'] ?? '');
    $name = trim((string)($input['name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $passwordConfirm = (string)($input['password_confirm'] ?? '');
    $email = trim((string)($input['email'] ?? ''));
    $nip = trim((string)($input['nip'] ?? ''));
    $nuptk = trim((string)($input['nuptk'] ?? ''));
    $gender = (string)($input['gender'] ?? '');
    $phone = trim((string)($input['phone'] ?? ''));
    $position = trim((string)($input['position'] ?? '')) ?: 'Guru Mapel';
    $subjectId = (int)($input['subject_id'] ?? 0);
    $subjectName = trim((string)($input['subject_name'] ?? ''));
    $classIds = array_values(array_unique(array_filter(array_map('intval', (array)($input['class_ids'] ?? [])))));
    $className = trim((string)($input['class_name'] ?? ''));

    if ($chatId === '' || telegram_registration_token_row($token) === null) {
        throw new RuntimeException('Link daftar sudah tidak berlaku. Ketik /daftar lagi di Telegram.');
    }
    if ($name === '') {
        throw new RuntimeException('Nama guru wajib diisi.');
    }
    if ($username === '') {
        $username = telegram_unique_username($name);
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
        throw new RuntimeException('Username hanya boleh huruf, angka, titik, garis bawah, atau strip, minimal 3 karakter.');
    }
    if (fetch_one('SELECT id FROM users WHERE username = ?', [$username])) {
        throw new RuntimeException('Username sudah dipakai. Pilih username lain.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Format email tidak valid.');
    }
    if (!in_array($gender, ['', 'L', 'P'], true)) {
        throw new RuntimeException('Pilihan JK tidak valid.');
    }
    if ($password === '') {
        throw new RuntimeException('Password wajib diisi.');
    }
    if ($password !== $passwordConfirm) {
        throw new RuntimeException('Konfirmasi password tidak sama.');
    }
    validate_password_strength($password);
    if ($classIds && $subjectId <= 0 && $subjectName === '') {
        throw new RuntimeException('Pilih mapel sebelum memilih kelas.');
    }

    $existingUser = fetch_one('SELECT id, name FROM users WHERE telegram_chat_id = ? ORDER BY id LIMIT 1', [$chatId]);
    if ($existingUser) {
        throw new RuntimeException('Telegram ini sudah terhubung ke akun ' . $existingUser['name'] . '. Ketik /login password untuk masuk.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $freshRegistration = telegram_registration_token_row($token);
        if (!$freshRegistration) {
            throw new RuntimeException('Link daftar sudah dipakai atau kedaluwarsa. Ketik /daftar lagi di Telegram.');
        }

        $teacher = fetch_one('SELECT id FROM teachers WHERE telegram_chat_id = ? ORDER BY id LIMIT 1', [$chatId]);
        if (!$teacher) {
            $teacher = fetch_one(
                "SELECT id FROM teachers WHERE name = ? AND (telegram_chat_id IS NULL OR telegram_chat_id = '' OR telegram_chat_id = ?) ORDER BY id LIMIT 1",
                [$name, $chatId]
            );
        }

        $teacherData = [$name, $nip, $nuptk, $gender, $phone, $email, $position, $chatId, 1, now_string()];
        if ($teacher) {
            $teacherId = (int)$teacher['id'];
            execute_sql(
                'UPDATE teachers SET name = ?, nip = ?, nuptk = ?, gender = ?, phone = ?, email = ?, position = ?, telegram_chat_id = ?, active = ?, updated_at = ? WHERE id = ?',
                array_merge($teacherData, [$teacherId])
            );
        } else {
            execute_sql(
                'INSERT INTO teachers (name, nip, nuptk, gender, phone, email, position, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $teacherData
            );
            $teacherId = (int)$pdo->lastInsertId();
        }

        execute_sql(
            'INSERT INTO users (username, password_hash, name, email, role, teacher_id, student_id, telegram_chat_id, telegram_login_active, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, 'guru', $teacherId, null, $chatId, 0, 1, now_string()]
        );
        $userId = (int)$pdo->lastInsertId();

        $assignmentMessage = 'Data guru dan akun pengguna tersimpan.';
        if ($subjectId > 0) {
            $subject = fetch_one('SELECT id, name FROM subjects WHERE id = ? AND active = 1', [$subjectId]);
            if (!$subject) {
                throw new RuntimeException('Mapel yang dipilih tidak valid.');
            }

            $subjectName = (string)$subject['name'];
            $assignmentMessage = 'Mapel utama tersimpan: ' . $subjectName . '.';
            if ($classIds) {
                $assignmentMessages = [];
                foreach ($classIds as $classId) {
                    $assignmentResult = telegram_create_optional_assignment_by_class_id($teacherId, $subjectId, $classId);
                    if ($assignmentResult) {
                        $assignmentMessages[] = $assignmentResult['message'];
                    }
                }
                if ($assignmentMessages) {
                    $assignmentMessage = implode(' ', $assignmentMessages);
                }
            } elseif ($className !== '') {
                $assignmentResult = telegram_create_optional_assignment($teacherId, $subjectId, $className);
                if ($assignmentResult) {
                    $assignmentMessage = $assignmentResult['message'];
                }
            }
        } elseif ($subjectName !== '') {
            $legacySubjectId = telegram_find_or_create_subject($subjectName);
            $assignmentMessage = 'Mapel utama tersimpan: ' . $subjectName . '.';
            if ($className !== '') {
                $assignmentResult = telegram_create_optional_assignment($teacherId, $legacySubjectId, $className);
                if ($assignmentResult) {
                    $assignmentMessage = $assignmentResult['message'];
                }
            }
        }

        execute_sql(
            'UPDATE telegram_registration_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [now_string(), (int)$freshRegistration['id']]
        );
        $pdo->commit();

        return [
            'user_id' => $userId,
            'teacher_id' => $teacherId,
            'username' => $username,
            'name' => $name,
            'assignment_message' => $assignmentMessage,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
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
    return telegram_create_assignment_for_class($teacherId, $subjectId, $class);
}

function telegram_create_optional_assignment_by_class_id(int $teacherId, int $subjectId, int $classId): ?array
{
    if ($classId <= 0) {
        return null;
    }

    $class = fetch_one('SELECT * FROM classes WHERE id = ? AND active = 1 ORDER BY id LIMIT 1', [$classId]);
    if (!$class) {
        return ['ok' => false, 'message' => 'Ada kelas yang tidak valid, jadi pembelajaran dilewati.'];
    }

    return telegram_create_assignment_for_class($teacherId, $subjectId, $class);
}

function telegram_create_assignment_for_class(int $teacherId, int $subjectId, array $class): array
{
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
        $payload = trim((string)preg_replace('/^\/?daftar\b/i', '', $text, 1));
        [$name, $subjectName, $className] = telegram_parse_registration($payload);
        $teacherId = telegram_find_or_create_teacher($chatId, $name);
        $subjectId = telegram_find_or_create_subject($subjectName);
        execute_sql('UPDATE teachers SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), $teacherId]);
        $userResult = telegram_find_or_create_user_for_teacher($chatId, $fromUsername, $name, $teacherId);
        $assignmentResult = telegram_create_optional_assignment($teacherId, $subjectId, $className);

        $user = $userResult['user'] ?? [];
        $lines = [
            '🎉 <b>Pendaftaran guru berhasil!</b>',
            '',
            '👤 <b>Nama:</b> ' . $name,
            '📚 <b>Mapel:</b> ' . $subjectName,
            '🆔 <b>Telegram ID:</b> <code>' . $chatId . '</code>',
            '🌐 <b>Username web:</b> <code>' . ($user['username'] ?? '-') . '</code>',
        ];
        if (!empty($userResult['password'])) {
            $lines[] = '🔑 <b>Password web:</b> <code>' . $userResult['password'] . '</code>';
        } else {
            $lines[] = '🔑 <b>Password web:</b> tetap memakai password akun yang sudah ada.';
        }
        if ($assignmentResult) {
            $lines[] = '';
            $lines[] = '📝 ' . $assignmentResult['message'];
        } else {
            $lines[] = '';
            $lines[] = '📝 Pembelajaran kelas belum dibuat. Admin bisa mapping di Data Pembelajaran, atau daftar dengan format: <code>/daftar Nama | Mapel | Kelas</code>';
        }
        $lines[] = '';
        $lines[] = '💡 Ketik <code>/profil</code> untuk cek akun atau <code>/kelas</code> untuk melihat pembelajaran.';
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

function telegram_handle_command(string $chatId, ?string $fromUsername, string $text): mixed
{
    $parts = preg_split('/\s+/', trim($text));
    $rawCommand = (string)($parts[0] ?? '');
    $commandLength = strlen($rawCommand);
    $command = strtolower($rawCommand);
    $plainCommands = [
        'start', 'help', 'daftar', 'login', 'profil', 'menu', 'web', 'jadwal', 'requestjadwal', 'kelas',
        'absensi', 'absensi-siswa', 'absensiguru', 'absensi-guru', 'absensikehadiran', 'hadir', 'absen', 'jurnal', 'logout',
    ];
    if ($command !== '' && !str_starts_with($command, '/') && in_array($command, $plainCommands, true)) {
        $command = '/' . $command;
    }

    if ($command === '/start') {
        return telegram_home_response($chatId, $fromUsername);
    }

    if ($command === '/help') {
        return telegram_help();
    }

    if ($command === '/daftar') {
        $payload = trim(substr($text, $commandLength));
        if ($payload === '') {
            return telegram_registration_reply($chatId, $fromUsername);
        }

        return telegram_register_teacher($chatId, $fromUsername, $text);
    }

    if ($command === '/login') {
        if (count($parts) === 1) {
            return telegram_login_card_response($chatId, $fromUsername);
        }

        if (count($parts) === 2) {
            telegram_registration_ensure_schema();
            $password = (string)$parts[1];
            $linkedUsers = fetch_all('SELECT * FROM users WHERE telegram_chat_id = ? AND active = 1 ORDER BY id', [$chatId]);
            if (!$linkedUsers) {
                return '⚠️ <b>Akun Telegram ini belum terhubung.</b>'
                    . "\n\n📋 Ketik <code>/daftar</code> untuk daftar lewat miniweb."
                    . "\n🔑 Atau pakai <code>/login username password</code> untuk akun lama.';
            }

            $user = null;
            foreach ($linkedUsers as $linkedUser) {
                if (password_verify($password, (string)$linkedUser['password_hash'])) {
                    $user = $linkedUser;
                    break;
                }
            }
            if (!$user) {
                return '❌ <b>Login gagal.</b> Password salah.';
            }
            if (!in_array($user['role'], ['admin', 'guru'], true)) {
                return '⛔ <b>Akun ini belum diizinkan memakai bot.</b>';
            }

            telegram_user_set_login_state((int)$user['id'], true);
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$user['id']]);
            }
            if (!empty($user['teacher_id'])) {
                execute_sql('UPDATE teachers SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$user['teacher_id']]);
            }

            return '✅ <b>Login berhasil!</b>'
                . "\n" . '👤 Masuk sebagai <b>' . e($user['name']) . '</b>'
                . "\n" . "\n💡 Ketik <code>/menu</code> untuk membuka menu atau <code>/kelas</code> untuk melihat pembelajaran.';
        }

        if (count($parts) < 3) {
            return '📌 Format: <code>/login password</code> atau <code>/login username password</code>';
        }
        $user = fetch_one('SELECT * FROM users WHERE username = ? AND active = 1', [$parts[1]]);
        if (!$user || !password_verify($parts[2], (string)$user['password_hash'])) {
            return '❌ <b>Login gagal.</b> Periksa username dan password.';
        }
        if (!in_array($user['role'], ['admin', 'guru'], true)) {
            return '⛔ <b>Akun ini belum diizinkan memakai bot.</b>';
        }

        execute_sql('UPDATE users SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$user['id']]);
        telegram_user_set_login_state((int)$user['id'], true);
        if (!empty($user['teacher_id'])) {
            execute_sql('UPDATE teachers SET telegram_chat_id = ?, updated_at = ? WHERE id = ?', [$chatId, now_string(), (int)$user['teacher_id']]);
        }

        return '✅ <b>Login berhasil!</b>'
            . "\n" . '👤 Masuk sebagai <b>' . e($user['name']) . '</b>'
            . "\n\n💡 Ketik <code>/menu</code> untuk membuka menu atau <code>/kelas</code> untuk melihat pembelajaran.';
    }

    $user = telegram_user_by_chat($chatId);
    if (!$user) {
        return '🔒 <b>Anda belum login.</b>'
        . "\n\n🔐 Ketik <code>/login password</code> untuk masuk."
        . "\n📋 Ketik <code>/daftar</code> untuk membuat akun baru.";
    }

    if ($command === '/logout') {
        if (table_exists('users') && table_column_exists('users', 'telegram_login_active')) {
            telegram_user_set_login_state((int)$user['id'], false);
        } else {
            execute_sql('UPDATE users SET telegram_chat_id = NULL, updated_at = ? WHERE id = ?', [now_string(), (int)$user['id']]);
            if (!empty($user['teacher_id'])) {
                execute_sql('UPDATE teachers SET telegram_chat_id = NULL, updated_at = ? WHERE id = ?', [now_string(), (int)$user['teacher_id']]);
            }
        }
        return '👋 <b>Logout berhasil!</b>'
            . "\n\n🔐 Ketik <code>/login password</code> untuk masuk lagi.';
    }

    if ($command === '/profil') {
        $roleIcon = $user['role'] === 'admin' ? '👑' : '👤';
        return '📋 <b>Profil Akun</b>'
            . "\n\n" . $roleIcon . ' <b>' . e($user['name']) . '</b>'
            . "\n" . '🏷️ Role: <b>' . e($user['role']) . '</b>'
            . "\n" . '🆔 Chat ID: <code>' . e($chatId) . '</code>';
    }

    if (in_array($command, ['/menu', '/web'], true)) {
        return telegram_web_menu($user);
    }

    if ($command === '/jadwal') {
        return telegram_web_page_hint(
            'Jadwal Pelajaran',
            'lesson-schedule',
            'Di halaman ini guru bisa melihat jadwal mengajar. Admin bisa filter, input, dan generate jadwal.',
            $user
        );
    }

    if ($command === '/requestjadwal') {
        return telegram_web_page_hint(
            'Request Jadwal Guru',
            'lesson-schedule',
            'Buka halaman Jadwal Pelajaran, lalu gunakan form Request Jadwal Guru untuk memilih tipe Utamakan/Hindari, hari, dan rentang jam.',
            $user
        );
    }

    if (in_array($command, ['/absensi', '/absensi-siswa'], true)
        || ($command === '/absen' && count($parts) === 1)
        || ($command === '/hadir' && count($parts) === 1)
    ) {
        return telegram_web_page_hint(
            'Absensi Siswa/Mapel',
            'student-attendance',
            'Pilih pembelajaran, tanggal, pertemuan, lalu simpan kehadiran siswa.',
            $user
        );
    }

    if (in_array($command, ['/absensiguru', '/absensi-guru'], true)) {
        return telegram_web_page_hint(
            'Absensi Mengajar',
            'teacher-attendance',
            'Pilih tanggal lalu catat kehadiran guru mengajar sesuai jadwal kelas.',
            $user
        );
    }

    if ($command === '/absensikehadiran') {
        return telegram_web_page_hint(
            'Absensi Kehadiran Guru',
            'teacher-attendance-self',
            'Catat kehadiran masuk/pulang dengan lokasi GPS. Pastikan lokasi aktif dan berada di area sekolah.',
            $user
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
            return '📭 <b>Belum ada pembelajaran</b> untuk akun ini.';
        }
        $lines = ['📋 <b>Daftar Pembelajaran</b>', ''];
        foreach ($rows as $row) {
            $lines[] = '📚 <b>' . e($row['class_name']) . '</b> — ' . e($row['subject_name']) . '\n    👨‍🏫 ' . e($row['teacher_name']) . '  │  🆔 <code>' . $row['id'] . '</code>';
        }
        return implode("\n", $lines);
    }

    if ($command === '/hadir') {
        if (count($parts) < 3) {
            return '📌 Format: <code>/hadir ID_PEMBELAJARAN YYYY-MM-DD [PERTEMUAN] [topik]</code>';
        }
        $assignment = telegram_assignment_for_user((int)$parts[1], $user);
        if (!$assignment) {
            return '❌ <b>Pembelajaran tidak ditemukan</b> atau bukan milik akun ini.';
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

        return '✅ <b>Absensi tersimpan</b>'
            . "\n" . '📚 ' . e($assignment['subject_name'])
            . "\n" . '👥 ' . e($assignment['class_name'])
            . "\n" . '👨‍🎓 ' . count($students) . ' siswa hadir'
            . "\n" . '📅 ' . e($date)
            . "\n" . '💡 Topik: ' . e($topic);
    }

    if ($command === '/absen') {
        if (count($parts) < 5) {
            return '📌 Format: <code>/absen ID_PEMBELAJARAN YYYY-MM-DD NIS status [catatan]</code>';
        }
        $assignment = telegram_assignment_for_user((int)$parts[1], $user);
        if (!$assignment) {
            return '❌ Pembelajaran tidak ditemukan atau bukan milik akun ini.';
        }
        $status = strtolower($parts[4]);
        if (!array_key_exists($status, allowed_statuses())) {
            return '⚠️ <b>Status tidak valid.</b> Pakai: ' . implode(', ', array_keys(allowed_statuses()));
        }
        $student = fetch_one(
            'SELECT * FROM students WHERE class_id = ? AND active = 1 AND (nis = ? OR nisn = ?)',
            [(int)$assignment['class_id'], $parts[3], $parts[3]]
        );
        if (!$student) {
            return '❌ <b>Siswa dengan NIS/NISN ' . e($parts[3]) . ' tidak ditemukan</b> di kelas pembelajaran.';
        }

        $date = date_ymd($parts[2]);
        $notes = trim(implode(' ', array_slice($parts, 5)));
        $sessionId = save_attendance_session((int)$assignment['id'], $date, 1, 'Absensi via Telegram', (int)$user['id']);
        save_student_attendance_entry($sessionId, (int)$student['id'], $status, $notes);

        $statusEmoji = ['hadir' => '✅', 'sakit' => '🤒', 'izin' => '📝', 'alpa' => '❌', 'terlambat' => '⏰'][$status] ?? '📌';
        return $statusEmoji . ' <b>' . e($student['name']) . '</b>: ' . allowed_statuses()[$status] . '.';
    }

    if ($command === '/jurnal') {
        $payload = trim(substr($text, $commandLength));
        $segments = array_map('trim', explode('|', $payload));
        $first = preg_split('/\s+/', (string)($segments[0] ?? ''));
        if (count($first) < 2 || count($segments) < 3) {
            return '📌 Format: <code>/jurnal ID YYYY-MM-DD | topik | kegiatan | materi | kendala | tindak_lanjut</code>';
        }
        $assignment = telegram_assignment_for_user((int)$first[0], $user);
        if (!$assignment) {
            return '❌ <b>Pembelajaran tidak ditemukan</b> atau bukan milik akun ini.';
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

        return '📝 <b>Jurnal tersimpan</b>'
            . "\n" . '📚 ' . e($assignment['subject_name'])
            . "\n" . '👥 ' . e($assignment['class_name'])
            . "\n" . '📅 ' . e($date)
            . "\n" . '💡 Topik: ' . e($topic);
    }

    return '❓ <b>Perintah belum dikenal.</b>' . "\n\n" . telegram_help();
}

function telegram_handle_callback(string $chatId, ?string $fromUsername, string $data): mixed
{
    if (str_starts_with($data, 'login:')) {
        return telegram_login_callback_response($data);
    }

    if ($data === 'home:menu') {
        return telegram_home_response($chatId, $fromUsername);
    }

    if ($data === 'home:kelas') {
        return telegram_handle_command($chatId, $fromUsername, 'kelas');
    }

    if ($data === 'home:profil') {
        return telegram_handle_command($chatId, $fromUsername, 'profil');
    }

    if ($data === 'home:logout') {
        return telegram_handle_command($chatId, $fromUsername, 'logout');
    }

    if ($data === 'home:missing-url') {
        return '⚠️ <b>Miniweb belum bisa dibuka.</b> Isi APP_URL/base_url di config agar tombol Telegram punya alamat web lengkap.';
    }

    return '⚠️ <b>Pilihan tidak dikenal.</b>';
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

    $callback = $update['callback_query'] ?? null;
    if (is_array($callback)) {
        $callbackId = (string)($callback['id'] ?? '');
        $data = trim((string)($callback['data'] ?? ''));
        $chatId = (string)($callback['message']['chat']['id'] ?? '');
        $username = $callback['from']['username'] ?? null;

        if ($callbackId !== '') {
            telegram_answer_callback($callbackId);
        }
        if ($chatId === '' || $data === '') {
            echo 'OK';
            return;
        }

        try {
            $response = telegram_handle_callback($chatId, $username, $data);
        } catch (Throwable $exception) {
            log_exception($exception);
            $response = app_debug()
            ? '⚠️ Maaf, tombol gagal diproses: ' . $exception->getMessage()
            : '⚠️ <b>Maaf, tombol gagal diproses.</b> Coba ulangi atau hubungi admin.';
        }

        $response = telegram_attach_home_menu($response, $chatId, $username);
        telegram_log($chatId, $username, '[button] ' . $data, telegram_response_text($response));
        telegram_send_message($chatId, $response);
        echo 'OK';
        return;
    }

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
            ? '⚠️ Maaf, perintah gagal diproses: ' . $exception->getMessage()
            : '⚠️ <b>Maaf, perintah gagal diproses.</b> Coba ulangi atau hubungi admin.';
    }

    $response = telegram_attach_home_menu($response, $chatId, $username);
    telegram_log($chatId, $username, $text, telegram_response_text($response));
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
