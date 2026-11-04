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

    <!-- Live Classroom & Attendance Monitor Widget -->
    <?php render_live_monitoring_widget(); ?>

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

function page_live_monitor_feed(): void
{
    require_role(['admin', 'guru']);
    header('Content-Type: application/json; charset=utf-8');

    $today = (int)date('N');
    $date = date('Y-m-d');
    $timeNow = date('H:i');

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

    $rows = [];
    $totalOngoing = 0;
    $totalCheckedIn = 0;
    $totalAlertNotCheckedIn = 0;
    $totalStudentsHadir = 0;
    $totalStudentsSakit = 0;
    $totalStudentsIzin = 0;
    $totalStudentsAlpa = 0;

    foreach ($schedules as $row) {
        $schedId = (int)$row['id'];
        $asgnId = (int)$row['assignment_id'];
        $start = substr((string)$row['start_time'], 0, 5);
        $end = substr((string)$row['end_time'], 0, 5);

        $timeStatus = 'upcoming';
        if ($timeNow >= $start && $timeNow <= $end) {
            $timeStatus = 'ongoing';
            $totalOngoing++;
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
                $totalStudentsHadir += $studentStats['hadir'];
                $totalStudentsSakit += $studentStats['sakit'];
                $totalStudentsIzin += $studentStats['izin'];
                $totalStudentsAlpa += $studentStats['alpa'];
            }
        } elseif ($tAtt && !empty($tAtt['status']) && $tAtt['status'] !== 'belum input') {
            $isCheckedIn = true;
            $checkinTime = (string)($tAtt['time_in'] ?: '');
            $checkinStatus = (string)$tAtt['status'];
            $checkinNotes = (string)($tAtt['notes'] ?: '');
        }

        if ($isCheckedIn) {
            $totalCheckedIn++;
        } elseif ($timeStatus === 'ongoing') {
            $totalAlertNotCheckedIn++;
        }

        $level = function_exists('class_level_from_grade_or_name') 
            ? class_level_from_grade_or_name($row['class_level'] ?? '', null, $row['class_name'] ?? '')
            : ($row['class_level'] ?: '');

        $rows[] = [
            'id' => $schedId,
            'assignment_id' => $asgnId,
            'period_no' => (int)$row['period_no'],
            'start_time' => $start,
            'end_time' => $end,
            'time_status' => $timeStatus,
            'class_name' => $row['class_name'],
            'class_level' => $level,
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

    echo json_encode([
        'ok' => true,
        'server_time' => date('H:i:s'),
        'date_formatted' => schedule_days()[$today] . ', ' . date('d/m/Y'),
        'summary' => [
            'total_slots' => count($schedules),
            'total_checked_in' => $totalCheckedIn,
            'total_ongoing' => $totalOngoing,
            'total_alert_not_checked_in' => $totalAlertNotCheckedIn,
            'students_hadir' => $totalStudentsHadir,
            'students_sakit' => $totalStudentsSakit,
            'students_izin' => $totalStudentsIzin,
            'students_alpa' => $totalStudentsAlpa,
        ],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function render_live_monitoring_widget(): void
{
    $feedUrl = route_url('live-monitor-feed');
    ?>
    <style>
    .live-monitor-panel {
        background: #ffffff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .live-monitor-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #ffffff;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
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
        margin-right: 6px;
    }
    @keyframes livePulseAnim {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .live-monitor-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 12px;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }
    .live-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .live-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .live-filters {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
        overflow-x: auto;
    }
    .live-filter-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .live-filter-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
    .live-filter-btn.active {
        background: #0284c7;
        border-color: #0284c7;
        color: #ffffff;
    }
    .live-table-wrap {
        max-height: 440px;
        overflow-y: auto;
    }
    .live-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }
    .live-table th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        padding: 10px 14px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        z-index: 2;
    }
    .live-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .live-table tr:hover {
        background: #f8fafc;
    }
    .live-badge-ongoing {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        animation: pulseWarning 1.5s infinite;
    }
    @keyframes pulseWarning {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.65; }
    }
    .live-badge-checked {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .live-badge-upcoming {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .live-badge-finished {
        background: #f8fafc;
        color: #94a3b8;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }
    </style>

    <div class="live-monitor-panel">
        <div class="live-monitor-header">
            <div>
                <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center;">
                    <span class="live-dot-pulse"></span>
                    <span>LIVE MONITORING KBM &amp; PRESENSI</span>
                </div>
                <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 2px;">
                    <span id="live-header-date">Memuat data...</span> &bull; Server time: <span id="live-header-time">--:--:--</span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="font-size: 0.8rem; color: #cbd5e1; background: rgba(255,255,255,0.08); padding: 4px 10px; border-radius: 6px; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                    <span>Update dlm: <strong id="live-countdown" style="color:#38bdf8;">10</strong>s</span>
                </div>
                <button type="button" id="btn-toggle-auto" class="button small" style="background: rgba(255,255,255,0.15); border: none; color: #ffffff; padding: 4px 10px; font-size: 0.8rem;" title="Jeda/Lanjutkan Update Otomatis">
                    <span id="btn-toggle-auto-text">⏸️ Jeda</span>
                </button>
                <button type="button" id="btn-manual-refresh" class="button small primary" style="background: #0284c7; border: none; font-size: 0.8rem; padding: 4px 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <i data-lucide="refresh-cw" id="icon-manual-refresh" style="width: 14px; height: 14px;"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <div class="live-monitor-stats">
            <div class="live-stat-card">
                <div class="live-stat-icon" style="background: #e0f2fe; color: #0284c7;">🏫</div>
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Kelas Berlangsung</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;" id="stat-ongoing">0</div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon" style="background: #dcfce7; color: #16a34a;">👨‍🏫</div>
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Guru Hadir di Kelas</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #16a34a;" id="stat-checked-in">0</div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon" style="background: #fee2e2; color: #dc2626;">🚨</div>
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Belum Masuk Kelas</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #dc2626;" id="stat-alert">0</div>
                </div>
            </div>
            <div class="live-stat-card">
                <div class="live-stat-icon" style="background: #fef3c7; color: #d97706;">🎓</div>
                <div>
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Kehadiran Siswa Hari Ini</div>
                    <div style="font-size: 0.88rem; font-weight: 700; color: #1e293b;" id="stat-students">
                        <span style="color:#16a34a;">0 H</span> &bull; <span style="color:#0284c7;">0 S</span> &bull; <span style="color:#d97706;">0 I</span> &bull; <span style="color:#dc2626;">0 A</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="live-filters">
            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b; margin-right: 4px;">Filter:</span>
            <button type="button" class="live-filter-btn active" data-filter="all">Semua Sesi (<span id="filter-cnt-all">0</span>)</button>
            <button type="button" class="live-filter-btn" data-filter="ongoing">Sedang Berlangsung (<span id="filter-cnt-ongoing">0</span>)</button>
            <button type="button" class="live-filter-btn" data-filter="alert" style="color: #b91c1c;">Belum Check-in (<span id="filter-cnt-alert">0</span>)</button>
            <button type="button" class="live-filter-btn" data-filter="checked">Sudah Hadir (<span id="filter-cnt-checked">0</span>)</button>
            <button type="button" class="live-filter-btn" data-filter="upcoming">Jam Berikutnya</button>
        </div>

        <div class="live-table-wrap">
            <table class="live-table">
                <thead>
                    <tr>
                        <th style="width: 130px;">Jam &amp; Waktu</th>
                        <th style="width: 140px;">Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengampu</th>
                        <th style="width: 200px;">Status Kehadiran Guru</th>
                        <th style="width: 160px;">Presensi Siswa</th>
                        <th style="width: 90px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="live-table-body">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                            Memuat data aktivitas KBM hari ini...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function(){
        var feedUrl = <?= json_encode($feedUrl) ?>;
        var countdownSec = 10;
        var curTimer = 10;
        var isPaused = false;
        var activeFilter = 'all';
        var cachedRows = [];
        var intervalId = null;

        var countdownEl = document.getElementById('live-countdown');
        var btnToggleAuto = document.getElementById('btn-toggle-auto');
        var btnToggleText = document.getElementById('btn-toggle-auto-text');
        var btnManual = document.getElementById('btn-manual-refresh');
        var tableBody = document.getElementById('live-table-body');
        var dateEl = document.getElementById('live-header-date');
        var timeEl = document.getElementById('live-header-time');

        function fetchFeed() {
            var icon = document.getElementById('icon-manual-refresh');
            if (icon) icon.style.animation = 'livePulseAnim 0.6s infinite';

            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    if (icon) icon.style.animation = '';
                    if (!data || !data.ok) return;

                    cachedRows = data.rows || [];
                    if (dateEl) dateEl.textContent = data.date_formatted || '';
                    if (timeEl) timeEl.textContent = data.server_time || '';

                    // Update summary
                    var sm = data.summary || {};
                    var elOngoing = document.getElementById('stat-ongoing');
                    var elChecked = document.getElementById('stat-checked-in');
                    var elAlert = document.getElementById('stat-alert');
                    var elStudents = document.getElementById('stat-students');

                    if (elOngoing) elOngoing.textContent = sm.total_ongoing || 0;
                    if (elChecked) elChecked.textContent = (sm.total_checked_in || 0) + ' / ' + (sm.total_slots || 0);
                    if (elAlert) elAlert.textContent = sm.total_alert_not_checked_in || 0;
                    if (elStudents) {
                        elStudents.innerHTML = '<span style="color:#16a34a;">' + (sm.students_hadir || 0) + ' H</span> &bull; ' +
                            '<span style="color:#0284c7;">' + (sm.students_sakit || 0) + ' S</span> &bull; ' +
                            '<span style="color:#d97706;">' + (sm.students_izin || 0) + ' I</span> &bull; ' +
                            '<span style="color:#dc2626;">' + (sm.students_alpa || 0) + ' A</span>';
                    }

                    // Update filter counts
                    var cntAlert = 0;
                    var cntChecked = 0;
                    var cntOngoing = 0;
                    cachedRows.forEach(function(r){
                        if (r.time_status === 'ongoing') cntOngoing++;
                        if (r.is_checked_in) cntChecked++;
                        if (r.time_status === 'ongoing' && !r.is_checked_in) cntAlert++;
                    });

                    var elFCntAll = document.getElementById('filter-cnt-all');
                    var elFCntOngoing = document.getElementById('filter-cnt-ongoing');
                    var elFCntAlert = document.getElementById('filter-cnt-alert');
                    var elFCntChecked = document.getElementById('filter-cnt-checked');
                    if (elFCntAll) elFCntAll.textContent = cachedRows.length;
                    if (elFCntOngoing) elFCntOngoing.textContent = cntOngoing;
                    if (elFCntAlert) elFCntAlert.textContent = cntAlert;
                    if (elFCntChecked) elFCntChecked.textContent = cntChecked;

                    renderRows();
                })
                .catch(function(err){
                    if (icon) icon.style.animation = '';
                    console.error('Live feed error:', err);
                });
        }

        function renderRows() {
            if (!tableBody) return;
            var filtered = cachedRows.filter(function(r){
                if (activeFilter === 'ongoing') return r.time_status === 'ongoing';
                if (activeFilter === 'alert') return r.time_status === 'ongoing' && !r.is_checked_in;
                if (activeFilter === 'checked') return r.is_checked_in;
                if (activeFilter === 'upcoming') return r.time_status === 'upcoming';
                return true;
            });

            if (filtered.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">Tidak ada sesi KBM yang cocok dengan filter ini.</td></tr>';
                return;
            }

            var html = '';
            filtered.forEach(function(r){
                var rowBg = '';
                if (r.time_status === 'ongoing' && !r.is_checked_in) {
                    rowBg = 'background: #fff1f2;';
                } else if (r.time_status === 'ongoing' && r.is_checked_in) {
                    rowBg = 'background: #f0fdf4;';
                }

                // Status Badge
                var statusBadge = '';
                if (r.is_checked_in) {
                    var gpsIcon = r.has_gps ? ' <span title="Lokasi GPS terdeteksi (' + r.lat.toFixed(4) + ', ' + r.lng.toFixed(4) + ')">📍</span>' : '';
                    statusBadge = '<span class="live-badge-checked">✓ Hadir ' + (r.checkin_time ? '(' + r.checkin_time.substring(0,5) + ')' : '') + gpsIcon + '</span>';
                } else if (r.time_status === 'ongoing') {
                    statusBadge = '<span class="live-badge-ongoing">🚨 Belum Masuk Kelas</span>';
                } else if (r.time_status === 'upcoming') {
                    statusBadge = '<span class="live-badge-upcoming">⏳ Akan Datang</span>';
                } else {
                    statusBadge = '<span class="live-badge-finished">Selesai (Tanpa Check-in)</span>';
                }

                // Student Stats
                var stHtml = '<span style="color:#94a3b8;font-size:0.78rem;">-</span>';
                var st = r.student_stats;
                var totalSt = (st.hadir || 0) + (st.sakit || 0) + (st.izin || 0) + (st.alpa || 0);
                if (totalSt > 0) {
                    stHtml = '<span style="font-size:0.78rem;font-weight:600;">' +
                        '<span style="color:#16a34a;">' + st.hadir + 'H</span> ' +
                        '<span style="color:#0284c7;">' + st.sakit + 'S</span> ' +
                        '<span style="color:#d97706;">' + st.izin + 'I</span> ' +
                        '<span style="color:#dc2626;">' + st.alpa + 'A</span>' +
                        '</span>';
                }

                var levelBadge = r.class_level ? ' <span style="font-size:0.7rem;padding:1px 5px;border-radius:4px;background:#e0f2fe;color:#0369a1;font-weight:700;">' + escapeHtml(r.class_level) + '</span>' : '';

                html += '<tr style="' + rowBg + '">';
                html += '<td><strong>Jam ke-' + r.period_no + '</strong><br><span style="font-size:0.75rem;color:#64748b;">' + r.start_time + ' - ' + r.end_time + '</span></td>';
                html += '<td><strong>' + escapeHtml(r.class_name) + '</strong>' + levelBadge + '</td>';
                html += '<td>' + escapeHtml(r.subject_name) + '</td>';
                html += '<td><strong>' + escapeHtml(r.teacher_name) + '</strong></td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td>' + stHtml + '</td>';
                html += '<td style="text-align:center;"><a href="' + r.attendance_url + '" class="button small secondary" style="padding:3px 8px;font-size:0.75rem;" title="Buka Detail Absensi Kelas">Buka</a></td>';
                html += '</tr>';
            });

            tableBody.innerHTML = html;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Timer loop
        function startTimer() {
            if (intervalId) clearInterval(intervalId);
            intervalId = setInterval(function(){
                if (isPaused) return;
                curTimer--;
                if (countdownEl) countdownEl.textContent = curTimer;
                if (curTimer <= 0) {
                    curTimer = countdownSec;
                    fetchFeed();
                }
            }, 1000);
        }

        // Filter button clicks
        document.querySelectorAll('.live-filter-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.live-filter-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                activeFilter = btn.getAttribute('data-filter') || 'all';
                renderRows();
            });
        });

        if (btnToggleAuto) {
            btnToggleAuto.addEventListener('click', function(){
                isPaused = !isPaused;
                if (btnToggleText) {
                    btnToggleText.textContent = isPaused ? '▶️ Lanjutkan' : '⏸️ Jeda';
                }
                btnToggleAuto.style.background = isPaused ? 'rgba(239,68,68,0.3)' : 'rgba(255,255,255,0.15)';
            });
        }

        if (btnManual) {
            btnManual.addEventListener('click', function(){
                curTimer = countdownSec;
                if (countdownEl) countdownEl.textContent = curTimer;
                fetchFeed();
            });
        }

        // Initial fetch
        fetchFeed();
        startTimer();
    })();
    </script>
    <?php
}
