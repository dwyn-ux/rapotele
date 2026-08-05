<?php declare(strict_types=1);

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
            'import-bulk' => ['Import Bulk (CSV)', 'import-bulk'],
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
            'teacher-attendance-self' => ['Absensi Kehadiran Guru', 'teacher-attendance-self'],
            'lesson-schedule' => ['Jadwal Pelajaran', 'lesson-schedule'],
            'journals' => ['Jurnal Harian', 'journals'],
            'violations' => ['Pelanggaran Siswa', 'violations'],
            'input-nilai-ekskul' => ['Input Nilai Ekskul', 'input-nilai-ekskul'],
            'naik-kelas' => ['Keterangan Naik Kelas', 'naik-kelas'],
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
