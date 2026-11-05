<?php

declare(strict_types=1);

function page_rekap_kehadiran(): void
{
    require_role(['admin', 'guru', 'kepsek', 'pengawas']);
    teacher_teaching_attendance_ensure_schema();

    $tab = (string)($_GET['tab'] ?? 'guru-harian');
    if (!in_array($tab, ['guru-harian', 'guru-jadwal', 'siswa-harian'], true)) {
        $tab = 'guru-harian';
    }

    $defaultStart = date('Y-m-01'); // Awal bulan ini
    $defaultEnd = date('Y-m-d');    // Hari ini

    $startDate = date_ymd((string)($_GET['start_date'] ?? $defaultStart));
    $endDate = date_ymd((string)($_GET['end_date'] ?? $defaultEnd));
    if ($startDate > $endDate) {
        $tmp = $startDate;
        $startDate = $endDate;
        $endDate = $tmp;
    }

    $teachers = fetch_all('SELECT id, name, nip FROM teachers WHERE active = 1 ORDER BY name');
    $classes = fetch_all('SELECT id, name, grade FROM classes WHERE active = 1 ORDER BY grade, name');

    // Role restrictions: guru can filter or by default view their own if needed
    $currentTeacherId = (int)(current_user()['teacher_id'] ?? 0);
    $isRestrictedTeacher = (!is_admin() && !in_array(user_role(), ['kepsek', 'pengawas'], true) && $currentTeacherId > 0);

    $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : ($isRestrictedTeacher ? $currentTeacherId : 0);
    $classId = (int)($_GET['class_id'] ?? 0);

    render_header('Rekapitulasi Kehadiran');
    ?>

    <div class="no-print">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
            <div>
                <h2 style="margin:0;font-size:1.5rem;font-weight:700;color:var(--text,#1e293b);display:flex;align-items:center;gap:.6rem;">
                    <i data-lucide="bar-chart-2" style="color:var(--primary,#2563eb);"></i>
                    Rekapitulasi & Laporan Kehadiran
                </h2>
                <p style="margin:.35rem 0 0;color:var(--text-muted,#64748b);font-size:.9rem;">
                    Laporan kehadiran guru harian, kehadiran mengajar per jadwal, dan kehadiran harian siswa dengan rentang periode tanggal.
                </p>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <button type="button" class="button primary" onclick="window.print()" style="display:inline-flex;align-items:center;gap:.4rem;">
                    <i data-lucide="printer" style="width:16px;height:16px;"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div style="display:flex;border-bottom:2px solid var(--border,#e2e8f0);margin-bottom:1.5rem;gap:.5rem;overflow-x:auto;">
            <a href="<?= e(route_url('rekap-kehadiran', ['tab' => 'guru-harian', 'start_date' => $startDate, 'end_date' => $endDate, 'teacher_id' => $teacherId])) ?>"
               class="tab-link <?= $tab === 'guru-harian' ? 'active' : '' ?>"
               style="padding:.75rem 1.25rem;text-decoration:none;font-weight:600;font-size:.92rem;display:inline-flex;align-items:center;gap:.5rem;border-bottom:3px solid <?= $tab === 'guru-harian' ? 'var(--primary,#2563eb)' : 'transparent' ?>;color:<?= $tab === 'guru-harian' ? 'var(--primary,#2563eb)' : 'var(--text-muted,#64748b)' ?>;margin-bottom:-2px;">
                <i data-lucide="user-check" style="width:18px;height:18px;"></i>
                Kehadiran Guru Harian
            </a>
            <a href="<?= e(route_url('rekap-kehadiran', ['tab' => 'guru-jadwal', 'start_date' => $startDate, 'end_date' => $endDate, 'teacher_id' => $teacherId, 'class_id' => $classId])) ?>"
               class="tab-link <?= $tab === 'guru-jadwal' ? 'active' : '' ?>"
               style="padding:.75rem 1.25rem;text-decoration:none;font-weight:600;font-size:.92rem;display:inline-flex;align-items:center;gap:.5rem;border-bottom:3px solid <?= $tab === 'guru-jadwal' ? 'var(--primary,#2563eb)' : 'transparent' ?>;color:<?= $tab === 'guru-jadwal' ? 'var(--primary,#2563eb)' : 'var(--text-muted,#64748b)' ?>;margin-bottom:-2px;">
                <i data-lucide="calendar-check" style="width:18px;height:18px;"></i>
                Kehadiran Guru Per Jadwal
            </a>
            <a href="<?= e(route_url('rekap-kehadiran', ['tab' => 'siswa-harian', 'start_date' => $startDate, 'end_date' => $endDate, 'class_id' => $classId])) ?>"
               class="tab-link <?= $tab === 'siswa-harian' ? 'active' : '' ?>"
               style="padding:.75rem 1.25rem;text-decoration:none;font-weight:600;font-size:.92rem;display:inline-flex;align-items:center;gap:.5rem;border-bottom:3px solid <?= $tab === 'siswa-harian' ? 'var(--primary,#2563eb)' : 'transparent' ?>;color:<?= $tab === 'siswa-harian' ? 'var(--primary,#2563eb)' : 'var(--text-muted,#64748b)' ?>;margin-bottom:-2px;">
                <i data-lucide="users" style="width:18px;height:18px;"></i>
                Kehadiran Siswa Harian
            </a>
        </div>
    </div>

    <!-- Print Header (Hanya tampil saat print) -->
    <div class="print-only" style="display:none;margin-bottom:1.5rem;text-align:center;border-bottom:2px solid #000;padding-bottom:1rem;">
        <?php $school = get_school_profile(); ?>
        <h2 style="margin:0;font-size:1.4rem;text-transform:uppercase;"><?= e($school['name'] ?? 'SMP MUHAMMADIYAH') ?></h2>
        <p style="margin:2px 0;font-size:.9rem;"><?= e($school['address'] ?? '') ?></p>
        <h3 style="margin:10px 0 4px;font-size:1.15rem;font-weight:bold;text-decoration:underline;">
            <?php
            if ($tab === 'guru-harian') {
                echo 'LAPORAN REKAPITULASI KEHADIRAN HARIAN GURU';
            } elseif ($tab === 'guru-jadwal') {
                echo 'LAPORAN REKAPITULASI KEHADIRAN MENGAJAR GURU (PER JADWAL)';
            } else {
                echo 'LAPORAN REKAPITULASI KEHADIRAN HARIAN SISWA';
            }
            ?>
        </h3>
        <p style="margin:0;font-size:.85rem;">Periode: <?= e(date('d/m/Y', strtotime($startDate))) ?> s/d <?= e(date('d/m/Y', strtotime($endDate))) ?></p>
    </div>

    <style>
    @media print {
        .no-print, nav, .sidebar, header, footer, .alert { display: none !important; }
        .print-only { display: block !important; }
        .panel { box-shadow: none !important; border: 1px solid #ccc !important; padding: 0 !important; }
        body { background: #fff !important; color: #000 !important; font-size: 11pt !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        th, td { border: 1px solid #000 !important; padding: 4px 6px !important; color: #000 !important; }
        .metric-grid { display: none !important; }
        .badge { border: none !important; background: transparent !important; color: #000 !important; padding: 0 !important; font-weight: bold !important; }
    }
    </style>

    <?php
    if ($tab === 'guru-harian') {
        render_rekap_guru_harian_tab($startDate, $endDate, $teacherId, $teachers);
    } elseif ($tab === 'guru-jadwal') {
        render_rekap_guru_jadwal_tab($startDate, $endDate, $teacherId, $classId, $teachers, $classes);
    } else {
        render_rekap_siswa_harian_tab($startDate, $endDate, $classId, $classes);
    }

    render_footer();
}

/**
 * TAB 1: REKAP KEHADIRAN GURU HARIAN
 */
function render_rekap_guru_harian_tab(string $startDate, string $endDate, int $teacherId, array $teachers): void
{
    $where = 't.active = 1';
    $params = [$startDate, $endDate];
    if ($teacherId > 0) {
        $where .= ' AND t.id = ?';
        $params[] = $teacherId;
    }

    // Query Rekap Agregat per Guru
    $summarySql = "SELECT t.id, t.name, t.nip,
            COUNT(ta.id) AS total_recorded,
            SUM(CASE WHEN ta.status = 'hadir' THEN 1 ELSE 0 END) AS count_hadir,
            SUM(CASE WHEN ta.status = 'terlambat' THEN 1 ELSE 0 END) AS count_terlambat,
            SUM(CASE WHEN ta.status = 'izin' THEN 1 ELSE 0 END) AS count_izin,
            SUM(CASE WHEN ta.status = 'sakit' THEN 1 ELSE 0 END) AS count_sakit,
            SUM(CASE WHEN ta.status = 'dinas' THEN 1 ELSE 0 END) AS count_dinas,
            SUM(CASE WHEN ta.status = 'alpa' THEN 1 ELSE 0 END) AS count_alpa
     FROM teachers t
     LEFT JOIN teacher_attendance ta ON ta.teacher_id = t.id AND ta.date >= ? AND ta.date <= ?
     WHERE $where
     GROUP BY t.id, t.name, t.nip
     ORDER BY t.name";
    $summaryRows = fetch_all($summarySql, $params);

    // Hitung KPI
    $totalHadir = 0;
    $totalTerlambat = 0;
    $totalIzinSakit = 0;
    $totalAlpa = 0;
    foreach ($summaryRows as $sr) {
        $totalHadir += (int)$sr['count_hadir'];
        $totalTerlambat += (int)$sr['count_terlambat'];
        $totalIzinSakit += (int)$sr['count_izin'] + (int)$sr['count_sakit'] + (int)$sr['count_dinas'];
        $totalAlpa += (int)$sr['count_alpa'];
    }

    // Query Detail Log Harian
    $logWhere = 'ta.date >= ? AND ta.date <= ?';
    $logParams = [$startDate, $endDate];
    if ($teacherId > 0) {
        $logWhere .= ' AND ta.teacher_id = ?';
        $logParams[] = $teacherId;
    }
    $logRows = fetch_all(
        "SELECT ta.*, t.name AS teacher_name, t.nip
         FROM teacher_attendance ta
         JOIN teachers t ON t.id = ta.teacher_id
         WHERE $logWhere
         ORDER BY ta.date DESC, ta.time_in DESC, t.name ASC",
        $logParams
    );

    $exportUrl = route_url('export-csv', [
        'type' => 'rekap_guru_harian',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'teacher_id' => $teacherId,
    ]);
    ?>

    <!-- Filter Form -->
    <section class="panel no-print" style="margin-bottom:1.5rem;">
        <form method="get" class="grid four" style="align-items:flex-end;">
            <input type="hidden" name="page" value="rekap-kehadiran">
            <input type="hidden" name="tab" value="guru-harian">
            <label>
                <span>Dari Tanggal</span>
                <input type="date" name="start_date" value="<?= e($startDate) ?>" required>
            </label>
            <label>
                <span>Sampai Tanggal</span>
                <input type="date" name="end_date" value="<?= e($endDate) ?>" required>
            </label>
            <label>
                <span>Pilih Guru</span>
                <select name="teacher_id">
                    <option value="0">-- Semua Guru --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" class="button primary" style="flex:1;">Tampilkan</button>
                <a href="<?= e($exportUrl) ?>" class="button success" style="display:inline-flex;align-items:center;gap:.35rem;" title="Download Format CSV/Excel">
                    <i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Export
                </a>
            </div>
        </form>
    </section>

    <!-- KPI Metric Cards -->
    <div class="metric-grid no-print" style="margin-bottom:1.5rem;">
        <div class="metric">
            <div class="metric-icon blue"><i data-lucide="users"></i></div>
            <div class="metric-body">
                <div class="metric-label">Guru Terdata</div>
                <div class="metric-value"><?= count($summaryRows) ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon green"><i data-lucide="user-check"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Hadir Tepat Waktu</div>
                <div class="metric-value"><?= $totalHadir ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon amber"><i data-lucide="clock"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Terlambat</div>
                <div class="metric-value"><?= $totalTerlambat ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon violet"><i data-lucide="alert-circle"></i></div>
            <div class="metric-body">
                <div class="metric-label">Izin / Sakit / Dinas</div>
                <div class="metric-value"><?= $totalIzinSakit ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekapitulasi per Guru -->
    <section class="panel" style="margin-bottom:1.5rem;">
        <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div class="panel-title">
                <i data-lucide="list-checks" style="color:var(--primary);"></i>
                <span>Rekapitulasi Kehadiran per Guru (<?= e(date('d/m/Y', strtotime($startDate))) ?> - <?= e(date('d/m/Y', strtotime($endDate))) ?>)</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">No</th>
                        <th>Nama Guru</th>
                        <th>NIP</th>
                        <th style="text-align:center;">Hadir</th>
                        <th style="text-align:center;">Terlambat</th>
                        <th style="text-align:center;">Izin</th>
                        <th style="text-align:center;">Sakit</th>
                        <th style="text-align:center;">Dinas</th>
                        <th style="text-align:center;">Alpa</th>
                        <th style="text-align:center;">Total Hadir</th>
                        <th style="text-align:center;">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summaryRows)): ?>
                        <tr><td colspan="11" class="empty" style="text-align:center;padding:2rem;">Belum ada data kehadiran pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($summaryRows as $row):
                            $masuk = (int)$row['count_hadir'] + (int)$row['count_terlambat'];
                            $totalRec = (int)$row['total_recorded'];
                            $pct = $totalRec > 0 ? round(($masuk / $totalRec) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $no++ ?></td>
                                <td style="font-weight:600;"><?= e($row['name']) ?></td>
                                <td><?= e($row['nip'] ?: '-') ?></td>
                                <td style="text-align:center;color:#15803d;font-weight:600;"><?= (int)$row['count_hadir'] ?></td>
                                <td style="text-align:center;color:#b45309;font-weight:600;"><?= (int)$row['count_terlambat'] ?></td>
                                <td style="text-align:center;"><?= (int)$row['count_izin'] ?></td>
                                <td style="text-align:center;"><?= (int)$row['count_sakit'] ?></td>
                                <td style="text-align:center;"><?= (int)$row['count_dinas'] ?></td>
                                <td style="text-align:center;color:#b91c1c;font-weight:600;"><?= (int)$row['count_alpa'] ?></td>
                                <td style="text-align:center;font-weight:700;"><?= $masuk ?></td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $pct >= 85 ? 'ok' : ($pct >= 70 ? 'warning' : 'danger') ?>">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Tabel Log Detail Harian -->
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="history" style="color:var(--primary);"></i>
                <span>Log Riwayat Presensi Guru Datang & Pulang (Total: <?= count($logRows) ?> entri)</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">No</th>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        <th>Nama Guru</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status</th>
                        <th>Lokasi / GPS</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logRows)): ?>
                        <tr><td colspan="9" class="empty" style="text-align:center;padding:2rem;">Belum ada catatan presensi harian pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php
                        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
                        $no = 1;
                        foreach ($logRows as $log):
                            $dow = (int)(new DateTimeImmutable($log['date']))->format('N');
                            $hasGps = !empty($log['location_lat']) && !empty($log['location_lng']);
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $no++ ?></td>
                                <td><?= e(date('d/m/Y', strtotime($log['date']))) ?></td>
                                <td><?= e($dayNames[$dow] ?? '-') ?></td>
                                <td style="font-weight:600;"><?= e($log['teacher_name']) ?></td>
                                <td style="font-weight:600;color:var(--primary);"><?= e(substr((string)($log['time_in'] ?: '-'), 0, 5)) ?></td>
                                <td><?= e(substr((string)($log['time_out'] ?: '-'), 0, 5)) ?></td>
                                <td>
                                    <?php
                                    $st = (string)$log['status'];
                                    $badgeClass = ($st === 'hadir' ? 'ok' : ($st === 'terlambat' ? 'warning' : 'info'));
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($st)) ?></span>
                                </td>
                                <td>
                                    <?php if ($hasGps): ?>
                                        <span style="font-size:.78rem;color:#15803d;display:inline-flex;align-items:center;gap:3px;">
                                            <i data-lucide="map-pin" style="width:12px;height:12px;"></i> Terverifikasi
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:.78rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($log['notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Print Footer Signatures -->
    <div class="print-only" style="display:none;margin-top:2.5rem;">
        <table style="width:100%;border:none;">
            <tr style="border:none;">
                <td style="width:50%;text-align:center;border:none;">
                    Mengetahui,<br>Kepala Sekolah<br><br><br><br>
                    <strong><u><?= e($school['headmaster_name'] ?? 'Kepala Sekolah') ?></u></strong><br>
                    NIP. <?= e($school['headmaster_nip'] ?? '-') ?>
                </td>
                <td style="width:50%;text-align:center;border:none;">
                    Ngawen, <?= e(date('d F Y')) ?><br>Petugas / Kurikulum<br><br><br><br>
                    <strong><u><?= e(current_user()['name'] ?? 'Administrator') ?></u></strong>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * Helper untuk mengambil data rekap kehadiran guru per jadwal
 * Mengintegrasikan lesson_schedules, teacher_teaching_attendance, student_attendance_sessions, dan teacher_attendance
 */
function get_rekap_guru_jadwal_data(string $startDate, string $endDate, int $teacherId = 0, int $classId = 0, string $statusFilter = 'all'): array
{
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    // Batasi rentang maksimal 60 hari
    $diffDays = (int)$start->diff($end)->format('%a');
    if ($diffDays > 60) {
        $end = $start->modify('+60 days');
    }

    $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

    // 1. Ambil jadwal mengajar mingguan
    $schedWhere = '1=1';
    $schedParams = [];
    if ($teacherId > 0) {
        $schedWhere .= ' AND ls.teacher_id = ?';
        $schedParams[] = $teacherId;
    }
    if ($classId > 0) {
        $schedWhere .= ' AND ls.class_id = ?';
        $schedParams[] = $classId;
    }

    $allSchedules = fetch_all(
        "SELECT ls.id AS schedule_id, ls.assignment_id, ls.teacher_id, ls.class_id, ls.subject_id,
                ls.day_of_week, ls.period_no, ls.start_time, ls.end_time,
                t.name AS teacher_name, t.nip,
                c.name AS class_name, c.grade,
                s.name AS subject_name
         FROM lesson_schedules ls
         JOIN teachers t ON t.id = ls.teacher_id
         JOIN classes c ON c.id = ls.class_id
         JOIN subjects s ON s.id = ls.subject_id
         WHERE $schedWhere
         ORDER BY ls.day_of_week, ls.period_no, c.grade, c.name",
        $schedParams
    );

    $schedulesByDow = [];
    foreach ($allSchedules as $s) {
        $dow = (int)$s['day_of_week'];
        $schedulesByDow[$dow][] = $s;
    }

    // 2. Ambil data teacher_teaching_attendance
    $ttaRows = fetch_all(
        'SELECT * FROM teacher_teaching_attendance WHERE date >= ? AND date <= ?',
        [$start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $ttaMap = [];
    foreach ($ttaRows as $ta) {
        $d = $ta['date'];
        if (!empty($ta['schedule_id'])) {
            $ttaMap[(int)$ta['schedule_id']][$d] = $ta;
        }
        if (!empty($ta['assignment_id'])) {
            $ttaMap['asgn_' . (int)$ta['assignment_id']][$d] = $ta;
        }
    }

    // 3. Ambil data student_attendance_sessions (Konfirmasi Masuk Kelas Guru)
    $sasRows = fetch_all(
        'SELECT id, assignment_id, date, meeting_no, teacher_attended, teacher_status, teacher_time_in, teacher_notes, teacher_lat, teacher_lng
         FROM student_attendance_sessions
         WHERE date >= ? AND date <= ?',
        [$start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $sasMap = [];
    $sessionIds = [];
    foreach ($sasRows as $sas) {
        $sasMap[(int)$sas['assignment_id']][$sas['date']] = $sas;
        $sessionIds[] = (int)$sas['id'];
    }

    // 4. Ambil jumlah siswa diabsen per sesi
    $studentCountMap = [];
    if (!empty($sessionIds)) {
        $p = implode(',', array_fill(0, count($sessionIds), '?'));
        $entryCounts = fetch_all(
            "SELECT session_id,
                    COUNT(*) as total_students,
                    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as count_hadir
             FROM student_attendance_entries
             WHERE session_id IN ($p)
             GROUP BY session_id",
            $sessionIds
        );
        foreach ($entryCounts as $ec) {
            $studentCountMap[(int)$ec['session_id']] = $ec;
        }
    }

    // 5. Ambil data presensi harian datang sekolah
    $dtaRows = fetch_all(
        'SELECT teacher_id, date, status, time_in, notes FROM teacher_attendance WHERE date >= ? AND date <= ?',
        [$start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $dtaMap = [];
    foreach ($dtaRows as $dta) {
        $dtaMap[(int)$dta['teacher_id']][$dta['date']] = $dta;
    }

    // 6. Bangun baris jadwal lengkap per hari
    $rows = [];
    $todayYmd = date('Y-m-d');

    foreach ($period as $dt) {
        $curDate = $dt->format('Y-m-d');
        $dow = (int)$dt->format('N');

        // Hari Minggu lewati jika tidak ada jadwal
        if ($dow === 7 && empty($schedulesByDow[7])) {
            continue;
        }

        $daySchedules = $schedulesByDow[$dow] ?? [];
        foreach ($daySchedules as $sched) {
            $schedId = (int)$sched['schedule_id'];
            $asgnId = (int)$sched['assignment_id'];
            $tId = (int)$sched['teacher_id'];

            $tta = $ttaMap[$schedId][$curDate] ?? ($ttaMap['asgn_' . $asgnId][$curDate] ?? null);
            $sas = $sasMap[$asgnId][$curDate] ?? null;
            $dta = $dtaMap[$tId][$curDate] ?? null;

            $stCount = $sas ? ($studentCountMap[(int)$sas['id']] ?? null) : null;
            $totalStudents = (int)($stCount['total_students'] ?? 0);
            $countHadir = (int)($stCount['count_hadir'] ?? 0);

            $status = 'belum_hadir';
            $timeIn = '';
            $timeOut = '';
            $notes = '';
            $source = 'none';

            if ($tta && !empty($tta['status']) && $tta['status'] !== 'belum input') {
                $status = (string)$tta['status'];
                $timeIn = (string)($tta['time_in'] ?: '');
                $timeOut = (string)($tta['time_out'] ?: '');
                $notes = (string)($tta['notes'] ?: '');
                $source = 'rekap_mengajar';
            } elseif ($sas && (!empty($sas['teacher_attended']) || !empty($sas['teacher_time_in']))) {
                $status = (string)($sas['teacher_status'] ?: 'hadir');
                $timeIn = (string)($sas['teacher_time_in'] ?: '');
                $notes = (string)($sas['teacher_notes'] ?: '');
                $source = 'presensi_kelas';
            } elseif ($totalStudents > 0) {
                $status = 'hadir';
                $timeIn = (string)($sched['start_time'] ?: '');
                $notes = 'Siswa diabsen (' . $totalStudents . ' siswa)';
                $source = 'absensi_siswa';
            } elseif ($dta && ($dta['status'] === 'hadir' || $dta['status'] === 'terlambat')) {
                $status = 'hadir_sekolah';
                $timeIn = (string)($dta['time_in'] ?: '');
                $notes = 'Presensi hadir di sekolah';
                $source = 'presensi_sekolah';
            } elseif ($curDate > $todayYmd) {
                $status = 'terjadwal';
                $notes = 'Akan datang';
            }

            // Filter status jika dipilih
            if ($statusFilter !== 'all') {
                if ($statusFilter === 'hadir' && !in_array($status, ['hadir', 'hadir_sekolah'], true)) continue;
                if ($statusFilter === 'inval' && $status !== 'inval') continue;
                if ($statusFilter === 'izin' && !in_array($status, ['izin', 'sakit', 'dinas'], true)) continue;
                if ($statusFilter === 'belum_hadir' && $status !== 'belum_hadir') continue;
            }

            $rows[] = [
                'date' => $curDate,
                'day_of_week' => $dow,
                'schedule_id' => $schedId,
                'assignment_id' => $asgnId,
                'teacher_id' => $tId,
                'teacher_name' => $sched['teacher_name'],
                'nip' => $sched['nip'],
                'class_id' => (int)$sched['class_id'],
                'class_name' => $sched['class_name'],
                'subject_name' => $sched['subject_name'],
                'period_no' => (int)$sched['period_no'],
                'start_time' => $sched['start_time'],
                'end_time' => $sched['end_time'],
                'status' => $status,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'notes' => $notes,
                'source' => $source,
                'total_students' => $totalStudents,
                'count_hadir_students' => $countHadir,
            ];
        }
    }

    // Cek juga sesi kelas yang tercatat di student_attendance_sessions namun di luar jadwal mingguan tetap
    $recordedAssignments = [];
    foreach ($rows as $r) {
        $recordedAssignments[$r['assignment_id'] . '_' . $r['date']] = true;
    }

    foreach ($sasRows as $sas) {
        $key = $sas['assignment_id'] . '_' . $sas['date'];
        if (!isset($recordedAssignments[$key])) {
            $asgn = fetch_one(
                'SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, t.name as teacher_name, t.nip, c.name as class_name, s.name as subject_name
                 FROM teaching_assignments ta
                 LEFT JOIN teachers t ON t.id = ta.teacher_id
                 LEFT JOIN classes c ON c.id = ta.class_id
                 LEFT JOIN subjects s ON s.id = ta.subject_id
                 WHERE ta.id = ?',
                [(int)$sas['assignment_id']]
            );
            if ($asgn) {
                if ($teacherId > 0 && (int)$asgn['teacher_id'] !== $teacherId) continue;
                if ($classId > 0 && (int)$asgn['class_id'] !== $classId) continue;

                $stCount = $studentCountMap[(int)$sas['id']] ?? null;
                $status = (string)($sas['teacher_status'] ?: 'hadir');
                if ($statusFilter !== 'all') {
                    if ($statusFilter === 'hadir' && !in_array($status, ['hadir', 'hadir_sekolah'], true)) continue;
                    if ($statusFilter === 'inval' && $status !== 'inval') continue;
                    if ($statusFilter === 'izin' && !in_array($status, ['izin', 'sakit', 'dinas'], true)) continue;
                    if ($statusFilter === 'belum_hadir' && $status !== 'belum_hadir') continue;
                }

                $rows[] = [
                    'date' => $sas['date'],
                    'day_of_week' => (int)(new DateTimeImmutable($sas['date']))->format('N'),
                    'schedule_id' => 0,
                    'assignment_id' => (int)$asgn['id'],
                    'teacher_id' => (int)$asgn['teacher_id'],
                    'teacher_name' => $asgn['teacher_name'],
                    'nip' => $asgn['nip'],
                    'class_id' => (int)$asgn['class_id'],
                    'class_name' => $asgn['class_name'],
                    'subject_name' => $asgn['subject_name'],
                    'period_no' => (int)($sas['meeting_no'] ?? 1),
                    'start_time' => (string)($sas['teacher_time_in'] ?: ''),
                    'end_time' => '',
                    'status' => $status,
                    'time_in' => (string)($sas['teacher_time_in'] ?: ''),
                    'time_out' => '',
                    'notes' => (string)($sas['teacher_notes'] ?: 'Sesi Pertemuan ke-' . ($sas['meeting_no'] ?? 1)),
                    'source' => 'presensi_kelas',
                    'total_students' => (int)($stCount['total_students'] ?? 0),
                    'count_hadir_students' => (int)($stCount['count_hadir'] ?? 0),
                ];
            }
        }
    }

    // Urutkan tanggal terbaru di atas, lalu jam pelajaran ASC, lalu kelas ASC
    usort($rows, function ($a, $b) {
        if ($a['date'] !== $b['date']) {
            return strcmp($b['date'], $a['date']);
        }
        if ($a['period_no'] !== $b['period_no']) {
            return $a['period_no'] <=> $b['period_no'];
        }
        return strcmp((string)($a['class_name'] ?? ''), (string)($b['class_name'] ?? ''));
    });

    return $rows;
}

/**
 * TAB 2: REKAP KEHADIRAN GURU PER JADWAL
 */
function render_rekap_guru_jadwal_tab(string $startDate, string $endDate, int $teacherId, int $classId, array $teachers, array $classes): void
{
    $statusFilter = (string)($_GET['status_filter'] ?? 'all');
    $rows = get_rekap_guru_jadwal_data($startDate, $endDate, $teacherId, $classId, $statusFilter);

    // Agregasi per Guru
    $aggMap = [];
    $totalHadir = 0;
    $totalHadirSekolah = 0;
    $totalInval = 0;
    $totalIzin = 0;
    $totalBelumHadir = 0;

    foreach ($rows as $r) {
        $tid = (int)$r['teacher_id'];
        if (!isset($aggMap[$tid])) {
            $aggMap[$tid] = [
                'name' => $r['teacher_name'],
                'nip' => $r['nip'],
                'total' => 0,
                'hadir' => 0,
                'hadir_sekolah' => 0,
                'inval' => 0,
                'izin' => 0,
                'belum' => 0,
            ];
        }
        $aggMap[$tid]['total']++;
        $st = (string)$r['status'];
        if ($st === 'hadir') {
            $aggMap[$tid]['hadir']++;
            $totalHadir++;
        } elseif ($st === 'hadir_sekolah') {
            $aggMap[$tid]['hadir_sekolah']++;
            $totalHadirSekolah++;
        } elseif ($st === 'inval') {
            $aggMap[$tid]['inval']++;
            $totalInval++;
        } elseif (in_array($st, ['izin', 'sakit', 'dinas'], true)) {
            $aggMap[$tid]['izin']++;
            $totalIzin++;
        } else {
            $aggMap[$tid]['belum']++;
            $totalBelumHadir++;
        }
    }

    $exportUrl = route_url('export-csv', [
        'type' => 'rekap_guru_jadwal',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'teacher_id' => $teacherId,
        'class_id' => $classId,
        'status_filter' => $statusFilter,
    ]);

    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    ?>

    <!-- Filter Form -->
    <section class="panel no-print" style="margin-bottom:1.5rem;">
        <form method="get" class="grid five" style="align-items:flex-end;gap:.75rem;">
            <input type="hidden" name="page" value="rekap-kehadiran">
            <input type="hidden" name="tab" value="guru-jadwal">
            <label>
                <span>Dari Tanggal</span>
                <input type="date" name="start_date" value="<?= e($startDate) ?>" required>
            </label>
            <label>
                <span>Sampai Tanggal</span>
                <input type="date" name="end_date" value="<?= e($endDate) ?>" required>
            </label>
            <label>
                <span>Pilih Guru</span>
                <select name="teacher_id">
                    <option value="0">-- Semua Guru --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Pilih Kelas</span>
                <select name="class_id">
                    <option value="0">-- Semua Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Status Kehadiran</span>
                <select name="status_filter">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>-- Semua Status --</option>
                    <option value="hadir" <?= $statusFilter === 'hadir' ? 'selected' : '' ?>>Hadir Mengajar</option>
                    <option value="inval" <?= $statusFilter === 'inval' ? 'selected' : '' ?>>Guru Pengganti (Inval)</option>
                    <option value="izin" <?= $statusFilter === 'izin' ? 'selected' : '' ?>>Izin / Sakit / Dinas</option>
                    <option value="belum_hadir" <?= $statusFilter === 'belum_hadir' ? 'selected' : '' ?>>Belum Ada Presensi</option>
                </select>
            </label>
            <div class="wide" style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.25rem;">
                <button type="submit" class="button primary">Tampilkan Filter</button>
                <a href="<?= e($exportUrl) ?>" class="button success" style="display:inline-flex;align-items:center;gap:.35rem;" title="Download Format CSV/Excel">
                    <i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Export Excel / CSV
                </a>
            </div>
        </form>
    </section>

    <!-- KPI Metric Cards -->
    <div class="metric-grid no-print" style="margin-bottom:1.5rem;">
        <div class="metric">
            <div class="metric-icon blue"><i data-lucide="calendar"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Sesi Terjadwal</div>
                <div class="metric-value"><?= count($rows) ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon green"><i data-lucide="check-circle-2"></i></div>
            <div class="metric-body">
                <div class="metric-label">Hadir Mengajar di Kelas</div>
                <div class="metric-value"><?= $totalHadir ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon amber"><i data-lucide="user-plus"></i></div>
            <div class="metric-body">
                <div class="metric-label">Guru Pengganti (Inval)</div>
                <div class="metric-value"><?= $totalInval ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon red"><i data-lucide="alert-circle"></i></div>
            <div class="metric-body">
                <div class="metric-label">Belum Ada Presensi</div>
                <div class="metric-value"><?= $totalBelumHadir ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekap Per-Guru -->
    <section class="panel" style="margin-bottom:1.5rem;">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="award" style="color:var(--primary);"></i>
                <span>Tingkat Keterlaksanaan Mengajar Guru (<?= count($aggMap) ?> Guru)</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">No</th>
                        <th>Nama Guru</th>
                        <th>NIP</th>
                        <th style="text-align:center;">Jadwal Mengajar</th>
                        <th style="text-align:center;">Hadir Mengajar</th>
                        <th style="text-align:center;">Hadir di Sekolah</th>
                        <th style="text-align:center;">Guru Inval</th>
                        <th style="text-align:center;">Izin/Sakit</th>
                        <th style="text-align:center;">Belum Hadir</th>
                        <th style="text-align:center;">% Keterlaksanaan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aggMap)): ?>
                        <tr><td colspan="10" class="empty" style="text-align:center;padding:2rem;">Belum ada jadwal mengajar pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($aggMap as $tid => $ag):
                            $laksana = $ag['hadir'] + $ag['hadir_sekolah'] + $ag['inval'];
                            $pct = $ag['total'] > 0 ? round(($laksana / $ag['total']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $no++ ?></td>
                                <td style="font-weight:600;"><?= e($ag['name']) ?></td>
                                <td><?= e($ag['nip'] ?: '-') ?></td>
                                <td style="text-align:center;font-weight:700;"><?= $ag['total'] ?></td>
                                <td style="text-align:center;color:#15803d;font-weight:600;"><?= $ag['hadir'] ?></td>
                                <td style="text-align:center;color:#0284c7;font-weight:600;"><?= $ag['hadir_sekolah'] ?></td>
                                <td style="text-align:center;color:#b45309;font-weight:600;"><?= $ag['inval'] ?></td>
                                <td style="text-align:center;color:#6b21a8;"><?= $ag['izin'] ?></td>
                                <td style="text-align:center;color:#b91c1c;font-weight:600;"><?= $ag['belum'] ?></td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $pct >= 85 ? 'ok' : ($pct >= 60 ? 'warning' : 'danger') ?>">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Tabel Rincian Per Sesi Jadwal -->
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="calendar-days" style="color:var(--primary);"></i>
                <span>Rincian Kehadiran Sesuai Jadwal Pelajaran (<?= count($rows) ?> Sesi)</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">No</th>
                        <th>Tanggal &amp; Hari</th>
                        <th>Jam Ke-</th>
                        <th>Waktu</th>
                        <th>Guru Pengampu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Status Kehadiran</th>
                        <th>Jam Masuk</th>
                        <th>Presensi Siswa</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="empty" style="text-align:center;padding:2rem;">Belum ada jadwal mengajar pada rentang tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $r):
                            $st = (string)($r['status'] ?? 'belum_hadir');
                            $timeStr = trim(substr((string)($r['start_time'] ?? ''), 0, 5) . ' - ' . substr((string)($r['end_time'] ?? ''), 0, 5));
                            $dow = (int)$r['day_of_week'];
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $no++ ?></td>
                                <td>
                                    <strong><?= e(date('d/m/Y', strtotime($r['date']))) ?></strong><br>
                                    <span style="font-size:.78rem;color:var(--text-muted);"><?= e($dayNames[$dow] ?? '-') ?></span>
                                </td>
                                <td style="font-weight:700;text-align:center;">Jam ke-<?= (int)$r['period_no'] ?></td>
                                <td style="font-size:.82rem;color:var(--text-muted);"><?= e($timeStr ?: '-') ?></td>
                                <td style="font-weight:600;"><?= e($r['teacher_name']) ?></td>
                                <td><span class="badge info"><?= e($r['class_name']) ?></span></td>
                                <td><?= e($r['subject_name']) ?></td>
                                <td>
                                    <?php if ($st === 'hadir'): ?>
                                        <span class="badge ok">✓ Hadir Mengajar</span>
                                    <?php elseif ($st === 'hadir_sekolah'): ?>
                                        <span class="badge info">Hadir di Sekolah</span>
                                    <?php elseif ($st === 'inval'): ?>
                                        <span class="badge warning">Guru Pengganti</span>
                                    <?php elseif (in_array($st, ['izin', 'sakit', 'dinas'], true)): ?>
                                        <span class="badge"><?= e(ucfirst($st)) ?></span>
                                    <?php elseif ($st === 'terjadwal'): ?>
                                        <span class="badge" style="background:#f1f5f9;color:#64748b;">Terjadwal</span>
                                    <?php else: ?>
                                        <span class="badge danger">Belum Hadir</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;color:var(--primary);"><?= e(substr((string)($r['time_in'] ?: '-'), 0, 5)) ?></td>
                                <td>
                                    <?php if ($r['total_students'] > 0): ?>
                                        <span style="color:#15803d;font-weight:600;font-size:.82rem;display:inline-flex;align-items:center;gap:3px;">
                                            <i data-lucide="check" style="width:13px;height:13px;"></i> <?= $r['total_students'] ?> Siswa
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:.8rem;">Belum Diabsen</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem;"><?= e($r['notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Print Footer Signatures -->
    <div class="print-only" style="display:none;margin-top:2.5rem;">
        <?php $school = get_school_profile(); ?>
        <table style="width:100%;border:none;">
            <tr style="border:none;">
                <td style="width:50%;text-align:center;border:none;">
                    Mengetahui,<br>Kepala Sekolah<br><br><br><br>
                    <strong><u><?= e($school['headmaster_name'] ?? 'Kepala Sekolah') ?></u></strong><br>
                    NIP. <?= e($school['headmaster_nip'] ?? '-') ?>
                </td>
                <td style="width:50%;text-align:center;border:none;">
                    Ngawen, <?= e(date('d F Y')) ?><br>Waka Kurikulum<br><br><br><br>
                    <strong><u><?= e(current_user()['name'] ?? 'Administrator') ?></u></strong>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * TAB 3: REKAP KEHADIRAN SISWA HARIAN
 */
function render_rekap_siswa_harian_tab(string $startDate, string $endDate, int $classId, array $classes): void
{
    $where = 's.active = 1';
    $params = [$startDate, $endDate];
    if ($classId > 0) {
        $where .= ' AND s.class_id = ?';
        $params[] = $classId;
    }

    // Query Rekap Agregat per Siswa
    $sql = "SELECT s.id, s.name, s.nis, s.nisn, c.name AS class_name, c.grade,
                   COUNT(e.id) AS total_recorded,
                   SUM(CASE WHEN e.status = 'hadir' THEN 1 ELSE 0 END) AS count_hadir,
                   SUM(CASE WHEN e.status = 'sakit' THEN 1 ELSE 0 END) AS count_sakit,
                   SUM(CASE WHEN e.status = 'izin' THEN 1 ELSE 0 END) AS count_izin,
                   SUM(CASE WHEN e.status = 'alpa' THEN 1 ELSE 0 END) AS count_alpa,
                   SUM(CASE WHEN e.status = 'terlambat' THEN 1 ELSE 0 END) AS count_terlambat
            FROM students s
            JOIN classes c ON c.id = s.class_id
            LEFT JOIN (
                SELECT sub_e.id, sub_e.student_id, sub_e.status
                FROM student_attendance_entries sub_e
                JOIN student_attendance_sessions sub_s ON sub_s.id = sub_e.session_id
                WHERE sub_s.date >= ? AND sub_s.date <= ?
            ) e ON e.student_id = s.id
            WHERE $where
            GROUP BY s.id, s.name, s.nis, s.nisn, c.name, c.grade
            ORDER BY c.grade, c.name, s.name";
    $students = fetch_all($sql, $params);

    // Hitung KPI
    $totalHadir = 0;
    $totalSakit = 0;
    $totalIzin = 0;
    $totalAlpa = 0;
    $totalTerlambat = 0;
    foreach ($students as $st) {
        $totalHadir += (int)$st['count_hadir'];
        $totalSakit += (int)$st['count_sakit'];
        $totalIzin += (int)$st['count_izin'];
        $totalAlpa += (int)$st['count_alpa'];
        $totalTerlambat += (int)$st['count_terlambat'];
    }

    $exportUrl = route_url('export-csv', [
        'type' => 'rekap_siswa_harian',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'class_id' => $classId,
    ]);
    ?>

    <!-- Filter Form -->
    <section class="panel no-print" style="margin-bottom:1.5rem;">
        <form method="get" class="grid four" style="align-items:flex-end;">
            <input type="hidden" name="page" value="rekap-kehadiran">
            <input type="hidden" name="tab" value="siswa-harian">
            <label>
                <span>Dari Tanggal</span>
                <input type="date" name="start_date" value="<?= e($startDate) ?>" required>
            </label>
            <label>
                <span>Sampai Tanggal</span>
                <input type="date" name="end_date" value="<?= e($endDate) ?>" required>
            </label>
            <label>
                <span>Pilih Kelas</span>
                <select name="class_id">
                    <option value="0">-- Semua Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="display:flex;gap:.5rem;">
                <button type="submit" class="button primary" style="flex:1;">Tampilkan</button>
                <a href="<?= e($exportUrl) ?>" class="button success" style="display:inline-flex;align-items:center;gap:.35rem;" title="Download Format CSV/Excel">
                    <i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Export
                </a>
            </div>
        </form>
    </section>

    <!-- KPI Metric Cards -->
    <div class="metric-grid no-print" style="margin-bottom:1.5rem;">
        <div class="metric">
            <div class="metric-icon blue"><i data-lucide="graduation-cap"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Siswa</div>
                <div class="metric-value"><?= count($students) ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon green"><i data-lucide="user-check"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Kehadiran Siswa</div>
                <div class="metric-value"><?= $totalHadir ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon amber"><i data-lucide="heart-pulse"></i></div>
            <div class="metric-body">
                <div class="metric-label">Sakit & Izin</div>
                <div class="metric-value"><?= $totalSakit + $totalIzin ?></div>
            </div>
        </div>
        <div class="metric">
            <div class="metric-icon red"><i data-lucide="alert-octagon"></i></div>
            <div class="metric-body">
                <div class="metric-label">Total Alpa (Tanpa Ket.)</div>
                <div class="metric-value"><?= $totalAlpa ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekapitulasi Kehadiran Siswa -->
    <section class="panel">
        <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div class="panel-title">
                <i data-lucide="clipboard-check" style="color:var(--primary);"></i>
                <span>Rekapitulasi Presensi Siswa (<?= e(date('d/m/Y', strtotime($startDate))) ?> - <?= e(date('d/m/Y', strtotime($endDate))) ?>)</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th style="text-align:center;" title="Hadir">Hadir (H)</th>
                        <th style="text-align:center;" title="Sakit">Sakit (S)</th>
                        <th style="text-align:center;" title="Izin">Izin (I)</th>
                        <th style="text-align:center;" title="Alpa">Alpa (A)</th>
                        <th style="text-align:center;" title="Terlambat">Telat (T)</th>
                        <th style="text-align:center;">Total Pertemuan</th>
                        <th style="text-align:center;">% Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="11" class="empty" style="text-align:center;padding:2rem;">Belum ada data absensi siswa pada filter ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($students as $st):
                            $tot = (int)$st['total_recorded'];
                            $hadir = (int)$st['count_hadir'];
                            $pct = $tot > 0 ? round(($hadir / $tot) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $no++ ?></td>
                                <td><?= e($st['nis'] ?: '-') ?></td>
                                <td style="font-weight:600;"><?= e($st['name']) ?></td>
                                <td><span class="badge info"><?= e($st['class_name']) ?></span></td>
                                <td style="text-align:center;color:#15803d;font-weight:600;"><?= (int)$st['count_hadir'] ?></td>
                                <td style="text-align:center;color:#b45309;font-weight:600;"><?= (int)$st['count_sakit'] ?></td>
                                <td style="text-align:center;color:#0284c7;font-weight:600;"><?= (int)$st['count_izin'] ?></td>
                                <td style="text-align:center;color:#b91c1c;font-weight:600;"><?= (int)$st['count_alpa'] ?></td>
                                <td style="text-align:center;color:#d97706;"><?= (int)$st['count_terlambat'] ?></td>
                                <td style="text-align:center;font-weight:700;"><?= $tot ?></td>
                                <td style="text-align:center;">
                                    <?php if ($tot === 0): ?>
                                        <span style="color:var(--text-muted);font-size:.8rem;">Belum ada sesi</span>
                                    <?php else: ?>
                                        <span class="badge <?= $pct >= 85 ? 'ok' : ($pct >= 70 ? 'warning' : 'danger') ?>">
                                            <?= $pct ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Print Footer Signatures -->
    <div class="print-only" style="display:none;margin-top:2.5rem;">
        <?php $school = get_school_profile(); ?>
        <table style="width:100%;border:none;">
            <tr style="border:none;">
                <td style="width:50%;text-align:center;border:none;">
                    Mengetahui,<br>Kepala Sekolah<br><br><br><br>
                    <strong><u><?= e($school['headmaster_name'] ?? 'Kepala Sekolah') ?></u></strong><br>
                    NIP. <?= e($school['headmaster_nip'] ?? '-') ?>
                </td>
                <td style="width:50%;text-align:center;border:none;">
                    Ngawen, <?= e(date('d F Y')) ?><br>Wali Kelas / Guru Piket<br><br><br><br>
                    <strong><u><?= e(current_user()['name'] ?? 'Administrator') ?></u></strong>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
