<?php
/**
 * Smoke test: friendly_error & render_access_denied memberikan
 * pesan informatif (class + file:line) untuk debugging.
 *
 * Cara jalan: cp storage/eraport.sqlite storage/eraport_test.sqlite
 *             php tests/error_handling_test.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$base = dirname(__DIR__);
$DB = $base . '/storage/eraport_test.sqlite';
if (!is_file($DB)) {
    fwrite(STDERR, "Missing test DB. cp storage/eraport.sqlite storage/eraport_test.sqlite\n");
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

function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $name\n"; }
    else { $fail++; echo "  [FAIL] $name" . ($detail !== '' ? " :: $detail" : '') . "\n"; }
}

echo "\n== friendly_error: sertakan class & file:line ==\n";

// force APP_DEBUG=true via config
$GLOBALS['config']['debug'] = true;

$runtimeExc = new RuntimeException('Test runtime error');
$msg = friendly_error($runtimeExc);
ok('RuntimeException: sertakan class', str_contains($msg, 'RuntimeException'), "got: $msg");
ok('RuntimeException: sertakan file:line (debug mode)', preg_match('/di .+\.php:\d+/', $msg) === 1, "got: $msg");

$invalidExc = new InvalidArgumentException('Action tidak dikenal.');
$msg = friendly_error($invalidExc);
ok('InvalidArgumentException: sertakan class', str_contains($msg, 'InvalidArgumentException'), "got: $msg");

// switch ke production mode (debug=false) untuk verifikasi fallback
$GLOBALS['config']['debug'] = false;
$pdoExc = new PDOException('SQLSTATE[23000]: duplicate key');
$msg = friendly_error($pdoExc);
ok('PDOException: sertakan class (prod mode)', str_contains($msg, 'PDOException'), "got: $msg");
ok('PDOException: sertakan file:line (prod mode)', preg_match('/di .+\.php:\d+/', $msg) === 1, "got: $msg");
ok('PDOException: sertakan fallback message (prod mode)', str_contains($msg, 'Terjadi kesalahan internal'), "got: $msg");
$GLOBALS['config']['debug'] = true;

echo "\n== render_access_denied: halt caller di CLI ==\n";

// setup: guru A tidak mengajar BHS
$teacherA = fetch_one("SELECT id FROM teachers WHERE nip = 'TSTA' LIMIT 1");
$teacherB = fetch_one("SELECT id FROM teachers WHERE nip = 'TSTB' LIMIT 1");
$class7A  = fetch_one("SELECT id FROM classes WHERE name = '7A Test' LIMIT 1");
$subjMTK  = fetch_one("SELECT id FROM subjects WHERE short_name = 'MTK' LIMIT 1");
$subjBHS  = fetch_one("SELECT id FROM subjects WHERE short_name = 'BHS' LIMIT 1");
$userA    = fetch_one("SELECT id FROM users WHERE username = 'test_guru_a' LIMIT 1");

if ($teacherA && $teacherB && $class7A && $subjMTK && $subjBHS && $userA) {
    $_SESSION['user_id'] = (int)$userA['id'];

    try {
        require_subject_access((int)$subjBHS['id'], (int)$class7A['id']);
        ok('require_subject_access ditolak guru A untuk BHS', false, 'tidak throw');
    } catch (Throwable $e) {
        ok('require_subject_access ditolak guru A untuk BHS', true);
        ok('  message: "Anda tidak mengajar"', str_contains($e->getMessage(), 'tidak mengajar'), 'msg: ' . $e->getMessage());
        ok('  message: sertakan nama mapel', str_contains($e->getMessage(), 'Bahasa Indonesia'), 'msg: ' . $e->getMessage());
        ok('  message: sertakan nama kelas', str_contains($e->getMessage(), '7A Test'), 'msg: ' . $e->getMessage());
    }

    try {
        require_class_access((int)$class7A['id']);
        ok('require_class_access guru A di 7A (MTK) diizinkan', true);
    } catch (Throwable $e) {
        ok('require_class_access guru A di 7A (MTK) diizinkan', false, 'tidak seharusnya throw: ' . $e->getMessage());
    }

    // guru non-admin ke halaman admin
    try {
        require_role(['admin']);
        ok('require_role(["admin"]) ditolak untuk guru', false, 'tidak throw');
    } catch (Throwable $e) {
        ok('require_role(["admin"]) ditolak untuk guru', true);
        ok('  message: sertakan role saat ini', str_contains($e->getMessage(), 'guru'), 'msg: ' . $e->getMessage());
        ok('  message: sertakan role yang dibutuhkan', str_contains($e->getMessage(), 'admin'), 'msg: ' . $e->getMessage());
    }
} else {
    ok('fixture guru A & BHS', false, 'jalankan teacher_scope_test dulu untuk seed fixture');
}

echo "\nTOTAL: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
