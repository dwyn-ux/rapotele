<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Services/schedule.php';
require_once dirname(__DIR__) . '/Services/whatsapp.php';

function mobile_api_secret(): string
{
    $secret = (string)get_app_setting('mobile_api_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        set_app_setting('mobile_api_secret', $secret);
    }
    return $secret;
}

function mobile_api_base64_url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function mobile_api_base64_url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function mobile_api_generate_token(int $userId): string
{
    $payload = [
        'uid' => $userId,
        'iat' => time(),
        'exp' => time() + (86400 * 90), // 90 days validity
        'nonce' => bin2hex(random_bytes(8)),
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $payloadEncoded = mobile_api_base64_url_encode($payloadJson);
    $signature = hash_hmac('sha256', $payloadEncoded, mobile_api_secret());
    return $payloadEncoded . '.' . $signature;
}

function mobile_api_verify_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !str_contains($token, '.')) {
        return null;
    }

    [$payloadEncoded, $signature] = explode('.', $token, 2);
    $expectedSignature = hash_hmac('sha256', $payloadEncoded, mobile_api_secret());
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    $payloadJson = mobile_api_base64_url_decode($payloadEncoded);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || !isset($payload['uid'], $payload['exp'])) {
        return null;
    }

    if ($payload['exp'] < time()) {
        return null;
    }

    $user = fetch_one('SELECT * FROM users WHERE id = ? AND active = 1', [(int)$payload['uid']]);
    return $user ?: null;
}

function mobile_api_get_request_token(): string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return $matches[1];
    }

    $customHeader = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($customHeader !== '') {
        return trim($customHeader);
    }

    return trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
}

function mobile_api_authenticated_user(): ?array
{
    $token = mobile_api_get_request_token();
    if ($token === '') {
        return null;
    }
    return mobile_api_verify_token($token);
}

function mobile_api_require_auth(): array
{
    $user = mobile_api_authenticated_user();
    if (!$user) {
        mobile_api_response(false, [], 'Akses ditolak. Token tidak valid atau sesi telah berakhir.', 401);
        exit;
    }
    return $user;
}

function mobile_api_response(bool $ok, array $data = [], string $message = '', int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With');

    $payload = array_merge([
        'ok' => $ok,
        'message' => $message,
    ], $data);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mobile_api_school_profile(): array
{
    if (!function_exists('get_school_profile')) {
        require_once dirname(__DIR__) . '/Pages/render.php';
    }
    $school = function_exists('get_school_profile') ? get_school_profile() : (fetch_one('SELECT * FROM school_profile ORDER BY id LIMIT 1') ?: []);
    return [
        'name' => (string)($school['name'] ?? config('app_name', 'E-Rapor Kurikulum Merdeka')),
        'npsn' => (string)($school['npsn'] ?? ''),
        'address' => (string)($school['address'] ?? ''),
        'academic_year' => (string)get_app_setting('academic_year', '2026/2027'),
        'semester' => (string)get_app_setting('semester', 'Gasal'),
        'logo_url' => app_url('media.php?type=logo'),
    ];
}

function mobile_api_dispatch(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With');
        http_response_code(204);
        exit;
    }

    $rawInput = file_get_contents('php://input') ?: '';
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput)) {
        $_POST = array_merge($_POST, $jsonInput);
    }

    $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

    try {
        switch ($action) {
            case 'login':
                mobile_api_handle_login();
                break;

            case 'me':
                mobile_api_handle_me();
                break;

            case 'whatsapp_feed':
                mobile_api_handle_whatsapp_feed();
                break;

            case 'send_whatsapp_queue':
                mobile_api_handle_send_whatsapp_queue();
                break;

            case 'notifications':
                mobile_api_handle_notifications();
                break;

            case 'my_schedule':
                mobile_api_handle_my_schedule();
                break;

            case 'trigger_schedule_reminders':
                mobile_api_handle_trigger_schedule();
                break;

            case 'teacher_attendance':
                mobile_api_handle_teacher_attendance();
                break;

            case 'teacher_checkin':
                mobile_api_handle_teacher_checkin();
                break;

            case 'teaching_assignments':
                mobile_api_handle_teaching_assignments();
                break;

            case 'students_for_attendance':
                mobile_api_handle_students_for_attendance();
                break;

            case 'save_student_attendance':
                mobile_api_handle_save_student_attendance();
                break;

            case 'teacher_class_checkin':
                mobile_api_handle_teacher_class_checkin();
                break;

            case 'journals':
                mobile_api_handle_journals();
                break;

            case 'save_journal':
                mobile_api_handle_save_journal();
                break;

            case 'grades_data':
                mobile_api_handle_grades_data();
                break;

            case 'save_grades':
                mobile_api_handle_save_grades();
                break;

            default:
                mobile_api_response(true, [
                    'version' => '1.0.0',
                    'app' => config('app_name', 'E-Rapor Kurikulum Merdeka'),
                    'endpoints' => [
                        'POST ?action=login' => 'Login user (username, password)',
                        'GET ?action=me' => 'Current authenticated user profile',
                        'GET ?action=whatsapp_feed' => 'Live WhatsApp queue, sent logs, and statistics',
                        'POST ?action=send_whatsapp_queue' => 'Trigger sending pending WhatsApp messages',
                        'GET ?action=notifications' => 'Real-time schedule reminders & system triggers',
                        'GET ?action=my_schedule' => 'Today & weekly teaching/class schedule',
                        'POST ?action=trigger_schedule_reminders' => 'Trigger schedule reminder dispatcher',
                    ],
                ], 'E-Rapor Mobile API Ready');
                break;
        }
    } catch (Throwable $e) {
        log_exception($e);
        mobile_api_response(false, ['error' => $e->getMessage()], friendly_error($e), 500);
    }
}

function mobile_api_handle_login(): void
{
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        mobile_api_response(false, [], 'Username dan password wajib diisi.', 422);
    }

    $user = fetch_one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        mobile_api_response(false, [], 'Username atau password tidak sesuai.', 401);
    }

    $token = mobile_api_generate_token((int)$user['id']);

    $teacher = null;
    if (!empty($user['teacher_id'])) {
        $teacher = fetch_one('SELECT * FROM teachers WHERE id = ?', [(int)$user['teacher_id']]);
    }

    $student = null;
    if (!empty($user['student_id'])) {
        $student = fetch_one(
            'SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?',
            [(int)$user['student_id']]
        );
    }

    mobile_api_response(true, [
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'name' => (string)$user['name'],
            'role' => (string)$user['role'],
            'email' => (string)($user['email'] ?? ''),
            'teacher_id' => $user['teacher_id'] ? (int)$user['teacher_id'] : null,
            'student_id' => $user['student_id'] ? (int)$user['student_id'] : null,
        ],
        'teacher' => $teacher,
        'student' => $student,
        'school' => mobile_api_school_profile(),
    ], 'Login berhasil.');
}

function mobile_api_handle_me(): void
{
    $user = mobile_api_require_auth();

    $teacher = null;
    if (!empty($user['teacher_id'])) {
        $teacher = fetch_one('SELECT * FROM teachers WHERE id = ?', [(int)$user['teacher_id']]);
    }

    $student = null;
    if (!empty($user['student_id'])) {
        $student = fetch_one(
            'SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?',
            [(int)$user['student_id']]
        );
    }

    mobile_api_response(true, [
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'name' => (string)$user['name'],
            'role' => (string)$user['role'],
            'email' => (string)($user['email'] ?? ''),
            'teacher_id' => $user['teacher_id'] ? (int)$user['teacher_id'] : null,
            'student_id' => $user['student_id'] ? (int)$user['student_id'] : null,
        ],
        'teacher' => $teacher,
        'student' => $student,
        'school' => mobile_api_school_profile(),
    ]);
}

function mobile_api_handle_whatsapp_feed(): void
{
    mobile_api_require_auth();

    if (!whatsapp_tables_ready()) {
        mobile_api_response(true, [
            'stats' => ['total_pending' => 0, 'total_sent' => 0, 'total_failed' => 0, 'total_today' => 0],
            'queue' => [],
            'logs' => [],
        ], 'WhatsApp tables not initialized.');
    }

    $todayStart = date('Y-m-d 00:00:00');

    $statsRow = fetch_one(
        "SELECT
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS total_pending,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) AS total_sent,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) AS total_failed,
            COUNT(CASE WHEN created_at >= ? THEN 1 END) AS total_today
         FROM whatsapp_queue",
        [$todayStart]
    ) ?: [];

    $limit = max(5, min(100, (int)($_GET['limit'] ?? 40)));

    $queue = fetch_all(
        'SELECT q.id, q.created_at, q.scheduled_at, q.student_id, q.phone,
                q.message_type, q.status, q.message, q.error_message,
                COALESCE(s.name, "-") AS student_name,
                COALESCE(wg.name, "-") AS guardian_name,
                COALESCE(c.name, "-") AS class_name
         FROM whatsapp_queue q
         LEFT JOIN students s ON s.id = q.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN whatsapp_guardians wg ON wg.id = q.guardian_id
         ORDER BY q.id DESC
         LIMIT ' . $limit
    );

    $logs = fetch_all(
        'SELECT wl.id, wl.created_at, wl.student_id, wl.phone,
                wl.status, wl.response_code, wl.error_message, wl.message,
                COALESCE(s.name, "-") AS student_name,
                COALESCE(wg.name, "-") AS guardian_name,
                COALESCE(c.name, "-") AS class_name
         FROM whatsapp_logs wl
         LEFT JOIN students s ON s.id = wl.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN whatsapp_guardians wg ON wg.id = wl.guardian_id
         ORDER BY wl.id DESC
         LIMIT ' . $limit
    );

    mobile_api_response(true, [
        'stats' => [
            'total_pending' => (int)($statsRow['total_pending'] ?? 0),
            'total_sent' => (int)($statsRow['total_sent'] ?? 0),
            'total_failed' => (int)($statsRow['total_failed'] ?? 0),
            'total_today' => (int)($statsRow['total_today'] ?? 0),
        ],
        'queue' => $queue,
        'logs' => $logs,
    ]);
}

function mobile_api_handle_send_whatsapp_queue(): void
{
    $user = mobile_api_require_auth();
    if (!in_array($user['role'], ['admin', 'guru'], true)) {
        mobile_api_response(false, [], 'Hanya admin atau guru yang dapat memproses antrian WhatsApp.', 403);
    }

    if (!whatsapp_tables_ready()) {
        mobile_api_response(false, [], 'Layanan WhatsApp belum siap.', 400);
    }

    $limit = max(1, min(50, (int)($_POST['limit'] ?? 25)));
    $result = whatsapp_send_pending_queue($limit);

    mobile_api_response(true, $result, 'Antrian diproses. Terkirim: ' . $result['sent'] . ', gagal: ' . $result['failed'] . '.');
}

function mobile_api_handle_notifications(): void
{
    $user = mobile_api_require_auth();
    $now = new DateTimeImmutable('now');
    $day = (int)$now->format('N');
    $todayDate = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:s');
    $notifications = [];

    // 1. SCHEDULE TRIGGERS (Jadwal Pelajaran / KBM)
    if (schedule_tables_ready()) {
        $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : 0;
        
        $sql = "SELECT ls.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
                FROM lesson_schedules ls
                JOIN teachers t ON t.id = ls.teacher_id
                JOIN classes c ON c.id = ls.class_id
                JOIN subjects s ON s.id = ls.subject_id
                WHERE ls.day_of_week = ?
                  AND ls.start_time IS NOT NULL";
        $params = [$day];

        if ($teacherId > 0 && $user['role'] !== 'admin') {
            $sql .= " AND ls.teacher_id = ?";
            $params[] = $teacherId;
        } elseif ($user['role'] === 'siswa' && !empty($user['student_id'])) {
            $student = fetch_one('SELECT class_id FROM students WHERE id = ?', [(int)$user['student_id']]);
            if ($student && !empty($student['class_id'])) {
                $sql .= " AND ls.class_id = ?";
                $params[] = (int)$student['class_id'];
            }
        }

        $sql .= " ORDER BY ls.start_time, c.name";
        $schedules = fetch_all($sql, $params);

        foreach ($schedules as $sch) {
            $startTime = (string)$sch['start_time'];
            $endTime = (string)($sch['end_time'] ?: date('H:i:s', strtotime($startTime) + 40 * 60));

            $startEpoch = strtotime($todayDate . ' ' . $startTime);
            $endEpoch = strtotime($todayDate . ' ' . $endTime);
            $nowEpoch = $now->getTimestamp();
            $diffMinutes = (int)round(($startEpoch - $nowEpoch) / 60);

            // Active right now
            if ($nowEpoch >= $startEpoch && $nowEpoch <= $endEpoch) {
                $notifications[] = [
                    'id' => 'kbm-active-' . $sch['id'],
                    'type' => 'schedule',
                    'level' => 'success',
                    'title' => '🔔 KBM Sedang Berlangsung: ' . $sch['subject_name'],
                    'body' => 'Kelas ' . $sch['class_name'] . ' • Jam ke-' . $sch['period'] . ' (' . substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5) . ') Pengajar: ' . $sch['teacher_name'],
                    'time' => substr($startTime, 0, 5),
                    'minutes_remaining' => 0,
                    'is_active' => true,
                ];
            }
            // Upcoming in <= 60 minutes
            elseif ($diffMinutes > 0 && $diffMinutes <= 60) {
                $notifications[] = [
                    'id' => 'kbm-upcoming-' . $sch['id'],
                    'type' => 'schedule',
                    'level' => 'warning',
                    'title' => '⏳ ' . $diffMinutes . ' Menit Lagi: KBM ' . $sch['subject_name'],
                    'body' => 'Persiapan kelas ' . $sch['class_name'] . ' jam ke-' . $sch['period'] . ' mulai ' . substr($startTime, 0, 5) . '. Guru: ' . $sch['teacher_name'],
                    'time' => substr($startTime, 0, 5),
                    'minutes_remaining' => $diffMinutes,
                    'is_active' => false,
                ];
            }
            // Later today (if <= 4 total items)
            elseif ($diffMinutes > 60 && count($notifications) < 5) {
                $notifications[] = [
                    'id' => 'kbm-later-' . $sch['id'],
                    'type' => 'schedule',
                    'level' => 'info',
                    'title' => '📅 Jadwal KBM: ' . $sch['subject_name'] . ' (' . $sch['class_name'] . ')',
                    'body' => 'Jam ke-' . $sch['period'] . ' pukul ' . substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5),
                    'time' => substr($startTime, 0, 5),
                    'minutes_remaining' => $diffMinutes,
                    'is_active' => false,
                ];
            }
        }
    }

    // 2. WHATSAPP FEED ALERTS
    if (whatsapp_tables_ready()) {
        $failedWa = fetch_one("SELECT COUNT(*) AS total FROM whatsapp_queue WHERE status = 'failed' AND created_at >= ?", [$todayDate . ' 00:00:00']);
        if ((int)($failedWa['total'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'wa-failed-' . date('YmdH'),
                'type' => 'whatsapp',
                'level' => 'danger',
                'title' => '⚠️ ' . $failedWa['total'] . ' Pesan WhatsApp Gagal Terkirim',
                'body' => 'Terdapat kendala pengiriman pesan WhatsApp hari ini. Periksa status antrian di tab WhatsApp.',
                'time' => date('H:i'),
                'is_active' => false,
            ];
        }

        $pendingWa = fetch_one("SELECT COUNT(*) AS total FROM whatsapp_queue WHERE status = 'pending'");
        if ((int)($pendingWa['total'] ?? 0) > 0) {
            $notifications[] = [
                'id' => 'wa-pending-' . date('YmdH'),
                'type' => 'whatsapp',
                'level' => 'info',
                'title' => '📬 ' . $pendingWa['total'] . ' Antrian WhatsApp Siap Dikirim',
                'body' => 'Antrian pesan (presensi, mingguan, pelanggaran) menunggu diproses pengirimannya.',
                'time' => date('H:i'),
                'is_active' => false,
            ];
        }
    }

    // 3. STUDENT ABSENCE ALERTS (Presensi Santri Hari Ini)
    if (table_exists('student_attendance_entries')) {
        $absences = fetch_all(
            "SELECT sae.*, s.name AS student_name, c.name AS class_name
             FROM student_attendance_entries sae
             JOIN students s ON s.id = sae.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE sae.date = ? AND sae.status IN ('alfa', 'izin', 'sakit')
             ORDER BY sae.id DESC
             LIMIT 5",
            [$todayDate]
        );
        foreach ($absences as $abs) {
            $statusLabel = strtoupper($abs['status']);
            $notifications[] = [
                'id' => 'abs-' . $abs['id'],
                'type' => 'attendance',
                'level' => $abs['status'] === 'alfa' ? 'danger' : 'warning',
                'title' => '📋 Ketidakhadiran: ' . $abs['student_name'] . ' (' . $statusLabel . ')',
                'body' => 'Kelas ' . $abs['class_name'] . ' tidak hadir (' . $statusLabel . ')' . ($abs['notes'] ? ': ' . $abs['notes'] : ''),
                'time' => substr((string)($abs['created_at'] ?? date('H:i')), 11, 5) ?: date('H:i'),
                'is_active' => false,
            ];
        }
    }

    // 4. STUDENT VIOLATION ALERTS (Pelanggaran Santri Terbaru)
    if (table_exists('student_violations')) {
        $violations = fetch_all(
            "SELECT sv.*, s.name AS student_name, c.name AS class_name
             FROM student_violations sv
             JOIN students s ON s.id = sv.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE sv.date >= ?
             ORDER BY sv.id DESC
             LIMIT 5",
            [date('Y-m-d', strtotime('-3 days'))]
        );
        foreach ($violations as $v) {
            $notifications[] = [
                'id' => 'viol-' . $v['id'],
                'type' => 'violation',
                'level' => 'danger',
                'title' => '⚠️ Catatan Kedisiplinan: ' . $v['student_name'],
                'body' => 'Kelas ' . $v['class_name'] . ' • Poin: ' . ($v['points'] ?? 0) . ' • ' . ($v['violation_name'] ?? $v['notes'] ?? 'Pelanggaran'),
                'time' => (string)$v['date'],
                'is_active' => false,
            ];
        }
    }

    mobile_api_response(true, [
        'count' => count($notifications),
        'notifications' => $notifications,
    ]);
}

function mobile_api_handle_my_schedule(): void
{
    $user = mobile_api_require_auth();
    $now = new DateTimeImmutable('now');
    $day = (int)$now->format('N');
    $todayDate = $now->format('Y-m-d');

    if (!schedule_tables_ready()) {
        mobile_api_response(true, ['today' => [], 'week' => []], 'Jadwal belum dikonfigurasi.');
    }

    $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : 0;

    $baseSql = "SELECT ls.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
                FROM lesson_schedules ls
                JOIN teachers t ON t.id = ls.teacher_id
                JOIN classes c ON c.id = ls.class_id
                JOIN subjects s ON s.id = ls.subject_id";
    $where = [];
    $params = [];

    if ($teacherId > 0 && $user['role'] !== 'admin') {
        $where[] = "ls.teacher_id = ?";
        $params[] = $teacherId;
    } elseif ($user['role'] === 'siswa' && !empty($user['student_id'])) {
        $student = fetch_one('SELECT class_id FROM students WHERE id = ?', [(int)$user['student_id']]);
        if ($student && !empty($student['class_id'])) {
            $where[] = "ls.class_id = ?";
            $params[] = (int)$student['class_id'];
        }
    }

    $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $allRows = fetch_all($baseSql . $whereClause . ' ORDER BY ls.day_of_week, ls.period, ls.start_time', $params);

    $daysMap = schedule_days();
    $todaySchedules = [];
    $weekSchedules = [];

    foreach ($daysMap as $dNum => $dName) {
        $weekSchedules[$dNum] = [
            'day_number' => $dNum,
            'day_name' => $dName,
            'items' => [],
        ];
    }

    foreach ($allRows as $row) {
        $rowDay = (int)$row['day_of_week'];
        $startTime = (string)$row['start_time'];
        $endTime = (string)($row['end_time'] ?: date('H:i:s', strtotime($startTime) + 40 * 60));

        $item = [
            'id' => (int)$row['id'],
            'day_of_week' => $rowDay,
            'day_name' => $daysMap[$rowDay] ?? 'Hari ' . $rowDay,
            'period' => (int)$row['period'],
            'start_time' => substr($startTime, 0, 5),
            'end_time' => substr($endTime, 0, 5),
            'subject_name' => (string)$row['subject_name'],
            'class_name' => (string)$row['class_name'],
            'teacher_name' => (string)$row['teacher_name'],
            'room' => (string)($row['room'] ?? ''),
        ];

        if ($rowDay === $day) {
            $startEpoch = strtotime($todayDate . ' ' . $startTime);
            $endEpoch = strtotime($todayDate . ' ' . $endTime);
            $nowEpoch = $now->getTimestamp();

            if ($nowEpoch > $endEpoch) {
                $status = 'finished';
            } elseif ($nowEpoch >= $startEpoch && $nowEpoch <= $endEpoch) {
                $status = 'active';
            } else {
                $status = 'upcoming';
            }

            $item['status'] = $status;
            $item['minutes_until_start'] = (int)round(($startEpoch - $nowEpoch) / 60);
            $todaySchedules[] = $item;
        }

        if (isset($weekSchedules[$rowDay])) {
            $weekSchedules[$rowDay]['items'][] = $item;
        }
    }

    mobile_api_response(true, [
        'today_day_name' => $daysMap[$day] ?? '',
        'today_date' => $todayDate,
        'today' => $todaySchedules,
        'week' => array_values($weekSchedules),
    ]);
}

function mobile_api_handle_trigger_schedule(): void
{
    $user = mobile_api_require_auth();
    if (!in_array($user['role'], ['admin', 'guru'], true)) {
        mobile_api_response(false, [], 'Tidak diizinkan memicu pengiriman jadwal.', 403);
    }

    $now = new DateTimeImmutable('now');
    $result = schedule_send_due_reminders($now);

    mobile_api_response(true, $result, 'Reminder jadwal berhasil dicek & dipicu.');
}

function mobile_api_handle_teacher_attendance(): void
{
    $user = mobile_api_require_auth();
    $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : 0;
    if ($teacherId <= 0 && $user['role'] === 'admin') {
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
    }

    $date = date_ymd($_GET['date'] ?? date('Y-m-d'));
    $attendance = null;
    if ($teacherId > 0) {
        $attendance = fetch_one('SELECT * FROM teacher_attendance WHERE teacher_id = ? AND date = ?', [$teacherId, $date]);
    }

    if (!function_exists('get_school_profile')) {
        require_once dirname(__DIR__) . '/Pages/render.php';
    }
    $school = function_exists('get_school_profile') ? get_school_profile() : [];

    mobile_api_response(true, [
        'attendance' => $attendance,
        'date' => $date,
        'teacher_id' => $teacherId,
        'school_lat' => !empty($school['location_lat']) ? (float)$school['location_lat'] : null,
        'school_lng' => !empty($school['location_lng']) ? (float)$school['location_lng'] : null,
        'radius_meters' => (int)($school['attendance_radius_meters'] ?? 500),
    ]);
}

function mobile_api_handle_teacher_checkin(): void
{
    $user = mobile_api_require_auth();
    $teacherId = !empty($user['teacher_id']) ? (int)$user['teacher_id'] : 0;
    if ($teacherId <= 0) {
        mobile_api_response(false, [], 'Akun Anda tidak terhubung dengan data guru.', 400);
    }

    $type = trim((string)($_POST['type'] ?? 'checkin'));
    if (!in_array($type, ['checkin', 'checkout'], true)) {
        mobile_api_response(false, [], 'Jenis absensi harus checkin atau checkout.', 422);
    }

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $lat = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;
    $status = trim((string)($_POST['status'] ?? 'hadir'));
    $notes = trim((string)($_POST['notes'] ?? 'Presensi mandiri via Mobile App'));

    $existing = fetch_one('SELECT id, time_in, time_out FROM teacher_attendance WHERE teacher_id = ? AND date = ?', [$teacherId, $date]);

    if ($existing) {
        if ($type === 'checkout') {
            execute_sql(
                'UPDATE teacher_attendance SET time_out = ?, location_lat = ?, location_lng = ?, updated_at = ? WHERE id = ?',
                [$time, $lat, $lng, now_string(), (int)$existing['id']]
            );
            mobile_api_response(true, ['time_out' => $time], 'Check-out berhasil pukul ' . $time);
        } else {
            execute_sql(
                'UPDATE teacher_attendance SET time_in = ?, location_lat = ?, location_lng = ?, notes = ?, updated_at = ? WHERE id = ?',
                [$time, $lat, $lng, $notes, now_string(), (int)$existing['id']]
            );
            mobile_api_response(true, ['time_in' => $time], 'Check-in berhasil diperbarui pukul ' . $time);
        }
    } else {
        execute_sql(
            'INSERT INTO teacher_attendance (teacher_id, date, status, time_in, location_lat, location_lng, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$teacherId, $date, $status, $time, $lat, $lng, $notes, (int)$user['id'], now_string()]
        );
        mobile_api_response(true, ['time_in' => $time], 'Check-in berhasil pukul ' . $time);
    }
}

function mobile_api_handle_teaching_assignments(): void
{
    $user = mobile_api_require_auth();
    $sql = "SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.academic_year, ta.semester,
                   c.name AS class_name, c.grade, s.name AS subject_name, s.code AS subject_code
            FROM teaching_assignments ta
            JOIN classes c ON c.id = ta.class_id
            JOIN subjects s ON s.id = ta.subject_id
            WHERE ta.active = 1";
    $params = [];

    if ($user['role'] !== 'admin' && !empty($user['teacher_id'])) {
        $sql .= " AND ta.teacher_id = ?";
        $params[] = (int)$user['teacher_id'];
    }

    $sql .= " ORDER BY c.grade, c.name, s.name";
    $assignments = fetch_all($sql, $params);

    mobile_api_response(true, ['assignments' => $assignments]);
}

function mobile_api_handle_students_for_attendance(): void
{
    $user = mobile_api_require_auth();
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        mobile_api_response(false, [], 'assignment_id wajib diisi.', 422);
    }

    $assignment = fetch_one(
        "SELECT ta.*, c.name AS class_name, c.grade, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.id = ?",
        [$assignmentId]
    );

    if (!$assignment) {
        mobile_api_response(false, [], 'Pembelajaran kelas tidak ditemukan.', 404);
    }

    $date = date_ymd($_GET['date'] ?? date('Y-m-d'));
    $meetingNo = max(1, (int)($_GET['meeting_no'] ?? 1));

    $session = fetch_one(
        'SELECT * FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [$assignmentId, $date, $meetingNo]
    );

    $sessionId = $session['id'] ?? 0;

    $students = fetch_all(
        "SELECT s.id, s.nis, s.nisn, s.name, s.gender,
                COALESCE(sae.status, 'hadir') AS status,
                COALESCE(sae.notes, '') AS notes
         FROM students s
         LEFT JOIN student_attendance_entries sae ON sae.student_id = s.id AND sae.session_id = ?
         WHERE s.class_id = ? AND s.active = 1
         ORDER BY s.name",
        [$sessionId, (int)$assignment['class_id']]
    );

    $teacherCheckedIn = !empty($session['teacher_attended']) && (int)$session['teacher_attended'] === 1;

    mobile_api_response(true, [
        'assignment' => $assignment,
        'date' => $date,
        'meeting_no' => $meetingNo,
        'session' => $session,
        'students' => $students,
        'teacher_checked_in' => $teacherCheckedIn,
        'teacher_time_in' => $session['teacher_time_in'] ?? null,
        'teacher_status' => $session['teacher_status'] ?? 'hadir',
    ]);
}

function mobile_api_handle_teacher_class_checkin(): void
{
    $user = mobile_api_require_auth();
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $date = date_ymd($_POST['date'] ?? date('Y-m-d'));
    $meetingNo = max(1, (int)($_POST['meeting_no'] ?? 1));
    $status = trim((string)($_POST['status'] ?? 'hadir'));
    $timeIn = date('H:i:s');

    if ($assignmentId <= 0) {
        mobile_api_response(false, [], 'assignment_id wajib diisi.', 422);
    }

    $session = fetch_one(
        'SELECT id, teacher_attended, teacher_time_in FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [$assignmentId, $date, $meetingNo]
    );

    $now = now_string();
    if ($session) {
        execute_sql(
            'UPDATE student_attendance_sessions SET teacher_attended = 1, teacher_status = ?, teacher_time_in = ?, updated_at = ? WHERE id = ?',
            [$status, $timeIn, $now, (int)$session['id']]
        );
        $sessionId = (int)$session['id'];
    } else {
        execute_sql(
            'INSERT INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, teacher_attended, teacher_status, teacher_time_in, created_by, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)',
            [$assignmentId, $date, $meetingNo, 'Presensi KBM', $status, $timeIn, (int)$user['id'], $now]
        );
        $sessionId = (int)db()->lastInsertId();
    }

    mobile_api_response(true, [
        'session_id' => $sessionId,
        'teacher_checked_in' => true,
        'teacher_time_in' => $timeIn,
        'teacher_status' => $status,
    ], 'Absen masuk kelas berhasil dicatat pukul ' . substr($timeIn, 0, 5));
}

function mobile_api_handle_save_student_attendance(): void
{
    $user = mobile_api_require_auth();
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $date = date_ymd($_POST['date'] ?? date('Y-m-d'));
    $meetingNo = max(1, (int)($_POST['meeting_no'] ?? 1));
    $topic = trim((string)($_POST['topic'] ?? 'Presensi KBM'));
    $queueWa = !empty($_POST['queue_whatsapp_absence']);
    $rows = $_POST['rows'] ?? [];

    if ($assignmentId <= 0 || !is_array($rows) || empty($rows)) {
        mobile_api_response(false, [], 'Data presensi siswa tidak lengkap.', 422);
    }

    $session = fetch_one(
        'SELECT id, teacher_attended, teacher_time_in FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [$assignmentId, $date, $meetingNo]
    );

    // Rule: Guru TIDAK BISA absen siswa sebelum absen masuk kelas
    if (!$session || empty($session['teacher_attended']) || (int)$session['teacher_attended'] !== 1) {
        mobile_api_response(false, ['require_teacher_checkin' => true], 'Guru belum melakukan absensi masuk kelas. Silakan lakukan absen masuk kelas terlebih dahulu sebelum mengabsen siswa.', 403);
    }

    $pdo = db();
    if (!$pdo->beginTransaction()) {
        mobile_api_response(false, [], 'Gagal memulai transaksi.', 500);
    }

    try {
        if ($session) {
            $sessionId = (int)$session['id'];
            execute_sql(
                'UPDATE student_attendance_sessions SET topic = ?, teacher_attended = 1, updated_at = ? WHERE id = ?',
                [$topic, now_string(), $sessionId]
            );
        } else {
            execute_sql(
                'INSERT INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, teacher_attended, teacher_status, teacher_time_in, created_by, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)',
                [$assignmentId, $date, $meetingNo, $topic, 'hadir', date('H:i:s'), (int)$user['id'], now_string()]
            );
            $sessionId = (int)$pdo->lastInsertId();
        }

        $now = now_string();
        $absentEntryIds = [];

        foreach ($rows as $item) {
            $studentId = (int)($item['student_id'] ?? 0);
            $status = strtolower(trim((string)($item['status'] ?? 'hadir')));
            if (!in_array($status, ['hadir', 'sakit', 'izin', 'alfa'], true)) {
                $status = 'hadir';
            }
            $notes = trim((string)($item['notes'] ?? ''));

            $entry = fetch_one('SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?', [$sessionId, $studentId]);
            if ($entry) {
                execute_sql(
                    'UPDATE student_attendance_entries SET status = ?, notes = ?, updated_at = ? WHERE id = ?',
                    [$status, $notes, $now, (int)$entry['id']]
                );
                $entryId = (int)$entry['id'];
            } else {
                execute_sql(
                    'INSERT INTO student_attendance_entries (session_id, student_id, status, notes, updated_at) VALUES (?, ?, ?, ?, ?)',
                    [$sessionId, $studentId, $status, $notes, $now]
                );
                $entryId = (int)$pdo->lastInsertId();
            }

            if ($status !== 'hadir') {
                $absentEntryIds[] = $entryId;
            }
        }

        $pdo->commit();

        // Enqueue WhatsApp notices if selected
        $waCount = 0;
        if ($queueWa && !empty($absentEntryIds) && function_exists('whatsapp_enqueue_attendance_notice')) {
            foreach ($absentEntryIds as $eid) {
                try {
                    $waRes = whatsapp_enqueue_attendance_notice($eid, (int)$user['id']);
                    $waCount += (int)($waRes['queued'] ?? 0);
                } catch (_) {}
            }
        }

        $msg = 'Presensi siswa berhasil disimpan.';
        if ($waCount > 0) {
            $msg .= ' ' . $waCount . ' notifikasi WhatsApp ditambahkan ke antrian.';
        }

        mobile_api_response(true, ['session_id' => $sessionId, 'wa_queued' => $waCount], $msg);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mobile_api_response(false, [], 'Gagal menyimpan absensi: ' . $e->getMessage(), 500);
    }
}

function mobile_api_handle_journals(): void
{
    $user = mobile_api_require_auth();
    $sql = "SELECT dj.*, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name
            FROM daily_journals dj
            JOIN classes c ON c.id = dj.class_id
            JOIN subjects s ON s.id = dj.subject_id
            JOIN teachers t ON t.id = dj.teacher_id";
    $params = [];

    if ($user['role'] !== 'admin' && !empty($user['teacher_id'])) {
        $sql .= " WHERE dj.teacher_id = ?";
        $params[] = (int)$user['teacher_id'];
    }

    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    if ($assignmentId > 0) {
        $sql .= ($params ? " AND" : " WHERE") . " dj.assignment_id = ?";
        $params[] = $assignmentId;
    }

    $sql .= " ORDER BY dj.date DESC, dj.id DESC LIMIT 40";
    $journals = fetch_all($sql, $params);

    mobile_api_response(true, ['journals' => $journals]);
}

function mobile_api_handle_save_journal(): void
{
    $user = mobile_api_require_auth();
    $id = (int)($_POST['id'] ?? 0);
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);

    if ($assignmentId <= 0) {
        mobile_api_response(false, [], 'assignment_id wajib diisi.', 422);
    }

    $assignment = fetch_one('SELECT * FROM teaching_assignments WHERE id = ?', [$assignmentId]);
    if (!$assignment) {
        mobile_api_response(false, [], 'Pembelajaran tidak ditemukan.', 404);
    }

    $date = date_ymd($_POST['date'] ?? date('Y-m-d'));
    $meetingNo = max(1, (int)($_POST['meeting_no'] ?? 1));
    $topic = trim((string)($_POST['topic'] ?? ''));
    $activities = trim((string)($_POST['activities'] ?? ''));
    $materials = trim((string)($_POST['materials'] ?? ''));
    $obstacles = trim((string)($_POST['obstacles'] ?? ''));
    $followUp = trim((string)($_POST['follow_up'] ?? ''));

    if ($topic === '' || $activities === '') {
        mobile_api_response(false, [], 'Materi / topik dan kegiatan pembelajaran wajib diisi.', 422);
    }

    $now = now_string();

    if ($id > 0) {
        execute_sql(
            'UPDATE daily_journals SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, date = ?, meeting_no = ?, topic = ?, activities = ?, materials = ?, obstacles = ?, follow_up = ?, updated_at = ? WHERE id = ?',
            [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $date, $meetingNo, $topic, $activities, $materials, $obstacles, $followUp, $now, $id]
        );
        mobile_api_response(true, ['id' => $id], 'Jurnal pembelajaran berhasil diperbarui.');
    } else {
        execute_sql(
            'INSERT INTO daily_journals (assignment_id, teacher_id, class_id, subject_id, date, meeting_no, topic, activities, materials, obstacles, follow_up, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], $date, $meetingNo, $topic, $activities, $materials, $obstacles, $followUp, (int)$user['id'], $now]
        );
        mobile_api_response(true, ['id' => (int)db()->lastInsertId()], 'Jurnal pembelajaran berhasil disimpan.');
    }
}

function mobile_api_handle_grades_data(): void
{
    $user = mobile_api_require_auth();
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        mobile_api_response(false, [], 'assignment_id wajib diisi.', 422);
    }

    $assignment = fetch_one(
        "SELECT ta.*, c.name AS class_name, c.grade, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE ta.id = ?",
        [$assignmentId]
    );

    if (!$assignment) {
        mobile_api_response(false, [], 'Pembelajaran tidak ditemukan.', 404);
    }

    $assessmentType = trim((string)($_GET['assessment_type'] ?? 'UH'));
    $learningObjectiveId = !empty($_GET['learning_objective_id']) ? (int)$_GET['learning_objective_id'] : null;

    $objectives = fetch_all(
        'SELECT id, code, description FROM learning_objectives WHERE subject_id = ? AND grade = ? AND active = 1 ORDER BY code',
        [(int)$assignment['subject_id'], (int)$assignment['grade']]
    );

    $sql = "SELECT s.id, s.nis, s.name, g.score, g.description
            FROM students s
            LEFT JOIN grades g ON g.student_id = s.id AND g.assignment_id = ? AND g.assessment_type = ? ";
    $params = [$assignmentId, $assessmentType];

    if ($learningObjectiveId !== null) {
        $sql .= "AND g.learning_objective_id = ? ";
        $params[] = $learningObjectiveId;
    } else {
        $sql .= "AND g.learning_objective_id IS NULL ";
    }

    $sql .= "WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name";
    $params[] = (int)$assignment['class_id'];

    $students = fetch_all($sql, $params);

    mobile_api_response(true, [
        'assignment' => $assignment,
        'assessment_type' => $assessmentType,
        'learning_objective_id' => $learningObjectiveId,
        'objectives' => $objectives,
        'students' => $students,
    ]);
}

function mobile_api_handle_save_grades(): void
{
    $user = mobile_api_require_auth();
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $assessmentType = trim((string)($_POST['assessment_type'] ?? 'UH'));
    $learningObjectiveId = !empty($_POST['learning_objective_id']) ? (int)$_POST['learning_objective_id'] : null;
    $scores = $_POST['scores'] ?? [];

    if ($assignmentId <= 0 || !is_array($scores) || empty($scores)) {
        mobile_api_response(false, [], 'Data penilaian siswa tidak lengkap.', 422);
    }

    $pdo = db();
    if (!$pdo->beginTransaction()) {
        mobile_api_response(false, [], 'Gagal memulai transaksi.', 500);
    }

    try {
        $now = now_string();
        $userId = (int)$user['id'];

        foreach ($scores as $item) {
            $studentId = (int)($item['student_id'] ?? 0);
            $rawScore = $item['score'] ?? null;
            $score = ($rawScore !== null && $rawScore !== '') ? (float)$rawScore : null;

            $params = [$assignmentId, $studentId, $assessmentType];
            $objCond = $learningObjectiveId !== null ? 'learning_objective_id = ?' : 'learning_objective_id IS NULL';
            if ($learningObjectiveId !== null) {
                $params[] = $learningObjectiveId;
            }

            $existing = fetch_one("SELECT id FROM grades WHERE assignment_id = ? AND student_id = ? AND assessment_type = ? AND $objCond", $params);

            if ($existing) {
                execute_sql('UPDATE grades SET score = ?, updated_at = ? WHERE id = ?', [$score, $now, (int)$existing['id']]);
            } elseif ($score !== null) {
                $loKey = $learningObjectiveId !== null ? (string)$learningObjectiveId : '';
                execute_sql(
                    'INSERT INTO grades (assignment_id, student_id, assessment_type, learning_objective_id, learning_objective_key, score, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$assignmentId, $studentId, $assessmentType, $learningObjectiveId, $loKey, $score, $userId, $now]
                );
            }
        }

        $pdo->commit();
        mobile_api_response(true, [], 'Nilai siswa berhasil disimpan.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mobile_api_response(false, [], 'Gagal menyimpan nilai: ' . $e->getMessage(), 500);
    }
}
