<?php
/**
 * Smoke test: input validation di action_save_assignment & action_save_learning_objective.
 * Memastikan skenario invalid menghasilkan flash danger (bukan exception
 * yang muncul sebagai "Gagal: Terjadi kesalahan internal").
 *
 * Cara jalan: cp storage/eraport.sqlite storage/eraport_test.sqlite
 *             php tests/input_validation_test.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$base = dirname(__DIR__);
$DB = $base . '/storage/eraport_test.sqlite';

if (!is_file($DB)) {
    fwrite(STDERR, "Test DB missing. Run: cp storage/eraport.sqlite storage/eraport_test.sqlite\n");
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

$admin = fetch_one("SELECT id FROM users WHERE role = ? LIMIT 1", ['admin']);
$teacher = fetch_one("SELECT id FROM teachers WHERE active = 1 LIMIT 1");
$class = fetch_one("SELECT id, name FROM classes WHERE active = 1 LIMIT 1");
$subject = fetch_one("SELECT id FROM subjects WHERE active = 1 LIMIT 1");

$pass = 0;
$fail = 0;

function run_case(string $script, string $label, array $post): array {
    global $base, $DB;
    $env = ['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => $DB];
    $args = [PHP_BINARY, '-d', 'display_errors=1', $script, $label, json_encode($post)];
    $proc = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname($script), $env);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return ['output' => $out, 'expect_danger' => str_contains($out, 'FLASH danger'), 'expect_success' => str_contains($out, 'FLASH success')];
}

function check(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $name\n"; }
    else { $fail++; echo "  [FAIL] $name" . ($detail !== '' ? " :: $detail" : '') . "\n"; }
}

$scriptAssign = $base . '/tests/_case_save_assignment.php';
$scriptTp = $base . '/tests/_case_save_tp.php';

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
$_SESSION['_csrf'] = 'testtoken';
$post = json_decode($argv[2] ?? '{}', true) ?: [];
$_POST = ['_csrf' => 'testtoken', 'action' => 'save_assignment', 'id' => '0'] + $post;
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

file_put_contents($scriptTp, str_replace('action_save_assignment', 'action_save_learning_objective', file_get_contents($scriptAssign)));

echo "\n== action_save_assignment ==\n";
$cases = [
    ['A: form kosong', [], 'danger'],
    ['B: teacher 0', ['teacher_id' => '0', 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger'],
    ['C: teacher FK invalid', ['teacher_id' => '99999', 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger'],
    ['D: class FK invalid', ['teacher_id' => (string)$teacher['id'], 'class_id' => '99999', 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'danger'],
    ['E: full valid', ['teacher_id' => (string)$teacher['id'], 'class_id' => (string)$class['id'], 'subject_id' => (string)$subject['id'], 'academic_year' => '2025/2026', 'semester' => 'Ganjil'], 'success'],
];
foreach ($cases as [$label, $post, $expected]) {
    $r = run_case($scriptAssign, $label, $post);
    $hasException = str_contains($r['output'], 'EXC:');
    $hasFlash = str_contains($r['output'], 'FLASH');
    $hasExpected = $expected === 'danger' ? $r['expect_danger'] : $r['expect_success'];
    check("$label: flash $expected (no exception)", $hasExpected && !$hasException, $r['output']);
}

echo "\n== action_save_learning_objective ==\n";
$cases = [
    ['A: full valid', ['subject_id' => (string)$subject['id'], 'grade' => $class['name'], 'description' => 'TP valid', 'active' => '1'], 'success'],
    ['B: subject_id kosong', ['subject_id' => '', 'grade' => $class['name'], 'description' => 'TP no subj', 'active' => '1'], 'danger'],
    ['C: grade ngaco', ['subject_id' => (string)$subject['id'], 'grade' => 'Kelas Fiktif', 'description' => 'TP no class', 'active' => '1'], 'danger'],
    ['D: subject_id FK invalid', ['subject_id' => '99999', 'grade' => $class['name'], 'description' => 'TP subj invalid', 'active' => '1'], 'danger'],
];
foreach ($cases as [$label, $post, $expected]) {
    $r = run_case($scriptTp, $label, $post);
    $hasException = str_contains($r['output'], 'EXC:');
    $hasExpected = $expected === 'danger' ? $r['expect_danger'] : $r['expect_success'];
    check("$label: flash $expected (no exception)", $hasExpected && !$hasException, $r['output']);
}

@unlink($scriptAssign);
@unlink($scriptTp);

echo "\nTOTAL: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
