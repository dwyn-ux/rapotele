<?php
/**
 * Smoke test: scope guru sesuai mapel & kelasnya sendiri.
 * Cara jalan: php tests/teacher_scope_test.php
 *
 * Strategi: bootstrap app, login sebagai guru A, panggil helper/query,
 *         verifikasi hasil. Tidak pakai HTTP; pakai DB existing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Jalankan via CLI: php tests/teacher_scope_test.php\n");
    exit(2);
}

putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . dirname(__DIR__) . '/storage/eraport_test.sqlite');
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = dirname(__DIR__) . '/storage/eraport_test.sqlite';
$_SERVER['DB_DRIVER'] = 'sqlite';
$_SERVER['DB_DATABASE'] = dirname(__DIR__) . '/storage/eraport_test.sqlite';

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Services/telegram.php';
require_once __DIR__ . '/../app/Services/schedule.php';
require_once __DIR__ . '/../app/Services/pdf.php';
require_once __DIR__ . '/../app/Services/whatsapp.php';
require_once __DIR__ . '/../app/Actions/master.php';
require_once __DIR__ . '/../app/Actions/assessment.php';
require_once __DIR__ . '/../app/Actions/settings.php';
require_once __DIR__ . '/../app/Pages/render.php';
require_once __DIR__ . '/../app/Pages/helpers.php';
require_once __DIR__ . '/../app/Pages/master.php';
require_once __DIR__ . '/../app/Pages/assessment.php';
require_once __DIR__ . '/../app/Pages/kokurikuler.php';

$pass = 0;
$fail = 0;
$log = [];

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail, $log;
    if ($cond) {
        $pass++;
        $log[] = "  [PASS] $name";
    } else {
        $fail++;
        $log[] = "  [FAIL] $name" . ($detail !== '' ? " :: $detail" : '');
    }
}

function section(string $name): void
{
    global $log;
    $log[] = "\n== $name ==";
}

function run_as(int $userId, callable $fn): mixed
{
    $_SESSION['user_id'] = $userId;
    $GLOBALS['__session_dirty'] = true;
    return $fn();
}

/**
 * Jalankan callable di subprocess. Return ['ok' => bool, 'output' => string, 'exit' => int].
 * Dipakai untuk test yang trigger exit() di helper (require_*).
 */
function run_subprocess(string $phpCode, array $env = []): array
{
    $script = "<?php\ndeclare(strict_types=1);\nrequire_once " . var_export(__DIR__ . '/../app/bootstrap.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Actions/master.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Actions/assessment.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Actions/settings.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Pages/render.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Pages/helpers.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Pages/master.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Pages/assessment.php', true) . ";\n"
        . "require_once " . var_export(__DIR__ . '/../app/Pages/kokurikuler.php', true) . ";\n"
        . $phpCode . "\n";

    $tmp = tempnam(sys_get_temp_dir(), 'tscope_');
    file_put_contents($tmp, $script);

    $baseEnv = [
        'PATH' => getenv('PATH') ?: '',
        'SystemRoot' => getenv('SystemRoot') ?: '',
        'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
        'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
        'DB_DRIVER' => 'sqlite',
        'DB_DATABASE' => dirname(__DIR__) . '/storage/eraport_test.sqlite',
    ];
    foreach ($env as $k => $v) {
        $baseEnv[$k] = (string)$v;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open([PHP_BINARY, '-d', 'display_errors=1', $tmp], $descriptors, $pipes, dirname($tmp), $baseEnv);
    if (!is_resource($proc)) {
        unlink($tmp);
        return ['ok' => false, 'output' => 'proc_open failed', 'exit' => -1];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    unlink($tmp);
    $combined = (string)$stdout . (string)$stderr;
    return ['ok' => $exit !== 0, 'output' => $combined, 'exit' => $exit];
}

function run_subprocess_expect_denied(string $name, string $phpCode, array $env = []): void
{
    $r = run_subprocess($phpCode, $env);
    ok($name, $r['exit'] !== 0, 'subprocess exit=' . $r['exit'] . ' output=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));
}

function find_or_create_user(string $username, string $role, ?int $teacherId): int
{
    $u = fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
    if ($u) {
        execute_sql('UPDATE users SET role = ?, teacher_id = ?, active = 1 WHERE id = ?', [$role, $teacherId, (int)$u['id']]);
        return (int)$u['id'];
    }
    execute_sql(
        'INSERT INTO users (username, password_hash, name, role, teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
        [$username, password_hash('test', PASSWORD_BCRYPT), $username, $role, $teacherId, now_string(), now_string()]
    );
    return (int)db()->lastInsertId();
}

function ensure_teacher(string $name, string $nip): int
{
    $t = fetch_one('SELECT id FROM teachers WHERE nip = ?', [$nip]);
    if ($t) {
        return (int)$t['id'];
    }
    execute_sql(
        'INSERT INTO teachers (nip, name, active, updated_at) VALUES (?, ?, 1, ?)',
        [$nip, $name, now_string()]
    );
    return (int)db()->lastInsertId();
}

function ensure_class(string $name, string $grade): int
{
    $c = fetch_one('SELECT id FROM classes WHERE name = ?', [$name]);
    if ($c) {
        return (int)$c['id'];
    }
    execute_sql(
        'INSERT INTO classes (name, grade, academic_year, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$name, $grade, '2025/2026', now_string()]
    );
    return (int)db()->lastInsertId();
}

function ensure_subject(string $shortName, string $name): int
{
    $s = fetch_one('SELECT id FROM subjects WHERE short_name = ?', [$shortName]);
    if ($s) {
        return (int)$s['id'];
    }
    execute_sql(
        'INSERT INTO subjects (short_name, name, active, updated_at) VALUES (?, ?, 1, ?)',
        [$shortName, $name, now_string()]
    );
    return (int)db()->lastInsertId();
}

function ensure_assignment(int $teacherId, int $classId, int $subjectId): void
{
    $a = fetch_one(
        'SELECT id FROM teaching_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?',
        [$teacherId, $classId, $subjectId]
    );
    if ($a) {
        return;
    }
    execute_sql(
        'INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
        [$teacherId, $classId, $subjectId, '2025/2026', 'Ganjil', now_string()]
    );
}

function ensure_ekskul(string $name, ?int $teacherId): int
{
    $e = fetch_one('SELECT id FROM extracurriculars WHERE name = ?', [$name]);
    if ($e) {
        execute_sql('UPDATE extracurriculars SET teacher_id = ? WHERE id = ?', [$teacherId, (int)$e['id']]);
        return (int)$e['id'];
    }
    execute_sql(
        'INSERT INTO extracurriculars (name, class_name, teacher_id, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$name, '-', $teacherId, now_string()]
    );
    return (int)db()->lastInsertId();
}

function ensure_student(int $classId, string $name): int
{
    $s = fetch_one('SELECT id FROM students WHERE name = ? AND class_id = ?', [$name, $classId]);
    if ($s) {
        return (int)$s['id'];
    }
    execute_sql(
        'INSERT INTO students (name, class_id, active, updated_at) VALUES (?, ?, 1, ?)',
        [$name, $classId, now_string()]
    );
    return (int)db()->lastInsertId();
}

try {
    section('Seed fixture');
    $teacherA = ensure_teacher('Guru A Test', 'TSTA');
    $teacherB = ensure_teacher('Guru B Test', 'TSTB');
    $class7A  = ensure_class('7A Test', '7');
    $class7B  = ensure_class('7B Test', '7');
    $subjMTK  = ensure_subject('MTK', 'Matematika');
    $subjBHS  = ensure_subject('BHS', 'Bahasa Indonesia');
    ensure_assignment($teacherA, $class7A, $subjMTK);
    ensure_assignment($teacherB, $class7A, $subjBHS);
    ensure_assignment($teacherA, $class7B, $subjMTK);
    $ekskulPramuka = ensure_ekskul('Pramuka Test', $teacherA);
    $ekskulPMR     = ensure_ekskul('PMR Test', $teacherB);
    $student1 = ensure_student($class7A, 'Siswa Test 1');

    $userA = find_or_create_user('test_guru_a', 'guru', $teacherA);
    $userB = find_or_create_user('test_guru_b', 'guru', $teacherB);
    ok('seed guru A & B', $userA > 0 && $userB > 0);
    ok('seed kelas 7A & 7B', $class7A > 0 && $class7B > 0);
    ok('seed mapel MTK & BHS', $subjMTK > 0 && $subjBHS > 0);
    ok('seed ekskul', $ekskulPramuka > 0 && $ekskulPMR > 0);

    section('Helper: require_subject_access');
    $r = run_subprocess(
        "\$_SESSION['user_id'] = (int)getenv('USER_ID');\n"
        . "try { require_subject_access((int)getenv('SUBJECT_OK'), (int)getenv('CLASS_ID')); echo 'OK_ALLOWED'; }\n"
        . "catch (Throwable \$e) { echo 'THROWN:'.\$e->getMessage(); }\n"
        . "echo '__EXIT__=0';\n",
        ['USER_ID' => (string)$userA, 'SUBJECT_OK' => (string)$subjMTK, 'CLASS_ID' => (string)$class7A]
    );
    ok('guru A boleh akses MTK di 7A', str_contains($r['output'], 'OK_ALLOWED'), 'out=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));

    $r = run_subprocess(
        "\$_SESSION['user_id'] = (int)getenv('USER_ID');\n"
        . "try { require_subject_access((int)getenv('SUBJECT_DENY'), (int)getenv('CLASS_ID')); echo 'NOT_DENIED'; }\n"
        . "catch (Throwable \$e) { echo 'THROWN:'.\$e->getMessage(); }\n"
        . "echo '__EXIT__=0';\n",
        ['USER_ID' => (string)$userA, 'SUBJECT_DENY' => (string)$subjBHS, 'CLASS_ID' => (string)$class7A]
    );
    ok('guru A ditolak akses BHS di 7A (helper)', str_contains($r['output'], 'ACCESS DENIED') || str_contains($r['output'], 'tidak mengajar'), 'out=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));

    section('Helper: subjects_for_current_user_in_class');
    $subjects = run_as($userA, fn() => subjects_for_current_user_in_class($class7A));
    $ids = array_map(fn($r) => (int)$r['id'], $subjects);
    ok('guru A di 7A: hanya MTK', $ids === [$subjMTK], 'got: ' . json_encode($ids));

    $subjects = run_as($userB, fn() => subjects_for_current_user_in_class($class7A));
    $ids = array_map(fn($r) => (int)$r['id'], $subjects);
    ok('guru B di 7A: hanya BHS', $ids === [$subjBHS], 'got: ' . json_encode($ids));

    section('data-tp: list guru A tidak muncul TP subject lain');
    $tpBhsId = (int)db()->lastInsertId();
    execute_sql(
        'INSERT INTO learning_objectives (subject_id, grade, description, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$subjBHS, '7', 'TP BHS test', now_string()]
    );
    $tpBhsId = (int)db()->lastInsertId();
    execute_sql(
        'INSERT INTO learning_objectives (subject_id, grade, description, active, updated_at) VALUES (?, ?, ?, 1, ?)',
        [$subjMTK, '7', 'TP MTK test', now_string()]
    );
    $tpMtkId = (int)db()->lastInsertId();

    $rows = run_as($userA, function () {
        $teacherId = (int)(current_user()['teacher_id'] ?? 0);
        return fetch_all(
            'SELECT lo.* FROM learning_objectives lo
             JOIN teaching_assignments ta ON ta.subject_id = lo.subject_id AND ta.teacher_id = ? AND ta.active = 1
             ORDER BY lo.id DESC',
            [$teacherId]
        );
    });
    $subjectIds = array_map(fn($r) => (int)$r['subject_id'], $rows);
    ok('data-tp guru A: tidak ada BHS', !in_array($subjBHS, $subjectIds, true), 'got: ' . json_encode($subjectIds));
    ok('data-tp guru A: ada MTK', in_array($subjMTK, $subjectIds, true));

    section('action_save_learning_objective: guru A tidak bisa simpan TP BHS');
    $r = run_subprocess(
        "\$_SESSION['user_id'] = (int)getenv('USER_ID');\n"
        . "\$_POST = ['id'=>'0', 'subject_id'=>getenv('SUBJECT_DENY'), 'grade'=>'7', 'description'=>'attempt cross-subject', 'active'=>'1'];\n"
        . "ob_start();\n"
        . "try { action_save_learning_objective(); echo 'NOT_DENIED'; }\n"
        . "catch (Throwable \$e) { echo 'DENIED:'.\$e->getMessage(); }\n"
        . "ob_end_clean();\n"
        . "echo '__EXIT__=0';\n",
        ['USER_ID' => (string)$userA, 'SUBJECT_DENY' => (string)$subjBHS]
    );
    ok('action TP ditolak (no row created)', !str_contains($r['output'], 'NOT_DENIED'), 'out=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));

    $tpCountForBhs = (int)fetch_one('SELECT COUNT(*) AS c FROM learning_objectives WHERE subject_id = ? AND description = ?', [$subjBHS, 'attempt cross-subject'])['c'];
    ok('TP BHS tidak terbuat', $tpCountForBhs === 0, "got count=$tpCountForBhs");

    section('action_save_deskripsi_nilai: guru A tidak bisa isi deskripsi BHS');
    $descMarker = 'deskripsi liar marker ' . bin2hex(random_bytes(4));
    $r = run_subprocess(
        "\$_SESSION['user_id'] = (int)getenv('USER_ID');\n"
        . "\$_POST = ['student_id'=>getenv('STUDENT'), 'class_id'=>getenv('CLASS'), 'grade'=>'7', 'desc'=>[getenv('SUBJECT_DENY')=>getenv('MARKER')]];\n"
        . "ob_start();\n"
        . "try { action_save_deskripsi_nilai(); echo 'NOT_DENIED'; }\n"
        . "catch (Throwable \$e) { echo 'DENIED:'.\$e->getMessage(); }\n"
        . "ob_end_clean();\n"
        . "echo '__EXIT__=0';\n",
        ['USER_ID' => (string)$userA, 'STUDENT' => (string)$student1, 'CLASS' => (string)$class7A, 'SUBJECT_DENY' => (string)$subjBHS, 'MARKER' => $descMarker]
    );
    ok('action deskripsi ditolak (marker ' . $descMarker . ')', str_contains($r['output'], 'ACCESS DENIED') || str_contains($r['output'], 'tidak mengajar'), 'out=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));

    $descCount = (int)fetch_one('SELECT COUNT(*) AS c FROM student_descriptions WHERE student_id = ? AND subject_id = ? AND description = ?', [$student1, $subjBHS, $descMarker])['c'];
    ok('deskripsi BHS tidak terbuat (marker spesifik)', $descCount === 0, "got count=$descCount");

    section('action_save_extracurricular_scores: guru A tidak bisa isi nilai PMR');
    $r = run_subprocess(
        "\$_SESSION['user_id'] = (int)getenv('USER_ID');\n"
        . "\$_POST = ['class_id'=>'0', 'scores'=>[getenv('STUDENT')=>[getenv('EKSKUL_PMR')=>'85']]];\n"
        . "ob_start();\n"
        . "try { action_save_extracurricular_scores(); echo 'NOT_DENIED'; }\n"
        . "catch (Throwable \$e) { echo 'DENIED:'.\$e->getMessage(); }\n"
        . "ob_end_clean();\n"
        . "echo '__EXIT__=0';\n",
        ['USER_ID' => (string)$userA, 'STUDENT' => (string)$student1, 'EKSKUL_PMR' => (string)$ekskulPMR]
    );
    ok('action ekskul ditolak (no row created)', !str_contains($r['output'], 'NOT_DENIED'), 'out=' . substr(preg_replace('/\s+/', ' ', $r['output']), 0, 200));

    $scoreCount = (int)fetch_one('SELECT COUNT(*) AS c FROM extracurricular_scores WHERE student_id = ? AND extracurricular_id = ?', [$student1, $ekskulPMR])['c'];
    ok('nilai PMR tidak terbuat', $scoreCount === 0, "got count=$scoreCount");

    section('page_data_tp: guru A hanya lihat subject MTK di form');
    $formSubjects = run_as($userA, fn() => subjects_for_current_user());
    $formSubjectIds = array_map(fn($r) => (int)$r['id'], $formSubjects);
    ok('dropdown subject: tidak ada BHS', !in_array($subjBHS, $formSubjectIds, true), 'got: ' . json_encode($formSubjectIds));
    ok('dropdown subject: ada MTK', in_array($subjMTK, $formSubjectIds, true));

    section('page_status_penilaian: query filter guru A');
    $rows = run_as($userA, function () use ($class7A) {
        $teacherId = (int)(current_user()['teacher_id'] ?? 0);
        return fetch_all(
            'SELECT ta.*, s.name AS subject_name
             FROM teaching_assignments ta JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.class_id = ? AND ta.teacher_id = ?
             ORDER BY s.name',
            [$class7A, $teacherId]
        );
    });
    $rowSubjectIds = array_map(fn($r) => (int)$r['subject_id'], $rows);
    ok('status-penilaian guru A: tidak ada BHS', !in_array($subjBHS, $rowSubjectIds, true), 'got: ' . json_encode($rowSubjectIds));

    section('page_input_nilai_ekskul: query filter guru A');
    $ekskuls = run_as($userA, function () {
        $teacherId = (int)(current_user()['teacher_id'] ?? 0);
        return fetch_all('SELECT * FROM extracurriculars WHERE active = 1 AND teacher_id = ? ORDER BY name', [$teacherId]);
    });
    $ekskulIds = array_map(fn($r) => (int)$r['id'], $ekskuls);
    ok('ekskul guru A: hanya Pramuka', $ekskulIds === [$ekskulPramuka], 'got: ' . json_encode($ekskulIds));
} catch (Throwable $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $fail++;
    $log[] = "  [FATAL] " . $e->getMessage();
}

echo implode("\n", $log) . "\n\n";
echo "TOTAL: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
