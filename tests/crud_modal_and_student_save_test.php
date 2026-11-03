<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$base = dirname(__DIR__);
$DB = $base . '/storage/eraport_test.sqlite';

if (!is_file($DB)) {
    fwrite(STDERR, "Missing test DB.\n");
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

require_once __DIR__ . '/_fixture.php';

$testAdmin = fetch_one("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if (!$testAdmin) {
    $tId = test_fixture_teacher('Admin Teacher Fixture');
    $testAdmin = ['id' => test_fixture_admin($tId)];
}
$_SESSION['user_id'] = (int)$testAdmin['id'];
$_SESSION['_csrf'] = 'tok_test';

section('Modal Pop-up UI Render');
ob_start();
try {
    input_panel_start('Edit Siswa', 'Tambah Siswa', false);
    echo '<form method="post"><input type="text" name="name"></form>';
    input_panel_end();
    $modalHtml = ob_get_clean();
    ok('render input_panel_start mengandung crud-toolbar', str_contains($modalHtml, 'crud-toolbar'));
    ok('render input_panel_start mengandung app-modal-trigger-add', str_contains($modalHtml, 'app-modal-trigger-add'));
    ok('render input_panel_start mengandung app-modal-backdrop', str_contains($modalHtml, 'app-modal-backdrop'));
    ok('render input_panel_start mengandung app-modal-dialog', str_contains($modalHtml, 'app-modal-dialog'));
    ok('render input_panel_start mengandung app-modal-close', str_contains($modalHtml, 'app-modal-close'));
} catch (Throwable $e) {
    ob_end_clean();
    ok('render input_panel_start tidak error', false, $e->getMessage());
}

section('row_actions helper attributes');
$rowActionsHtml = row_actions('students', 9999, 'delete_student');
ok('row_actions memiliki data-crud-edit', str_contains($rowActionsHtml, 'data-crud-edit'));
ok('row_actions memiliki app-btn-edit', str_contains($rowActionsHtml, 'app-btn-edit'));
ok('row_actions memiliki app-form-delete', str_contains($rowActionsHtml, 'app-form-delete'));

section('Student Edit Class without password (subprocess verification)');

// Get or create class 1 and class 2
$classA = fetch_one("SELECT id FROM classes WHERE active = 1 ORDER BY id LIMIT 1");
$classB = fetch_one("SELECT id FROM classes WHERE active = 1 AND id != ? ORDER BY id LIMIT 1", [(int)$classA['id']]);
if (!$classB) {
    execute_sql("INSERT INTO classes (name, grade, active, academic_year, created_at, updated_at) VALUES ('9Z Test', '9', 1, '2026/2027', ?, ?)", [now_string(), now_string()]);
    $classB = fetch_one("SELECT id FROM classes WHERE name = '9Z Test'");
}

// Create a student WITHOUT a user account
$nisnTest = '999' . time();
execute_sql("INSERT INTO students (nis, nisn, name, gender, class_id, active, created_at, updated_at) VALUES ('1234', ?, 'Siswa Test Class Edit', 'L', ?, 1, ?, ?)", [
    $nisnTest,
    (int)$classA['id'],
    now_string(),
    now_string(),
]);
$studentId = (int)db()->lastInsertId();

// Verify no user exists for this student
$userBefore = fetch_one("SELECT id FROM users WHERE student_id = ?", [$studentId]);
ok('siswa baru belum memiliki user login', $userBefore === null);

// Run subprocess for action_save_student
$runnerScript = $base . '/tests/_case_student_edit.php';
file_put_contents($runnerScript, <<<'PHP'
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
$post = json_decode($argv[1] ?? '{}', true) ?: [];
$_POST = ['_csrf' => 'tok'] + $post;

action_save_student();
PHP
);

$postData = [
    'action' => 'save_student',
    'id' => (string)$studentId,
    'name' => 'Siswa Test Class Edit',
    'nis' => '1234',
    'nisn' => $nisnTest,
    'gender' => 'L',
    'class_id' => (string)$classB['id'],
    'username' => $nisnTest,
    'password' => '', // blank password
    'active' => '1',
    '_ajax' => '1',
];

$cmd = [PHP_BINARY, '-d', 'display_errors=1', $runnerScript, json_encode($postData)];
$proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($proc);
@unlink($runnerScript);

ok('action_save_student exit code 0', $exitCode === 0, $err);
$json = json_decode((string)$out, true);
ok('action_save_student return JSON ok=true via AJAX', is_array($json) && ($json['ok'] ?? false) === true, $out);
$studentAfter = fetch_one("SELECT class_id FROM students WHERE id = ?", [$studentId]);
ok('kelas siswa berhasil terupdate di database', (int)$studentAfter['class_id'] === (int)$classB['id']);

echo implode("\n", $log) . "\n\n";
echo "TOTAL: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
