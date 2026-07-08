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
        $teacherName = 'Smoke Auto Setup Guru ' . bin2hex(random_bytes(4));
        $teacherId = 0;

        try {
            set_app_setting('dapodik_token', '');
            set_app_setting('dapodik_npsn', '');

            $response = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'guru',
                    'npsn' => $npsn,
                    'token' => $dapodikToken,
                    'data' => [
                        [
                            'ptk_id' => 'auto-ptk-' . bin2hex(random_bytes(5)),
                            'nama' => $teacherName,
                            'nuptk' => 'AUT' . bin2hex(random_bytes(4)),
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

            $teacher = fetch_one('SELECT id FROM teachers WHERE name = ?', [$teacherName]);
            if (!$teacher) {
                $this->fail('dapodik bridge auto setup imported teacher');
                return;
            }

            $teacherId = (int)$teacher['id'];
            $this->pass('dapodik bridge auto setup imported teacher');
        } finally {
            if ($teacherId > 0) {
                execute_sql('DELETE FROM teachers WHERE id = ?', [$teacherId]);
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
        $bodyTokenTeacherName = 'Smoke Bridge Body Token Guru ' . bin2hex(random_bytes(4));
        $bodyTokenTeacherDapodikId = 'body-ptk-' . bin2hex(random_bytes(5));
        $teacherName = 'Smoke Dapodik Guru ' . bin2hex(random_bytes(4));
        $teacherDapodikId = 'ptk-' . bin2hex(random_bytes(6));
        $subjectName = 'Smoke Bridge Mapel ' . bin2hex(random_bytes(4));
        $subjectDapodikId = 'mapel-' . bin2hex(random_bytes(6));
        $rawSubjectName = 'Smoke Raw Mapel Tanpa Guru ' . bin2hex(random_bytes(4));
        $rawSubjectDapodikId = 'raw-mapel-' . bin2hex(random_bytes(6));
        $assignmentDapodikId = 'pb-' . bin2hex(random_bytes(6));
        $className = 'VII Smoke Rombel ' . bin2hex(random_bytes(4));
        $classDapodikId = 'rombel-' . bin2hex(random_bytes(6));
        $studentName = 'Smoke Anggota Rombel ' . bin2hex(random_bytes(4));
        $studentDapodikId = 'pd-' . bin2hex(random_bytes(6));
        $studentNisn = (string)random_int(1000000000, 9999999999);
        $extracurricularName = 'MTQ Smoke ' . bin2hex(random_bytes(4));
        $extracurricularDapodikId = 'ekskul-' . bin2hex(random_bytes(6));
        $bodyTokenTeacherId = 0;
        $teacherId = 0;
        $subjectId = 0;
        $assignmentId = 0;
        $classId = 0;
        $studentId = 0;
        $extraClassId = 0;
        $extracurricularId = 0;
        $extracurricularMemberId = 0;

        try {
            set_app_setting('dapodik_token', $dapodikToken);
            set_app_setting('dapodik_npsn', $npsn);

            $wrongNpsn = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'guru',
                    'npsn' => 'NPSN-SALAH',
                    'data' => [['nama' => $teacherName, 'ptk_id' => $teacherDapodikId]],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge rejects wrong npsn', $wrongNpsn, 403);
            $this->assertContains('dapodik bridge wrong npsn message', $wrongNpsn->body, 'Token sinkron');

            $bodyTokenResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'guru',
                    'npsn' => $npsn,
                    'token' => $dapodikToken,
                    'data' => [
                        [
                            'nama' => $bodyTokenTeacherName,
                            'ptk_id' => $bodyTokenTeacherDapodikId,
                            'nuptk' => 'BOD' . bin2hex(random_bytes(4)),
                        ],
                    ],
                ],
                ['X-Eraport-Token: wrong-header-token']
            );
            $this->assertStatus('dapodik bridge accepts body token fallback', $bodyTokenResponse, 200);
            $bodyTokenTeacher = fetch_one('SELECT id FROM teachers WHERE name = ?', [$bodyTokenTeacherName]);
            if (!$bodyTokenTeacher) {
                $this->fail('dapodik bridge imported body token teacher');
                return;
            }
            $bodyTokenTeacherId = (int)$bodyTokenTeacher['id'];
            $this->pass('dapodik bridge imported body token teacher');

            $response = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'guru',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'ptk_id' => $teacherDapodikId,
                            'nama' => $teacherName,
                            'nuptk' => 'NUP' . bin2hex(random_bytes(4)),
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );

            $this->assertStatus('dapodik bridge accepts dapodik token', $response, 200);
            $this->assertContains('dapodik bridge valid json ok', $response->body, '"ok":true');
            $this->assertContains('dapodik bridge valid import count', $response->body, '"count":1');

            $teacher = fetch_one('SELECT id FROM teachers WHERE dapodik_id = ?', [$teacherDapodikId]);
            if (!$teacher) {
                $this->fail('dapodik bridge imported teacher');
                return;
            }

            $teacherId = (int)$teacher['id'];
            $this->pass('dapodik bridge imported teacher');

            $duplicateTeacher = $client->postJson('/dapodik_bridge.php', [
                'type' => 'guru',
                'npsn' => $npsn,
                'data' => [[
                    'ptk_id' => $teacherDapodikId,
                    'nama' => $teacherName,
                    'nuptk' => 'NUP' . bin2hex(random_bytes(4)),
                ]],
            ], ['X-Eraport-Token: ' . $dapodikToken]);
            $this->assertStatus('dapodik bridge duplicate teacher update ok', $duplicateTeacher, 200);
            $teacherCount = (int)(fetch_one('SELECT COUNT(*) AS c FROM teachers WHERE dapodik_id = ?', [$teacherDapodikId])['c'] ?? 0);
            if ($teacherCount === 1) {
                $this->pass('dapodik bridge duplicate teacher not duplicated');
            } else {
                $this->fail('dapodik bridge duplicate teacher not duplicated');
            }

            $rombelResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'rombel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'rombongan_belajar_id' => $classDapodikId,
                            'nama' => $className,
                            'tingkat_pendidikan_id' => '7',
                            'nama_jurusan_sp' => 'Reguler',
                            'semester_id' => '20251',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge imports rombel', $rombelResponse, 200);
            $this->assertContains('dapodik bridge rombel count', $rombelResponse->body, '"count":1');

            $class = fetch_one('SELECT id, grade, major, academic_year FROM classes WHERE name = ?', [$className]);
            if (!$class) {
                $this->fail('dapodik bridge rombel created class');
                return;
            }
            $classId = (int)$class['id'];
            $this->pass('dapodik bridge rombel created class');
            if ((string)$class['grade'] === '7' && (string)$class['major'] === 'Reguler' && (string)$class['academic_year'] === '2025/2026') {
                $this->pass('dapodik bridge rombel mapped class fields');
            } else {
                $this->fail('dapodik bridge rombel mapped class fields');
            }

            execute_sql(
                'INSERT INTO classes (name, grade, major, academic_year, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)',
                [$extracurricularName, '0', '', '2025/2026', now_string()]
            );
            $extraClassId = (int)db()->lastInsertId();
            $extraResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'rombel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'rombongan_belajar_id' => $extracurricularDapodikId,
                            'nama' => $extracurricularName,
                            'jenis_rombel' => 'Ekstrakurikuler',
                            'tingkat_pendidikan_id' => '0',
                            'semester_id' => '20251',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge skips extracurricular rombel', $extraResponse, 200);
            $this->assertContains('dapodik bridge extracurricular count zero', $extraResponse->body, '"count":0');
            if (!fetch_one('SELECT id FROM classes WHERE id = ?', [$extraClassId])) {
                $this->pass('dapodik bridge removes extracurricular from classes');
            } else {
                $this->fail('dapodik bridge removes extracurricular from classes');
            }
            if (!fetch_one('SELECT id FROM extracurriculars WHERE dapodik_id = ?', [$extracurricularDapodikId])) {
                $this->pass('dapodik bridge waits for extracurricular member');
            } else {
                $this->fail('dapodik bridge waits for extracurricular member');
            }

            $studentResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'siswa',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'peserta_didik_id' => $studentDapodikId,
                            'nisn' => $studentNisn,
                            'nama' => $studentName,
                            'jenis_kelamin' => 'L',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge imports student before membership', $studentResponse, 200);
            $student = fetch_one('SELECT id, class_id FROM students WHERE nisn = ?', [$studentNisn]);
            if (!$student) {
                $this->fail('dapodik bridge imported membership student');
                return;
            }
            $studentId = (int)$student['id'];
            $this->pass('dapodik bridge imported membership student');

            $memberResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'anggota_rombel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'rombongan_belajar_id' => $classDapodikId,
                            'peserta_didik_id' => $studentDapodikId,
                            'nisn' => $studentNisn,
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge imports anggota rombel', $memberResponse, 200);
            $this->assertContains('dapodik bridge anggota rombel count', $memberResponse->body, '"count":1');
            $mappedStudent = fetch_one('SELECT class_id FROM students WHERE id = ?', [$studentId]);
            if ((int)($mappedStudent['class_id'] ?? 0) === $classId) {
                $this->pass('dapodik bridge anggota rombel mapped student class');
            } else {
                $this->fail('dapodik bridge anggota rombel mapped student class');
            }

            $rawMapelResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'mapel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'mata_pelajaran_id' => $rawSubjectDapodikId,
                            'nama_mata_pelajaran' => $rawSubjectName,
                            'kode_mata_pelajaran' => 'RAW',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge skips raw mapel without teacher', $rawMapelResponse, 200);
            $this->assertContains('dapodik bridge raw mapel count zero', $rawMapelResponse->body, '"count":0');
            if (!fetch_one('SELECT id FROM subjects WHERE dapodik_id = ?', [$rawSubjectDapodikId])) {
                $this->pass('dapodik bridge raw mapel without teacher not imported');
            } else {
                $this->fail('dapodik bridge raw mapel without teacher not imported');
            }

            $learningResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'pembelajaran',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'pembelajaran_id' => $assignmentDapodikId,
                            'ptk_id' => $teacherDapodikId,
                            'rombongan_belajar_id' => $classDapodikId,
                            'mata_pelajaran_id' => $subjectDapodikId,
                            'nama_mata_pelajaran' => $subjectName,
                            'kode_mata_pelajaran' => 'SMK',
                            'semester_id' => '20251',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge imports pembelajaran', $learningResponse, 200);
            $this->assertContains('dapodik bridge pembelajaran count', $learningResponse->body, '"count":1');
            $subject = fetch_one('SELECT id FROM subjects WHERE dapodik_id = ?', [$subjectDapodikId]);
            if (!$subject) {
                $this->fail('dapodik bridge pembelajaran created subject');
                return;
            }
            $subjectId = (int)$subject['id'];
            $this->pass('dapodik bridge pembelajaran created subject');
            $assignment = fetch_one('SELECT id FROM teaching_assignments WHERE dapodik_id = ?', [$assignmentDapodikId]);
            if (!$assignment) {
                $this->fail('dapodik bridge pembelajaran created assignment');
                return;
            }
            $assignmentId = (int)$assignment['id'];
            $this->pass('dapodik bridge pembelajaran created assignment');

            $duplicateLearning = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'pembelajaran',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'pembelajaran_id' => $assignmentDapodikId,
                            'ptk_id' => $teacherDapodikId,
                            'rombongan_belajar_id' => $classDapodikId,
                            'mata_pelajaran_id' => $subjectDapodikId,
                            'nama_mata_pelajaran' => $subjectName,
                            'kode_mata_pelajaran' => 'SMK',
                            'semester_id' => '20251',
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge duplicate pembelajaran update ok', $duplicateLearning, 200);
            $assignmentCount = (int)(fetch_one('SELECT COUNT(*) AS c FROM teaching_assignments WHERE dapodik_id = ?', [$assignmentDapodikId])['c'] ?? 0);
            $subjectCount = (int)(fetch_one('SELECT COUNT(*) AS c FROM subjects WHERE dapodik_id = ?', [$subjectDapodikId])['c'] ?? 0);
            if ($assignmentCount === 1 && $subjectCount === 1) {
                $this->pass('dapodik bridge duplicate pembelajaran not duplicated');
            } else {
                $this->fail('dapodik bridge duplicate pembelajaran not duplicated');
            }

            $extraMemberResponse = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'anggota_rombel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'rombongan_belajar_id' => $extracurricularDapodikId,
                            'peserta_didik_id' => $studentDapodikId,
                            'nisn' => $studentNisn,
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge imports extracurricular member', $extraMemberResponse, 200);
            $this->assertContains('dapodik bridge extracurricular member count', $extraMemberResponse->body, '"count":1');
            $extracurricular = fetch_one('SELECT id FROM extracurriculars WHERE dapodik_id = ?', [$extracurricularDapodikId]);
            if (!$extracurricular) {
                $this->fail('dapodik bridge creates extracurricular only with member');
                return;
            }
            $extracurricularId = (int)$extracurricular['id'];
            $member = fetch_one('SELECT id FROM extracurricular_members WHERE extracurricular_id = ? AND student_id = ?', [$extracurricularId, $studentId]);
            if (!$member) {
                $this->fail('dapodik bridge stores extracurricular member');
                return;
            }
            $extracurricularMemberId = (int)$member['id'];
            $this->pass('dapodik bridge creates extracurricular only with member');
            $this->pass('dapodik bridge stores extracurricular member');

            $duplicateExtraMember = $client->postJson(
                '/dapodik_bridge.php',
                [
                    'type' => 'anggota_rombel',
                    'npsn' => $npsn,
                    'data' => [
                        [
                            'rombongan_belajar_id' => $extracurricularDapodikId,
                            'peserta_didik_id' => $studentDapodikId,
                            'nisn' => $studentNisn,
                        ],
                    ],
                ],
                ['X-Eraport-Token: ' . $dapodikToken]
            );
            $this->assertStatus('dapodik bridge duplicate extracurricular member update ok', $duplicateExtraMember, 200);
            $extraMemberCount = (int)(fetch_one('SELECT COUNT(*) AS c FROM extracurricular_members WHERE extracurricular_id = ? AND student_id = ?', [$extracurricularId, $studentId])['c'] ?? 0);
            if ($extraMemberCount === 1) {
                $this->pass('dapodik bridge duplicate extracurricular member not duplicated');
            } else {
                $this->fail('dapodik bridge duplicate extracurricular member not duplicated');
            }
        } finally {
            if ($extracurricularMemberId > 0) {
                execute_sql('DELETE FROM extracurricular_members WHERE id = ?', [$extracurricularMemberId]);
            }
            if ($extracurricularId > 0) {
                execute_sql('DELETE FROM extracurricular_members WHERE extracurricular_id = ?', [$extracurricularId]);
                execute_sql('DELETE FROM extracurriculars WHERE id = ?', [$extracurricularId]);
            }
            if ($assignmentId > 0) {
                execute_sql('DELETE FROM teaching_assignments WHERE id = ?', [$assignmentId]);
            }
            if ($subjectId > 0) {
                execute_sql('DELETE FROM subjects WHERE id = ?', [$subjectId]);
            }
            if ($studentId > 0) {
                execute_sql('DELETE FROM students WHERE id = ?', [$studentId]);
            }
            if ($classId > 0) {
                execute_sql('DELETE FROM classes WHERE id = ?', [$classId]);
            }
            if ($extraClassId > 0) {
                execute_sql('DELETE FROM classes WHERE id = ?', [$extraClassId]);
            }
            if ($teacherId > 0) {
                execute_sql('DELETE FROM teachers WHERE id = ?', [$teacherId]);
            }
            if ($bodyTokenTeacherId > 0) {
                execute_sql('DELETE FROM teachers WHERE id = ?', [$bodyTokenTeacherId]);
            }
            execute_sql('DELETE FROM dapodik_rombel_cache WHERE dapodik_id = ?', [$classDapodikId]);
            execute_sql('DELETE FROM dapodik_rombel_cache WHERE dapodik_id = ?', [$extracurricularDapodikId]);
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
        $sampleUser = fetch_one("SELECT id FROM users WHERE username = 'siswa1001' AND active = 1");
        if (!$sampleUser) {
            $this->pass('siswa access skipped because sample siswa1001 user is missing');
            return;
        }

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
