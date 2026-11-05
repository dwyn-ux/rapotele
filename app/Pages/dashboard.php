<?php declare(strict_types=1);

function page_dashboard(): void
{
    if (user_role() === 'siswa') {
        page_student_dashboard();
        return;
    }

    if (user_role() === 'pengawas') {
        page_pengawas_dashboard();
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

    <!-- Live Classroom & Attendance Monitor Widget (Hanya Admin & Kepsek di Dashboard Umum) -->
    <?php if (in_array(user_role(), ['admin', 'kepsek'], true)): ?>
        <?php render_live_monitoring_widget(); ?>
    <?php endif; ?>

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

function page_pengawas_dashboard(): void
{
    require_role(['pengawas', 'admin', 'kepsek']);
    $user = current_user();
    render_header('Live Monitoring KBM');
    ?>
    <section class="hero" style="margin-bottom: 1.25rem;">
        <div>
            <div class="eyebrow" style="color: #38bdf8;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Portal Pengawas Sekolah
            </div>
            <h2>Live Pemantauan KBM &amp; Presensi Sekolah</h2>
            <p>Selamat datang, <?= e($user['name'] ?? 'Pengawas') ?>. Pantau kehadiran guru mengajar dan presensi siswa di seluruh kelas secara real-time.</p>
        </div>
    </section>

    <?php
    render_live_monitoring_widget();
    render_footer();
}

function get_live_monitoring_data(): array
{
    require_once APP_ROOT . '/app/Services/schedule.php';

    $today = (int)date('N');
    $date = date('Y-m-d');
    $timeNow = date('H:i');

    // Fetch all active classes in proper natural school order (SMP 7-9 then SMA 10-12)
    $classOrder = function_exists('class_order_by_clause') ? class_order_by_clause() : 'display_order ASC, name ASC';
    $classes = fetch_all("SELECT id, name, grade, level, display_order FROM classes ORDER BY {$classOrder}");

    // Fetch all schedules for today
    $schedules = fetch_all(
        'SELECT ls.id, ls.assignment_id, ls.teacher_id, ls.class_id, ls.subject_id, ls.day_of_week, ls.period_no, ls.start_time, ls.end_time,
                t.name as teacher_name, c.name as class_name, c.level as class_level, s.name as subject_name
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         WHERE ls.day_of_week = ?
         ORDER BY ls.period_no ASC, c.name ASC',
        [$today]
    );

    // Get today's classroom sessions
    $sessions = fetch_all(
        'SELECT id, assignment_id, meeting_no, teacher_attended, teacher_status, teacher_time_in, teacher_lat, teacher_lng, teacher_notes
         FROM student_attendance_sessions
         WHERE date = ?',
        [$date]
    );
    $sessionByAssignment = [];
    $sessionIds = [];
    foreach ($sessions as $sess) {
        $sessionByAssignment[(int)$sess['assignment_id']] = $sess;
        $sessionIds[] = (int)$sess['id'];
    }

    $studentCountsBySession = [];
    if (!empty($sessionIds)) {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $entries = fetch_all(
            "SELECT session_id, status, COUNT(*) as count 
             FROM student_attendance_entries 
             WHERE session_id IN ($placeholders) 
             GROUP BY session_id, status",
            $sessionIds
        );
        foreach ($entries as $e) {
            $sid = (int)$e['session_id'];
            $st = strtolower((string)$e['status']);
            $studentCountsBySession[$sid][$st] = (int)$e['count'];
        }
    }

    $teachingAtt = fetch_all('SELECT * FROM teacher_teaching_attendance WHERE date = ?', [$date]);
    $teachingAttBySchedule = [];
    foreach ($teachingAtt as $ta) {
        $teachingAttBySchedule[(int)$ta['schedule_id']] = $ta;
    }

    // Index schedules by class_id and period_no
    $schedulesByClass = [];
    $periodsToday = [];
    $activePeriodNow = null;

    foreach ($schedules as $row) {
        $p = (int)$row['period_no'];
        $start = substr((string)$row['start_time'], 0, 5);
        $end = substr((string)$row['end_time'], 0, 5);

        if (!isset($periodsToday[$p])) {
            $periodsToday[$p] = [
                'period_no' => $p,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        // Check if this period is active right now
        if ($timeNow >= $start && $timeNow <= $end) {
            $activePeriodNow = $p;
        }

        $schedId = (int)$row['id'];
        $asgnId = (int)$row['assignment_id'];
        $classId = (int)$row['class_id'];

        $timeStatus = 'upcoming';
        if ($timeNow >= $start && $timeNow <= $end) {
            $timeStatus = 'ongoing';
        } elseif ($timeNow > $end) {
            $timeStatus = 'finished';
        }

        $sess = $sessionByAssignment[$asgnId] ?? null;
        $tAtt = $teachingAttBySchedule[$schedId] ?? null;

        $isCheckedIn = false;
        $checkinTime = '';
        $checkinStatus = '';
        $checkinNotes = '';
        $hasGps = false;
        $lat = null;
        $lng = null;
        $studentStats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0];
        $meetingNo = 1;

        if ($sess && (!empty($sess['teacher_attended']) || !empty($sess['teacher_time_in']))) {
            $isCheckedIn = true;
            $meetingNo = (int)($sess['meeting_no'] ?? 1);
            $checkinTime = (string)($sess['teacher_time_in'] ?: '');
            $checkinStatus = (string)($sess['teacher_status'] ?: 'hadir');
            $checkinNotes = (string)($sess['teacher_notes'] ?: '');
            if (!empty($sess['teacher_lat']) && !empty($sess['teacher_lng'])) {
                $hasGps = true;
                $lat = (float)$sess['teacher_lat'];
                $lng = (float)$sess['teacher_lng'];
            }
            if (isset($studentCountsBySession[(int)$sess['id']])) {
                $studentStats = array_merge($studentStats, $studentCountsBySession[(int)$sess['id']]);
            }
        } elseif ($tAtt && !empty($tAtt['status']) && $tAtt['status'] !== 'belum input') {
            $isCheckedIn = true;
            $checkinTime = (string)($tAtt['time_in'] ?: '');
            $checkinStatus = (string)$tAtt['status'];
            $checkinNotes = (string)($tAtt['notes'] ?: '');
        }

        $schedulesByClass[$classId][$p] = [
            'id' => $schedId,
            'assignment_id' => $asgnId,
            'period_no' => $p,
            'start_time' => $start,
            'end_time' => $end,
            'time_status' => $timeStatus,
            'subject_name' => $row['subject_name'],
            'teacher_name' => $row['teacher_name'],
            'meeting_no' => $meetingNo,
            'is_checked_in' => $isCheckedIn,
            'checkin_time' => $checkinTime,
            'checkin_status' => $checkinStatus,
            'checkin_notes' => $checkinNotes,
            'has_gps' => $hasGps,
            'lat' => $lat,
            'lng' => $lng,
            'student_stats' => $studentStats,
            'attendance_url' => route_url('student-attendance', ['assignment_id' => $asgnId, 'date' => $date, 'meeting_no' => $meetingNo]),
        ];
    }

    ksort($periodsToday);

    // Build class cards data
    $classCards = [];
    $totalOngoingClasses = 0;
    $totalPresentTeachers = 0;
    $totalAbsentAlerts = 0;
    $totalHadirStudents = 0;
    $totalSakitStudents = 0;
    $totalIzinStudents = 0;
    $totalAlpaStudents = 0;

    $availablePeriodKeys = array_keys($periodsToday);
    $latestPeriodToday = !empty($availablePeriodKeys) ? end($availablePeriodKeys) : 1;

    foreach ($classes as $c) {
        $cid = (int)$c['id'];
        $cName = $c['name'];
        $cLevel = function_exists('class_level_from_grade_or_name') 
            ? class_level_from_grade_or_name($c['level'] ?? '', null, $cName)
            : ($c['level'] ?: 'SMP');

        $classSlots = $schedulesByClass[$cid] ?? [];

        // Sum student attendance for this class today across all sessions
        $classStudentSum = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0];
        foreach ($classSlots as $slot) {
            $classStudentSum['hadir'] += $slot['student_stats']['hadir'];
            $classStudentSum['sakit'] += $slot['student_stats']['sakit'];
            $classStudentSum['izin'] += $slot['student_stats']['izin'];
            $classStudentSum['alpa'] += $slot['student_stats']['alpa'];
        }
        $totalHadirStudents += $classStudentSum['hadir'];
        $totalSakitStudents += $classStudentSum['sakit'];
        $totalIzinStudents += $classStudentSum['izin'];
        $totalAlpaStudents += $classStudentSum['alpa'];

        // Find active slot right now
        $currentSlot = null;
        if ($activePeriodNow && isset($classSlots[$activePeriodNow])) {
            $currentSlot = $classSlots[$activePeriodNow];
        } else {
            foreach ($classSlots as $s) {
                if ($s['time_status'] === 'ongoing') {
                    $currentSlot = $s;
                    break;
                }
            }
        }

        // Find next slot if no current slot
        $nextSlot = null;
        if (!$currentSlot) {
            foreach ($classSlots as $s) {
                if ($s['time_status'] === 'upcoming') {
                    $nextSlot = $s;
                    break;
                }
            }
        }

        if ($currentSlot) {
            $totalOngoingClasses++;
            if ($currentSlot['is_checked_in']) {
                $totalPresentTeachers++;
            } else {
                $totalAbsentAlerts++;
            }
        }

        $classCards[] = [
            'class_id' => $cid,
            'class_name' => $cName,
            'class_level' => $cLevel,
            'has_schedule_today' => !empty($classSlots),
            'slots' => $classSlots,
            'current_slot' => $currentSlot,
            'next_slot' => $nextSlot,
            'latest_slot' => !empty($classSlots) ? end($classSlots) : null,
            'class_student_sum' => $classStudentSum,
        ];
    }

    return [
        'ok' => true,
        'server_time' => date('H:i:s'),
        'date_formatted' => schedule_days()[$today] . ', ' . date('d/m/Y'),
        'active_period_now' => $activePeriodNow,
        'latest_period_today' => $latestPeriodToday,
        'periods_list' => array_values($periodsToday),
        'summary' => [
            'total_classes' => count($classes),
            'total_ongoing' => $totalOngoingClasses,
            'total_checked_in' => $totalPresentTeachers,
            'total_alert_not_checked_in' => $totalAbsentAlerts,
            'students_hadir' => $totalHadirStudents,
            'students_sakit' => $totalSakitStudents,
            'students_izin' => $totalIzinStudents,
            'students_alpa' => $totalAlpaStudents,
        ],
        'class_cards' => $classCards,
    ];
}

function page_live_monitor_feed(): void
{
    require_role(['admin', 'kepsek', 'pengawas']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(get_live_monitoring_data(), JSON_UNESCAPED_UNICODE);
    exit;
}

function render_live_monitoring_widget(): void
{
    $initialData = get_live_monitoring_data();
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $feedUrl = $scriptName . '?page=live-monitor-feed';
    $initSummary = $initialData['summary'] ?? [];
    ?>
    <style>
    .live-monitor-panel {
        background: #ffffff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
        margin-bottom: 2rem;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .live-monitor-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #ffffff;
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .live-dot-pulse {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        animation: livePulseAnim 1.6s infinite;
        vertical-align: middle;
        margin-right: 8px;
    }
    @keyframes livePulseAnim {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .live-monitor-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        padding: 10px 18px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }
    .live-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .live-stat-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .live-filters-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 18px;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
        flex-wrap: wrap;
    }
    .live-filters-group {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .live-filter-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .live-filter-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .live-filter-btn.active {
        background: #0284c7;
        border-color: #0284c7;
        color: #ffffff;
    }
    .live-select-period {
        font-size: 0.78rem;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #0f172a;
        font-weight: 600;
        cursor: pointer;
    }

    /* Cards Container */
    .live-cards-container {
        padding: 14px 18px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* Level Section */
    .live-level-section {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .live-level-title-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 2px 2px;
    }
    .live-level-pill {
        font-size: 0.72rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .pill-smp {
        background: #0284c7;
        color: #ffffff;
    }
    .pill-sma {
        background: #7c3aed;
        color: #ffffff;
    }
    .live-level-title-text {
        font-size: 0.82rem;
        font-weight: 700;
        color: #334155;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    .live-level-line {
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    /* Subgrids for SMP and SMA */
    .live-cards-row {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(var(--cols, 6), minmax(0, 1fr));
    }
    .live-cards-row.grid-smp {
        grid-template-columns: repeat(var(--cols, 6), minmax(0, 1fr));
    }
    .live-cards-row.grid-sma {
        grid-template-columns: repeat(var(--cols, 5), minmax(0, 1fr));
    }

    @media (max-width: 900px) {
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-smp { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-sma { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
    }
    @media (max-width: 640px) {
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-smp { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-sma { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    }
    @media (max-width: 420px) {
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-smp { grid-template-columns: 1fr !important; }
        .live-monitor-panel:not(.is-fullscreen) .live-cards-row.grid-sma { grid-template-columns: 1fr !important; }
    }

    /* Compact Class Card */
    .live-class-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 10px 12px;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
        border: 2px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 8px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        min-height: 155px;
        box-sizing: border-box;
    }
    .live-class-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px -2px rgba(15, 23, 42, 0.08);
    }

    /* Red Card (Teacher absent during active class) */
    .live-class-card.status-alert {
        border-color: #ef4444;
        background: linear-gradient(180deg, #fff5f5 0%, #ffffff 40%);
        box-shadow: 0 3px 10px rgba(239, 68, 68, 0.08);
    }

    /* Green Card (Teacher present) */
    .live-class-card.status-present {
        border-color: #10b981;
        background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 40%);
        box-shadow: 0 3px 10px rgba(16, 185, 129, 0.08);
    }

    /* Neutral Card (Break / Outside hours) */
    .live-class-card.status-neutral {
        border-color: #e2e8f0;
        background: #ffffff;
    }

    /* Card Header */
    .live-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 6px;
    }
    .live-card-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 6px;
        line-height: 1.2;
    }
    .live-badge-level {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 4px;
        text-transform: uppercase;
    }
    .level-smp {
        background: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }
    .level-sma {
        background: #ede9fe;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .live-card-period {
        font-size: 0.68rem;
        font-weight: 600;
        color: #64748b;
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }

    /* Status Banner Pill */
    .live-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        width: 100%;
        box-sizing: border-box;
        height: 24px;
        justify-content: center;
    }
    .live-status-pill.pill-alert {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        animation: cardAlertGlow 1.8s infinite;
    }
    @keyframes cardAlertGlow {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.75; }
    }
    .live-status-pill.pill-present {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .live-status-pill.pill-neutral {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    /* Lesson info */
    .live-lesson-info {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        padding: 6px 8px;
        display: flex;
        flex-direction: column;
        gap: 3px;
        font-size: 0.78rem;
    }
    .live-info-row {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .live-info-row svg {
        flex-shrink: 0;
        color: #64748b;
    }
    .live-info-row span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .live-info-row strong {
        color: #0f172a;
    }

    /* Student attendance numbers strip (Numbers only + 1-letter labels) */
    .live-student-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
    }
    .live-st-box {
        border-radius: 6px;
        padding: 4px 2px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px solid;
    }
    .live-st-num {
        font-size: 0.96rem;
        font-weight: 800;
        line-height: 1;
    }
    .live-st-lbl {
        font-size: 0.6rem;
        font-weight: 700;
        margin-top: 1px;
        opacity: 0.9;
        text-transform: uppercase;
    }
    .st-box-h {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #15803d;
    }
    .st-box-s {
        background: #f0f9ff;
        border-color: #bae6fd;
        color: #0369a1;
    }
    .st-box-i {
        background: #fffbeb;
        border-color: #fde68a;
        color: #b45309;
    }
    .st-box-a {
        background: #fef2f2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    /* Card Footer */
    .live-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 4px;
        border-top: 1px dashed #e2e8f0;
    }
    .live-btn-detail {
        font-size: 0.72rem;
        font-weight: 600;
        color: #0284c7;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 5px;
        border-radius: 4px;
    }
    .live-btn-detail:hover {
        background: #e0f2fe;
    }

    /* Fullscreen / 1 Layar Penuh (TV Wallboard / Command Center Mode) */
    .live-monitor-panel.is-fullscreen {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 999999 !important;
        margin: 0 !important;
        border-radius: 0 !important;
        border: none !important;
        background: #0b1120 !important;
        display: flex !important;
        flex-direction: column !important;
        box-shadow: none !important;
    }
    .live-monitor-panel.is-fullscreen .live-monitor-header {
        padding: 8px 18px !important;
        background: #0f172a !important;
        border-bottom: 1px solid #1e293b !important;
        flex-shrink: 0 !important;
    }
    .live-monitor-panel.is-fullscreen .live-monitor-stats {
        padding: 6px 18px !important;
        background: #0f172a !important;
        border-bottom: 1px solid #1e293b !important;
        gap: 8px !important;
        flex-shrink: 0 !important;
    }
    .live-monitor-panel.is-fullscreen .live-stat-card {
        background: #1e293b !important;
        border-color: #334155 !important;
        padding: 5px 10px !important;
    }
    .live-monitor-panel.is-fullscreen .live-stat-card div:last-child div:first-child {
        color: #94a3b8 !important;
    }
    .live-monitor-panel.is-fullscreen .live-stat-card div:last-child div:last-child {
        color: #f8fafc !important;
    }
    .live-monitor-panel.is-fullscreen .live-filters-bar {
        padding: 6px 18px !important;
        background: #0f172a !important;
        border-bottom: 1px solid #1e293b !important;
        flex-shrink: 0 !important;
    }
    .live-monitor-panel.is-fullscreen .live-filters-bar span {
        color: #94a3b8 !important;
    }
    .live-monitor-panel.is-fullscreen .live-filter-btn {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #cbd5e1 !important;
    }
    .live-monitor-panel.is-fullscreen .live-filter-btn:hover {
        background: #334155 !important;
        color: #ffffff !important;
    }
    .live-monitor-panel.is-fullscreen .live-filter-btn.active {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }
    .live-monitor-panel.is-fullscreen .live-select-period {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }
    .live-monitor-panel.is-fullscreen .live-cards-container {
        flex: 1 !important;
        height: auto !important;
        padding: 10px 18px !important;
        background: #0b1120 !important;
        overflow: hidden !important;
        justify-content: space-evenly !important;
    }
    .live-monitor-panel.is-fullscreen .live-level-title-text {
        color: #cbd5e1 !important;
    }
    .live-monitor-panel.is-fullscreen .live-level-line {
        background: #334155 !important;
    }
    .live-monitor-panel.is-fullscreen .live-cards-row.grid-smp {
        grid-template-columns: repeat(var(--cols, 6), minmax(0, 1fr)) !important;
    }
    .live-monitor-panel.is-fullscreen .live-cards-row.grid-sma {
        grid-template-columns: repeat(var(--cols, 5), minmax(0, 1fr)) !important;
    }
    .live-monitor-panel.is-fullscreen .live-class-card {
        height: 100% !important;
        box-sizing: border-box !important;
    }
    </style>

    <div class="live-monitor-panel">
        <!-- Header Panel -->
        <div class="live-monitor-header">
            <div>
                <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; letter-spacing: 0.3px;">
                    <span class="live-dot-pulse"></span>
                    <span>LIVE MONITORING KBM &amp; PRESENSI</span>
                </div>
                <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 2px; display: flex; align-items: center; gap: 8px;">
                    <span id="live-header-date"><?= htmlspecialchars((string)($initialData['date_formatted'] ?? 'Hari Ini')) ?></span>
                    <span>&bull;</span>
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span id="live-header-time"><?= htmlspecialchars((string)($initialData['server_time'] ?? date('H:i:s'))) ?></span>
                    </span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="font-size: 0.78rem; color: #cbd5e1; background: rgba(255,255,255,0.08); padding: 4px 10px; border-radius: 6px; display: flex; align-items: center; gap: 5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Refresh: <strong id="live-countdown" style="color:#38bdf8;">10</strong>s</span>
                </div>
                <button type="button" id="btn-toggle-auto" class="button small" style="background: rgba(255,255,255,0.15); border: none; color: #ffffff; padding: 4px 10px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px;" title="Jeda/Lanjutkan Update Otomatis">
                    <span id="btn-toggle-auto-icon">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                    </span>
                    <span id="btn-toggle-auto-text">Jeda</span>
                </button>
                <button type="button" id="btn-manual-refresh" class="button small primary" style="background: #0284c7; border: none; font-size: 0.78rem; padding: 4px 11px; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px;" title="Refresh Sekarang">
                    <svg id="icon-manual-refresh" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                    <span>Refresh</span>
                </button>
                <button type="button" id="btn-fullscreen-toggle" class="button small" style="background: #3b82f6; border: none; color: #ffffff; padding: 4px 11px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px; border-radius: 6px; font-weight: 700;" title="Buka Mode 1 Layar Penuh (TV Monitor)">
                    <span id="btn-fullscreen-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                    </span>
                    <span id="btn-fullscreen-text">1 Layar Penuh</span>
                </button>
            </div>
        </div>

        <!-- Metric Counter Cards -->
        <div class="live-monitor-stats">
            <div class="live-stat-card">
                <div class="live-stat-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">Kelas Aktif</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #0f172a;" id="stat-ongoing"><?= (int)($initSummary['total_ongoing'] ?? 0) ?></div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon-wrap" style="background: #dcfce7; color: #16a34a;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">Guru Hadir</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #16a34a;" id="stat-checked-in"><?= (int)($initSummary['total_checked_in'] ?? 0) ?> / <?= (int)($initSummary['total_ongoing'] ?? ($initSummary['total_classes'] ?? 0)) ?></div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon-wrap" style="background: #fee2e2; color: #dc2626;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">Belum Masuk</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #dc2626;" id="stat-alert"><?= (int)($initSummary['total_alert_not_checked_in'] ?? 0) ?></div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon-wrap" style="background: #fef3c7; color: #d97706;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">Siswa Hadir Hari Ini</div>
                    <div style="font-size: 1rem; font-weight: 800; color: #0f172a;" id="stat-students">
                        <span style="color:#16a34a;"><?= (int)($initSummary['students_hadir'] ?? 0) ?> H</span> &nbsp;&bull;&nbsp;
                        <span style="color:#0284c7;"><?= (int)($initSummary['students_sakit'] ?? 0) ?> S</span> &nbsp;&bull;&nbsp;
                        <span style="color:#d97706;"><?= (int)($initSummary['students_izin'] ?? 0) ?> I</span> &nbsp;&bull;&nbsp;
                        <span style="color:#dc2626;"><?= (int)($initSummary['students_alpa'] ?? 0) ?> A</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Period Bar -->
        <div class="live-filters-bar">
            <div class="live-filters-group">
                <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-right: 2px;">Jenjang:</span>
                <button type="button" class="live-filter-btn active" data-level="all">Semua Kelas</button>
                <button type="button" class="live-filter-btn" data-level="SMP">SMP</button>
                <button type="button" class="live-filter-btn" data-level="SMA">SMA</button>

                <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-left: 8px; margin-right: 2px;">Status:</span>
                <button type="button" class="live-filter-btn active" data-status="all">Semua</button>
                <button type="button" class="live-filter-btn" data-status="alert" style="color: #b91c1c;">Belum Hadir</button>
                <button type="button" class="live-filter-btn" data-status="present" style="color: #15803d;">Hadir</button>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="live-period-select" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Jam Pelajaran:</label>
                <select id="live-period-select" class="live-select-period">
                    <option value="current">⚡ Jam Saat Ini (Otomatis)</option>
                </select>
            </div>
        </div>

        <!-- Cards Container (Structured by School Units) -->
        <div class="live-cards-container" id="live-cards-container">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: livePulseAnim 1s infinite; margin-bottom: 6px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <div>Memuat pemantauan kelas...</div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var initialMonitorData = <?= json_encode($initialData, JSON_UNESCAPED_UNICODE) ?>;
        var feedUrl = (window.location.pathname || 'index.php') + '?page=live-monitor-feed';
        var countdownSec = 10;
        var curTimer = 10;
        var isPaused = false;
        var isTabHidden = false;
        var activeLevel = 'all';
        var activeStatus = 'all';
        var selectedPeriod = 'current'; // 'current' or integer period_no

        var cachedCards = initialMonitorData.class_cards || [];
        var cachedPeriods = initialMonitorData.periods_list || [];
        var activePeriodNow = initialMonitorData.active_period_now;
        var latestPeriodToday = initialMonitorData.latest_period_today;
        var intervalId = null;

        var countdownEl = document.getElementById('live-countdown');
        var btnToggleAuto = document.getElementById('btn-toggle-auto');
        var btnToggleIcon = document.getElementById('btn-toggle-auto-icon');
        var btnToggleText = document.getElementById('btn-toggle-auto-text');
        var btnManual = document.getElementById('btn-manual-refresh');
        var cardsContainer = document.getElementById('live-cards-container');
        var dateEl = document.getElementById('live-header-date');
        var timeEl = document.getElementById('live-header-time');
        var periodSelect = document.getElementById('live-period-select');

        var svgPlay = '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
        var svgPause = '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
        var svgCheck = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
        var svgAlert = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        var svgUser = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        var svgBook = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
        var svgClock = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
        var svgPin = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
        var svgExtLink = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';

        function fetchFeed() {
            var icon = document.getElementById('icon-manual-refresh');
            if (icon) icon.style.animation = 'livePulseAnim 0.6s infinite';

            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    if (icon) icon.style.animation = '';
                    if (!data || !data.ok) return;

                    cachedCards = data.class_cards || [];
                    cachedPeriods = data.periods_list || [];
                    activePeriodNow = data.active_period_now;
                    latestPeriodToday = data.latest_period_today;

                    if (dateEl) dateEl.textContent = data.date_formatted || '';
                    if (timeEl) timeEl.textContent = data.server_time || '';

                    // Update summary numbers
                    var sm = data.summary || {};
                    var elOngoing = document.getElementById('stat-ongoing');
                    var elChecked = document.getElementById('stat-checked-in');
                    var elAlert = document.getElementById('stat-alert');
                    var elStudents = document.getElementById('stat-students');

                    if (elOngoing) elOngoing.textContent = sm.total_ongoing || 0;
                    if (elChecked) elChecked.textContent = (sm.total_checked_in || 0) + ' / ' + (sm.total_ongoing || sm.total_classes || 0);
                    if (elAlert) elAlert.textContent = sm.total_alert_not_checked_in || 0;
                    if (elStudents) {
                        elStudents.innerHTML = '<span style="color:#16a34a;">' + (sm.students_hadir || 0) + ' H</span> &nbsp;&bull;&nbsp; ' +
                            '<span style="color:#0284c7;">' + (sm.students_sakit || 0) + ' S</span> &nbsp;&bull;&nbsp; ' +
                            '<span style="color:#d97706;">' + (sm.students_izin || 0) + ' I</span> &nbsp;&bull;&nbsp; ' +
                            '<span style="color:#dc2626;">' + (sm.students_alpa || 0) + ' A</span>';
                    }

                    updatePeriodDropdown();
                    renderCards();
                })
                .catch(function(err){
                    if (icon) icon.style.animation = '';
                    console.error('Live feed error:', err);
                });
        }

        function updatePeriodDropdown() {
            if (!periodSelect) return;
            var currentVal = periodSelect.value;
            var html = '<option value="current">⚡ Jam Saat Ini ' + (activePeriodNow ? '(Jam ke-' + activePeriodNow + ')' : '(Otomatis)') + '</option>';
            cachedPeriods.forEach(function(p){
                var isSelected = (currentVal == p.period_no) ? ' selected' : '';
                html += '<option value="' + p.period_no + '"' + isSelected + '>Jam ke-' + p.period_no + ' (' + p.start_time + ' - ' + p.end_time + ')</option>';
            });
            periodSelect.innerHTML = html;
            if (currentVal) periodSelect.value = currentVal;
        }

        function renderCardHtml(card, targetPeriod) {
            // Determine slot to show
            var slot = null;
            var isLiveCurrent = false;

            if (selectedPeriod === 'current') {
                if (card.current_slot) {
                    slot = card.current_slot;
                    isLiveCurrent = true;
                } else if (card.next_slot) {
                    slot = card.next_slot;
                } else if (card.latest_slot) {
                    slot = card.latest_slot;
                }
            } else if (targetPeriod && card.slots && card.slots[targetPeriod]) {
                slot = card.slots[targetPeriod];
            }

            // Determine Card State: Alert (Red), Present (Green), Neutral (Slate)
            var cardClass = 'status-neutral';
            var statusPill = '';
            var teacherName = '-';
            var subjectName = '-';
            var periodLabel = 'Tidak ada KBM';
            var studentStats = { hadir: 0, sakit: 0, izin: 0, alpa: 0 };
            var attUrl = '#';

            if (slot) {
                teacherName = slot.teacher_name || '-';
                subjectName = slot.subject_name || '-';
                periodLabel = 'Jam ' + slot.period_no + ' &bull; ' + slot.start_time;
                studentStats = slot.student_stats || { hadir: 0, sakit: 0, izin: 0, alpa: 0 };
                attUrl = slot.attendance_url || '#';

                if (slot.is_checked_in) {
                    cardClass = 'status-present';
                    var inTime = slot.checkin_time ? ' (' + slot.checkin_time.substring(0, 5) + ')' : '';
                    var gpsIcon = slot.has_gps ? ' ' + svgPin : '';
                    statusPill = '<div class="live-status-pill pill-present">' +
                        svgCheck +
                        '<span>GURU HADIR' + inTime + gpsIcon + '</span>' +
                        '</div>';
                } else if (slot.time_status === 'ongoing' || isLiveCurrent) {
                    cardClass = 'status-alert';
                    statusPill = '<div class="live-status-pill pill-alert">' +
                        svgAlert +
                        '<span>BELUM HADIR (ALERT)</span>' +
                        '</div>';
                } else if (slot.time_status === 'upcoming') {
                    cardClass = 'status-neutral';
                    statusPill = '<div class="live-status-pill pill-neutral">' +
                        svgClock +
                        '<span>JAM BERIKUTNYA</span>' +
                        '</div>';
                } else {
                    cardClass = 'status-neutral';
                    statusPill = '<div class="live-status-pill pill-neutral">' +
                        svgClock +
                        '<span>SELESAI (TANPA ABSEN)</span>' +
                        '</div>';
                }
            } else {
                statusPill = '<div class="live-status-pill pill-neutral">' +
                    svgClock +
                    '<span>KOSONG / ISTIRAHAT</span>' +
                    '</div>';
            }

            var levelClass = (card.class_level.toUpperCase() === 'SMA') ? 'level-sma' : 'level-smp';

            var cHtml = '<div class="live-class-card ' + cardClass + '">';
            
            // Head
            cHtml += '<div class="live-card-head">';
            cHtml += '<div class="live-card-title">';
            cHtml += '<span>Kelas ' + escapeHtml(card.class_name) + '</span>';
            cHtml += '<span class="live-badge-level ' + levelClass + '">' + escapeHtml(card.class_level) + '</span>';
            cHtml += '</div>';
            cHtml += '<div class="live-card-period">' + periodLabel + '</div>';
            cHtml += '</div>';

            // Status Banner
            cHtml += statusPill;

            // Lesson Info
            cHtml += '<div class="live-lesson-info">';
            cHtml += '<div class="live-info-row" title="Guru: ' + escapeHtml(teacherName) + '">' + svgUser + '<span>Guru: <strong>' + escapeHtml(teacherName) + '</strong></span></div>';
            cHtml += '<div class="live-info-row" title="Mapel: ' + escapeHtml(subjectName) + '">' + svgBook + '<span>Mapel: <strong>' + escapeHtml(subjectName) + '</strong></span></div>';
            cHtml += '</div>';

            // Student Attendance Strip (Numbers only with 1-letter labels)
            cHtml += '<div class="live-student-strip">';
            cHtml += '<div class="live-st-box st-box-h" title="Siswa Hadir: ' + (studentStats.hadir || 0) + '"><span class="live-st-num">' + (studentStats.hadir || 0) + '</span><span class="live-st-lbl">Hadir</span></div>';
            cHtml += '<div class="live-st-box st-box-s" title="Siswa Sakit: ' + (studentStats.sakit || 0) + '"><span class="live-st-num">' + (studentStats.sakit || 0) + '</span><span class="live-st-lbl">Sakit</span></div>';
            cHtml += '<div class="live-st-box st-box-i" title="Siswa Izin: ' + (studentStats.izin || 0) + '"><span class="live-st-num">' + (studentStats.izin || 0) + '</span><span class="live-st-lbl">Izin</span></div>';
            cHtml += '<div class="live-st-box st-box-a" title="Siswa Alpa: ' + (studentStats.alpa || 0) + '"><span class="live-st-num">' + (studentStats.alpa || 0) + '</span><span class="live-st-lbl">Alpa</span></div>';
            cHtml += '</div>';

            // Foot
            cHtml += '<div class="live-card-foot">';
            cHtml += '<span style="font-size: 0.7rem; color: #94a3b8;">Presensi</span>';
            if (attUrl && attUrl !== '#') {
                cHtml += '<a href="' + attUrl + '" class="live-btn-detail" title="Buka Detail Presensi"><span>Detail Siswa</span>' + svgExtLink + '</a>';
            } else {
                cHtml += '<span style="font-size: 0.7rem; color: #cbd5e1;">Belum Ada Sesi</span>';
            }
            cHtml += '</div>';

            cHtml += '</div>';
            return cHtml;
        }

        function renderCards() {
            if (!cardsContainer) return;

            // Determine which period we are displaying for each card
            var targetPeriod = null;
            if (selectedPeriod === 'current') {
                targetPeriod = activePeriodNow || latestPeriodToday;
            } else {
                targetPeriod = parseInt(selectedPeriod, 10);
            }

            // Filter classes by level and status
            var visibleCards = cachedCards.filter(function(card){
                // Level filter
                if (activeLevel !== 'all') {
                    if (card.class_level.toUpperCase() !== activeLevel.toUpperCase()) return false;
                }

                // Get target slot for this card
                var slot = null;
                if (selectedPeriod === 'current' && card.current_slot) {
                    slot = card.current_slot;
                } else if (targetPeriod && card.slots && card.slots[targetPeriod]) {
                    slot = card.slots[targetPeriod];
                }

                // Status filter
                if (activeStatus === 'alert') {
                    if (!slot || slot.is_checked_in) return false;
                } else if (activeStatus === 'present') {
                    if (!slot || !slot.is_checked_in) return false;
                }

                return true;
            });

            if (visibleCards.length === 0) {
                cardsContainer.innerHTML = '<div style="text-align: center; padding: 3rem; color: #64748b;">' +
                    '<div style="font-weight: 600; margin-bottom: 4px;">Tidak ada kelas yang cocok dengan filter saat ini.</div>' +
                    '<div style="font-size: 0.8rem;">Coba ubah filter jenjang atau pilih jam pelajaran lain.</div>' +
                    '</div>';
                return;
            }

            // Group into SMP and SMA
            var smpCards = [];
            var smaCards = [];
            visibleCards.forEach(function(c){
                if (c.class_level.toUpperCase() === 'SMA') {
                    smaCards.push(c);
                } else {
                    smpCards.push(c);
                }
            });

            var html = '';

            // Section SMP
            if (smpCards.length > 0) {
                var smpCols = Math.max(1, smpCards.length);
                html += '<div class="live-level-section">';
                html += '<div class="live-level-title-bar">';
                html += '<span class="live-level-pill pill-smp">SMP</span>';
                html += '<span class="live-level-title-text">Kelas SMP (' + smpCards.length + ' Kelas)</span>';
                html += '<span class="live-level-line"></span>';
                html += '</div>';
                html += '<div class="live-cards-row grid-smp" style="--cols: ' + smpCols + '; grid-template-columns: repeat(' + smpCols + ', minmax(0, 1fr));">';
                smpCards.forEach(function(c){ html += renderCardHtml(c, targetPeriod); });
                html += '</div>';
                html += '</div>';
            }

            // Section SMA
            if (smaCards.length > 0) {
                var smaCols = Math.max(1, smaCards.length);
                var topMargin = (smpCards.length > 0) ? ' style="margin-top: 12px;"' : '';
                html += '<div class="live-level-section"' + topMargin + '>';
                html += '<div class="live-level-title-bar">';
                html += '<span class="live-level-pill pill-sma">SMA</span>';
                html += '<span class="live-level-title-text">Kelas SMA (' + smaCards.length + ' Kelas)</span>';
                html += '<span class="live-level-line"></span>';
                html += '</div>';
                html += '<div class="live-cards-row grid-sma" style="--cols: ' + smaCols + '; grid-template-columns: repeat(' + smaCols + ', minmax(0, 1fr));">';
                smaCards.forEach(function(c){ html += renderCardHtml(c, targetPeriod); });
                html += '</div>';
                html += '</div>';
            }

            cardsContainer.innerHTML = html;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Timer Loop
        function startTimer() {
            if (intervalId) clearInterval(intervalId);
            intervalId = setInterval(function(){
                if (isPaused || isTabHidden) return;
                curTimer--;
                if (countdownEl) countdownEl.textContent = curTimer;
                if (curTimer <= 0) {
                    curTimer = countdownSec;
                    fetchFeed();
                }
            }, 1000);
        }

        // Smart Visibility Handler (Stop requesting server when user leaves tab)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                isTabHidden = true;
            } else {
                isTabHidden = false;
                curTimer = countdownSec;
                if (countdownEl) countdownEl.textContent = curTimer;
                fetchFeed();
            }
        });

        // Filter button handlers
        document.querySelectorAll('.live-filter-btn[data-level]').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.live-filter-btn[data-level]').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                activeLevel = btn.getAttribute('data-level') || 'all';
                renderCards();
            });
        });

        document.querySelectorAll('.live-filter-btn[data-status]').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.live-filter-btn[data-status]').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                activeStatus = btn.getAttribute('data-status') || 'all';
                renderCards();
            });
        });

        if (periodSelect) {
            periodSelect.addEventListener('change', function(){
                selectedPeriod = periodSelect.value;
                renderCards();
            });
        }

        if (btnToggleAuto) {
            btnToggleAuto.addEventListener('click', function(){
                isPaused = !isPaused;
                if (btnToggleText) {
                    btnToggleText.textContent = isPaused ? 'Lanjutkan' : 'Jeda';
                }
                if (btnToggleIcon) {
                    btnToggleIcon.innerHTML = isPaused ? svgPlay : svgPause;
                }
                btnToggleAuto.style.background = isPaused ? 'rgba(239,68,68,0.35)' : 'rgba(255,255,255,0.15)';
            });
        }

        if (btnManual) {
            btnManual.addEventListener('click', function(){
                curTimer = countdownSec;
                if (countdownEl) countdownEl.textContent = curTimer;
                fetchFeed();
            });
        }

        // Fullscreen Mode Handler
        var btnFullscreen = document.getElementById('btn-fullscreen-toggle');
        var btnFsText = document.getElementById('btn-fullscreen-text');
        var btnFsIcon = document.getElementById('btn-fullscreen-icon');
        var panelEl = document.querySelector('.live-monitor-panel');

        var svgMaximize = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>';
        var svgMinimize = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6m0 0v6m0-6L3 21m17-7h-6m0 0v6m0-6l7 7M14 4h6m0 0v6m0-6l-7 7M10 4H4m0 0v6m0-6l7 7"/></svg>';

        function toggleFullscreen() {
            if (!panelEl) return;
            var isFs = panelEl.classList.toggle('is-fullscreen');
            if (btnFsText) btnFsText.textContent = isFs ? '✕ Keluar' : '1 Layar Penuh';
            if (btnFsIcon) btnFsIcon.innerHTML = isFs ? svgMinimize : svgMaximize;
            if (btnFullscreen) {
                btnFullscreen.style.background = isFs ? '#dc2626' : '#3b82f6';
            }

            if (isFs) {
                if (panelEl.requestFullscreen) {
                    panelEl.requestFullscreen().catch(function(){});
                }
            } else {
                if (document.fullscreenElement && document.exitFullscreen) {
                    document.exitFullscreen().catch(function(){});
                }
            }
        }

        if (btnFullscreen) {
            btnFullscreen.addEventListener('click', toggleFullscreen);
        }

        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement && panelEl && panelEl.classList.contains('is-fullscreen')) {
                panelEl.classList.remove('is-fullscreen');
                if (btnFsText) btnFsText.textContent = '1 Layar Penuh';
                if (btnFsIcon) btnFsIcon.innerHTML = svgMaximize;
                if (btnFullscreen) btnFullscreen.style.background = '#3b82f6';
            }
        });

        // Keyboard shortcut 'F' to toggle fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT')) return;
            if (e.key === 'f' || e.key === 'F') {
                toggleFullscreen();
            }
        });

        // Immediate render with zero network delay
        updatePeriodDropdown();
        renderCards();
        startTimer();

        // Silent background refresh
        fetchFeed();
    })();
    </script>
    <?php
}
