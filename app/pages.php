<?php

declare(strict_types=1);

function render_header(string $title): void
{
    $user = current_user();
    $school = get_school_profile();
    $menuGroups = [
        'Menu Utama' => [
            'dashboard' => ['Dashboard', 'dashboard'],
            'update-data' => ['Update Data E Rapor', 'update-data'],
        ],
        'Data Referensi' => [
            'school' => ['Data Sekolah', 'school'],
            'teachers' => ['Data Guru', 'teachers'],
            'students' => ['Data Siswa', 'students'],
            'classes' => ['Data Kelas', 'classes'],
            'subjects' => ['Data Mapel', 'subjects'],
            'assignments' => ['Data Pembelajaran', 'assignments'],
            'data-ekskul' => ['Data Ekskul', 'data-ekskul'],
            'data-kelompok' => ['Data Kelompok Mapel', 'data-kelompok'],
            'gabung-mapel' => ['Gabung Mapel', 'gabung-mapel'],
            'data-mapping' => ['Data Mapping Rapor', 'data-mapping'],
            'data-logo-ttd' => ['Data Logo dan TTD', 'data-logo-ttd'],
            'tanggal-rapor' => ['Data Tanggal Rapor', 'tanggal-rapor'],
            'foto-siswa' => ['Foto Siswa', 'foto-siswa'],
        ],
        'Kokurikuler' => [
            'tema-kokurikuler' => ['Daftar Tema', 'tema-kokurikuler'],
            'kegiatan-kokurikuler' => ['Kegiatan Kokurikuler', 'kegiatan-kokurikuler'],
            'kelompok-kokurikuler' => ['Kelompok Kokurikuler', 'kelompok-kokurikuler'],
        ],
        'Penilaian' => [
            'data-tp' => ['Tujuan Pembelajaran', 'data-tp'],
            'status-penilaian' => ['Status Penilaian', 'status-penilaian'],
            'grades' => ['Input Nilai', 'grades'],
            'student-attendance' => ['Absensi Siswa/Mapel', 'student-attendance'],
            'teacher-attendance' => ['Absensi Mengajar', 'teacher-attendance'],
            'lesson-schedule' => ['Jadwal Pelajaran', 'lesson-schedule'],
            'journals' => ['Jurnal Harian', 'journals'],
            'violations' => ['Pelanggaran Siswa', 'violations'],
        ],
        'Cetak Rapor' => [
            'cetak-pelengkap-rapor' => ['Cetak Biodata', 'cetak-pelengkap-rapor'],
            'cetak-nilai-rapor' => ['Cetak Rapor', 'cetak-nilai-rapor'],
            'cetak-leger-rapor' => ['Leger Rapor', 'cetak-leger-rapor'],
            'cetak-leger-pts' => ['Leger PTS', 'cetak-leger-pts'],
            'cetak-nilai-rapor-pts' => ['Rapor PTS', 'cetak-nilai-rapor-pts'],
            'cetak-buku-induk' => ['Buku Induk', 'cetak-buku-induk'],
            'reports' => ['Laporan Ringkas', 'reports'],
        ],
        'Ijazah & SKL' => [
            'input-kelulusan' => ['Input Kelulusan', 'input-kelulusan'],
            'import-nomor-ijazah' => ['Import Nomor Ijazah', 'import-nomor-ijazah'],
            'setting-transkrip' => ['Setting Transkrip', 'setting-transkrip'],
            'setting-skl' => ['Setting SKL', 'setting-skl'],
            'mapping-mapel-skl' => ['Mapping Mapel SKL', 'mapping-mapel-skl'],
            'input-nilai-skl' => ['Input Nilai Akhir', 'input-nilai-skl'],
            'cetak-skl' => ['Cetak SKL', 'cetak-skl'],
            'cetak-transkrip-ijazah' => ['Cetak Transkrip', 'cetak-transkrip-ijazah'],
        ],
        'Integrasi' => [
            'kirim-data-dapodik' => ['Kirim Data Dapodik', 'kirim-data-dapodik'],
            'backup-restore' => ['Backup dan Restore', 'backup-restore'],
            'telegram' => ['Bot Telegram', 'telegram'],
            'whatsapp' => ['WhatsApp Report', 'whatsapp'],
        ],
        'Pengaturan' => [],
    ];
    if (user_role() === 'siswa') {
        $menuGroups = [
            'Portal Siswa' => [
                'dashboard' => ['Dashboard', 'dashboard'],
                'lesson-schedule' => ['Jadwal Pelajaran', 'lesson-schedule'],
                'student-progress' => ['Progres Nilai', 'student-progress'],
                'student-attendance-view' => ['Kehadiran', 'student-attendance-view'],
                'student-violations' => ['Pelanggaran', 'student-violations'],
                'student-documents' => ['Dokumen Kelulusan', 'student-documents'],
            ],
            'Pengaturan' => [
                'profile' => ['Profil Pengguna', 'profile'],
            ],
        ];
    }
    if (!is_admin()) {
        foreach ([
            'update-data', 'school', 'teachers', 'classes', 'subjects', 'data-ekskul', 'data-kelompok',
            'gabung-mapel', 'data-mapping', 'data-logo-ttd', 'tanggal-rapor', 'foto-siswa',
            'tema-kokurikuler', 'kegiatan-kokurikuler', 'kelompok-kokurikuler', 'input-kelulusan',
            'import-nomor-ijazah', 'setting-transkrip', 'setting-skl', 'mapping-mapel-skl',
            'input-nilai-skl', 'kirim-data-dapodik', 'backup-restore', 'whatsapp', 'users', 'violations'
        ] as $adminPage) {
            foreach ($menuGroups as &$groupItems) {
                unset($groupItems[$adminPage]);
            }
            unset($groupItems);
        }
    } else {
        $menuGroups['Pengaturan']['users'] = ['Data Pengguna', 'users'];
    }
    $menuGroups['Pengaturan']['profile'] = ['Profil Pengguna', 'profile'];

    $active = (string)($_GET['page'] ?? 'dashboard');
    $appLogoUrl = signature_media_url('logo');
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - <?= e(config('app_name')) ?></title>
        <?php if ($appLogoUrl !== ''): ?>
            <link rel="icon" href="<?= e($appLogoUrl) ?>">
            <link rel="apple-touch-icon" href="<?= e($appLogoUrl) ?>">
        <?php endif; ?>
        <link rel="stylesheet" href="<?= e(asset_version_url('assets/app.css')) ?>">
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar">
            <button type="button" class="sidebar-close" data-sidebar-close aria-label="Tutup menu">&times;</button>
            <div class="brand">
                <?= render_app_mark() ?>
                <div>
                    <strong><?= e(config('app_name')) ?></strong>
                    <span><?= e($school['academic_year'] ?? config('school.academic_year')) ?> <?= e($school['semester'] ?? config('school.semester')) ?></span>
                </div>
            </div>
            <nav class="menu">
                <?php foreach ($menuGroups as $groupLabel => $items): ?>
                    <?php if (!$items) {
                        continue;
                    } ?>
                    <?php $groupOpen = array_key_exists($active, $items); ?>
                    <details class="menu-group" <?= $groupOpen ? 'open' : '' ?>>
                        <summary>
                            <span><?= e($groupLabel) ?></span>
                            <span class="menu-count"><?= e(count($items)) ?></span>
                        </summary>
                        <div class="submenu">
                            <?php foreach ($items as $page => [$label, $urlPage]): ?>
                                <a class="<?= $active === $page ? 'active' : '' ?>" href="<?= e(route_url($urlPage)) ?>"><?= e($label) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </nav>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-close></div>
        <main class="main">
            <header class="topbar">
                <div class="topbar-title">
                    <button type="button" class="mobile-menu-button" data-sidebar-open aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false">
                        <span></span><span></span><span></span>
                    </button>
                    <div>
                        <div class="school-name"><?= e($school['name'] ?? config('school.name')) ?></div>
                        <h1><?= e($title) ?></h1>
                    </div>
                </div>
                <?php if ($user): ?>
                    <div class="userbox">
                        <span><?= e($user['name']) ?></span>
                        <a href="<?= e(route_url('profile')) ?>">Profil</a>
                        <form method="post" action="<?= e(route_url('logout')) ?>" class="logout-form">
                            <?= csrf_field() ?>
                            <button type="submit">Keluar</button>
                        </form>
                    </div>
                <?php endif; ?>
            </header>
            <?php render_flash(); ?>
    <?php
}

function render_footer(): void
{
    ?>
            <footer class="footer">
                <span><?= e(date('Y')) ?> <?= e(config('app_name')) ?></span>
                <span>PHP shared hosting, tanpa Node.js</span>
            </footer>
            <nav class="mobile-bottom-nav" aria-label="Navigasi cepat">
                <button type="button" class="mobile-bottom-item mobile-menu-button" data-sidebar-open aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false">
                    <span class="bottom-icon menu-lines"><span></span><span></span><span></span></span>
                    <span>Menu</span>
                </button>
                <a class="mobile-bottom-item" href="<?= e(route_url('dashboard')) ?>">
                    <span class="bottom-icon home-icon"></span>
                    <span>Home</span>
                </a>
                <a class="mobile-bottom-item" href="<?= e(route_url('profile')) ?>">
                    <span class="bottom-icon profile-icon"></span>
                    <span>Profil</span>
                </a>
                <form method="post" action="<?= e(route_url('logout')) ?>" class="mobile-bottom-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="mobile-bottom-item">
                        <span class="bottom-icon logout-icon"></span>
                        <span>Keluar</span>
                    </button>
                </form>
            </nav>
        </main>
    </div>
    <script src="<?= e(asset_version_url('assets/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

function render_app_mark(bool $big = false): string
{
    $logoUrl = signature_media_url('logo');
    $class = 'brand-mark' . ($big ? ' big' : '');
    if ($logoUrl === '') {
        return '<div class="' . e($class) . '">ER</div>';
    }

    return '<div class="' . e($class) . ' brand-logo"><img src="' . e($logoUrl) . '" alt="' . e(config('app_name')) . '"></div>';
}

function asset_version_url(string $path): string
{
    $url = app_url($path);
    $file = APP_ROOT . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? (string)filemtime($file) : (string)time();
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
}

function panel_title(string $title, string $icon = '', string $actions = ''): void
{
    ?>
    <div class="panel-heading">
        <div class="panel-title"><span class="panel-icon"><?= e($icon) ?></span><span><?= e($title) ?></span></div>
        <?php if ($actions !== ''): ?>
            <div class="panel-actions"><?= $actions ?></div>
        <?php endif; ?>
    </div>
    <?php
}

function input_panel_start(string $title, string $addLabel, bool $open = false): void
{
    $button = '<button type="button" class="button primary input-panel-toggle" data-toggle-label="' . e($addLabel) . '" data-close-label="Tutup Form">' . e($open ? 'Tutup Form' : $addLabel) . '</button>';
    echo '<section class="panel input-panel ' . ($open ? 'is-open' : '') . '" id="form-data">';
    panel_title($title, '', $button);
    echo '<div class="input-panel-body">';
}

function input_panel_end(): void
{
    echo '</div></section>';
}

function render_flash(): void
{
    foreach (take_flash() as $flash) {
        echo '<div class="alert ' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
}

function render_public_header(string $title): void
{
    $appLogoUrl = signature_media_url('logo');
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - <?= e(config('app_name')) ?></title>
        <?php if ($appLogoUrl !== ''): ?>
            <link rel="icon" href="<?= e($appLogoUrl) ?>">
            <link rel="apple-touch-icon" href="<?= e($appLogoUrl) ?>">
        <?php endif; ?>
        <link rel="stylesheet" href="<?= e(asset_version_url('assets/app.css')) ?>">
    </head>
    <body class="public-page">
    <main class="public-card">
        <?php render_flash(); ?>
    <?php
}

function render_public_footer(): void
{
    ?>
    </main>
    </body>
    </html>
    <?php
}

function get_school_profile(): array
{
    if (!table_exists('school_profile')) {
        return [
            'name' => config('school.name'),
            'academic_year' => config('school.academic_year'),
            'semester' => config('school.semester'),
        ];
    }

    $school = fetch_one('SELECT * FROM school_profile ORDER BY id LIMIT 1');
    return $school ?: [
        'name' => config('school.name'),
        'academic_year' => config('school.academic_year'),
        'semester' => config('school.semester'),
    ];
}

function page_install(): void
{
    if (app_installed()) {
        render_public_header('Instalasi');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi sudah terpasang</h1>
            <p>Installer dikunci setelah database dan akun awal tersedia.</p>
        </div>
        <a class="button primary full" href="<?= e(route_url(current_user() ? 'dashboard' : 'login')) ?>">Lanjutkan</a>
        <?php
        render_public_footer();
        return;
    }

    if (is_post()) {
        verify_csrf();
        install_database();
        flash('success', 'Database siap. Login awal: administrator / administrator. Segera ganti password setelah masuk.');
        redirect_to('login');
    }

    render_public_header('Instalasi');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1>Instalasi E-Raport KumerBot</h1>
        <p>Aplikasi akan membuat tabel dan data contoh pada database yang terhubung di config/config.php.</p>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <button class="button primary full" type="submit">Jalankan Instalasi</button>
    </form>
    <?php
    render_public_footer();
}

function login_next_page(): string
{
    $next = trim((string)($_POST['next'] ?? $_GET['next'] ?? ''));
    if ($next !== '' && preg_match('/^[a-z0-9-]+$/', $next) && array_key_exists($next, app_routes()['private'] ?? [])) {
        return $next;
    }

    return 'dashboard';
}

function login_next_params(string $nextPage): array
{
    return $nextPage === 'dashboard' ? [] : ['next' => $nextPage];
}

function page_login(): void
{
    $nextPage = login_next_page();
    if (current_user()) {
        redirect_to($nextPage);
    }

    if (is_post()) {
        verify_csrf();
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rateIdentity = client_ip() . '|' . strtolower($username);
        $maxAttempts = (int)config('security.login_max_attempts', 5);
        $windowSeconds = (int)config('security.login_window_seconds', 900);

        if (rate_limited('login', $rateIdentity, $maxAttempts, $windowSeconds)) {
            flash('danger', 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.');
            redirect_to('login', login_next_params($nextPage));
        }

        $user = fetch_one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);

        if ($user && password_verify($password, (string)$user['password_hash'])) {
            rate_limit_clear('login', $rateIdentity);
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);
            $_SESSION['user_id'] = (int)$user['id'];
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$user['id']]);
            }
            flash(is_weak_password($password) ? 'danger' : 'success', is_weak_password($password)
                ? 'Anda masih memakai password default/lemah. Segera ganti dari menu Profil.'
                : 'Selamat datang, ' . $user['name'] . '.');
            redirect_to($nextPage);
        }

        rate_limit_hit('login', $rateIdentity, $windowSeconds);
        flash('danger', 'Username atau password salah.');
    }

    render_public_header('Login');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1><?= e(config('app_name')) ?></h1>
        <p>Masuk untuk mengelola e-rapor, absensi, jurnal, dan bot Telegram.</p>
    </div>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <?php if ($nextPage !== 'dashboard'): ?>
            <input type="hidden" name="next" value="<?= e($nextPage) ?>">
        <?php endif; ?>
        <label>Username
            <input name="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="button primary full" type="submit">Login</button>
    </form>
    <?php
    render_public_footer();
}

function page_telegram_register(): void
{
    if (!app_installed()) {
        render_public_header('Daftar Guru Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi belum siap</h1>
            <p>Database belum diinstall. Jalankan instalasi dulu sebelum membuka pendaftaran guru.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    $token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
    $registration = $token !== '' ? telegram_registration_token_row($token) : null;
    $error = '';
    $values = $_POST;

    if (!$registration) {
        render_public_header('Daftar Guru Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Link Tidak Berlaku</h1>
            <p>Ketik <strong>/daftar</strong> lagi di Telegram untuk mendapatkan link baru.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }
    if (!is_post() && !empty($registration['from_username'])) {
        $values['username'] = telegram_slug((string)$registration['from_username'], 'guru');
    }

    if (is_post()) {
        verify_csrf();
        try {
            $result = telegram_complete_registration($registration, $_POST);
            render_public_header('Daftar Guru Telegram');
            ?>
            <div class="center">
                <?= render_app_mark(true) ?>
                <h1>Pendaftaran Selesai</h1>
                <p><?= e($result['name']) ?> sudah dibuat sebagai guru dan pengguna.</p>
            </div>
            <div class="alert success"><?= e($result['assignment_message']) ?></div>
            <div class="stack">
                <label>Username Web
                    <input readonly value="<?= e($result['username']) ?>">
                </label>
                <label>Login Bot Telegram
                    <input readonly value="/login password-yang-dibuat">
                </label>
                <a class="button primary full" href="<?= e(route_url('login')) ?>">Buka Login Web</a>
            </div>
            <?php
            render_public_footer();
            return;
        } catch (Throwable $exception) {
            $error = friendly_error($exception);
            $registration = telegram_registration_token_row($token);
            if (!$registration) {
                render_public_header('Daftar Guru Telegram');
                ?>
                <div class="center">
                    <?= render_app_mark(true) ?>
                    <h1>Link Tidak Berlaku</h1>
                    <p>Ketik <strong>/daftar</strong> lagi di Telegram untuk mendapatkan link baru.</p>
                </div>
                <?php
                render_public_footer();
                return;
            }
        }
    }

    $subjects = array_column_map(
        fetch_all('SELECT id, name FROM subjects WHERE active = 1 ORDER BY group_name, name'),
        'id',
        'name'
    );
    $classes = array_column_map(
        fetch_all('SELECT id, name FROM classes WHERE active = 1 ORDER BY grade, name'),
        'id',
        'name'
    );
    $selectedClassIds = array_map('strval', (array)($values['class_ids'] ?? []));

    render_public_header('Daftar Guru Telegram');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1>Daftar Guru</h1>
        <p>Isi data guru dan akun pengguna untuk menghubungkan Telegram dengan e-rapor.</p>
    </div>
    <?php if ($error !== ''): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nama Lengkap
            <input name="name" required autofocus value="<?= e($values['name'] ?? '') ?>">
        </label>
        <label>Username Web
            <input name="username" required pattern="[A-Za-z0-9_.-]{3,64}" value="<?= e($values['username'] ?? '') ?>">
        </label>
        <label>Password
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Ulangi Password
            <input type="password" name="password_confirm" required minlength="8">
        </label>
        <label>Email
            <input type="email" name="email" value="<?= e($values['email'] ?? '') ?>">
        </label>
        <label>NIP
            <input name="nip" value="<?= e($values['nip'] ?? '') ?>">
        </label>
        <label>NUPTK
            <input name="nuptk" value="<?= e($values['nuptk'] ?? '') ?>">
        </label>
        <label>JK
            <select name="gender">
                <option value="">-</option>
                <?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $values['gender'] ?? '') ?>
            </select>
        </label>
        <label>Telepon
            <input name="phone" value="<?= e($values['phone'] ?? '') ?>">
        </label>
        <label>Jabatan
            <input name="position" value="<?= e($values['position'] ?? 'Guru Mapel') ?>">
        </label>
        <label>Mapel Utama
            <select name="subject_id">
                <option value="">Pilih mapel</option>
                <?= options($subjects, $values['subject_id'] ?? '') ?>
            </select>
        </label>
        <fieldset class="check-group">
            <legend>Kelas</legend>
            <?php if (!$classes): ?>
                <p class="hint">Belum ada data kelas aktif.</p>
            <?php else: ?>
                <?php foreach ($classes as $classId => $className): ?>
                    <label class="check"><input type="checkbox" name="class_ids[]" value="<?= e($classId) ?>" <?= in_array((string)$classId, $selectedClassIds, true) ? 'checked' : '' ?>> <?= e($className) ?></label>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>
        <button class="button primary full" type="submit">Daftar</button>
    </form>
    <?php
    render_public_footer();
}

function page_telegram_web_login(): void
{
    if (!app_installed()) {
        render_public_header('Miniweb Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi belum siap</h1>
            <p>Database belum diinstall. Jalankan instalasi dulu sebelum membuka miniweb Telegram.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    $token = trim((string)($_GET['token'] ?? ''));
    $login = telegram_web_login_consume_token($token);
    if (!$login) {
        render_public_header('Miniweb Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Link Tidak Berlaku</h1>
            <p>Kembali ke Telegram lalu tekan tombol menu lagi.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user_id'] = (int)$login['user_id'];
    redirect_to((string)$login['next_page']);
}

function page_logout(): void
{
    if (!is_post()) {
        redirect_to(current_user() ? 'dashboard' : 'login');
    }

    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    session_start();
    flash('success', 'Anda sudah keluar.');
    redirect_to('login');
}

function handle_post_action(): void
{
    app_handle_post_request();
}

function action_delete(string $table, string $returnPage): void
{
    require_role(['admin']);
    $allowed = ['teachers', 'classes', 'students', 'subjects', 'teaching_assignments', 'users', 'daily_journals', 'student_violations'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabel tidak valid.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($table === 'users' && $id === (int)current_user()['id']) {
        throw new RuntimeException('Akun yang sedang login tidak bisa dihapus.');
    }
    execute_sql("DELETE FROM $table WHERE id = ?", [$id]);
    flash('success', 'Data dihapus.');
    redirect_to($returnPage);
}

function action_save_school(): void
{
    require_role(['admin']);
    $school = get_school_profile();
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['npsn'] ?? '')),
        trim((string)($_POST['address'] ?? '')),
        trim((string)($_POST['principal_name'] ?? '')),
        trim((string)($_POST['principal_nip'] ?? '')),
        trim((string)$_POST['academic_year']),
        trim((string)$_POST['semester']),
        now_string(),
    ];
    execute_sql(
        'UPDATE school_profile SET name = ?, npsn = ?, address = ?, principal_name = ?, principal_nip = ?, academic_year = ?, semester = ?, updated_at = ? WHERE id = ?',
        array_merge($data, [(int)$school['id']])
    );
    flash('success', 'Data sekolah tersimpan.');
    redirect_to('school');
}

function action_save_teacher(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['nip'] ?? '')),
        trim((string)($_POST['nuptk'] ?? '')),
        (string)($_POST['gender'] ?? ''),
        trim((string)($_POST['phone'] ?? '')),
        trim((string)($_POST['email'] ?? '')),
        trim((string)($_POST['position'] ?? '')),
        trim((string)($_POST['telegram_chat_id'] ?? '')),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE teachers SET name = ?, nip = ?, nuptk = ?, gender = ?, phone = ?, email = ?, position = ?, telegram_chat_id = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO teachers (name, nip, nuptk, gender, phone, email, position, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Data guru tersimpan.');
    redirect_to('teachers');
}

function action_save_class(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['name']),
        trim((string)$_POST['grade']),
        trim((string)($_POST['major'] ?? '')),
        (int)($_POST['homeroom_teacher_id'] ?? 0) ?: null,
        trim((string)$_POST['academic_year']),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE classes SET name = ?, grade = ?, major = ?, homeroom_teacher_id = ?, academic_year = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO classes (name, grade, major, homeroom_teacher_id, academic_year, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Data kelas tersimpan.');
    redirect_to('classes');
}

function action_save_student(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)($_POST['nis'] ?? '')),
        trim((string)($_POST['nisn'] ?? '')),
        trim((string)$_POST['name']),
        (string)($_POST['gender'] ?? ''),
        trim((string)($_POST['birth_place'] ?? '')),
        $_POST['birth_date'] ?: null,
        trim((string)($_POST['religion'] ?? '')),
        (int)($_POST['class_id'] ?? 0) ?: null,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE students SET nis = ?, nisn = ?, name = ?, gender = ?, birth_place = ?, birth_date = ?, religion = ?, class_id = ?, active = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, religion, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Data siswa tersimpan.');
    redirect_to('students');
}

function action_save_subject(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['short_name'] ?? '')),
        trim((string)($_POST['group_name'] ?? '')),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE subjects SET name = ?, short_name = ?, group_name = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data mapel tersimpan.');
    redirect_to('subjects');
}

function action_save_assignment(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        (int)$_POST['teacher_id'],
        (int)$_POST['class_id'],
        (int)$_POST['subject_id'],
        trim((string)$_POST['academic_year']),
        trim((string)$_POST['semester']),
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];
    if ($id > 0) {
        execute_sql('UPDATE teaching_assignments SET teacher_id = ?, class_id = ?, subject_id = ?, academic_year = ?, semester = ?, active = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Data pembelajaran tersimpan.');
    redirect_to('assignments');
}

function action_save_user(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $role = (string)$_POST['role'];
    if (!in_array($role, ['admin', 'guru', 'siswa'], true)) {
        throw new RuntimeException('Role pengguna tidak valid.');
    }
    $teacherId = $role === 'siswa' ? null : ((int)($_POST['teacher_id'] ?? 0) ?: null);
    $studentId = $role === 'siswa' ? ((int)($_POST['student_id'] ?? 0) ?: null) : null;
    $telegramChatId = $role === 'siswa' ? '' : trim((string)($_POST['telegram_chat_id'] ?? ''));
    if ($role === 'siswa' && !$studentId) {
        throw new RuntimeException('Siswa terkait wajib dipilih untuk akun siswa.');
    }
    $username = trim((string)$_POST['username']);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
        throw new RuntimeException('Username hanya boleh huruf, angka, titik, garis bawah, atau strip, minimal 3 karakter.');
    }
    if ($password !== '') {
        validate_password_strength($password);
    }
    $base = [
        $username,
        trim((string)$_POST['name']),
        trim((string)($_POST['email'] ?? '')),
        $role,
        $teacherId,
        $studentId,
        $telegramChatId,
        isset($_POST['active']) ? 1 : 0,
        now_string(),
    ];

    if ($id > 0) {
        if ($password !== '') {
            execute_sql(
                'UPDATE users SET username = ?, name = ?, email = ?, role = ?, teacher_id = ?, student_id = ?, telegram_chat_id = ?, active = ?, updated_at = ?, password_hash = ? WHERE id = ?',
                array_merge($base, [password_hash($password, PASSWORD_DEFAULT), $id])
            );
        } else {
            execute_sql(
                'UPDATE users SET username = ?, name = ?, email = ?, role = ?, teacher_id = ?, student_id = ?, telegram_chat_id = ?, active = ?, updated_at = ? WHERE id = ?',
                array_merge($base, [$id])
            );
        }
    } else {
        if ($password === '') {
            throw new RuntimeException('Password wajib diisi untuk pengguna baru.');
        }
        execute_sql(
            'INSERT INTO users (username, name, email, role, teacher_id, student_id, telegram_chat_id, active, updated_at, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($base, [password_hash($password, PASSWORD_DEFAULT)])
        );
    }

    flash('success', 'Data pengguna tersimpan.');
    redirect_to('users');
}

function action_save_violation(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        (int)$_POST['student_id'],
        date_ymd((string)($_POST['date'] ?? date('Y-m-d'))),
        trim((string)$_POST['type']),
        trim((string)($_POST['description'] ?? '')),
        (int)($_POST['points'] ?? 0),
        trim((string)($_POST['action_taken'] ?? '')),
        (int)current_user()['id'],
        now_string(),
    ];
    $violationId = $id;
    if ($id > 0) {
        execute_sql('UPDATE student_violations SET student_id = ?, date = ?, type = ?, description = ?, points = ?, action_taken = ?, created_by = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO student_violations (student_id, date, type, description, points, action_taken, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $data);
        $violationId = (int)db()->lastInsertId();
    }
    if (!empty($_POST['queue_whatsapp']) && function_exists('whatsapp_enqueue_violation_notice') && $violationId > 0) {
        whatsapp_enqueue_violation_notice($violationId, (int)current_user()['id']);
    }
    flash('success', 'Data pelanggaran tersimpan.');
    redirect_to('violations');
}

function action_save_profile(): void
{
    $user = current_user();
    $password = (string)($_POST['password'] ?? '');
    $data = [
        trim((string)$_POST['name']),
        trim((string)($_POST['email'] ?? '')),
        now_string(),
        (int)$user['id'],
    ];
    execute_sql('UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?', $data);
    if ($password !== '') {
        validate_password_strength($password);
        execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$user['id']]);
    }
    flash('success', 'Profil tersimpan.');
    redirect_to('profile');
}

function action_save_grades(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    foreach ((array)($_POST['score'] ?? []) as $studentId => $score) {
        $studentId = (int)$studentId;
        require_student_in_assignment_class($studentId, $assignment);
        $scoreValue = trim((string)$score) === '' ? null : (float)$score;
        $description = trim((string)(($_POST['description'][$studentId] ?? '')));
        $existing = fetch_one('SELECT id FROM grades WHERE assignment_id = ? AND student_id = ?', [(int)$assignment['id'], $studentId]);
        if ($existing) {
            execute_sql('UPDATE grades SET score = ?, description = ?, created_by = ?, updated_at = ? WHERE id = ?', [$scoreValue, $description, (int)current_user()['id'], now_string(), (int)$existing['id']]);
        } else {
            execute_sql('INSERT INTO grades (assignment_id, student_id, score, description, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [(int)$assignment['id'], $studentId, $scoreValue, $description, (int)current_user()['id'], now_string()]);
        }
    }
    flash('success', 'Nilai tersimpan.');
    redirect_to('grades', ['assignment_id' => (int)$assignment['id']]);
}

function action_save_student_attendance(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $date = date_ymd((string)$_POST['date']);
    $meetingNo = max(1, (int)($_POST['meeting_no'] ?? 1));
    $topic = trim((string)($_POST['topic'] ?? 'Absensi'));
    $sessionId = save_attendance_session((int)$assignment['id'], $date, $meetingNo, $topic, (int)current_user()['id']);
    $queueWhatsapp = !empty($_POST['queue_whatsapp_absence']) && function_exists('whatsapp_enqueue_attendance_notice');

    foreach ((array)($_POST['status'] ?? []) as $studentId => $status) {
        $studentId = (int)$studentId;
        require_student_in_assignment_class($studentId, $assignment);
        $status = strtolower((string)$status);
        if (!array_key_exists($status, allowed_statuses())) {
            $status = 'hadir';
        }
        $notes = trim((string)(($_POST['notes'][$studentId] ?? '')));
        save_student_attendance_entry($sessionId, $studentId, $status, $notes);
        if ($queueWhatsapp && $status !== 'hadir') {
            $entry = fetch_one('SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?', [$sessionId, $studentId]);
            if ($entry) {
                whatsapp_enqueue_attendance_notice((int)$entry['id'], (int)current_user()['id']);
            }
        }
    }

    flash('success', 'Absensi siswa tersimpan.');
    redirect_to('student-attendance', ['assignment_id' => (int)$assignment['id'], 'date' => $date, 'meeting_no' => $meetingNo]);
}

function action_save_teacher_attendance(): void
{
    require_role(['admin', 'guru']);
    teacher_teaching_attendance_ensure_schema();
    $date = date_ymd((string)$_POST['date']);
    foreach ((array)($_POST['status'] ?? []) as $scheduleId => $status) {
        $schedule = teacher_teaching_schedule_by_id((int)$scheduleId);
        if (!$schedule) {
            continue;
        }
        if ((int)$schedule['day_of_week'] !== teacher_teaching_day_for_date($date)) {
            continue;
        }
        $status = strtolower((string)$status);
        if (!array_key_exists($status, teacher_attendance_statuses())) {
            $status = 'hadir';
        }
        $timeIn = trim((string)(($_POST['time_in'][$schedule['schedule_id']] ?? ''))) ?: null;
        $timeOut = trim((string)(($_POST['time_out'][$schedule['schedule_id']] ?? ''))) ?: null;
        $notes = trim((string)(($_POST['notes'][$schedule['schedule_id']] ?? '')));
        $base = [
            (int)$schedule['schedule_id'],
            (int)$schedule['assignment_id'],
            (int)$schedule['teacher_id'],
            (int)$schedule['class_id'],
            (int)$schedule['subject_id'],
            $date,
            $status,
            $timeIn,
            $timeOut,
            $notes,
            (int)current_user()['id'],
            now_string(),
        ];
        $existing = fetch_one('SELECT id FROM teacher_teaching_attendance WHERE schedule_id = ? AND date = ?', [(int)$schedule['schedule_id'], $date]);
        if ($existing) {
            execute_sql(
                'UPDATE teacher_teaching_attendance SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, status = ?, time_in = ?, time_out = ?, notes = ?, recorded_by = ?, updated_at = ? WHERE id = ?',
                [(int)$schedule['assignment_id'], (int)$schedule['teacher_id'], (int)$schedule['class_id'], (int)$schedule['subject_id'], $status, $timeIn, $timeOut, $notes, (int)current_user()['id'], now_string(), (int)$existing['id']]
            );
        } else {
            execute_sql(
                'INSERT INTO teacher_teaching_attendance (schedule_id, assignment_id, teacher_id, class_id, subject_id, date, status, time_in, time_out, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $base
            );
        }
    }
    flash('success', 'Absensi mengajar guru tersimpan.');
    redirect_to('teacher-attendance', ['date' => $date]);
}

function action_save_journal(): void
{
    $assignment = require_assignment_access((int)$_POST['assignment_id']);
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $existing = fetch_one('SELECT assignment_id FROM daily_journals WHERE id = ?', [$id]);
        if (!$existing) {
            throw new RuntimeException('Jurnal tidak ditemukan.');
        }
        require_assignment_access((int)$existing['assignment_id']);
    }
    $data = [
        (int)$assignment['id'],
        (int)$assignment['teacher_id'],
        (int)$assignment['class_id'],
        (int)$assignment['subject_id'],
        date_ymd((string)$_POST['date']),
        max(1, (int)($_POST['meeting_no'] ?? 1)),
        trim((string)$_POST['topic']),
        trim((string)$_POST['activities']),
        trim((string)($_POST['materials'] ?? '')),
        trim((string)($_POST['obstacles'] ?? '')),
        trim((string)($_POST['follow_up'] ?? '')),
        (int)current_user()['id'],
        now_string(),
    ];
    if ($id > 0) {
        execute_sql(
            'UPDATE daily_journals SET assignment_id = ?, teacher_id = ?, class_id = ?, subject_id = ?, date = ?, meeting_no = ?, topic = ?, activities = ?, materials = ?, obstacles = ?, follow_up = ?, created_by = ?, updated_at = ? WHERE id = ?',
            array_merge($data, [$id])
        );
    } else {
        execute_sql(
            'INSERT INTO daily_journals (assignment_id, teacher_id, class_id, subject_id, date, meeting_no, topic, activities, materials, obstacles, follow_up, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $data
        );
    }
    flash('success', 'Jurnal tersimpan.');
    redirect_to('journals');
}

function require_assignment_access(int $assignmentId): array
{
    $assignment = telegram_assignment_for_user($assignmentId, current_user());
    if (!$assignment) {
        throw new RuntimeException('Pembelajaran tidak ditemukan atau bukan hak akses akun ini.');
    }
    return $assignment;
}

function require_student_in_assignment_class(int $studentId, array $assignment): void
{
    if ($studentId <= 0) {
        throw new RuntimeException('Data siswa tidak valid.');
    }

    $student = fetch_one(
        'SELECT id FROM students WHERE id = ? AND class_id = ? AND active = 1',
        [$studentId, (int)$assignment['class_id']]
    );
    if (!$student) {
        throw new RuntimeException('Siswa tidak termasuk kelas pembelajaran ini.');
    }
}

function classes_for_current_user(): array
{
    if (is_admin()) {
        return fetch_all('SELECT * FROM classes WHERE active = 1 ORDER BY grade, name');
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        return [];
    }

    return fetch_all(
        'SELECT DISTINCT c.*
         FROM classes c
         JOIN teaching_assignments ta ON ta.class_id = c.id
         WHERE c.active = 1 AND ta.active = 1 AND ta.teacher_id = ?
         ORDER BY c.grade, c.name',
        [$teacherId]
    );
}

function require_class_access(int $classId): void
{
    if (is_admin()) {
        return;
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($teacherId <= 0 || !fetch_one('SELECT id FROM teaching_assignments WHERE class_id = ? AND teacher_id = ? AND active = 1 LIMIT 1', [$classId, $teacherId])) {
        http_response_code(403);
        exit('Akses kelas ditolak.');
    }
}

function allowed_teacher_ids_for_attendance(): array
{
    if (is_admin()) {
        return array_map('intval', array_column(fetch_all('SELECT id FROM teachers WHERE active = 1'), 'id'));
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    return $teacherId > 0 ? [$teacherId] : [];
}

function teacher_teaching_attendance_ensure_schema(): void
{
    if (!table_exists('lesson_schedules') || !table_exists('teacher_teaching_attendance')) {
        run_migrations();
    }
}

function teacher_teaching_day_for_date(string $date): int
{
    return (int)(new DateTimeImmutable($date))->format('N');
}

function teacher_teaching_schedule_rows(string $date): array
{
    teacher_teaching_attendance_ensure_schema();
    if (!table_exists('lesson_schedules')) {
        return [];
    }
    $day = teacher_teaching_day_for_date($date);
    $params = [$date, $day];
    $where = 'ls.day_of_week = ?';
    if (!is_admin()) {
        $where .= ' AND ls.teacher_id = ?';
        $params[] = (int)(current_user()['teacher_id'] ?? 0);
    }

    return fetch_all(
        "SELECT ls.id AS schedule_id, ls.assignment_id, ls.teacher_id, ls.class_id, ls.subject_id,
                ls.day_of_week, ls.period_no, ls.start_time, ls.end_time, ls.note AS schedule_note,
                t.name AS teacher_name, c.name AS class_name, s.name AS subject_name,
                a.id AS attendance_id, a.status, a.time_in, a.time_out, a.notes
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         LEFT JOIN teacher_teaching_attendance a ON a.schedule_id = ls.id AND a.date = ?
         WHERE $where
         ORDER BY ls.period_no, ls.start_time, t.name, c.grade, c.name, s.name",
        $params
    );
}

function teacher_teaching_schedule_by_id(int $scheduleId): ?array
{
    if ($scheduleId <= 0) {
        return null;
    }
    teacher_teaching_attendance_ensure_schema();
    if (!table_exists('lesson_schedules')) {
        return null;
    }
    $params = [$scheduleId];
    $where = 'ls.id = ?';
    if (!is_admin()) {
        $where .= ' AND ls.teacher_id = ?';
        $params[] = (int)(current_user()['teacher_id'] ?? 0);
    }

    return fetch_one(
        "SELECT ls.id AS schedule_id, ls.assignment_id, ls.teacher_id, ls.class_id, ls.subject_id,
                ls.day_of_week, ls.period_no, ls.start_time, ls.end_time,
                t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         WHERE $where",
        $params
    );
}

function current_student(): array
{
    $user = current_user();
    $studentId = (int)($user['student_id'] ?? 0);
    if ($studentId <= 0) {
        http_response_code(403);
        exit('Akun ini belum terhubung dengan data siswa.');
    }
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name, c.grade
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         WHERE s.id = ? AND s.active = 1',
        [$studentId]
    );
    if (!$student) {
        http_response_code(404);
        exit('Data siswa tidak ditemukan.');
    }
    return $student;
}

function student_score_rows(int $studentId): array
{
    return fetch_all(
        'SELECT sub.name AS subject_name, ROUND(AVG(g.score), 2) AS score, MAX(g.description) AS description
         FROM grades g
         JOIN teaching_assignments ta ON ta.id = g.assignment_id
         JOIN subjects sub ON sub.id = ta.subject_id
         WHERE g.student_id = ?
         GROUP BY sub.id, sub.name
         ORDER BY sub.name',
        [$studentId]
    );
}

function student_average_score(int $studentId): ?float
{
    $row = fetch_one('SELECT AVG(score) AS avg_score FROM grades WHERE student_id = ? AND score IS NOT NULL', [$studentId]);
    return $row && $row['avg_score'] !== null ? (float)$row['avg_score'] : null;
}

function student_violations(int $studentId): array
{
    if (!table_exists('student_violations')) {
        return [];
    }
    return fetch_all('SELECT * FROM student_violations WHERE student_id = ? ORDER BY date DESC, id DESC', [$studentId]);
}

function page_dashboard(): void
{
    if (user_role() === 'siswa') {
        page_student_dashboard();
        return;
    }

    render_header('Dashboard');
    $cards = [
        ['Guru', (int)db()->query('SELECT COUNT(*) FROM teachers WHERE active = 1')->fetchColumn()],
        ['Siswa', (int)db()->query('SELECT COUNT(*) FROM students WHERE active = 1')->fetchColumn()],
        ['Kelas', (int)db()->query('SELECT COUNT(*) FROM classes WHERE active = 1')->fetchColumn()],
        ['Pembelajaran', (int)db()->query('SELECT COUNT(*) FROM teaching_assignments WHERE active = 1')->fetchColumn()],
    ];
    ?>
    <section class="hero">
        <div>
            <p class="eyebrow">Ringkasan aplikasi</p>
            <h2>Selamat datang, <?= e(current_user()['name']) ?></h2>
            <p>Kelola data e-rapor, input nilai, absensi siswa per mata pelajaran, absensi mengajar guru sesuai jadwal kelas, jurnal harian, dan input cepat lewat Telegram.</p>
        </div>
    </section>
    <div class="metric-grid">
        <?php foreach ($cards as [$label, $value]): ?>
            <div class="metric"><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <div class="grid two">
        <section class="panel">
            <h3>Aktivitas terbaru</h3>
            <?php $logs = fetch_all('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT 8'); ?>
            <?php if (!$logs): ?>
                <p class="empty">Belum ada log Telegram.</p>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($logs as $log): ?>
                        <div class="list-row">
                            <strong><?= e($log['chat_id']) ?></strong>
                            <span><?= e(mb_strimwidth((string)$log['message'], 0, 90, '...')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <h3>Shortcut guru</h3>
            <div class="quick-links">
                <a href="<?= e(route_url('student-attendance')) ?>">Input Absensi Siswa</a>
                <a href="<?= e(route_url('teacher-attendance')) ?>">Absensi Mengajar</a>
                <a href="<?= e(route_url('journals')) ?>">Tulis Jurnal</a>
                <a href="<?= e(route_url('grades')) ?>">Input Nilai</a>
            </div>
        </section>
    </div>
    <?php
    render_footer();
}

function page_student_dashboard(): void
{
    require_role(['siswa']);
    $student = current_student();
    $avg = student_average_score((int)$student['id']);
    $attendance = attendance_summary_for_student((int)$student['id']);
    $violations = student_violations((int)$student['id']);
    render_header('Dashboard Siswa');
    ?>
    <section class="hero">
        <div>
            <p class="eyebrow">Portal Siswa</p>
            <h2>Selamat datang, <?= e($student['name']) ?></h2>
            <p>Kelas <?= e($student['class_name'] ?? '-') ?>. Pantau progres nilai, kehadiran, pelanggaran, dan dokumen kelulusan dari akun ini.</p>
        </div>
    </section>
    <div class="metric-grid">
        <div class="metric"><span>Rata-rata Nilai</span><strong><?= e($avg !== null ? number_format($avg, 2) : '-') ?></strong></div>
        <div class="metric"><span>Hadir</span><strong><?= e($attendance['hadir'] ?? 0) ?></strong></div>
        <div class="metric"><span>Alpa</span><strong><?= e($attendance['alpa'] ?? 0) ?></strong></div>
        <div class="metric"><span>Pelanggaran</span><strong><?= e(count($violations)) ?></strong></div>
    </div>
    <section class="panel">
        <?php panel_title('Akses Cepat'); ?>
        <div class="quick-links">
            <a href="<?= e(route_url('student-progress')) ?>">Lihat Progres Nilai</a>
            <a href="<?= e(route_url('student-attendance-view')) ?>">Lihat Kehadiran</a>
            <a href="<?= e(route_url('student-violations')) ?>">Lihat Pelanggaran</a>
            <a href="<?= e(route_url('student-documents')) ?>">Dokumen Kelulusan</a>
        </div>
    </section>
    <?php render_footer();
}

function page_school(): void
{
    require_role(['admin']);
    $school = get_school_profile();
    render_header('Data Sekolah');
    ?>
    <section class="panel">
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_school">
            <label>Nama Sekolah <input name="name" required value="<?= e($school['name'] ?? '') ?>"></label>
            <label>NPSN <input name="npsn" value="<?= e($school['npsn'] ?? '') ?>"></label>
            <label class="wide">Alamat <textarea name="address"><?= e($school['address'] ?? '') ?></textarea></label>
            <label>Nama Kepala Sekolah <input name="principal_name" value="<?= e($school['principal_name'] ?? '') ?>"></label>
            <label>NIP Kepala Sekolah <input name="principal_nip" value="<?= e($school['principal_nip'] ?? '') ?>"></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($school['academic_year'] ?? '') ?>"></label>
            <label>Semester <input name="semester" required value="<?= e($school['semester'] ?? '') ?>"></label>
            <div class="wide actions"><button class="button primary">Simpan</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function edit_row(string $table): ?array
{
    $id = (int)($_GET['edit'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    return fetch_one("SELECT * FROM $table WHERE id = ?", [$id]);
}

function page_teachers(): void
{
    require_role(['admin']);
    $edit = edit_row('teachers') ?: [];
    $rows = fetch_all('SELECT * FROM teachers ORDER BY active DESC, name');
    render_header('Data Guru');
    input_panel_start($edit ? 'Edit Guru' : 'Input Guru', 'Tambah Guru', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>NIP <input name="nip" value="<?= e($edit['nip'] ?? '') ?>"></label>
            <label>NUPTK <input name="nuptk" value="<?= e($edit['nuptk'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Telepon <input name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Jabatan <input name="position" value="<?= e($edit['position'] ?? '') ?>"></label>
            <label>Telegram Chat ID <input name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('teachers')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Guru', ['Nama', 'NIP', 'JK', 'Jabatan', 'Telegram', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nip']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['position']) ?></td><td><?= e($row['telegram_chat_id']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('teachers', (int)$row['id'], 'delete_teacher') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_classes(): void
{
    require_role(['admin']);
    $edit = edit_row('classes') ?: [];
    $teachers = map_options('teachers', 'name');
    $rows = fetch_all('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id ORDER BY c.grade, c.name');
    render_header('Data Kelas');
    input_panel_start($edit ? 'Edit Kelas' : 'Input Kelas', 'Tambah Kelas', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_class"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Nama Kelas <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
            <label>Jurusan/Fase <input name="major" value="<?= e($edit['major'] ?? '') ?>"></label>
            <label>Wali Kelas <select name="homeroom_teacher_id"><option value="">-</option><?= options($teachers, $edit['homeroom_teacher_id'] ?? '') ?></select></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($edit['academic_year'] ?? config('school.academic_year')) ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('classes')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Kelas', ['Kelas', 'Tingkat', 'Wali Kelas', 'Jumlah Siswa', 'Status', 'Aksi'], $rows, function ($row) {
        $count = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ?', [(int)$row['id']])['c'];
        ?><td><?= e($row['name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($count) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('classes', (int)$row['id'], 'delete_class') ?></td><?php
    }); ?>
    <?php render_footer();
}

function page_students(): void
{
    require_role(['admin']);
    $edit = edit_row('students') ?: [];
    $classes = map_options('classes', 'name');
    $rows = fetch_all('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.grade, c.name, s.name');
    render_header('Data Siswa');
    input_panel_start($edit ? 'Edit Siswa' : 'Input Siswa', 'Tambah Siswa', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>NIS <input name="nis" value="<?= e($edit['nis'] ?? '') ?>"></label>
            <label>NISN <input name="nisn" value="<?= e($edit['nisn'] ?? '') ?>"></label>
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Tempat Lahir <input name="birth_place" value="<?= e($edit['birth_place'] ?? '') ?>"></label>
            <label>Tanggal Lahir <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>"></label>
            <label>Agama <input name="religion" value="<?= e($edit['religion'] ?? '') ?>"></label>
            <label>Kelas <select name="class_id"><option value="">-</option><?= options($classes, $edit['class_id'] ?? '') ?></select></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('students')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Siswa', ['Nama', 'NIS', 'NISN', 'JK', 'Kelas', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nis']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['class_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('students', (int)$row['id'], 'delete_student') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_subjects(): void
{
    require_role(['admin']);
    $edit = edit_row('subjects') ?: [];
    $rows = fetch_all('SELECT * FROM subjects ORDER BY group_name, name');
    render_header('Data Mapel');
    input_panel_start($edit ? 'Edit Mapel' : 'Input Mapel', 'Tambah Mapel', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama Mapel <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Nama Singkat <input name="short_name" value="<?= e($edit['short_name'] ?? '') ?>"></label>
            <label>Kelompok <input name="group_name" value="<?= e($edit['group_name'] ?? '') ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('subjects')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Mapel', ['Nama Mapel', 'Singkat', 'Kelompok', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['short_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('subjects', (int)$row['id'], 'delete_subject') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_assignments(): void
{
    require_role(['admin']);
    $edit = edit_row('teaching_assignments') ?: [];
    $teachers = map_options('teachers', 'name');
    $classes = map_options('classes', 'name');
    $subjects = map_options('subjects', 'name');
    $rows = assignment_rows();
    render_header('Data Pembelajaran');
    input_panel_start($edit ? 'Edit Pembelajaran' : 'Input Pembelajaran', 'Tambah Pembelajaran', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_assignment"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Guru <select name="teacher_id" required><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Kelas <select name="class_id" required><?= options($classes, $edit['class_id'] ?? '') ?></select></label>
            <label>Mapel <select name="subject_id" required><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($edit['academic_year'] ?? config('school.academic_year')) ?>"></label>
            <label>Semester <input name="semester" required value="<?= e($edit['semester'] ?? config('school.semester')) ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('assignments')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pembelajaran', ['Guru', 'Kelas', 'Mapel', 'Tahun', 'Semester', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['teacher_name']) ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['academic_year']) ?></td><td><?= e($row['semester']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('assignments', (int)$row['id'], 'delete_assignment') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_users(): void
{
    require_role(['admin']);
    $edit = edit_row('users') ?: [];
    $teachers = map_options('teachers', 'name');
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT u.*, t.name AS teacher_name, s.name AS student_name FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id LEFT JOIN students s ON s.id = u.student_id ORDER BY u.role, u.name');
    render_header('Data Pengguna');
    input_panel_start($edit ? 'Edit Pengguna' : 'Input Pengguna', 'Tambah Pengguna', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Username <input name="username" required value="<?= e($edit['username'] ?? '') ?>"></label>
            <label>Password <input type="password" name="password" placeholder="<?= $edit ? 'Kosongkan jika tidak diganti' : '' ?>"></label>
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Role <select name="role"><?= options(['admin' => 'Admin', 'guru' => 'Guru', 'operator' => 'Operator', 'siswa' => 'Siswa'], $edit['role'] ?? 'guru') ?></select></label>
            <label>Guru Terkait <select name="teacher_id"><option value="">-</option><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Siswa Terkait <select name="student_id"><option value="">-</option><?= options($students, $edit['student_id'] ?? '') ?></select></label>
            <label>Telegram Chat ID <input name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>" placeholder="Kosong untuk role siswa"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('users')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pengguna', ['Username', 'Nama', 'Role', 'Guru/Siswa', 'Telegram', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['username']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= e($row['role'] === 'siswa' ? $row['student_name'] : $row['teacher_name']) ?></td><td><?= e($row['telegram_chat_id']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('users', (int)$row['id'], 'delete_user') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_profile(): void
{
    $user = current_user();
    render_header('Profil Pengguna');
    ?>
    <section class="panel">
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
            <label>Nama <input name="name" required value="<?= e($user['name']) ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($user['email']) ?>"></label>
            <label>Password Baru <input type="password" name="password" placeholder="Kosongkan jika tidak diganti"></label>
            <?php if (($user['role'] ?? '') !== 'siswa'): ?>
                <label>Telegram Chat ID <input readonly value="<?= e($user['telegram_chat_id']) ?>"></label>
            <?php endif; ?>
            <div class="wide actions"><button class="button primary">Simpan Profil</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function page_grades(): void
{
    require_role(['admin', 'guru']);
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    $assignments = assignments_for_current_user();
    $assignment = $assignmentId > 0 ? require_assignment_access($assignmentId) : ($assignments[0] ?? null);
    render_header('Input Nilai');
    assignment_picker('grades', $assignments, $assignment ? (int)$assignment['id'] : 0);
    if (!$assignment) {
        echo '<section class="panel"><p class="empty">Belum ada pembelajaran.</p></section>';
        render_footer();
        return;
    }
    $students = fetch_all('SELECT s.*, g.score, g.description FROM students s LEFT JOIN grades g ON g.student_id = s.id AND g.assignment_id = ? WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name', [(int)$assignment['id'], (int)$assignment['class_id']]);
    ?>
    <section class="panel">
        <h3><?= e($assignment['class_name']) ?> - <?= e($assignment['subject_name']) ?></h3>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_grades"><input type="hidden" name="assignment_id" value="<?= e($assignment['id']) ?>">
            <div class="table-wrap">
                <table><thead><tr><th>Nama Siswa</th><th>NIS</th><th>Nilai Akhir</th><th>Deskripsi</th></tr></thead><tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['name']) ?></td><td><?= e($student['nis']) ?></td>
                            <td><input class="small-input" type="number" min="0" max="100" step="0.01" name="score[<?= e($student['id']) ?>]" value="<?= e($student['score']) ?>"></td>
                            <td><input name="description[<?= e($student['id']) ?>]" value="<?= e($student['description']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
            <div class="actions"><button class="button primary">Simpan Nilai</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function page_student_attendance(): void
{
    require_role(['admin', 'guru']);
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    $date = date_ymd((string)($_GET['date'] ?? date('Y-m-d')));
    $meetingNo = max(1, (int)($_GET['meeting_no'] ?? 1));
    $assignments = assignments_for_current_user();
    $assignment = $assignmentId > 0 ? require_assignment_access($assignmentId) : ($assignments[0] ?? null);
    render_header('Absensi Siswa per Mata Pelajaran');
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="student-attendance">
            <label>Pembelajaran <select name="assignment_id"><?= assignment_options($assignments, $assignment['id'] ?? '') ?></select></label>
            <label>Tanggal <input type="date" name="date" value="<?= e($date) ?>"></label>
            <label>Pertemuan <input type="number" min="1" name="meeting_no" value="<?= e($meetingNo) ?>"></label>
            <div class="actions"><button class="button">Tampilkan</button></div>
        </form>
    </section>
    <?php
    if (!$assignment) {
        echo '<section class="panel"><p class="empty">Belum ada pembelajaran.</p></section>';
        render_footer();
        return;
    }
    $session = fetch_one('SELECT * FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?', [(int)$assignment['id'], $date, $meetingNo]);
    $students = fetch_all(
        'SELECT s.*, e.status, e.notes
         FROM students s
         LEFT JOIN student_attendance_entries e ON e.student_id = s.id AND e.session_id = ?
         WHERE s.class_id = ? AND s.active = 1
         ORDER BY s.name',
        [(int)($session['id'] ?? 0), (int)$assignment['class_id']]
    );
    ?>
    <section class="panel">
        <h3><?= e($assignment['class_name']) ?> - <?= e($assignment['subject_name']) ?></h3>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_student_attendance">
            <input type="hidden" name="assignment_id" value="<?= e($assignment['id']) ?>">
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <input type="hidden" name="meeting_no" value="<?= e($meetingNo) ?>">
            <label>Topik Pertemuan <input name="topic" value="<?= e($session['topic'] ?? '') ?>" placeholder="Misal: Pecahan sederhana"></label>
            <label class="checkbox"><input type="checkbox" name="queue_whatsapp_absence" value="1"> Tambahkan WA untuk siswa selain hadir</label>
            <div class="table-wrap">
                <table><thead><tr><th>Nama Siswa</th><th>NIS</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['name']) ?></td><td><?= e($student['nis']) ?></td>
                            <td><select name="status[<?= e($student['id']) ?>]"><?= options(allowed_statuses(), $student['status'] ?? 'hadir') ?></select></td>
                            <td><input name="notes[<?= e($student['id']) ?>]" value="<?= e($student['notes']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
            <div class="actions"><button class="button primary">Simpan Absensi</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function page_teacher_attendance(): void
{
    require_role(['admin', 'guru']);
    $date = date_ymd((string)($_GET['date'] ?? date('Y-m-d')));
    $dayName = schedule_days()[teacher_teaching_day_for_date($date)] ?? '-';
    $rows = teacher_teaching_schedule_rows($date);
    render_header('Absensi Kehadiran Mengajar');
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="teacher-attendance">
            <label>Tanggal <input type="date" name="date" value="<?= e($date) ?>"></label>
            <div class="actions"><button class="button">Tampilkan</button></div>
        </form>
    </section>
    <section class="panel">
        <h3>Jadwal Mengajar <?= e($dayName) ?>, <?= e($date) ?></h3>
        <p class="hint">Absensi ini mencatat kehadiran guru saat mengajar di kelas sesuai jadwal pelajaran, bukan absensi datang/pulang kantor.</p>
        <?php if (!$rows): ?>
            <p class="empty">Belum ada jadwal mengajar pada tanggal ini.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher_attendance"><input type="hidden" name="date" value="<?= e($date) ?>">
                <div class="table-wrap">
                    <table><thead><tr><th>Jam</th><th>Guru</th><th>Kelas</th><th>Mapel</th><th>Status</th><th>Mulai</th><th>Selesai</th><th>Catatan</th></tr></thead><tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $scheduleId = (int)$row['schedule_id'];
                            $start = (string)($row['time_in'] ?: $row['start_time'] ?: '');
                            $end = (string)($row['time_out'] ?: $row['end_time'] ?: '');
                            ?>
                            <tr>
                                <td>Jam ke-<?= e($row['period_no']) ?><br><span class="hint"><?= e(trim(($row['start_time'] ?: '-') . ' - ' . ($row['end_time'] ?: '-'))) ?></span></td>
                                <td><?= e($row['teacher_name']) ?></td>
                                <td><?= e($row['class_name']) ?></td>
                                <td><?= e($row['subject_name']) ?></td>
                                <td><select name="status[<?= e($scheduleId) ?>]"><?= options(teacher_attendance_statuses(), $row['status'] ?? 'hadir') ?></select></td>
                                <td><input type="time" name="time_in[<?= e($scheduleId) ?>]" value="<?= e(substr($start, 0, 5)) ?>"></td>
                                <td><input type="time" name="time_out[<?= e($scheduleId) ?>]" value="<?= e(substr($end, 0, 5)) ?>"></td>
                                <td><input name="notes[<?= e($scheduleId) ?>]" value="<?= e($row['notes']) ?>" placeholder="Materi/kelas pengganti/catatan"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                </div>
                <div class="actions"><button class="button primary">Simpan Kehadiran Mengajar</button></div>
            </form>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function page_journals(): void
{
    require_role(['admin', 'guru']);
    $edit = [];
    if ((int)($_GET['edit'] ?? 0) > 0) {
        $candidate = fetch_one('SELECT * FROM daily_journals WHERE id = ?', [(int)$_GET['edit']]);
        if ($candidate) {
            require_assignment_access((int)$candidate['assignment_id']);
            $edit = $candidate;
        }
    }
    $assignments = assignments_for_current_user();
    [$scope, $params] = teacher_scope_sql('j');
    $rows = fetch_all(
        "SELECT j.*, c.name AS class_name, s.name AS subject_name, t.name AS teacher_name
         FROM daily_journals j
         JOIN classes c ON c.id = j.class_id
         JOIN subjects s ON s.id = j.subject_id
         JOIN teachers t ON t.id = j.teacher_id
         WHERE 1=1 $scope
         ORDER BY j.date DESC, j.id DESC
         LIMIT 50",
        $params
    );
    render_header('Jurnal Harian');
    input_panel_start($edit ? 'Edit Jurnal' : 'Input Jurnal', 'Tambah Jurnal', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_journal"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Pembelajaran <select name="assignment_id" required><?= assignment_options($assignments, $edit['assignment_id'] ?? '') ?></select></label>
            <label>Tanggal <input type="date" name="date" required value="<?= e($edit['date'] ?? date('Y-m-d')) ?>"></label>
            <label>Pertemuan <input type="number" min="1" name="meeting_no" value="<?= e($edit['meeting_no'] ?? 1) ?>"></label>
            <label>Topik <input name="topic" required value="<?= e($edit['topic'] ?? '') ?>"></label>
            <label class="wide">Kegiatan Pembelajaran <textarea name="activities" required><?= e($edit['activities'] ?? '') ?></textarea></label>
            <label class="wide">Materi/Media <textarea name="materials"><?= e($edit['materials'] ?? '') ?></textarea></label>
            <label class="wide">Kendala <textarea name="obstacles"><?= e($edit['obstacles'] ?? '') ?></textarea></label>
            <label class="wide">Tindak Lanjut <textarea name="follow_up"><?= e($edit['follow_up'] ?? '') ?></textarea></label>
            <div class="wide actions"><button class="button primary">Simpan Jurnal</button><a class="button" href="<?= e(route_url('journals')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Jurnal Terbaru', ['Tanggal', 'Guru', 'Kelas', 'Mapel', 'Topik', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['topic']) ?></td><td><?= row_actions('journals', (int)$row['id'], 'delete_journal') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_violations(): void
{
    require_role(['admin']);
    $edit = edit_row('student_violations') ?: [];
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rows = fetch_all(
        'SELECT v.*, s.name AS student_name, s.nis, c.name AS class_name
         FROM student_violations v
         JOIN students s ON s.id = v.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         ORDER BY v.date DESC, v.id DESC'
    );
    render_header('Pelanggaran Siswa');
    input_panel_start($edit ? 'Edit Pelanggaran' : 'Input Pelanggaran', 'Tambah Pelanggaran', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_violation"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Siswa <select name="student_id" required><?= options($students, $edit['student_id'] ?? '') ?></select></label>
            <label>Tanggal <input type="date" name="date" required value="<?= e($edit['date'] ?? date('Y-m-d')) ?>"></label>
            <label>Jenis <input name="type" required value="<?= e($edit['type'] ?? '') ?>"></label>
            <label>Poin <input type="number" name="points" value="<?= e($edit['points'] ?? 0) ?>"></label>
            <label class="wide">Deskripsi <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
            <label class="wide">Tindak Lanjut <textarea name="action_taken"><?= e($edit['action_taken'] ?? '') ?></textarea></label>
            <label class="checkbox"><input type="checkbox" name="queue_whatsapp" value="1"> Tambahkan pemberitahuan WhatsApp ke antrian</label>
            <div class="wide actions"><button class="button primary">Simpan Pelanggaran</button><a class="button" href="<?= e(route_url('violations')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pelanggaran', ['Tanggal', 'Siswa', 'Kelas', 'Jenis', 'Poin', 'Tindak Lanjut', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['type']) ?></td>
        <td><?= e($row['points']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['action_taken'], 0, 80, '...')) ?></td>
        <td>
            <div class="row-actions">
                <?= row_actions('violations', (int)$row['id'], 'delete_violation') ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="queue_whatsapp_violation">
                    <input type="hidden" name="violation_id" value="<?= e($row['id']) ?>">
                    <input type="hidden" name="return_page" value="violations">
                    <button class="button small success">Kirim WA</button>
                </form>
            </div>
        </td>
    <?php }); render_footer();
}

function page_student_progress(): void
{
    require_role(['siswa']);
    $student = current_student();
    $rows = student_score_rows((int)$student['id']);
    render_header('Progres Nilai');
    ?>
    <section class="panel">
        <?php panel_title('Progres Nilai ' . (string)$student['name']); ?>
        <div class="table-wrap">
            <table><thead><tr><th>Mapel</th><th>Nilai</th><th>Capaian</th></tr></thead><tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="3" class="empty">Belum ada nilai.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr><td><?= e($row['subject_name']) ?></td><td><?= e($row['score']) ?></td><td><?= e($row['description']) ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </section>
    <?php render_footer();
}

function page_student_attendance_view(): void
{
    require_role(['siswa']);
    $student = current_student();
    $summary = attendance_summary_for_student((int)$student['id']);
    $rows = fetch_all(
        'SELECT ses.date, ses.meeting_no, ses.topic, sub.name AS subject_name, e.status, e.notes
         FROM student_attendance_entries e
         JOIN student_attendance_sessions ses ON ses.id = e.session_id
         JOIN teaching_assignments ta ON ta.id = ses.assignment_id
         JOIN subjects sub ON sub.id = ta.subject_id
         WHERE e.student_id = ?
         ORDER BY ses.date DESC, ses.meeting_no DESC',
        [(int)$student['id']]
    );
    render_header('Kehadiran Siswa');
    ?>
    <div class="metric-grid">
        <?php foreach (allowed_statuses() as $key => $label): ?>
            <div class="metric"><span><?= e($label) ?></span><strong><?= e($summary[$key] ?? 0) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php table_panel('Riwayat Kehadiran', ['Tanggal', 'Mapel', 'Pertemuan', 'Topik', 'Status', 'Catatan'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['subject_name']) ?></td>
        <td><?= e($row['meeting_no']) ?></td>
        <td><?= e($row['topic']) ?></td>
        <td><?= e(allowed_statuses()[$row['status']] ?? $row['status']) ?></td>
        <td><?= e($row['notes']) ?></td>
    <?php }); render_footer();
}

function page_student_violations(): void
{
    require_role(['siswa']);
    $student = current_student();
    $rows = student_violations((int)$student['id']);
    $total = array_sum(array_map(fn ($row) => (int)$row['points'], $rows));
    render_header('Pelanggaran Siswa');
    echo '<div class="metric-grid"><div class="metric"><span>Total Catatan</span><strong>' . e(count($rows)) . '</strong></div><div class="metric"><span>Total Poin</span><strong>' . e($total) . '</strong></div></div>';
    table_panel('Riwayat Pelanggaran', ['Tanggal', 'Jenis', 'Deskripsi', 'Poin', 'Tindak Lanjut'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['type']) ?></td>
        <td><?= e($row['description']) ?></td>
        <td><?= e($row['points']) ?></td>
        <td><?= e($row['action_taken']) ?></td>
    <?php }); render_footer();
}

function page_student_documents(): void
{
    require_role(['siswa']);
    $student = current_student();
    $grade = (int)preg_replace('/\D+/', '', (string)($student['grade'] ?? '0'));
    $graduation = fetch_one('SELECT * FROM graduations WHERE student_id = ?', [(int)$student['id']]) ?: [];
    render_header('Dokumen Kelulusan');
    ?>
    <section class="panel">
        <?php panel_title('Dokumen Kelulusan'); ?>
        <?php if ($grade < 9): ?>
            <p class="empty">Dokumen SKL, ijazah, dan transkrip dibuka untuk siswa kelas 9.</p>
        <?php else: ?>
            <div class="grid two">
                <div>
                    <p>Status: <strong><?= e($graduation['status'] ?? 'belum diinput') ?></strong></p>
                    <p>No SKL/Ijazah: <?= e($graduation['certificate_no'] ?? '-') ?></p>
                    <p>No Transkrip: <?= e($graduation['transcript_no'] ?? '-') ?></p>
                </div>
                <div class="quick-links">
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'skl'])) ?>">Download SKL</a>
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'ijazah'])) ?>">Download Ijazah</a>
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'transkrip'])) ?>">Download Transkrip Nilai</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php render_footer();
}

function page_reports(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }
    $class = $classId ? fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]) : null;
    $students = $class ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    render_header('Laporan');
    ?>
    <section class="panel no-print">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="reports">
            <label>Kelas <select name="class_id"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <div class="actions"><button class="button">Tampilkan</button><button type="button" class="button primary" onclick="window.print()">Cetak</button></div>
        </form>
    </section>
    <section class="panel print-panel">
        <h2>Rekap Rapor Kelas <?= e($class['name'] ?? '-') ?></h2>
        <div class="table-wrap">
            <table><thead><tr><th>Nama</th><th>NIS</th><th>Rata-rata Nilai</th><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpa</th></tr></thead><tbody>
                <?php foreach ($students as $student):
                    $avg = fetch_one('SELECT AVG(score) AS avg_score FROM grades WHERE student_id = ? AND score IS NOT NULL', [(int)$student['id']]);
                    $att = attendance_summary_for_student((int)$student['id']);
                ?>
                    <tr>
                        <td><?= e($student['name']) ?></td><td><?= e($student['nis']) ?></td><td><?= e($avg['avg_score'] !== null ? number_format((float)$avg['avg_score'], 2) : '-') ?></td>
                        <td><?= e($att['hadir'] ?? 0) ?></td><td><?= e($att['sakit'] ?? 0) ?></td><td><?= e($att['izin'] ?? 0) ?></td><td><?= e($att['alpa'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
    <?php
    if (is_admin()) {
        render_admin_report_panels();
    }
    render_footer();
}

function render_admin_report_panels(): void
{
    teacher_teaching_attendance_ensure_schema();
    $date = date_ymd((string)($_GET['admin_date'] ?? '2026-06-03'));
    ?>
    <section class="panel no-print">
        <?php panel_title('Report Admin'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="class_id" value="<?= e((int)($_GET['class_id'] ?? 0)) ?>">
            <label>Tanggal Absensi Mengajar <input type="date" name="admin_date" value="<?= e($date) ?>"></label>
            <div class="actions"><button class="button">Tampilkan Report Admin</button></div>
        </form>
    </section>
    <?php
    $day = teacher_teaching_day_for_date($date);
    $teacherRows = table_exists('lesson_schedules') ? fetch_all(
        'SELECT t.name, c.name AS class_name, s.name AS subject_name, ls.period_no, ls.start_time, ls.end_time,
                a.date, a.status, a.time_in, a.time_out, a.notes
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         LEFT JOIN teacher_teaching_attendance a ON a.schedule_id = ls.id AND a.date = ?
         WHERE ls.day_of_week = ?
         ORDER BY ls.period_no, t.name, c.grade, c.name, s.name',
        [$date, $day]
    ) : [];
    table_panel('Report Kehadiran Mengajar Guru', ['Guru', 'Kelas', 'Mapel', 'Jam', 'Tanggal', 'Status', 'Mulai', 'Selesai', 'Catatan'], $teacherRows, function ($row) use ($date) { ?>
        <td><?= e($row['name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['subject_name']) ?></td>
        <td>Jam ke-<?= e($row['period_no']) ?> (<?= e(($row['start_time'] ?: '-') . ' - ' . ($row['end_time'] ?: '-')) ?>)</td>
        <td><?= e($row['date'] ?: $date) ?></td>
        <td><?= e($row['status'] ? (teacher_attendance_statuses()[$row['status']] ?? $row['status']) : 'belum input') ?></td>
        <td><?= e($row['time_in']) ?></td>
        <td><?= e($row['time_out']) ?></td>
        <td><?= e($row['notes']) ?></td>
    <?php });

    $journals = fetch_all(
        'SELECT j.date, j.meeting_no, j.topic, j.activities, j.obstacles, j.follow_up, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM daily_journals j
         JOIN teachers t ON t.id = j.teacher_id
         JOIN classes c ON c.id = j.class_id
         JOIN subjects s ON s.id = j.subject_id
         ORDER BY j.date DESC, j.id DESC
         LIMIT 30'
    );
    table_panel('Report Jurnal Harian Guru', ['Tanggal', 'Guru', 'Kelas', 'Mapel', 'Pertemuan', 'Topik', 'Kegiatan', 'Tindak Lanjut'], $journals, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['teacher_name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['subject_name']) ?></td>
        <td><?= e($row['meeting_no']) ?></td>
        <td><?= e($row['topic']) ?></td>
        <td><?= e(mb_strimwidth((string)$row['activities'], 0, 90, '...')) ?></td>
        <td><?= e(mb_strimwidth((string)$row['follow_up'], 0, 80, '...')) ?></td>
    <?php });
}

function page_telegram(): void
{
    require_role(['admin']);
    $webhook = app_url('telegram_webhook.php');
    $reminderUrl = schedule_reminder_url();
    $hasReminderSecret = schedule_reminder_secret() !== '';
    $reminderMinutes = schedule_reminder_minutes_before();
    $hasToken = config('telegram.bot_token', '') !== '';
    $users = fetch_all('SELECT u.*, t.name AS teacher_name FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id ORDER BY u.role, u.name');
    $logs = fetch_all('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT 30');
    render_header('Bot Telegram');
    ?>
    <section class="panel">
        <h3>Status Bot</h3>
        <div class="grid two">
            <div>
                <p>Token bot: <?= $hasToken ? '<span class="badge ok">Terisi</span>' : '<span class="badge off">Belum diisi</span>' ?></p>
                <p>Webhook URL:</p>
                <code class="block"><?= e($webhook) ?></code>
            </div>
            <div>
                <p>Set webhook dari browser setelah token di config diisi:</p>
                <code class="block">https://api.telegram.org/botTOKEN_ANDA/setWebhook?url=<?= e(urlencode($webhook)) ?></code>
                <p class="hint">Jika memakai webhook_secret, set juga header secret melalui dashboard Bot API atau curl.</p>
            </div>
        </div>
    </section>
    <section class="panel">
        <h3>Reminder Jadwal Guru</h3>
        <div class="grid two">
            <div>
                <p>Reminder: <?= e($reminderMinutes) ?> menit sebelum jam pelajaran.</p>
                <p>Secret cron: <?= $hasReminderSecret ? '<span class="badge ok">Terisi</span>' : '<span class="badge off">Belum diisi</span>' ?></p>
                <p class="hint">Jalankan cron tiap 1 menit atau 5 menit agar guru mendapat notifikasi sebelum kelas dimulai.</p>
            </div>
            <div>
                <p>URL cron reminder:</p>
                <code class="block"><?= e($reminderUrl) ?></code>
                <p class="hint">Atau jalankan via CLI: <code>php tools/schedule_reminders.php</code></p>
            </div>
        </div>
    </section>
    <section class="panel">
        <h3>Daftar Guru via Telegram</h3>
        <div class="grid two">
            <div>
                <p>Guru bisa daftar langsung dari Telegram. Chat ID otomatis disimpan ke akun guru yang dibuat.</p>
                <code class="block">/daftar Fahmi Dwi Payana, S.H Fiqih</code>
            </div>
            <div>
                <p>Untuk mapel banyak kata atau langsung buat pembelajaran kelas, pakai pemisah garis.</p>
                <code class="block">/daftar Fahmi Dwi Payana, S.H | Bahasa Indonesia | 1A</code>
            </div>
        </div>
    </section>
    <?php table_panel('Akun dan Telegram Chat ID', ['Username', 'Nama', 'Role', 'Guru', 'Chat ID'], $users, function ($row) { ?>
        <td><?= e($row['username']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['telegram_chat_id']) ?></td>
    <?php }); ?>
    <?php table_panel('Log Telegram', ['Waktu', 'Chat ID', 'Pesan', 'Respon'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['chat_id']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 70, '...')) ?></td><td><?= e(mb_strimwidth((string)$row['response'], 0, 90, '...')) ?></td>
    <?php }); ?>
    <?php render_footer();
}

function options(array $options, mixed $selected): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $isSelected = (string)$value === (string)$selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e($label) . '</option>';
    }
    return $html;
}

function checked(mixed $value): string
{
    return (int)$value === 1 ? 'checked' : '';
}

function status_badge(int $active): string
{
    return $active ? '<span class="badge ok">Aktif</span>' : '<span class="badge off">Nonaktif</span>';
}

function row_actions(string $page, int $id, string $deleteAction): string
{
    if (!is_admin()) {
        return '<span class="hint">-</span>';
    }
    return '<div class="row-actions"><a class="button small" href="' . e(route_url($page, ['edit' => $id])) . '">Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus data ini?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($deleteAction) . '"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function table_panel(string $title, array $headers, array $rows, callable $renderer, string $actions = ''): void
{
    ?>
    <section class="panel table-panel">
        <?php panel_title($title, '', $actions); ?>
        <div class="table-wrap">
            <table>
                <thead><tr><?php foreach ($headers as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= e(count($headers)) ?>" class="empty">Belum ada data.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr><?php $renderer($row); ?></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

function map_options(string $table, string $labelColumn): array
{
    $rows = fetch_all("SELECT id, $labelColumn AS label FROM $table WHERE active = 1 ORDER BY $labelColumn");
    return array_column_map($rows, 'id', 'label');
}

function array_column_map(array $rows, string $key, string $value): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[(string)$row[$key]] = (string)$row[$value];
    }
    return $out;
}

function assignment_rows(): array
{
    return fetch_all(
        'SELECT ta.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         ORDER BY c.grade, c.name, s.name'
    );
}

function assignments_for_current_user(): array
{
    $params = [];
    $where = 'ta.active = 1';
    if (!is_admin()) {
        $where .= ' AND ta.teacher_id = ?';
        $params[] = (int)(current_user()['teacher_id'] ?? 0);
    }
    return fetch_all(
        "SELECT ta.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE $where
         ORDER BY c.grade, c.name, s.name",
        $params
    );
}

function assignment_options(array $assignments, mixed $selected): string
{
    $options = [];
    foreach ($assignments as $assignment) {
        $options[(string)$assignment['id']] = $assignment['class_name'] . ' - ' . $assignment['subject_name'] . ' - ' . $assignment['teacher_name'];
    }
    return options($options, $selected);
}

function assignment_picker(string $page, array $assignments, int $selected): void
{
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <label>Pembelajaran <select name="assignment_id"><?= assignment_options($assignments, $selected) ?></select></label>
            <div class="actions"><button class="button">Tampilkan</button></div>
        </form>
    </section>
    <?php
}

function attendance_summary_for_student(int $studentId): array
{
    $rows = fetch_all('SELECT status, COUNT(*) AS total FROM student_attendance_entries WHERE student_id = ? GROUP BY status', [$studentId]);
    $summary = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'terlambat' => 0];
    foreach ($rows as $row) {
        $summary[$row['status']] = (int)$row['total'];
    }
    return $summary;
}
