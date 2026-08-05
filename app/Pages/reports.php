<?php declare(strict_types=1);

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
