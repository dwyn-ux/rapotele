<?php declare(strict_types=1);

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
