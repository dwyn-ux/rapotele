<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/web.php';

final class SmokeResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public string $url
    ) {
    }

    public function header(string $name): string
    {
        $needle = strtolower($name) . ':';
        foreach ($this->headers as $header) {
            if (str_starts_with(strtolower($header), $needle)) {
                return trim(substr($header, strlen($needle)));
            }
        }
        return '';
    }
}

final class SmokeClient
{
    private array $cookies = [];

    public function __construct(private string $baseUrl)
    {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function get(string $path, array $headers = []): SmokeResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    public function postForm(string $path, array $data, array $headers = []): SmokeResponse
    {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return $this->request('POST', $path, http_build_query($data), $headers);
    }

    public function postJson(string $path, array $data, array $headers = []): SmokeResponse
    {
        $headers[] = 'Content-Type: application/json';
        return $this->request('POST', $path, json_encode($data, JSON_UNESCAPED_UNICODE), $headers);
    }

    public function follow(SmokeResponse $response, int $maxRedirects = 5): SmokeResponse
    {
        while ($maxRedirects-- > 0 && in_array($response->status, [301, 302, 303, 307, 308], true)) {
            $location = $response->header('Location');
            if ($location === '') {
                break;
            }
            $response = $this->get($this->relativeUrl($location));
        }
        return $response;
    }

    private function request(string $method, string $path, ?string $body, array $headers): SmokeResponse
    {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');
        $cookieHeader = $this->cookieHeader();
        if ($cookieHeader !== '') {
            $headers[] = 'Cookie: ' . $cookieHeader;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 20,
            ],
        ]);

        $contents = file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $this->storeCookies($responseHeaders);

        return new SmokeResponse(
            $this->statusFromHeaders($responseHeaders),
            $contents === false ? '' : $contents,
            $responseHeaders,
            $url
        );
    }

    private function cookieHeader(): string
    {
        $pairs = [];
        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        return implode('; ', $pairs);
    }

    private function storeCookies(array $headers): void
    {
        foreach ($headers as $header) {
            if (!str_starts_with(strtolower($header), 'set-cookie:')) {
                continue;
            }
            $cookie = trim(substr($header, strlen('set-cookie:')));
            $pair = explode(';', $cookie, 2)[0] ?? '';
            if (!str_contains($pair, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2);
            if ($value === '') {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                return (int)$match[1];
            }
        }
        return 0;
    }

    private function relativeUrl(string $url): string
    {
        if (str_starts_with($url, $this->baseUrl)) {
            return substr($url, strlen($this->baseUrl));
        }
        return $url;
    }
}

final class SmokeSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function __construct(private string $baseUrl, private bool $withScheduleMutation = false)
    {
    }

    public function run(): int
    {
        $this->testPublicSecurity();
        $this->testAdminFlow();
        if ($this->withScheduleMutation) {
            $this->testLessonScheduleMutation();
        }
        $this->testGuruAccess();
        $this->testSiswaAccess();

        echo "\nSmoke test: {$this->passed} passed, {$this->failed} failed.\n";
        foreach ($this->failures as $failure) {
            fwrite(STDERR, "[FAIL] $failure\n");
        }

        return $this->failed === 0 ? 0 : 1;
    }

    private function testPublicSecurity(): void
    {
        $client = new SmokeClient($this->baseUrl);

        $login = $client->get('/index.php?page=login');
        $this->assertStatus('public login page', $login, 200);
        $this->assertContains('login csrf field', $login->body, 'name="_csrf"');
        $this->assertHeader('security nosniff', $login, 'X-Content-Type-Options', 'nosniff');
        $this->assertHeader('security frame options', $login, 'X-Frame-Options', 'SAMEORIGIN');
        $this->assertHeaderPresent('security csp', $login, 'Content-Security-Policy');

        $private = $client->get('/index.php?page=dashboard');
        $this->assertStatus('unauth dashboard redirects', $private, 302);
        $this->assertContains('unauth redirect target', $private->header('Location'), 'page=login');

        $installer = $client->get('/install.php');
        $this->assertStatus('locked public installer', $installer, 403);

        $bridgeGet = $client->get('/dapodik_bridge.php');
        $this->assertStatus('dapodik bridge rejects GET', $bridgeGet, 405);

        $bridgePost = $client->postJson('/dapodik_bridge.php', ['token' => 'bad', 'type' => 'sekolah', 'data' => []]);
        $this->assertStatus('dapodik bridge rejects invalid token', $bridgePost, 403);
        $this->assertContains('dapodik bridge json error', $bridgePost->body, '"ok":false');
        $this->testDapodikBridgeAutoSetup($client);
        $this->testDapodikBridgeValidPayload($client);
        $this->testDapodikPortableDownload($client);

        $telegramGet = $client->get('/telegram_webhook.php');
        $this->assertStatus('telegram webhook rejects GET', $telegramGet, 405);
    }

    private function testDapodikBridgeAutoSetup(SmokeClient $client): void
    {
        $oldDapodikToken = (string)get_app_setting('dapodik_token', '');
        $oldNpsn = (string)get_app_setting('dapodik_npsn', '');
        $dapodikToken = 'auto-setup-' . bin2hex(random_bytes(6));
        $npsn = 'AUTO' . bin2hex(random_bytes(3));
        $subjectName = 'Smoke Auto Setup Mapel ' . bin2hex(random_bytes(4));
        $subjectId = 0;

        try {
            set_app_setting('dapodik_token', '');
            set_app_setting('dapodik_npsn', '');

            $response = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'mapel',
                    'npsn' => $npsn,
                    'token' => $dapodikToken,
                    'data' => [
                        [
                            'nama_mata_pelajaran' => $subjectName,
                            'kode' => 'SMKAUT',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );

            $this->assertStatus('dapodik bridge auto setup accepts empty server token', $response, 200);
            $this->assertContains('dapodik bridge auto setup flag', $response->body, '"auto_configured":true');

            if ((string)get_app_setting('dapodik_token', '') === $dapodikToken && (string)get_app_setting('dapodik_npsn', '') === $npsn) {
                $this->pass('dapodik bridge auto setup persists server token');
            } else {
                $this->fail('dapodik bridge auto setup persists server token');
            }

            $subject = fetch_one('SELECT id FROM subjects WHERE name = ?', [$subjectName]);
            if (!$subject) {
                $this->fail('dapodik bridge auto setup imported subject');
                return;
            }

            $subjectId = (int)$subject['id'];
            $this->pass('dapodik bridge auto setup imported subject');
        } finally {
            if ($subjectId > 0) {
                execute_sql('DELETE FROM subjects WHERE id = ?', [$subjectId]);
            }
            set_app_setting('dapodik_token', $oldDapodikToken);
            set_app_setting('dapodik_npsn', $oldNpsn);
        }
    }

    private function testDapodikBridgeValidPayload(SmokeClient $client): void
    {
        $oldDapodikToken = (string)get_app_setting('dapodik_token', '');
        $oldNpsn = (string)get_app_setting('dapodik_npsn', '');
        $dapodikToken = 'smoke-token-' . bin2hex(random_bytes(6));
        $npsn = 'SMOKE' . bin2hex(random_bytes(3));
        $bodyTokenSubjectName = 'Smoke Bridge Body Token ' . bin2hex(random_bytes(4));
        $subjectName = 'Smoke Bridge Mapel ' . bin2hex(random_bytes(4));
        $bodyTokenSubjectId = 0;
        $subjectId = 0;

        try {
            set_app_setting('dapodik_token', $dapodikToken);
            set_app_setting('dapodik_npsn', $npsn);

            $wrongNpsn = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'mapel',
                    'npsn' => 'NPSN-SALAH',
                    'data' => [['nama_mata_pelajaran' => $subjectName, 'kode' => 'BADNPSN']],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge rejects wrong npsn', $wrongNpsn, 403);
            $this->assertContains('dapodik bridge wrong npsn message', $wrongNpsn->body, 'Token sinkron');

            $bodyTokenResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'mapel',
                    'npsn' => $npsn,
                    'token' => $dapodikToken,
                    'data' => [
                        [
                            'nama_mata_pelajaran' => $bodyTokenSubjectName,
                            'kode' => 'SMKBOD',
                        ],
                    ],
                ],
                ['X-Eraport-Token: wrong-header-token']
            );
            $this->assertStatus('dapodik bridge accepts body token fallback', $bodyTokenResponse, 200);
            $bodyTokenSubject = fetch_one('SELECT id FROM subjects WHERE name = ?', [$bodyTokenSubjectName]);
            if (!$bodyTokenSubject) {
                $this->fail('dapodik bridge imported body token subject');
                return;
            }
            $bodyTokenSubjectId = (int)$bodyTokenSubject['id'];
            $this->pass('dapodik bridge imported body token subject');

            $response = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'mapel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'nama_mata_pelajaran' => $subjectName,
                            'kode' => 'SMKBRG',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );

            $this->assertStatus('dapodik bridge accepts dapodik token', $response, 200);
            $this->assertContains('dapodik bridge valid json ok', $response->body, '"ok":true');
            $this->assertContains('dapodik bridge valid import count', $response->body, '"count":1');

            $subject = fetch_one('SELECT id FROM subjects WHERE name = ?', [$subjectName]);
            if (!$subject) {
                $this->fail('dapodik bridge imported subject');
                return;
            }

            $subjectId = (int)$subject['id'];
            $this->pass('dapodik bridge imported subject');
        } finally {
            if ($bodyTokenSubjectId > 0) {
                execute_sql('DELETE FROM subjects WHERE id = ?', [$bodyTokenSubjectId]);
            }
            if ($subjectId > 0) {
                execute_sql('DELETE FROM subjects WHERE id = ?', [$subjectId]);
            }
            set_app_setting('dapodik_token', $oldDapodikToken);
            set_app_setting('dapodik_npsn', $oldNpsn);
        }
    }

    private function testDapodikPortableDownload(SmokeClient $client): void
    {
        $response = $client->get('/dapodik_local_helper.php?download=portable&dapodik_url=http%3A%2F%2F127.0.0.1%3A5774&dapodik_token=smoke-token&npsn=SMOKE123&bridge_url=' . rawurlencode($this->baseUrl));
        $this->assertStatus('dapodik portable zip downloads', $response, 200);
        $this->assertContains('dapodik portable zip content type', $response->header('Content-Type'), 'application/zip');
        if (!str_starts_with($response->body, "PK\x03\x04")) {
            $this->fail('dapodik portable zip body');
            return;
        }
        $this->pass('dapodik portable zip body');
    }

    private function testAdminFlow(): void
    {
        $client = $this->login('administrator', 'administrator', 'admin login');

        $safePages = [
            'dashboard',
            'school',
            'teachers',
            'students',
            'classes',
            'subjects',
            'assignments',
            'data-ekskul',
            'data-kelompok',
            'gabung-mapel',
            'data-mapping',
            'data-logo-ttd',
            'tanggal-rapor',
            'foto-siswa',
            'tema-kokurikuler',
            'kegiatan-kokurikuler',
            'kelompok-kokurikuler',
            'data-tp',
            'status-penilaian',
            'input-kelulusan',
            'import-nomor-ijazah',
            'setting-transkrip',
            'setting-skl',
            'mapping-mapel-skl',
            'input-nilai-skl',
            'kirim-data-dapodik',
            'backup-restore',
            'users',
            'profile',
            'grades',
            'student-attendance',
            'teacher-attendance',
            'lesson-schedule',
            'journals',
            'violations',
            'reports',
            'telegram',
            'cetak-pelengkap-rapor',
            'cetak-nilai-rapor',
        ];

        foreach ($safePages as $page) {
            $response = $client->get('/index.php?page=' . rawurlencode($page));
            $this->assertStatus("admin page $page", $response, 200);
        }

        $this->testDapodikSaveAndPortableDownload($client);

        $missing = $client->get('/index.php?page=__missing__');
        $this->assertStatus('authenticated missing page', $missing, 404);

        $dashboard = $client->get('/index.php?page=dashboard');
        $csrf = $this->csrf($dashboard);
        $badAction = $client->follow($client->postForm('/index.php?page=dashboard', [
            '_csrf' => $csrf,
            'action' => 'definitely_missing',
        ]));
        $this->assertStatus('invalid action redirects safely', $badAction, 200);
        $this->assertContains('invalid action flash', $badAction->body, 'Action tidak dikenal');

        $badCsrf = $client->postForm('/index.php?page=dashboard', ['action' => 'save_profile']);
        $this->assertStatus('missing csrf rejected', $badCsrf, 419);

        $studentId = $this->firstInt('SELECT id FROM students WHERE active = 1 ORDER BY id');
        if ($studentId > 0) {
            $pdf = $client->get('/index.php?page=rapor-download-student&student_id=' . $studentId);
            $this->assertStatus('admin rapor student download', $pdf, 200);
            $this->assertContains('admin rapor is pdf', $pdf->header('Content-Type'), 'application/pdf');
        }

        $backupId = $this->firstInt('SELECT id FROM backups ORDER BY id DESC');
        if ($backupId > 0) {
            $backup = $client->get('/index.php?page=backup-download&id=' . $backupId);
            $this->assertStatus('admin backup download', $backup, 200);
            $this->assertContains('admin backup is json', $backup->header('Content-Type'), 'application/json');
        }
    }

    private function testDapodikSaveAndPortableDownload(SmokeClient $client): void
    {
        $oldUrl = (string)get_app_setting('dapodik_url', '');
        $oldDapodikToken = (string)get_app_setting('dapodik_token', '');
        $oldNpsn = (string)get_app_setting('dapodik_npsn', '');
        $dapodikUrl = 'http://127.0.0.1:5774';
        $dapodikToken = 'save-download-' . bin2hex(random_bytes(4));
        $npsn = 'SAVE' . bin2hex(random_bytes(3));

        try {
            $page = $client->get('/index.php?page=update-data');
            $csrf = $this->csrf($page);
            $response = $client->postForm('/index.php?page=update-data', [
                '_csrf' => $csrf,
                'action' => 'save_dapodik_settings',
                'return_page' => 'update-data',
                'url' => $dapodikUrl,
                'token' => $dapodikToken,
                'npsn' => $npsn,
                'download_after_save' => 'portable',
            ]);

            $this->assertStatus('dapodik save portable redirects', $response, 302);
            $location = $response->header('Location');
            $this->assertContains('dapodik save portable location', $location, 'dapodik_local_helper.php');
            parse_str((string)parse_url($location, PHP_URL_QUERY), $params);
            if (($params['dapodik_token'] ?? '') === $dapodikToken && ($params['npsn'] ?? '') === $npsn) {
                $this->pass('dapodik save portable uses submitted settings');
            } else {
                $this->fail('dapodik save portable uses submitted settings');
            }

            if ((string)get_app_setting('dapodik_token', '') === $dapodikToken && (string)get_app_setting('dapodik_npsn', '') === $npsn) {
                $this->pass('dapodik save portable persists settings');
            } else {
                $this->fail('dapodik save portable persists settings');
            }

            $download = $client->follow($response, 1);
            $this->assertStatus('dapodik save portable downloads', $download, 200);
            $this->assertContains('dapodik save portable content type', $download->header('Content-Type'), 'application/zip');
            if (!str_starts_with($download->body, "PK\x03\x04")) {
                $this->fail('dapodik save portable zip body');
                return;
            }
            $this->pass('dapodik save portable zip body');
        } finally {
            set_app_setting('dapodik_url', $oldUrl);
            set_app_setting('dapodik_token', $oldDapodikToken);
            set_app_setting('dapodik_npsn', $oldNpsn);
        }
    }

    private function testLessonScheduleMutation(): void
    {
        $client = $this->login('administrator', 'administrator', 'admin schedule login');
        $teacherId = $this->firstInt('SELECT id FROM teachers WHERE active = 1 ORDER BY id');
        $assignmentCount = $this->firstInt('SELECT COUNT(*) FROM teaching_assignments WHERE active = 1');
        if ($teacherId <= 0 || $assignmentCount <= 0) {
            $this->fail('schedule mutation skipped because sample teacher/assignment is missing');
            return;
        }

        $page = $client->get('/index.php?page=lesson-schedule');
        $csrf = $this->csrf($page);
        $requestSaved = $client->follow($client->postForm('/index.php?page=lesson-schedule', [
            '_csrf' => $csrf,
            'action' => 'save_schedule_request',
            'teacher_id' => $teacherId,
            'request_type' => 'prefer',
            'day_of_week' => 1,
            'start_period' => 1,
            'end_period' => 2,
            'active' => 'on',
            'note' => 'Smoke test preferensi jadwal',
        ]));
        $this->assertStatus('admin save schedule request', $requestSaved, 200);
        $this->assertContains('admin save schedule request flash', $requestSaved->body, 'Request jadwal guru tersimpan');

        $csrf = $this->csrf($requestSaved);
        $generated = $client->follow($client->postForm('/index.php?page=lesson-schedule', [
            '_csrf' => $csrf,
            'action' => 'generate_lesson_schedule',
            'days' => [1, 2, 3, 4, 5],
            'max_period' => 8,
            'periods_per_assignment' => 1,
            'keep_locked' => 'on',
        ]));
        $this->assertStatus('admin generate lesson schedule', $generated, 200);
        $this->assertContains('admin generate lesson schedule flash', $generated->body, 'Jadwal otomatis dibuat');

        $slotCount = $this->firstInt('SELECT COUNT(*) FROM lesson_schedules');
        if ($slotCount > 0) {
            $this->pass('lesson schedule rows created');
        } else {
            $this->fail('lesson schedule rows created');
        }

        $guru = $this->login('guru1', 'guru123', 'guru schedule login');
        $guruPage = $guru->get('/index.php?page=lesson-schedule');
        $guruCsrf = $this->csrf($guruPage);
        $denied = $guru->postForm('/index.php?page=lesson-schedule', [
            '_csrf' => $guruCsrf,
            'action' => 'generate_lesson_schedule',
            'days' => [1],
        ]);
        $this->assertStatus('guru generate lesson schedule denied', $denied, 403);
    }

    private function testGuruAccess(): void
    {
        $client = $this->login('guru1', 'guru123', 'guru login');
        $this->assertStatus('guru dashboard', $client->get('/index.php?page=dashboard'), 200);
        $this->assertStatus('guru grades allowed', $client->get('/index.php?page=grades'), 200);
        $this->assertStatus('guru lesson schedule allowed', $client->get('/index.php?page=lesson-schedule'), 200);
        $this->assertStatus('guru users denied', $client->get('/index.php?page=users'), 403);
        $this->assertStatus('guru backup denied', $client->get('/index.php?page=backup-restore'), 403);

        $allowedClass = $this->firstInt(
            "SELECT ta.class_id FROM teaching_assignments ta JOIN users u ON u.teacher_id = ta.teacher_id WHERE u.username = 'guru1' AND ta.active = 1 ORDER BY ta.class_id"
        );
        if ($allowedClass > 0) {
            $this->assertStatus('guru own class report allowed', $client->get('/index.php?page=reports&class_id=' . $allowedClass), 200);
        }

        $otherClass = $this->firstInt(
            "SELECT id FROM classes WHERE id NOT IN (SELECT ta.class_id FROM teaching_assignments ta JOIN users u ON u.teacher_id = ta.teacher_id WHERE u.username = 'guru1') ORDER BY id"
        );
        if ($otherClass > 0) {
            $this->assertStatus('guru other class report denied', $client->get('/index.php?page=reports&class_id=' . $otherClass), 403);
        }
    }

    private function testSiswaAccess(): void
    {
        $client = $this->login('siswa1001', 'siswa123', 'siswa login');
        $this->assertStatus('siswa dashboard', $client->get('/index.php?page=dashboard'), 200);
        $this->assertStatus('siswa progress allowed', $client->get('/index.php?page=student-progress'), 200);
        $this->assertStatus('siswa lesson schedule allowed', $client->get('/index.php?page=lesson-schedule'), 200);
        $this->assertStatus('siswa users denied', $client->get('/index.php?page=users'), 403);
        $this->assertStatus('siswa grades denied', $client->get('/index.php?page=grades'), 403);

        $ownStudent = $this->firstInt("SELECT student_id FROM users WHERE username = 'siswa1001'");
        if ($ownStudent > 0) {
            $ownPdf = $client->get('/index.php?page=rapor-download-student&student_id=' . $ownStudent);
            $this->assertStatus('siswa own rapor allowed', $ownPdf, 200);
            $this->assertContains('siswa own rapor is pdf', $ownPdf->header('Content-Type'), 'application/pdf');
        }

        $otherStudent = $this->firstInt("SELECT id FROM students WHERE id <> $ownStudent ORDER BY id");
        if ($otherStudent > 0) {
            $this->assertStatus('siswa other rapor denied', $client->get('/index.php?page=rapor-download-student&student_id=' . $otherStudent), 403);
        }
    }

    private function login(string $username, string $password, string $label): SmokeClient
    {
        $client = new SmokeClient($this->baseUrl);
        $login = $client->get('/index.php?page=login');
        $csrf = $this->csrf($login);
        $posted = $client->postForm('/index.php?page=login', [
            '_csrf' => $csrf,
            'username' => $username,
            'password' => $password,
        ]);
        $dashboard = $client->follow($posted);
        $this->assertStatus($label, $dashboard, 200);
        $this->assertContains($label . ' dashboard', $dashboard->body, 'Dashboard');
        return $client;
    }

    private function csrf(SmokeResponse $response): string
    {
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $response->body, $match)) {
            return $match[1];
        }
        $this->fail('CSRF token tidak ditemukan di ' . $response->url);
        return '';
    }

    private function firstInt(string $sql): int
    {
        try {
            $value = db()->query($sql)->fetchColumn();
            return $value === false ? 0 : (int)$value;
        } catch (Throwable $exception) {
            $this->fail('Query test gagal: ' . $exception->getMessage());
            return 0;
        }
    }

    private function assertStatus(string $label, SmokeResponse $response, int $expected): void
    {
        if ($response->status !== $expected) {
            $this->fail("$label expected HTTP $expected, got {$response->status} at {$response->url}");
            return;
        }
        $this->pass($label);
    }

    private function assertContains(string $label, string $haystack, string $needle): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->fail("$label missing [$needle]");
            return;
        }
        $this->pass($label);
    }

    private function assertHeader(string $label, SmokeResponse $response, string $header, string $expected): void
    {
        $actual = $response->header($header);
        if (strcasecmp($actual, $expected) !== 0) {
            $this->fail("$label expected $header: $expected, got [$actual]");
            return;
        }
        $this->pass($label);
    }

    private function assertHeaderPresent(string $label, SmokeResponse $response, string $header): void
    {
        if ($response->header($header) === '') {
            $this->fail("$label missing $header");
            return;
        }
        $this->pass($label);
    }

    private function pass(string $label): void
    {
        $this->passed++;
        echo "[OK] $label\n";
    }

    private function fail(string $label): void
    {
        $this->failed++;
        $this->failures[] = $label;
        echo "[FAIL] $label\n";
    }
}

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8000';
$withScheduleMutation = in_array('--with-schedule-mutation', $argv, true);
exit((new SmokeSuite($baseUrl, $withScheduleMutation))->run());
