<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$base = dirname(__DIR__);
$DB = $base . '/storage/eraport_test.sqlite';

if (!is_file($DB)) {
    fwrite(STDERR, "Missing test DB. Run: cp storage/eraport.sqlite storage/eraport_test.sqlite\n");
    exit(2);
}

putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . $DB);
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = $DB;
$_SERVER['DB_DRIVER'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $DB;

require_once $base . '/app/bootstrap.php';
foreach (glob($base . '/app/Services/*.php') as $f) require_once $f;
foreach (glob($base . '/app/Actions/*.php') as $f) require_once $f;
foreach (glob($base . '/app/Pages/*.php') as $f) require_once $f;

$pass = 0;
$fail = 0;
$log = [];

function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail, $log;
    if ($cond) { $pass++; $log[] = "  [PASS] $name"; }
    else { $fail++; $log[] = "  [FAIL] $name" . ($detail !== '' ? " :: $detail" : ''); }
}

function section(string $name): void {
    global $log;
    $log[] = "\n== $name ==";
}

$admin = fetch_one("SELECT id FROM users WHERE role = ? LIMIT 1", ['admin']);
$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['_csrf'] = 'tok';

$teacher = fetch_one("SELECT id, name FROM teachers WHERE active = 1 ORDER BY id LIMIT 1");
$class = fetch_one("SELECT id, name, level FROM classes WHERE active = 1 ORDER BY id LIMIT 1");
$subject = fetch_one("SELECT id, name, level FROM subjects WHERE active = 1 ORDER BY id LIMIT 1");

section('fixtures');
ok('admin login', $admin !== null);
ok('teacher exists', $teacher !== null);
ok('class exists', $class !== null);
ok('subject exists', $subject !== null);

section('Page render: Data Pembelajaran (page_assignments)');
ob_start();
try {
    page_assignments();
    $render = ob_get_clean();
    ok('page_assignments renders without exception', true);
    ok('contains "Data Pembelajaran" header', str_contains($render, 'Data Pembelajaran') || str_contains($render, 'Input Pembelajaran') || str_contains($render, 'Tambah Pembelajaran'));
    ok('contains Guru dropdown', str_contains($render, 'name="teacher_id"'));
    ok('contains Kelas dropdown', str_contains($render, 'name="class_id"'));
    ok('contains Mapel dropdown', str_contains($render, 'name="subject_id"'));
    ok('contains Jenjang filter', str_contains($render, 'id="asg-level"'));
    ok('contains action save_assignment', str_contains($render, 'save_assignment'));
} catch (Throwable $e) {
    ob_end_clean();
    ok('page_assignments renders without exception', false, get_class($e) . ' :: ' . $e->getMessage());
}

section('Subprocess test: save_assignment cases');

function runSubprocess(string $script, string $label, array $post): string {
    global $base, $DB;
    $env = ['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => $DB];
    $args = [PHP_BINARY, '-d', 'display_errors=1', $script, $label, json_encode($post)];
    $proc = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname($script), $env);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return (string)$out;
}

$scriptAssign = $base . '/tests/_case_assign.php';
file_put_contents($scriptAssign, <<<'PHP'
<?php
declare(strict_types=1);
$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . $root . '/storage/eraport_test.sqlite');
$_ENV['DB_DRIVER'] = 'sqlite'; $_ENV['DB_DATABASE'] = $root . '/storage/eraport_test.sqlite';
$_SERVER['DB_DRIVER'] = 'sqlite'; $_SERVER['DB_DATABASE'] = $root . '/storage/eraport_test.sqlite';
require_once $root . '/app/bootstrap.php';
foreach (glob($root . '/app/Services/*.php') as $f) require_once $f;
foreach (glob($root . '/app/Actions/*.php') as $f) require_once $f;
foreach (glob($root . '/app/Pages/*.php') as $f) require_once $f;
$admin = fetch_one("SELECT id FROM users WHERE role = ? LIMIT 1", ['admin']);
$_SESSION['user_id'] = (int)$admin['id'];
$_SESSION['_csrf'] = 'tok';
$post = json_decode($argv[2] ?? '{}', true) ?: [];
$_POST = ['_csrf' => 'tok', 'action' => 'save_assignment', 'id' => '0'] + $post;
$_SESSION['_flash'] = [];
register_shutdown_function(function () {
    $f = $_SESSION['_flash'] ?? [];
    foreach ($f as $x) echo "FLASH {$x['type']}: {$x['message']}\n";
});
ob_start();
try { action_save_assignment(); } catch (Throwable $e) { echo "EXC: " . $e->getMessage() . "\n"; }
ob_end_clean();
echo "LABEL=" . ($argv[1] ?? '') . "\n";
PHP);

$cases = [
    ['kosong', [], 'danger', 'wajib dipilih'],
    ['teacher 0', ['teacher_id' => '0', 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'wajib dipilih'],
    ['class 0', ['teacher_id' => (string)$teacher['id'], 'class_id' => '0', 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'wajib dipilih'],
    ['subject 0', ['teacher_id' => (string)$teacher['id'], 'class_id' => (string)$class['id'], 'subject_id' => '0', 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'wajib dipilih'],
    ['teacher FK invalid', ['teacher_id' => '99999', 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'tidak ditemukan'],
    ['class FK invalid', ['teacher_id' => (string)$teacher['id'], 'class_id' => '99999', 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'tidak ditemukan'],
    ['subject FK invalid', ['teacher_id' => (string)$teacher['id'], 'class_id' => (string)$class['id'], 'subject_id' => '99999', 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger', 'tidak ditemukan'],
    ['akademik kosong', ['teacher_id' => (string)$teacher['id'], 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '', 'semester' => 'Ganjil'], 'danger', 'Tahun ajaran'],
    ['full valid', ['teacher_id' => (string)$teacher['id'], 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'success', 'tersimpan'],
];

foreach ($cases as [$label, $post, $expectType, $expectMsgPart]) {
    $out = runSubprocess($scriptAssign, $label, $post);
    $hasExpectedType = str_contains($out, "FLASH $expectType");
    $hasExpectedMsg = str_contains($out, $expectMsgPart);
    $hasException = str_contains($out, 'EXC:');
    $cond = $hasExpectedType && $hasExpectedMsg && !$hasException;
    ok("save_assignment [$label] -> FLASH $expectType ('$expectMsgPart')", $cond, 'out=' . substr(preg_replace('/\s+/', ' ', $out), 0, 250));
}

@unlink($scriptAssign);

echo implode("\n", $log) . "\n\n";
echo "TOTAL: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
