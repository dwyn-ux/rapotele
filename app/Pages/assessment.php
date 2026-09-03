<?php declare(strict_types=1);

function handle_post_action(): void
{
    app_handle_post_request();
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
        $classRow = fetch_one('SELECT name FROM classes WHERE id = ?', [$classId]);
        $className = $classRow ? (string)$classRow['name'] : ('#' . $classId);
        render_access_denied('Anda tidak mengajar di kelas ' . $className . '. Hanya admin yang dapat mengakses data kelas ini.');
    }
}

function require_subject_access(int $subjectId, int $classId): void
{
    if (is_admin()) {
        return;
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($subjectId <= 0 || $classId <= 0 || $teacherId <= 0
        || !fetch_one('SELECT id FROM teaching_assignments WHERE subject_id = ? AND class_id = ? AND teacher_id = ? AND active = 1 LIMIT 1', [$subjectId, $classId, $teacherId])) {
        $classRow = fetch_one('SELECT name FROM classes WHERE id = ?', [$classId]);
        $subjectRow = fetch_one('SELECT name FROM subjects WHERE id = ?', [$subjectId]);
        $className = $classRow ? (string)$classRow['name'] : ('kelas #' . $classId);
        $subjectName = $subjectRow ? (string)$subjectRow['name'] : ('mapel #' . $subjectId);
        render_access_denied('Anda tidak mengajar ' . $subjectName . ' di kelas ' . $className . '. Hanya guru pengampu mapel ini atau admin yang dapat mengakses.');
    }
}

function subjects_for_current_user_in_class(int $classId): array
{
    if (is_admin()) {
        return fetch_all(
            'SELECT DISTINCT s.id, s.name
             FROM subjects s
             JOIN teaching_assignments ta ON ta.subject_id = s.id
             WHERE ta.class_id = ? AND ta.active = 1 AND s.active = 1
             ORDER BY s.name',
            [$classId]
        );
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        return [];
    }

    return fetch_all(
        'SELECT DISTINCT s.id, s.name
         FROM subjects s
         JOIN teaching_assignments ta ON ta.subject_id = s.id
         WHERE ta.class_id = ? AND ta.teacher_id = ? AND ta.active = 1 AND s.active = 1
         ORDER BY s.name',
        [$classId, $teacherId]
    );
}

function subjects_for_current_user(): array
{
    if (is_admin()) {
        return fetch_all('SELECT id, name FROM subjects WHERE active = 1 ORDER BY name');
    }

    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        return [];
    }

    return fetch_all(
        'SELECT DISTINCT s.id, s.name
         FROM subjects s
         JOIN teaching_assignments ta ON ta.subject_id = s.id
         WHERE ta.teacher_id = ? AND ta.active = 1 AND s.active = 1
         ORDER BY s.name',
        [$teacherId]
    );
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
        render_access_denied('Akun ini belum terhubung dengan data siswa. Hubungi admin untuk ربط akun dengan data siswa.');
    }
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name, c.grade, s.location_lat, s.location_lng, sp.location_lat AS school_lat, sp.location_lng AS school_lng, sp.attendance_radius_meters AS radius
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN school_profile sp ON sp.id = 1
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

function page_grades(): void
{
    require_role(['admin', 'guru']);
    $assignmentId = (int)($_GET['assignment_id'] ?? 0);
    $assessmentType = (string)($_GET['type'] ?? 'UH');
    $validTypes = array_keys(assessment_type_options());
    if (!in_array($assessmentType, $validTypes, true)) {
        $assessmentType = 'UH';
    }
    $assignments = assignments_for_current_user();
    $assignment = $assignmentId > 0 ? require_assignment_access($assignmentId) : ($assignments[0] ?? null);
    render_header('Input Nilai');
    assignment_picker('grades', $assignments, $assignment ? (int)$assignment['id'] : 0, $assessmentType);
    if (!$assignment) {
        echo '<section class="panel"><p class="empty">Belum ada pembelajaran.</p></section>';
        render_footer();
        return;
    }

    $tps = learning_objectives_for_assignment($assignment);
    $students = fetch_all(
        'SELECT s.id, s.name, s.nis FROM students s WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name',
        [(int)$assignment['class_id']]
    );

    $existingGrades = fetch_all(
        'SELECT * FROM grades WHERE assignment_id = ? AND assessment_type = ?',
        [(int)$assignment['id'], $assessmentType]
    );
    $gradeMap = [];
    foreach ($existingGrades as $g) {
        $key = (int)$g['student_id'] . '_' . ((int)($g['learning_objective_id'] ?? 0));
        $gradeMap[$key] = $g;
    }
    ?>
    <section class="panel">
        <h3><?= e($assignment['class_name']) ?> — <?= e($assignment['subject_name']) ?></h3>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <?php foreach (assessment_type_options() as $typeKey => $typeLabel): ?>
                <a class="button <?= $typeKey === $assessmentType ? 'primary' : '' ?>" href="<?= e(route_url('grades', ['assignment_id' => (int)$assignment['id'], 'type' => $typeKey])) ?>"><?= e($typeLabel) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($tps): ?>
            <?php foreach ($tps as $tp): ?>
                <form method="post" style="margin-bottom:1.5rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_grades">
                    <input type="hidden" name="assignment_id" value="<?= e($assignment['id']) ?>">
                    <input type="hidden" name="assessment_type" value="<?= e($assessmentType) ?>">
                    <input type="hidden" name="learning_objective_id" value="<?= e($tp['id']) ?>">
                    <div class="panel-heading" style="margin:calc(-1 * var(--space-6)) calc(-1 * var(--space-6)) var(--space-4);padding:var(--space-3) var(--space-6);background:var(--surface-secondary);border-radius:var(--radius-xl) var(--radius-xl) 0 0;">
                        <div class="panel-title" style="font-size:var(--font-size-sm);">
                            <span style="display:inline-block;background:var(--primary);color:#fff;padding:2px 8px;border-radius:4px;font-size:0.7rem;font-weight:700;margin-right:6px;">TP</span>
                            <?= e($tp['description']) ?>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>No</th><th>Nama Siswa</th><th>NIS</th><th>Nilai</th></tr></thead>
                            <tbody>
                                <?php foreach ($students as $i => $student): ?>
                                    <?php $key = $student['id'] . '_' . $tp['id']; $g = $gradeMap[$key] ?? []; ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= e($student['name']) ?></td>
                                        <td><?= e($student['nis']) ?></td>
                                        <td><input class="small-input" type="number" min="0" max="100" step="0.01" name="score[<?= e($student['id']) ?>]" value="<?= e($g['score'] ?? '') ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="actions"><button class="button primary">Simpan — <?= e(assessment_type_label($assessmentType)) ?></button></div>
                </form>
            <?php endforeach; ?>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_grades">
                <input type="hidden" name="assignment_id" value="<?= e($assignment['id']) ?>">
                <input type="hidden" name="assessment_type" value="<?= e($assessmentType) ?>">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>No</th><th>Nama Siswa</th><th>NIS</th><th>Nilai</th><th>Catatan</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $i => $student): ?>
                                <?php $key = $student['id'] . '_0'; $g = $gradeMap[$key] ?? []; ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($student['name']) ?></td>
                                    <td><?= e($student['nis']) ?></td>
                                    <td><input class="small-input" type="number" min="0" max="100" step="0.01" name="score[<?= e($student['id']) ?>]" value="<?= e($g['score'] ?? '') ?>"></td>
                                    <td><input type="text" name="description[<?= e($student['id']) ?>]" value="<?= e($g['description'] ?? '') ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="actions"><button class="button primary">Simpan — <?= e(assessment_type_label($assessmentType)) ?></button></div>
            </form>
            <p style="color:var(--text-muted);font-size:.875rem;margin-top:1rem;">Belum ada Tujuan Pembelajaran (TP) untuk mapel ini di kelas <?= e($assignment['class_name']) ?>. Input nilai polos tersedia, atau buat TP terlebih dulu di menu <a href="<?= e(route_url('data-tp')) ?>">Tujuan Pembelajaran</a>.</p>
        <?php endif; ?>
        <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="button" href="<?= e(route_url('export-csv', ['type' => 'nilai', 'assignment_id' => (int)$assignment['id']])) ?>">Export CSV</a>
            <a class="button" href="<?= e(route_url('export-csv', ['type' => 'absensi', 'assignment_id' => (int)$assignment['id']])) ?>">Export Absensi CSV</a>
        </div>
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
            <?php render_cascading_assignment_selects($assignments, (int)($assignment['id'] ?? 0), 'sa-picker'); ?>
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
            <label>Topik Pertemuan <input type="text" name="topic" value="<?= e($session['topic'] ?? '') ?>" placeholder="Misal: Pecahan sederhana"></label>
            <label class="checkbox"><input type="checkbox" name="queue_whatsapp_absence" value="1"> Tambahkan WA untuk siswa selain hadir</label>
            <div class="table-wrap">
                <table><thead><tr><th>Nama Siswa</th><th>NIS</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['name']) ?></td><td><?= e($student['nis']) ?></td>
                            <td><select name="status[<?= e($student['id']) ?>]"><?= options(allowed_statuses(), $student['status'] ?? 'hadir') ?></select></td>
                            <td><input type="text" name="notes[<?= e($student['id']) ?>]" value="<?= e($student['notes']) ?>"></td>
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
                                <td><input type="text" name="notes[<?= e($scheduleId) ?>]" value="<?= e($row['notes']) ?>" placeholder="Materi/kelas pengganti/catatan"></td>
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

function page_teacher_attendance_self(): void
{
    require_role(['guru']);
    $user = current_user();
    $teacherId = (int)($user['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        exit('Akun ini belum terhubung dengan data guru.');
    }
    $date = date('Y-m-d');
    $school = get_school_profile();
    $attendance = fetch_one('SELECT * FROM teacher_attendance WHERE teacher_id = ? AND date = ?', [$teacherId, $date]);
    $hasLocation = !empty($school['location_lat']) && !empty($school['location_lng']);
    render_header('Absensi Guru');
    ?>
    <section class="panel">
        <h3>Absensi Kehadiran Guru</h3>
        <p class="hint">Tanggal: <?= e($date) ?></p>
        <?php if (!$hasLocation): ?>
            <div class="alert warning">Lokasi sekolah belum diatur. Admin harus mengisi Latitude/Longitude sekolah di menu Data Sekolah.</div>
        <?php endif; ?>
        <div id="attendance-status">
            <?php if ($attendance): ?>
                <p>Status: <strong><?= e(teacher_attendance_statuses()[$attendance['status']] ?? $attendance['status']) ?></strong></p>
                <?php if ($attendance['time_in']): ?>
                    <p>Jam Masuk: <strong><?= e($attendance['time_in']) ?></strong></p>
                <?php endif; ?>
                <?php if ($attendance['time_out']): ?>
                    <p>Jam Pulang: <strong><?= e($attendance['time_out']) ?></strong></p>
                <?php endif; ?>
                <?php if ($attendance['time_in'] && !$attendance['time_out']): ?>
                    <form method="post" id="attendance-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_teacher_attendance_self">
                        <input type="hidden" name="type" value="checkout">
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                        <button class="button primary" id="checkout-btn" <?= $hasLocation ? '' : 'disabled' ?>>Checkout / Pulang</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" id="attendance-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_teacher_attendance_self">
                    <input type="hidden" name="type" value="checkin">
                    <input type="hidden" name="status" value="hadir">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                    <button class="button primary" id="checkin-btn" <?= $hasLocation ? '' : 'disabled' ?>>Checkin / Masuk</button>
                </form>
            <?php endif; ?>
        </div>
        <div id="geo-status" style="margin-top:8px;font-size:0.9em"></div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var geoStatus = document.getElementById('geo-status');
        if (!navigator.geolocation) {
            geoStatus.textContent = 'Geolocation tidak didukung browser ini.';
            return;
        }
        function setPosition(lat, lng) {
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
            geoStatus.textContent = 'Lokasi terdeteksi: ' + lat + ', ' + lng;
            <?php if (!$hasLocation): ?>
            geoStatus.textContent += ' (lokasi sekolah belum diatur)';
            <?php endif; ?>
        }
        function showError(err) {
            geoStatus.textContent = 'Gagal dapat lokasi: ' + err.message;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) { setPosition(pos.coords.latitude, pos.coords.longitude); },
            showError,
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
    </script>
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
            <?php render_cascading_assignment_selects($assignments, (int)($edit['assignment_id'] ?? 0), 'journal-picker'); ?>
            <label>Tanggal <input type="date" name="date" required value="<?= e($edit['date'] ?? date('Y-m-d')) ?>"></label>
            <label>Pertemuan <input type="number" min="1" name="meeting_no" value="<?= e($edit['meeting_no'] ?? 1) ?>"></label>
            <label>Topik <input type="text" name="topic" required value="<?= e($edit['topic'] ?? '') ?>"></label>
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

function page_deskripsi_nilai(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $subjectId = (int)($_GET['subject_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }

    $class = $classId ? fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]) : null;
    $grade = $class ? (string)($class['grade'] ?? '') : '';

    $subjects = [];
    if ($classId) {
        $subjects = subjects_for_current_user_in_class($classId);
        if (!is_admin() && $subjectId > 0) {
            $allowed = false;
            foreach ($subjects as $s) {
                if ((int)$s['id'] === $subjectId) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                $subjectId = 0;
            }
        }
    }

    render_header('Deskripsi Nilai');
    ?>
    <section class="panel no-print">
        <?php panel_title('Deskripsi Nilai per Siswa per Mapel'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="deskripsi-nilai">
            <label>Pilih Kelas <select name="class_id" onchange="this.form.submit()"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <label>Pilih Mapel <select name="subject_id" onchange="this.form.submit()">
                <option value="">-- Semua Mapel --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['id']) ?>" <?= (int)$subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select></label>
            <div class="actions"><button type="submit" class="button">Tampilkan</button>
            <?php if ($classId): ?>
                <a class="button warning" href="<?= e(route_url('deskripsi-nilai', ['class_id' => $classId, 'subject_id' => $subjectId, 'generate_all' => 1])) ?>" onclick="return confirm('Generate ulang deskripsi otomatis untuk semua siswa? Deskripsi yang sudah diedit akan ditimpa.')">Generate Ulang Semua</a>
            <?php endif; ?>
            </div>
        </form>
    </section>
    <?php
    if (!$classId || !$grade) {
        echo '<section class="panel"><p class="empty">Pilih kelas terlebih dulu.</p></section>';
        render_footer();
        return;
    }

    $students = fetch_all(
        'SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name',
        [$classId]
    );

    $kkm = (int)get_app_setting('grade.kkm', '70');
    $examWeight = (int)get_app_setting('grade.exam_weight', '40');
    $dailyWeight = 100 - $examWeight;

    $allObjectives = fetch_all(
        'SELECT lo.id, lo.subject_id, lo.description FROM learning_objectives lo WHERE lo.grade = ? AND lo.active = 1',
        [$grade]
    );
    $objectivesBySubject = [];
    foreach ($allObjectives as $lo) {
        $objectivesBySubject[(int)$lo['subject_id']][] = $lo['description'];
    }

    $filterSubjects = $subjectId > 0 ? array_filter($subjects, fn($s) => (int)$s['id'] === $subjectId) : $subjects;

    foreach ($students as $student):
        $studentId = (int)$student['id'];
        ?>
        <section class="panel">
            <h3><?= e($student['name']) ?> — <?= e($student['nis'] ?? '') ?></h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_deskripsi_nilai">
                <input type="hidden" name="student_id" value="<?= e($studentId) ?>">
                <input type="hidden" name="class_id" value="<?= e($classId) ?>">
                <input type="hidden" name="grade" value="<?= e($grade) ?>">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Mapel</th><th>Nilai Akhir</th><th>Predikat</th><th style="width:50%">Deskripsi Capaian Kompetensi</th></tr></thead>
                        <tbody>
                        <?php foreach ($filterSubjects as $subject):
                            $sid = (int)$subject['id'];
                            $ta = fetch_one(
                                'SELECT id FROM teaching_assignments WHERE subject_id = ? AND class_id = ? AND active = 1 LIMIT 1',
                                [$sid, $classId]
                            );
                            $assignmentId = $ta ? (int)$ta['id'] : 0;
                            $dailyAvg = 0;
                            $examScore = 0;
                            $finalScore = 0;
                            if ($assignmentId) {
                                $g = fetch_one('SELECT AVG(score) AS avg_score FROM grades WHERE assignment_id = ? AND student_id = ?', [$assignmentId, $studentId]);
                                $dailyAvg = (float)($g['avg_score'] ?? 0);
                            }
                            $es = fetch_one('SELECT score FROM exam_scores WHERE subject_id = ? AND student_id = ?', [$sid, $studentId]);
                            $examScore = (float)($es['score'] ?? 0);
                            $fs = fetch_one('SELECT score FROM final_scores WHERE subject_id = ? AND student_id = ?', [$sid, $studentId]);
                            $finalScore = (float)($fs['score'] ?? 0);

                            if ($finalScore <= 0 && $dailyAvg > 0) {
                                $finalScore = $examScore > 0
                                    ? ($dailyAvg * $dailyWeight / 100) + ($examScore * $examWeight / 100)
                                    : $dailyAvg;
                            } elseif ($finalScore <= 0 && $examScore > 0) {
                                $finalScore = $examScore;
                            }
                            $finalRounded = $finalScore > 0 ? (int)round($finalScore) : 0;
                            $predikat = $finalRounded > 0 ? predikat_from_score($finalRounded) : '-';

                            $existing = fetch_one(
                                'SELECT id, description, auto_generated FROM student_descriptions WHERE student_id = ? AND subject_id = ? AND grade_val = ?',
                                [$studentId, $sid, $grade]
                            );

                            $autoDesc = '';
                            $objectives = $objectivesBySubject[$sid] ?? [];
                            if ($objectives) {
                                $achieved = [];
                                $needHelp = [];
                                foreach ($objectives as $obj) {
                                    if ($finalRounded >= $kkm) {
                                        $achieved[] = $obj;
                                    } else {
                                        $needHelp[] = $obj;
                                    }
                                }
                                $parts = [];
                                if ($achieved) {
                                    $parts[] = 'Mencapai kompetensi baik dalam ' . implode(', ', array_slice($achieved, 0, 3));
                                }
                                if ($needHelp) {
                                    $parts[] = 'Perlu peningkatan dalam memahami ' . implode(', ', array_slice($needHelp, 0, 2));
                                }
                                $autoDesc = $parts ? implode('. ', $parts) . '.' : '';
                            }
                            if ($autoDesc === '') {
                                $autoDesc = $finalRounded >= $kkm
                                    ? 'Mencapai kompetensi dengan baik.'
                                    : 'Perlu peningkatan dalam memahami kompetensi dasar.';
                            }

                            $savedDesc = $existing ? (string)$existing['description'] : $autoDesc;
                            ?>
                            <tr>
                                <td><?= e($subject['name']) ?></td>
                                <td style="text-align:center;"><?= $finalRounded > 0 ? $finalRounded : '-' ?></td>
                                <td style="text-align:center;"><?= $predikat ?></td>
                                <td>
                                    <textarea name="desc[<?= e($sid) ?>]" rows="3" style="width:100%;font-size:0.875rem;" placeholder="Deskripsi otomatis akan digenerate jika kosong"><?= e($savedDesc) ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="actions">
                    <button class="button primary">Simpan Deskripsi</button>
                    <a class="button" href="<?= e(route_url('deskripsi-nilai', ['class_id' => $classId, 'subject_id' => $subjectId])) ?>">Reset</a>
                </div>
            </form>
        </section>
    <?php endforeach;
    render_footer();
}


