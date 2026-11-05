<?php

declare(strict_types=1);

function page_teaching_wizard(): void
{
    require_role(['admin', 'guru']);
    teacher_teaching_attendance_ensure_schema();

    $date = date_ymd((string)($_GET['date'] ?? date('Y-m-d')));
    $scheduleId = (int)($_GET['schedule_id'] ?? 0);
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);

    // If schedule_id provided but no assignment_id, lookup from schedule
    if ($assignmentId <= 0 && $scheduleId > 0) {
        $sched = fetch_one('SELECT assignment_id FROM lesson_schedules WHERE id = ?', [$scheduleId]);
        if ($sched) {
            $assignmentId = (int)$sched['assignment_id'];
        }
    }

    $assignments = assignments_for_current_user();

    // If no assignment specified, try to find today's schedule for current teacher
    if ($assignmentId <= 0) {
        $teacherId = (int)(current_user()['teacher_id'] ?? 0);
        $dow = (int)(new DateTimeImmutable($date))->format('N');
        if ($teacherId > 0) {
            $todaySched = fetch_one(
                'SELECT id, assignment_id FROM lesson_schedules WHERE teacher_id = ? AND day_of_week = ? ORDER BY period_no LIMIT 1',
                [$teacherId, $dow]
            );
            if ($todaySched) {
                $assignmentId = (int)$todaySched['assignment_id'];
                $scheduleId = (int)$todaySched['id'];
            }
        }
    }

    // Fallback to first available assignment
    if ($assignmentId <= 0 && !empty($assignments)) {
        $assignmentId = (int)$assignments[0]['id'];
    }

    $assignment = $assignmentId > 0 ? require_assignment_access($assignmentId) : null;
    if (!$assignment) {
        render_header('Alur Mengajar Kelas');
        echo '<section class="panel"><p class="empty">Tidak ada kelas/pembelajaran yang dipilih.</p><a href="' . e(route_url('dashboard')) . '" class="button">Kembali ke Dashboard</a></section>';
        render_footer();
        return;
    }

    // Determine meeting number
    $meetingNo = (int)($_GET['meeting_no'] ?? 0);
    if ($meetingNo <= 0) {
        // Cek sesi yang sudah ada di tanggal ini
        $existingSession = fetch_one(
            'SELECT meeting_no FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? ORDER BY meeting_no DESC LIMIT 1',
            [$assignmentId, $date]
        );
        if ($existingSession) {
            $meetingNo = (int)$existingSession['meeting_no'];
        } else {
            // Hitung pertemuan berikutnya
            $maxM = fetch_one(
                'SELECT MAX(meeting_no) as max_m FROM student_attendance_sessions WHERE assignment_id = ?',
                [$assignmentId]
            );
            $meetingNo = max(1, ((int)($maxM['max_m'] ?? 0)) + 1);
        }
    }

    // Check sibling assignments if boarding physical group
    $siblingAssignments = function_exists('sibling_assignments_for_assignment') ? sibling_assignments_for_assignment($assignment) : [];
    $allAssignments = array_merge([$assignment], $siblingAssignments);
    $allClassIds = array_map(fn($a) => (int)$a['class_id'], $allAssignments);
    $allAssignmentIds = array_map(fn($a) => (int)$a['id'], $allAssignments);
    $isCombinedGroup = count($allAssignments) > 1;

    // Load Session & Teacher Presence Status (Step 1)
    $sessions = fetch_all(
        'SELECT * FROM student_attendance_sessions WHERE assignment_id IN (' . implode(',', array_fill(0, count($allAssignmentIds), '?')) . ') AND date = ? AND meeting_no = ?',
        array_merge($allAssignmentIds, [$date, $meetingNo])
    );
    $session = $sessions[0] ?? null;
    $sessionIds = array_values(array_filter(array_map(fn($s) => (int)$s['id'], $sessions)));

    $teacherAttended = false;
    foreach ($sessions as $s) {
        if (!empty($s['teacher_attended'])) {
            $teacherAttended = true;
            if (empty($session['teacher_attended'])) {
                $session = $s;
            }
            break;
        }
    }

    // Load Student Entries (Step 2)
    $entryMap = [];
    if (!empty($sessionIds)) {
        $entries = fetch_all(
            'SELECT student_id, status, notes, updated_at FROM student_attendance_entries WHERE session_id IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ')',
            $sessionIds
        );
        foreach ($entries as $ent) {
            $entryMap[(int)$ent['student_id']] = $ent;
        }
    }

    $students = fetch_all(
        'SELECT s.*, c.name AS class_name
         FROM students s
         JOIN classes c ON c.id = s.class_id
         WHERE s.class_id IN (' . implode(',', array_fill(0, count($allClassIds), '?')) . ') AND s.active = 1
         ORDER BY s.name',
        $allClassIds
    );
    foreach ($students as &$st) {
        $sid = (int)$st['id'];
        $st['status'] = $entryMap[$sid]['status'] ?? 'hadir'; // default hadir
        $st['notes'] = $entryMap[$sid]['notes'] ?? '';
    }
    unset($st);

    $studentAttendanceRecorded = !empty($entryMap);

    // Load Daily Journal (Step 3)
    $journal = fetch_one(
        'SELECT * FROM daily_journals WHERE assignment_id = ? AND date = ? AND meeting_no = ?',
        [$assignmentId, $date, $meetingNo]
    );
    if (!$journal) {
        // Fallback check by assignment and date
        $journal = fetch_one(
            'SELECT * FROM daily_journals WHERE assignment_id = ? AND date = ? ORDER BY id DESC LIMIT 1',
            [$assignmentId, $date]
        );
    }
    $journalStatus = (string)($journal['status'] ?? ($journal ? 'completed' : 'none'));

    // Tentukan Step Aktif
    $reqStep = (int)($_GET['step'] ?? 0);
    if ($reqStep >= 1 && $reqStep <= 3) {
        $step = $reqStep;
    } else {
        if (!$teacherAttended) {
            $step = 1;
        } elseif (!$studentAttendanceRecorded) {
            $step = 2;
        } else {
            $step = 3;
        }
    }

    $dow = (int)(new DateTimeImmutable($date))->format('N');
    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $dayName = $dayNames[$dow] ?? '-';

    render_header('Alur Mengajar: ' . ($assignment['class_name'] ?? 'Kelas') . ' - ' . ($assignment['subject_name'] ?? 'Mapel'));
    ?>

    <!-- Top Navigation Bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:1.25rem;">
        <div>
            <a href="<?= e(route_url('dashboard')) ?>" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:.88rem;color:var(--text-muted,#64748b);font-weight:600;margin-bottom:6px;">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Kembali ke Dashboard
            </a>
            <h2 style="margin:0;font-size:1.45rem;font-weight:700;color:var(--text,#1e293b);display:flex;align-items:center;gap:8px;">
                <i data-lucide="book-open" style="color:var(--primary,#2563eb);"></i>
                Alur Mengajar Kelas Terpandu
            </h2>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(route_url('rekap-kehadiran')) ?>" class="button small" style="display:inline-flex;align-items:center;gap:4px;">
                <i data-lucide="bar-chart-2" style="width:15px;height:15px;"></i> Rekap Kehadiran
            </a>
        </div>
    </div>

    <!-- Header Info Card -->
    <div class="panel" style="background:linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);color:white;border:none;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 10px 20px -5px rgba(37,99,235,.25);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="background:rgba(255,255,255,.2);display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600;margin-bottom:8px;backdrop-filter:blur(4px);">
                    <i data-lucide="calendar" style="width:14px;height:14px;"></i>
                    <?= e($dayName) ?>, <?= e(date('d F Y', strtotime($date))) ?>
                </div>
                <h3 style="margin:0 0 4px;font-size:1.35rem;color:white;font-weight:700;">
                    <?= e($assignment['class_name']) ?> &bull; <?= e($assignment['subject_name']) ?>
                </h3>
                <div style="font-size:.88rem;opacity:.9;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span>Guru: <strong><?= e($assignment['teacher_name'] ?? 'Guru Pengampu') ?></strong></span>
                    <span>&bull;</span>
                    <span>Pertemuan ke-<strong><?= $meetingNo ?></strong></span>
                    <?php if ($isCombinedGroup): ?>
                        <span>&bull;</span>
                        <span style="background:rgba(255,255,255,.25);padding:1px 8px;border-radius:4px;">Rombel Gabungan</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <!-- Pertemuan Selector -->
                <form method="get" style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:6px 10px;border-radius:8px;backdrop-filter:blur(4px);">
                    <input type="hidden" name="page" value="teaching-wizard">
                    <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                    <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <span style="font-size:.82rem;font-weight:600;">Pertemuan:</span>
                    <input type="number" name="meeting_no" value="<?= $meetingNo ?>" min="1" max="100" style="width:55px;padding:3px 6px;border-radius:4px;border:none;color:#000;font-weight:bold;text-align:center;">
                    <button type="submit" class="button small" style="background:white;color:var(--primary);border:none;font-weight:bold;padding:4px 8px;">Pilih</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Stepper Navigation (3 Langkah Alur Mengajar) -->
    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:12px;margin-bottom:1.5rem;">
        <!-- Step 1 Card -->
        <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => $assignmentId, 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 1])) ?>"
           style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;border:2px solid <?= $step === 1 ? 'var(--primary,#2563eb)' : ($teacherAttended ? '#86efac' : '#e2e8f0') ?>;background:<?= $step === 1 ? 'rgba(37,99,235,.05)' : ($teacherAttended ? '#f0fdf4' : '#fff') ?>;transition:all .2s ease;">
            <div style="width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:700;flex-shrink:0;background:<?= $teacherAttended ? '#16a34a' : ($step === 1 ? 'var(--primary,#2563eb)' : '#e2e8f0') ?>;color:<?= $teacherAttended || $step === 1 ? '#fff' : '#64748b' ?>;">
                <?php if ($teacherAttended): ?>
                    <i data-lucide="check" style="width:20px;height:20px;"></i>
                <?php else: ?>
                    1
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:<?= $step === 1 ? 'var(--primary,#2563eb)' : '#64748b' ?>;">Langkah 1</div>
                <div style="font-weight:600;font-size:.9rem;color:var(--text,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Absen Hadir Guru</div>
                <div style="font-size:.75rem;color:<?= $teacherAttended ? '#15803d' : '#94a3b8' ?>;font-weight:500;">
                    <?= $teacherAttended ? '✓ Sudah Hadir' : 'Wajib diisi guru' ?>
                </div>
            </div>
        </a>

        <!-- Step 2 Card -->
        <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => $assignmentId, 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 2])) ?>"
           style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;border:2px solid <?= $step === 2 ? 'var(--primary,#2563eb)' : ($studentAttendanceRecorded ? '#86efac' : '#e2e8f0') ?>;background:<?= $step === 2 ? 'rgba(37,99,235,.05)' : ($studentAttendanceRecorded ? '#f0fdf4' : '#fff') ?>;transition:all .2s ease;">
            <div style="width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:700;flex-shrink:0;background:<?= $studentAttendanceRecorded ? '#16a34a' : ($step === 2 ? 'var(--primary,#2563eb)' : '#e2e8f0') ?>;color:<?= $studentAttendanceRecorded || $step === 2 ? '#fff' : '#64748b' ?>;">
                <?php if ($studentAttendanceRecorded): ?>
                    <i data-lucide="check" style="width:20px;height:20px;"></i>
                <?php else: ?>
                    2
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:<?= $step === 2 ? 'var(--primary,#2563eb)' : '#64748b' ?>;">Langkah 2</div>
                <div style="font-weight:600;font-size:.9rem;color:var(--text,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Absensi Siswa</div>
                <div style="font-size:.75rem;color:<?= $studentAttendanceRecorded ? '#15803d' : '#94a3b8' ?>;font-weight:500;">
                    <?= $studentAttendanceRecorded ? '✓ ' . count($entryMap) . ' Siswa tercatat' : ($teacherAttended ? 'Siap diisi' : 'Terkunci') ?>
                </div>
            </div>
        </a>

        <!-- Step 3 Card -->
        <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => $assignmentId, 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 3])) ?>"
           style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;border:2px solid <?= $step === 3 ? 'var(--primary,#2563eb)' : ($journalStatus === 'completed' ? '#86efac' : ($journalStatus === 'draft' ? '#93c5fd' : '#e2e8f0')) ?>;background:<?= $step === 3 ? 'rgba(37,99,235,.05)' : ($journalStatus === 'completed' ? '#f0fdf4' : ($journalStatus === 'draft' ? '#eff6ff' : '#fff')) ?>;transition:all .2s ease;">
            <div style="width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:700;flex-shrink:0;background:<?= $journalStatus === 'completed' ? '#16a34a' : ($journalStatus === 'draft' ? '#2563eb' : ($step === 3 ? 'var(--primary,#2563eb)' : '#e2e8f0')) ?>;color:<?= $journalStatus !== 'none' || $step === 3 ? '#fff' : '#64748b' ?>;">
                <?php if ($journalStatus === 'completed'): ?>
                    <i data-lucide="check" style="width:20px;height:20px;"></i>
                <?php elseif ($journalStatus === 'draft'): ?>
                    <i data-lucide="file-edit" style="width:18px;height:18px;"></i>
                <?php else: ?>
                    3
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:<?= $step === 3 ? 'var(--primary,#2563eb)' : '#64748b' ?>;">Langkah 3</div>
                <div style="font-weight:600;font-size:.9rem;color:var(--text,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Jurnal Mengajar</div>
                <div style="font-size:.75rem;color:<?= $journalStatus === 'completed' ? '#15803d' : ($journalStatus === 'draft' ? '#2563eb' : '#94a3b8') ?>;font-weight:500;">
                    <?= $journalStatus === 'completed' ? '✓ Lengkap' : ($journalStatus === 'draft' ? '📝 Draf (Bisa Lanjut)' : 'Belum diisi') ?>
                </div>
            </div>
        </a>
    </div>

    <?php
    // RENDER STEP CONTENT
    if ($step === 1) {
        render_wizard_step1($assignment, $date, $meetingNo, $scheduleId, $session, $teacherAttended);
    } elseif ($step === 2) {
        render_wizard_step2($assignment, $date, $meetingNo, $scheduleId, $students, $teacherAttended, $studentAttendanceRecorded, $entryMap);
    } else {
        render_wizard_step3($assignment, $date, $meetingNo, $scheduleId, $journal, $studentAttendanceRecorded);
    }

    render_footer();
}

/**
 * STEP 1: ABSEN HADIR GURU DI KELAS
 */
function render_wizard_step1(array $assignment, string $date, int $meetingNo, int $scheduleId, ?array $session, bool $teacherAttended): void
{
    ?>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="user-check" style="color:var(--primary);"></i>
                <span>Langkah 1: Konfirmasi Kehadiran Guru di Kelas</span>
            </div>
        </div>

        <?php if ($teacherAttended): ?>
            <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                    <div style="background:#16a34a;color:white;width:44px;height:44px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;">
                        <i data-lucide="check-circle" style="width:24px;height:24px;"></i>
                    </div>
                    <div style="flex:1;min-width:260px;">
                        <h4 style="margin:0 0 6px;color:#166534;font-size:1.15rem;font-weight:700;">
                            Guru Terkonfirmasi Hadir di Kelas
                        </h4>
                        <div style="color:#14532d;font-size:.92rem;line-height:1.5;">
                            Pukul Masuk: <strong><?= e(substr((string)($session['teacher_time_in'] ?? date('H:i')), 0, 5)) ?> WIB</strong> &bull;
                            Status: <strong><?= e(ucfirst((string)($session['teacher_status'] ?? 'Hadir'))) ?></strong>
                            <?php if (!empty($session['teacher_notes'])): ?>
                                <br>Catatan: <em><?= e($session['teacher_notes']) ?></em>
                            <?php endif; ?>
                            <?php if (!empty($session['teacher_lat']) && !empty($session['teacher_lng'])): ?>
                                <br><span style="color:#15803d;font-weight:600;display:inline-flex;align-items:center;gap:4px;">📍 Lokasi GPS Terverifikasi (<?= round((float)$session['teacher_lat'], 5) ?>, <?= round((float)$session['teacher_lng'], 5) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-self:center;">
                        <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => (int)$assignment['id'], 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 2])) ?>"
                           class="button primary" style="display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:.65rem 1.25rem;">
                            Lanjut ke Absensi Siswa <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="background:#fffbeb;border:2px solid #fde68a;border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    <div style="background:#f59e0b;color:white;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;">
                        <i data-lucide="info" style="width:20px;height:20px;"></i>
                    </div>
                    <div>
                        <h4 style="margin:0 0 4px;color:#92400e;font-size:1.05rem;font-weight:700;">
                            Presensi Masuk Mengajar
                        </h4>
                        <p style="margin:0;color:#78350f;font-size:.9rem;line-height:1.45;">
                            Sebelum mengabsen siswa dan mengisi jurnal kegiatan, silakan konfirmasi bahwa Anda telah berada di dalam kelas <strong><?= e($assignment['class_name']) ?></strong> untuk mata pelajaran <strong><?= e($assignment['subject_name']) ?></strong>.
                        </p>
                    </div>
                </div>
            </div>

            <form method="post" id="form-wizard-checkin" class="grid two" style="gap:1rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkin_teacher_classroom">
                <input type="hidden" name="return_to" value="teaching-wizard">
                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="meeting_no" value="<?= $meetingNo ?>">
                <input type="hidden" name="lat" id="wiz-lat">
                <input type="hidden" name="lng" id="wiz-lng">

                <label>
                    <span style="font-weight:600;">Status Kehadiran Mengajar</span>
                    <select name="teacher_status">
                        <option value="hadir" selected>Hadir Mengajar Sendiri</option>
                        <option value="inval">Guru Pengganti (Inval)</option>
                    </select>
                </label>

                <label>
                    <span style="font-weight:600;">Waktu Masuk Kelas</span>
                    <input type="time" name="teacher_time_in" value="<?= date('H:i') ?>" required>
                </label>

                <label class="wide">
                    <span style="font-weight:600;">Catatan / Informasi Pengganti (Opsional)</span>
                    <input type="text" name="teacher_notes" placeholder="Contoh: Menggantikan jam Ust. Fulan / Kelas tepat waktu">
                </label>

                <div class="wide" id="wiz-geo-status" style="font-size:.85rem;display:flex;align-items:center;gap:6px;color:#b45309;font-weight:500;">
                    <i data-lucide="map-pin" style="width:16px;height:16px;"></i>
                    <span>Mendeteksi lokasi GPS perangkat...</span>
                </div>

                <div class="wide actions" style="margin-top:.5rem;">
                    <button type="submit" class="button primary" style="font-size:1rem;padding:.75rem 1.5rem;font-weight:700;display:inline-flex;align-items:center;gap:8px;">
                        <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
                        Konfirmasi Saya Hadir di Kelas
                    </button>
                </div>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var geoStatus = document.getElementById('wiz-geo-status');
                var latInput = document.getElementById('wiz-lat');
                var lngInput = document.getElementById('wiz-lng');
                if (!geoStatus || !latInput || !lngInput) return;

                if (!navigator.geolocation) {
                    geoStatus.innerHTML = '<span style="color:#b91c1c;">⚠️ Browser tidak mendukung Geolocation GPS.</span>';
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        latInput.value = pos.coords.latitude;
                        lngInput.value = pos.coords.longitude;
                        geoStatus.innerHTML = '<span style="color:#15803d;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><i data-lucide="check"></i> Lokasi GPS terdeteksi (' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5) + ')</span>';
                        if (window.lucide) lucide.createIcons();
                    },
                    function (err) {
                        geoStatus.innerHTML = '<span style="color:#64748b;">Lokasi opsional (' + err.message + '). Tetap dapat melakukan absensi.</span>';
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                );
            });
            </script>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * STEP 2: ABSEN MURID
 */
function render_wizard_step2(array $assignment, string $date, int $meetingNo, int $scheduleId, array $students, bool $teacherAttended, bool $studentAttendanceRecorded, array $entryMap): void
{
    ?>
    <section class="panel">
        <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div class="panel-title">
                <i data-lucide="users" style="color:var(--primary);"></i>
                <span>Langkah 2: Presensi Siswa di Kelas (Pertemuan ke-<?= $meetingNo ?>)</span>
            </div>
            <?php if ($studentAttendanceRecorded): ?>
                <span class="badge ok" style="padding:4px 10px;font-size:.82rem;">✓ Data Presensi Siswa Tersimpan</span>
            <?php endif; ?>
        </div>

        <?php if (!$teacherAttended): ?>
            <div style="background:#fffbeb;border:2px solid #fde68a;border-radius:12px;padding:2rem;text-align:center;margin-bottom:1.5rem;">
                <div style="width:48px;height:48px;border-radius:50%;background:#fde68a;color:#d97706;display:grid;place-items:center;margin:0 auto 12px;">
                    <i data-lucide="lock" style="width:24px;height:24px;"></i>
                </div>
                <h4 style="margin:0 0 6px;color:#92400e;font-size:1.15rem;font-weight:700;">Presensi Siswa Terkunci</h4>
                <p style="margin:0 0 1rem;color:#78350f;font-size:.9rem;">
                    Anda harus melakukan konfirmasi kehadiran guru di kelas (Langkah 1) terlebih dahulu agar daftar presensi siswa dapat diisi.
                </p>
                <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => (int)$assignment['id'], 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 1])) ?>"
                   class="button primary" style="display:inline-flex;align-items:center;gap:6px;">
                    <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Kembali ke Langkah 1 (Absen Guru)
                </a>
            </div>
        <?php else: ?>
            <!-- Quick Actions Bar -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:1.25rem;background:var(--bg-card,#f8fafc);padding:.75rem 1rem;border-radius:10px;border:1px solid var(--border,#e2e8f0);">
                <div style="display:flex;align-items:center;gap:12px;font-size:.88rem;color:var(--text-muted,#64748b);">
                    <span>Total: <strong><?= count($students) ?> Siswa</strong></span>
                    <span id="counter-hadir" style="color:#15803d;font-weight:600;">Hadir: 0</span>
                    <span id="counter-sakit" style="color:#b45309;font-weight:600;">Sakit: 0</span>
                    <span id="counter-izin" style="color:#0284c7;font-weight:600;">Izin: 0</span>
                    <span id="counter-alpa" style="color:#b91c1c;font-weight:600;">Alpa: 0</span>
                </div>
                <div>
                    <button type="button" class="button small" id="btn-set-all-hadir" style="display:inline-flex;align-items:center;gap:4px;font-weight:600;background:#dcfce7;color:#15803d;border-color:#86efac;">
                        <i data-lucide="check-check" style="width:14px;height:14px;"></i> Tandai Semua Hadir
                    </button>
                </div>
            </div>

            <form method="post" id="form-student-attendance">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_student_attendance">
                <input type="hidden" name="return_to" value="teaching-wizard">
                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="meeting_no" value="<?= $meetingNo ?>">

                <div class="table-wrap" style="margin-bottom:1.5rem;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:35px;text-align:center;">No</th>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <th style="min-width:320px;">Status Kehadiran</th>
                                <th style="min-width:200px;">Catatan (Opsional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($students as $st):
                                $sid = (int)$st['id'];
                                $curStatus = (string)($st['status'] ?? 'hadir');
                            ?>
                                <tr>
                                    <td style="text-align:center;"><?= $no++ ?></td>
                                    <td style="font-weight:600;">
                                        <input type="hidden" name="entries[<?= $sid ?>][student_id]" value="<?= $sid ?>">
                                        <?= e($st['name']) ?>
                                    </td>
                                    <td style="color:var(--text-muted);font-size:.85rem;"><?= e($st['nis'] ?: '-') ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <?php foreach (['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa', 'terlambat' => 'Telat'] as $val => $lbl): ?>
                                                <label style="margin:0;cursor:pointer;display:inline-flex;align-items:center;gap:3px;font-size:.82rem;padding:3px 8px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;" class="att-radio-label">
                                                    <input type="radio" name="entries[<?= $sid ?>][status]" value="<?= $val ?>" <?= $curStatus === $val ? 'checked' : '' ?> class="att-radio" data-status="<?= $val ?>">
                                                    <span><?= $lbl ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="entries[<?= $sid ?>][notes]" value="<?= e($st['notes'] ?? '') ?>" placeholder="Keterangan..." style="font-size:.82rem;padding:4px 8px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => (int)$assignment['id'], 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 1])) ?>"
                       class="button" style="display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Kembali ke Langkah 1
                    </a>
                    <button type="submit" class="button primary" style="font-size:1rem;padding:.75rem 1.5rem;font-weight:700;display:inline-flex;align-items:center;gap:8px;">
                        <i data-lucide="save" style="width:18px;height:18px;"></i>
                        Simpan Absensi Siswa & Lanjut ke Jurnal
                        <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                function updateCounters() {
                    var counts = { hadir: 0, sakit: 0, izin: 0, alpa: 0, terlambat: 0 };
                    document.querySelectorAll('.att-radio:checked').forEach(function (r) {
                        var st = r.value;
                        if (counts[st] !== undefined) counts[st]++;
                    });
                    var cHadir = document.getElementById('counter-hadir');
                    var cSakit = document.getElementById('counter-sakit');
                    var cIzin = document.getElementById('counter-izin');
                    var cAlpa = document.getElementById('counter-alpa');
                    if (cHadir) cHadir.textContent = 'Hadir: ' + counts.hadir;
                    if (cSakit) cSakit.textContent = 'Sakit: ' + counts.sakit;
                    if (cIzin) cIzin.textContent = 'Izin: ' + counts.izin;
                    if (cAlpa) cAlpa.textContent = 'Alpa: ' + counts.alpa;
                }

                document.querySelectorAll('.att-radio').forEach(function (r) {
                    r.addEventListener('change', updateCounters);
                });
                updateCounters();

                var btnAllHadir = document.getElementById('btn-set-all-hadir');
                if (btnAllHadir) {
                    btnAllHadir.addEventListener('click', function () {
                        document.querySelectorAll('.att-radio[value="hadir"]').forEach(function (r) {
                            r.checked = true;
                        });
                        updateCounters();
                    });
                }
            });
            </script>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * STEP 3: JURNAL PEMBELAJARAN (DENGAN DRAFT & LANJUTKAN)
 */
function render_wizard_step3(array $assignment, string $date, int $meetingNo, int $scheduleId, ?array $journal, bool $studentAttendanceRecorded): void
{
    $isDraft = ($journal && ($journal['status'] ?? '') === 'draft');
    $isCompleted = ($journal && ($journal['status'] ?? '') === 'completed');
    ?>
    <section class="panel">
        <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div class="panel-title">
                <i data-lucide="book-open" style="color:var(--primary);"></i>
                <span>Langkah 3: Jurnal Kegiatan Pembelajaran (Pertemuan ke-<?= $meetingNo ?>)</span>
            </div>
            <?php if ($isCompleted): ?>
                <span class="badge ok" style="padding:4px 10px;font-size:.82rem;">✓ Jurnal Selesai Tersimpan</span>
            <?php elseif ($isDraft): ?>
                <span class="badge warning" style="padding:4px 10px;font-size:.82rem;">📝 Status: Draft (Belum Selesai)</span>
            <?php endif; ?>
        </div>

        <?php if ($isDraft): ?>
            <div style="background:#eff6ff;border:2px solid #bfdbfe;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:12px;">
                <div style="background:#2563eb;color:white;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;">
                    <i data-lucide="file-edit" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <h4 style="margin:0 0 2px;color:#1e40af;font-size:1rem;font-weight:700;">Melanjutkan Draft Jurnal Sebelumnya</h4>
                    <p style="margin:0;color:#1e3a8a;font-size:.88rem;">
                        Anda sedang melanjutkan draft jurnal yang sebelumnya disimpan. Lengkapi uraian kegiatan atau kendala di bawah, lalu klik <strong>"Selesai & Simpan Jurnal"</strong>.
                    </p>
                </div>
            </div>
        <?php elseif ($isCompleted): ?>
            <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:12px;">
                <div style="background:#16a34a;color:white;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;">
                    <i data-lucide="check-circle" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <h4 style="margin:0 0 2px;color:#166534;font-size:1rem;font-weight:700;">Jurnal Mengajar Telah Lengkap</h4>
                    <p style="margin:0;color:#14532d;font-size:.88rem;">
                        Semua alur mengajar pada pertemuan ini (Absen Guru, Absen Murid, dan Jurnal) telah tuntas. Anda tetap dapat memperbarui isi jurnal jika ada perubahan.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="grid two" style="gap:1.25rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_journal">
            <input type="hidden" name="return_to" value="teaching-wizard">
            <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
            <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
            <input type="hidden" name="id" value="<?= (int)($journal['id'] ?? 0) ?>">
            <input type="hidden" name="date" value="<?= e($date) ?>">
            <input type="hidden" name="meeting_no" value="<?= $meetingNo ?>">

            <label class="wide">
                <span style="font-weight:700;color:var(--text,#1e293b);">Topik / Materi Pokok Pembelajaran <span style="color:#dc2626;">*</span></span>
                <input type="text" name="topic" required value="<?= e($journal['topic'] ?? '') ?>" placeholder="Contoh: Operasi Hitung Aljabar / Thaharah & Macam-Macam Najis">
            </label>

            <label class="wide">
                <span style="font-weight:700;color:var(--text,#1e293b);">Kegiatan Pembelajaran yang Dilaksanakan <span style="color:#dc2626;">*</span></span>
                <textarea name="activities" rows="4" required placeholder="Contoh: 1. Penjelasan konsep materi dasar&#10;2. Diskusi kelompok kecil dan latihan soal&#10;3. Presentasi perwakilan siswa dan refleksi bersama"><?= e($journal['activities'] ?? '') ?></textarea>
            </label>

            <label class="wide">
                <span style="font-weight:600;color:var(--text,#1e293b);">Bahan Ajar / Media / Sumber Belajar (Opsional)</span>
                <textarea name="materials" rows="2" placeholder="Contoh: Buku Paket Hal. 45-52, Lembar Kerja Siswa (LKS), Video Animasi Pembelajaran"><?= e($journal['materials'] ?? '') ?></textarea>
            </label>

            <label>
                <span style="font-weight:600;color:var(--text,#1e293b);">Kendala / Hambatan Siswa (Opsional)</span>
                <textarea name="obstacles" rows="2" placeholder="Contoh: Sebagian siswa masih bingung membedakan variabel..."><?= e($journal['obstacles'] ?? '') ?></textarea>
            </label>

            <label>
                <span style="font-weight:600;color:var(--text,#1e293b);">Tindak Lanjut / Penugasan (Opsional)</span>
                <textarea name="follow_up" rows="2" placeholder="Contoh: Remedial singkat pada awal pertemuan berikutnya / Tugas mandiri No. 1-5"><?= e($journal['follow_up'] ?? '') ?></textarea>
            </label>

            <div class="wide" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:1rem;border-top:1px solid var(--border,#e2e8f0);padding-top:1.25rem;">
                <div style="display:flex;gap:8px;">
                    <a href="<?= e(route_url('teaching-wizard', ['assignment_id' => (int)$assignment['id'], 'schedule_id' => $scheduleId, 'date' => $date, 'meeting_no' => $meetingNo, 'step' => 2])) ?>"
                       class="button" style="display:inline-flex;align-items:center;gap:6px;">
                        <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Kembali ke Absensi Siswa
                    </a>
                    <a href="<?= e(route_url('dashboard')) ?>" class="button">Kembali ke Dashboard</a>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <!-- Tombol Simpan Draft (Lanjutkan Nanti) -->
                    <button type="submit" name="status" value="draft" class="button" style="display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;color:#334155;border-color:#cbd5e1;font-weight:600;" title="Simpan sebagai draft untuk dilanjutkan nanti">
                        <i data-lucide="file-edit" style="width:16px;height:16px;color:#2563eb;"></i>
                        Simpan Draft (Lanjutkan Nanti)
                    </button>

                    <!-- Tombol Selesai Mengajar & Simpan Jurnal Final -->
                    <button type="submit" name="status" value="completed" class="button primary" style="display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:.65rem 1.4rem;background:#16a34a;border-color:#16a34a;">
                        <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
                        Selesai & Simpan Jurnal
                    </button>
                </div>
            </div>
        </form>
    </section>
    <?php
}

