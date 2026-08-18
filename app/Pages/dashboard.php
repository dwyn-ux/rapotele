<?php declare(strict_types=1);

function page_dashboard(): void
{
    if (user_role() === 'siswa') {
        page_student_dashboard();
        return;
    }

    $user = current_user();
    $cards = [
        ['Guru', (int)db()->query('SELECT COUNT(*) FROM teachers WHERE active = 1')->fetchColumn(), 'blue', 'users'],
        ['Siswa', (int)db()->query('SELECT COUNT(*) FROM students WHERE active = 1')->fetchColumn(), 'green', 'graduation-cap'],
        ['Kelas', (int)db()->query('SELECT COUNT(*) FROM classes WHERE active = 1')->fetchColumn(), 'violet', 'school'],
        ['Pembelajaran', (int)db()->query('SELECT COUNT(*) FROM teaching_assignments WHERE active = 1')->fetchColumn(), 'amber', 'book-open'],
    ];

    // Check if default password is still in use
    $hasDefaultPassword = ($user['password'] ?? '') !== '' && (
        password_verify('admin123', (string)($user['password'] ?? '')) ||
        password_verify('guru123', (string)($user['password'] ?? '')) ||
        password_verify('siswa123', (string)($user['password'] ?? ''))
    );

    render_header('Dashboard');
    ?>

    <!-- Welcome Section -->
    <section class="hero">
        <div>
            <div class="eyebrow">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </div>
            <h2>Selamat datang kembali, <?= e($user['name']) ?> 👋</h2>
            <p>Kelola data akademik, penilaian, kehadiran, jurnal, dan administrasi sekolah dari satu tempat.</p>
        </div>
    </section>

    <?php if ($hasDefaultPassword): ?>
    <div class="alert password-warning">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="alert-body">
            <div class="alert-title">Keamanan akun perlu diperhatikan</div>
            <div class="alert-desc">Anda masih menggunakan password default/lemah. Segera ubah password untuk menjaga keamanan akun.</div>
        </div>
        <a href="<?= e(route_url('profile')) ?>" class="button warning" style="white-space:nowrap;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Ganti Password
        </a>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="metric-grid">
        <?php foreach ($cards as [$label, $value, $color, $icon]): ?>
            <div class="metric">
                <div class="metric-icon <?= e($color) ?>">
                    <i data-lucide="<?= e($icon) ?>"></i>
                </div>
                <div class="metric-body">
                    <div class="metric-label">Jumlah <?= e($label) ?> Aktif</div>
                    <div class="metric-value"><?= e($value) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Two Column Layout -->
    <div class="grid two">
        <!-- Activity Timeline -->
        <section class="panel">
            <div class="panel-heading">
                <div class="panel-title">
                    <svg class="panel-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <span>Aktivitas Terbaru</span>
                </div>
            </div>
            <?php $logs = fetch_all('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT 8'); ?>
            <?php if (!$logs): ?>
                <div class="empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <p>Belum ada aktivitas terbaru.</p>
                </div>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($logs as $log): ?>
                        <div class="list-row" style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:var(--primary-lighter);display:grid;place-items:center;color:var(--primary);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:600;color:var(--text);font-size:13px;"><?= e($log['chat_id']) ?></div>
                                <div style="color:var(--text-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e(mb_strimwidth((string)$log['message'], 0, 80, '...')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Quick Actions -->
        <section class="panel">
            <div class="panel-heading">
                <div class="panel-title">
                    <svg class="panel-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Shortcut Guru</span>
                </div>
            </div>
            <div class="quick-links" style="grid-template-columns: 1fr;">
                <a href="<?= e(route_url('student-attendance')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Input Absensi Siswa</div>
                        <div style="font-size:12px;color:var(--text-muted);">Catat kehadiran siswa per mata pelajaran</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="<?= e(route_url('teacher-attendance')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Absensi Mengajar</div>
                        <div style="font-size:12px;color:var(--text-muted);">Rekam kehadiran guru saat mengajar</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="<?= e(route_url('journals')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Tulis Jurnal</div>
                        <div style="font-size:12px;color:var(--text-muted);">Dokumentasi kegiatan harian mengajar</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="<?= e(route_url('grades')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Input Nilai</div>
                        <div style="font-size:12px;color:var(--text-muted);">Masukkan nilai siswa per pembelajaran</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="<?= e(route_url('lesson-schedule')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><rect x="7" y="14" width="3" height="3"/><rect x="14" y="14" width="3" height="3"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Jadwal Mengajar</div>
                        <div style="font-size:12px;color:var(--text-muted);">Lihat jadwal pelajaran mingguan</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="<?= e(route_url('cetak-nilai-rapor')) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Cetak Rapor</div>
                        <div style="font-size:12px;color:var(--text-muted);">Generate dan cetak rapor siswa</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
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
            <div class="eyebrow">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Portal Siswa
            </div>
            <h2>Selamat datang, <?= e($student['name']) ?> 👋</h2>
            <p>Kelas <?= e($student['class_name'] ?? '-') ?>. Pantau progres nilai, kehadiran, pelanggaran, dan dokumen kelulusan dari akun ini.</p>
        </div>
    </section>

    <div class="metric-grid">
        <div class="metric">
            <div class="metric-icon blue"><i data-lucide="trending-up"></i></div>
            <div class="metric-body">
                <div class="metric-label">Rata-rata Nilai</div>
                <div class="metric-value"><?= e($avg !== null ? number_format($avg, 2) : '-') ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon green"><i data-lucide="check-circle"></i></div>
            <div class="metric-body">
                <div class="metric-label">Hadir</div>
                <div class="metric-value"><?= e($attendance['hadir'] ?? 0) ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon amber"><i data-lucide="x-circle"></i></div>
            <div class="metric-body">
                <div class="metric-label">Alpa</div>
                <div class="metric-value"><?= e($attendance['alpa'] ?? 0) ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon violet"><i data-lucide="alert-triangle"></i></div>
            <div class="metric-body">
                <div class="metric-label">Pelanggaran</div>
                <div class="metric-value"><?= e(count($violations)) ?></div>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <svg class="panel-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>Akses Cepat</span>
            </div>
        </div>
        <div class="quick-links">
            <a href="<?= e(route_url('student-progress')) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                <span>Lihat Progres Nilai</span>
            </a>
            <a href="<?= e(route_url('student-attendance-view')) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>Lihat Kehadiran</span>
            </a>
            <a href="<?= e(route_url('student-violations')) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Lihat Pelanggaran</span>
            </a>
            <a href="<?= e(route_url('student-documents')) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>Dokumen Kelulusan</span>
            </a>
        </div>
    </section>

    <?php render_footer();
}
