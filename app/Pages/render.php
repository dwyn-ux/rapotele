<?php declare(strict_types=1);

function render_header(string $title): void
{
    $user = current_user();
    $school = get_school_profile();
    $menuGroups = [
        'Menu Utama' => [
            'dashboard' => ['Dashboard', 'dashboard', 'layout-dashboard'],
            'update-data' => ['Update Data E-Rapor', 'update-data', 'database'],
        ],
        'Data Referensi' => [
            'schools' => ['Data Sekolah', 'schools', 'building'],
            'teachers' => ['Data Guru', 'teachers', 'users'],
            'students' => ['Data Siswa', 'students', 'graduation-cap'],
            'classes' => ['Data Kelas', 'classes', 'school'],
            'subjects' => ['Data Mapel', 'subjects', 'book-open'],
            'assignments' => ['Data Pembelajaran', 'assignments', 'file-text'],
            'data-ekskul' => ['Data Ekskul', 'data-ekskul', 'trophy'],
            'data-kelompok' => ['Data Kelompok Mapel', 'data-kelompok', 'layers'],
            'gabung-mapel' => ['Gabung Mapel', 'gabung-mapel', 'git-merge'],
            'data-mapping' => ['Data Mapping Rapor', 'data-mapping', 'shuffle'],
            'data-logo-ttd' => ['Data Logo dan TTD', 'data-logo-ttd', 'stamp'],
            'tanggal-rapor' => ['Data Tanggal Rapor', 'tanggal-rapor', 'calendar'],
            'foto-siswa' => ['Foto Siswa', 'foto-siswa', 'camera'],
            'import-bulk' => ['Import Bulk (CSV)', 'import-bulk', 'upload'],
        ],
        'Kokurikuler' => [
            'tema-kokurikuler' => ['Daftar Tema', 'tema-kokurikuler', 'palette'],
            'kegiatan-kokurikuler' => ['Kegiatan Kokurikuler', 'kegiatan-kokurikuler', 'target'],
            'kelompok-kokurikuler' => ['Kelompok Kokurikuler', 'kelompok-kokurikuler', 'users'],
        ],
        'Penilaian' => [
            'data-tp' => ['Tujuan Pembelajaran', 'data-tp', 'compass'],
            'status-penilaian' => ['Status Penilaian', 'status-penilaian', 'bar-chart'],
            'grades' => ['Input Nilai', 'grades', 'pencil'],
            'deskripsi-nilai' => ['Deskripsi Nilai', 'deskripsi-nilai', 'file-text'],
            'student-attendance' => ['Absensi Siswa/Mapel', 'student-attendance', 'clipboard-check'],
            'teacher-attendance' => ['Absensi Mengajar', 'teacher-attendance', 'check-square'],
            'teacher-attendance-self' => ['Absensi Kehadiran Guru', 'teacher-attendance-self', 'clock'],
            'lesson-schedule' => ['Jadwal Pelajaran', 'lesson-schedule', 'calendar-days'],
            'journals' => ['Jurnal Harian', 'journals', 'book'],
            'violations' => ['Pelanggaran Siswa', 'violations', 'alert-triangle'],
            'violation-rules' => ['Pasal & SP', 'violation-rules', 'book-open'],
            'input-nilai-ekskul' => ['Input Nilai Ekskul', 'input-nilai-ekskul', 'award'],
            'naik-kelas' => ['Keterangan Naik Kelas', 'naik-kelas', 'arrow-up-circle'],
        ],
        'Cetak Rapor' => [
            'cetak-pelengkap-rapor' => ['Cetak Biodata', 'cetak-pelengkap-rapor', 'file-text'],
            'laporan-belajar' => ['Laporan Hasil Belajar', 'laporan-belajar', 'file-check'],
            'cetak-nilai-rapor' => ['Cetak Rapor', 'cetak-nilai-rapor', 'printer'],
            'cetak-leger-rapor' => ['Leger Rapor', 'cetak-leger-rapor', 'list'],
            'cetak-leger-pts' => ['Leger PTS', 'cetak-leger-pts', 'list'],
            'cetak-nilai-rapor-pts' => ['Rapor PTS', 'cetak-nilai-rapor-pts', 'file'],
            'cetak-buku-induk' => ['Buku Induk', 'cetak-buku-induk', 'book-open'],
            'reports' => ['Laporan Ringkas', 'reports', 'pie-chart'],
        ],
        'Ijazah & SKL' => [
            'input-kelulusan' => ['Input Kelulusan', 'input-kelulusan', 'check-circle'],
            'import-nomor-ijazah' => ['Import Nomor Ijazah', 'import-nomor-ijazah', 'hash'],
            'setting-transkrip' => ['Setting Transkrip', 'setting-transkrip', 'settings'],
            'setting-skl' => ['Setting SKL', 'setting-skl', 'settings'],
            'mapping-mapel-skl' => ['Mapping Mapel SKL', 'mapping-mapel-skl', 'shuffle'],
            'input-nilai-skl' => ['Input Nilai Akhir', 'input-nilai-skl', 'edit-3'],
            'cetak-skl' => ['Cetak SKL', 'cetak-skl', 'printer'],
            'cetak-transkrip-ijazah' => ['Cetak Transkrip', 'cetak-transkrip-ijazah', 'file'],
        ],
        'Integrasi' => [
            'kirim-data-dapodik' => ['Kirim Data Dapodik', 'kirim-data-dapodik', 'cloud-upload'],
            'backup-restore' => ['Backup dan Restore', 'backup-restore', 'hard-drive'],
            'telegram' => ['Bot Telegram', 'telegram', 'send'],
            'whatsapp' => ['WhatsApp Report', 'whatsapp', 'message-circle'],
        ],
        'Administrasi' => [
            'bulk-delete' => ['Hapus Data Massal', 'bulk-delete', 'trash-2'],
        ],
        'Pengaturan' => [],
    ];

    if (user_role() === 'siswa') {
        $menuGroups = [
            'Portal Siswa' => [
                'dashboard' => ['Dashboard', 'dashboard', 'layout-dashboard'],
                'lesson-schedule' => ['Jadwal Pelajaran', 'lesson-schedule', 'calendar-days'],
                'student-progress' => ['Progres Nilai', 'student-progress', 'trending-up'],
                'student-attendance-view' => ['Kehadiran', 'student-attendance-view', 'check-circle'],
                'student-violations' => ['Pelanggaran', 'student-violations', 'alert-triangle'],
                'student-documents' => ['Dokumen Kelulusan', 'student-documents', 'file-text'],
            ],
            'Pengaturan' => [
                'profile' => ['Profil Pengguna', 'profile', 'user'],
            ],
        ];
    }

    if (!is_admin()) {
        foreach ([
            'update-data', 'school', 'teachers', 'classes', 'subjects', 'data-ekskul', 'data-kelompok',
            'gabung-mapel', 'data-mapping', 'data-logo-ttd', 'tanggal-rapor', 'foto-siswa',
            'tema-kokurikuler', 'kegiatan-kokurikuler', 'kelompok-kokurikuler', 'input-kelulusan',
            'import-nomor-ijazah', 'setting-transkrip', 'setting-skl', 'mapping-mapel-skl',
            'input-nilai-skl', 'kirim-data-dapodik', 'backup-restore', 'whatsapp', 'users',
            'import-bulk', 'bulk-delete', 'violation-rules',
        ] as $adminPage) {
            foreach ($menuGroups as &$groupItems) {
                unset($groupItems[$adminPage]);
            }
            unset($groupItems);
        }
    } else {
        $menuGroups['Pengaturan']['users'] = ['Data Pengguna', 'users', 'shield'];
        if (!function_exists('is_bk') || !is_bk()) {
            foreach ($menuGroups as &$groupItems) {
                unset($groupItems['violations']);
            }
            unset($groupItems);
        }
    }
    $menuGroups['Pengaturan']['profile'] = ['Profil Pengguna', 'profile', 'user'];

    $active = (string)($_GET['page'] ?? 'dashboard');
    $appLogoUrl = signature_media_url('logo');

    // Determine user initials for avatar
    $userName = $user['name'] ?? 'U';
    $nameParts = explode(' ', trim($userName));
    $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($nameParts), 0, 1));
    }
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light only">
        <title><?= e($title) ?> — <?= e(config('app_name')) ?></title>
        <?php if ($appLogoUrl !== ''): ?>
            <link rel="icon" href="<?= e($appLogoUrl) ?>">
            <link rel="apple-touch-icon" href="<?= e($appLogoUrl) ?>">
            <link rel="preload" as="image" href="<?= e($appLogoUrl) ?>">
        <?php endif; ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
        <link rel="stylesheet" href="<?= e(asset_version_url('assets/app.css')) ?>">
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar">
            <button type="button" class="sidebar-close" data-sidebar-close aria-label="Tutup menu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="brand">
                <?= render_app_mark() ?>
                <div class="brand-info">
                    <strong><?= e(config('app_name')) ?></strong>
                    <span><?= e(current_academic_year()) ?> · <?= e(current_semester()) ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="menu">
                    <?php foreach ($menuGroups as $groupLabel => $items): ?>
                        <?php if (!$items) {
                            continue;
                        } ?>
                        <?php $groupOpen = array_key_exists($active, $items); ?>
                        <details class="menu-group" <?= $groupOpen ? 'open' : '' ?>>
                            <summary>
                                <span><?= e($groupLabel) ?></span>
                                <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </summary>
                            <div class="submenu">
                                <?php foreach ($items as $page => [$label, $urlPage, $icon]): ?>
                                    <a class="<?= $active === $page ? 'active' : '' ?>" href="<?= e(route_url($urlPage)) ?>">
                                        <i data-lucide="<?= e($icon) ?>"></i>
                                        <?= e($label) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </nav>
            <div class="sidebar-help">
                <div class="sidebar-help-title">Butuh Bantuan?</div>
                <p>Hubungi admin atau lihat panduan penggunaan.</p>
                <a href="<?= e(route_url('panduan')) ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Lihat Panduan
                </a>
            </div>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-close></div>
        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="mobile-menu-button" data-sidebar-open aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false">
                        <span class="bottom-icon menu-lines"><span></span><span></span><span></span></span>
                    </button>
                    <div class="topbar-breadcrumb">
                        <span><?= e($school['name'] ?? config('school.name')) ?></span>
                        <span>/</span>
                        <h1><?= e($title) ?></h1>
                    </div>
                </div>
                <div class="topbar-search">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Cari data, menu, atau fitur..." aria-label="Cari">
                    <span class="search-shortcut">Ctrl K</span>
                </div>
                <div class="topbar-right">
                    <button type="button" class="topbar-icon-btn" aria-label="Notifikasi" title="Notifikasi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </button>
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-trigger" data-user-dropdown>
                            <div class="user-avatar"><?= e($initials) ?></div>
                            <div class="user-info">
                                <span class="user-name"><?= e($user['name']) ?></span>
                                <span class="user-role"><?= e($user['role']) ?></span>
                            </div>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <a href="<?= e(route_url('profile')) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Profil
                            </a>
                            <a href="<?= e(route_url('profile')) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                Pengaturan
                            </a>
                            <div class="divider"></div>
                            <form method="post" action="<?= e(route_url('logout')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="logout-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>
            <div class="page-content">
                <?php render_flash(); ?>
    <?php
}

function render_footer(): void
{
    $user = current_user();
    ?>
            </div><!-- .page-content -->
            <footer class="footer">
                <span>&copy; <?= e(date('Y')) ?> <?= e(config('app_name')) ?> — Sistem Administrasi Akademik Sekolah</span>
                <span>v2.0</span>
            </footer>
        </div><!-- .main -->
    </div><!-- .app-shell -->
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
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(asset_version_url('assets/app.js')) ?>"></script>
    <script>try{lucide.createIcons();}catch(e){console.warn('Lucide icons skipped:',e.message);}</script>
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
        <div class="panel-title">
            <?php if ($icon !== ''): ?>
                <svg class="panel-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            <?php endif; ?>
            <span><?= e($title) ?></span>
        </div>
        <?php if ($actions !== ''): ?>
            <div class="panel-actions"><?= $actions ?></div>
        <?php endif; ?>
    </div>
    <?php
}

function input_panel_start(string $title, string $addLabel, bool $open = false): void
{
    $button = '<button type="button" class="button primary input-panel-toggle" data-toggle-label="' . e($addLabel) . '" data-close-label="Tutup Form"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> ' . e($open ? 'Tutup Form' : $addLabel) . '</button>';
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
        $iconMap = [
            'success' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'danger' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            'warning' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            'info' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        ];
        $icon = $iconMap[$flash['type']] ?? $iconMap['info'];
        echo '<div class="alert ' . e($flash['type']) . '">' . $icon . '<span>' . e($flash['message']) . '</span></div>';
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
        <meta name="color-scheme" content="light only">
        <title><?= e($title) ?> — <?= e(config('app_name')) ?></title>
        <?php if ($appLogoUrl !== ''): ?>
            <link rel="icon" href="<?= e($appLogoUrl) ?>">
            <link rel="apple-touch-icon" href="<?= e($appLogoUrl) ?>">
            <link rel="preload" as="image" href="<?= e($appLogoUrl) ?>">
        <?php endif; ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
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

function current_academic_year(): string
{
    return (string)(get_school_profile()['academic_year'] ?? config('school.academic_year', '2025/2026'));
}

function current_semester(): string
{
    return (string)(get_school_profile()['semester'] ?? config('school.semester', 'Ganjil'));
}

function current_semester_number(): string
{
    return str_contains(strtolower(current_semester()), 'genap') ? '2' : '1';
}
