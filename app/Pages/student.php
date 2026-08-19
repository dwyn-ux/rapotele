<?php declare(strict_types=1);

function page_student_progress(): void
{
    require_role(['siswa']);
    $student = current_student();
    $rows = student_score_rows((int)$student['id']);
    render_header('Progres Nilai');
    ?>
    <section class="panel">
        <?php panel_title('Progres Nilai ' . (string)$student['name']); ?>
        <div class="table-wrap">
            <table><thead><tr><th>Mapel</th><th>Nilai</th><th>Capaian</th></tr></thead><tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="3" class="empty">Belum ada nilai.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr><td><?= e($row['subject_name']) ?></td><td><?= e($row['score']) ?></td><td><?= e($row['description']) ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </section>
    <?php render_footer();
}

function page_student_attendance_view(): void
{
    require_role(['siswa']);
    $student = current_student();
    $summary = attendance_summary_for_student((int)$student['id']);
    $rows = fetch_all(
        'SELECT ses.date, ses.meeting_no, ses.topic, sub.name AS subject_name, e.status, e.notes
         FROM student_attendance_entries e
         JOIN student_attendance_sessions ses ON ses.id = e.session_id
         JOIN teaching_assignments ta ON ta.id = ses.assignment_id
         JOIN subjects sub ON sub.id = ta.subject_id
         WHERE e.student_id = ?
         ORDER BY ses.date DESC, ses.meeting_no DESC',
        [(int)$student['id']]
    );
    render_header('Kehadiran Siswa');
    ?>
    <div class="metric-grid">
        <?php foreach (allowed_statuses() as $key => $label): ?>
            <div class="metric"><span><?= e($label) ?></span><strong><?= e($summary[$key] ?? 0) ?></strong></div>
        <?php endforeach; ?>
    </div>
    <?php table_panel('Riwayat Kehadiran', ['Tanggal', 'Mapel', 'Pertemuan', 'Topik', 'Status', 'Catatan'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['subject_name']) ?></td>
        <td><?= e($row['meeting_no']) ?></td>
        <td><?= e($row['topic']) ?></td>
        <td><?= e(allowed_statuses()[$row['status']] ?? $row['status']) ?></td>
        <td><?= e($row['notes']) ?></td>
    <?php }); render_footer();
}

function page_student_violations(): void
{
    require_role(['siswa']);
    $student = current_student();
    $rows = student_violations((int)$student['id']);
    $net = function_exists('violation_net_points') ? violation_net_points((int)$student['id']) : ['gross_points' => array_sum(array_column($rows, 'points')), 'net_points' => array_sum(array_column($rows, 'points')), 'discount_pct' => 0, 'deduction' => 0];
    $spLevel = function_exists('violation_sp_level') ? violation_sp_level($net['net_points']) : null;
    $rewards = table_exists('student_rewards') ? fetch_all('SELECT * FROM student_rewards WHERE student_id = ? ORDER BY date DESC', [(int)$student['id']]) : [];
    render_header('Pelanggaran & Poin Siswa');
    echo '<div class="metric-grid">'
        . '<div class="metric"><span>Total Catatan</span><strong>' . e(count($rows)) . '</strong></div>'
        . '<div class="metric"><span>Poin Pelanggaran</span><strong>' . e($net['gross_points']) . '</strong></div>';
    if ($net['discount_pct'] > 0) {
        echo '<div class="metric"><span>Diskon Reward</span><strong>-' . e($net['discount_pct']) . '%</strong></div>'
            . '<div class="metric"><span>Poin Bersih</span><strong>' . e($net['net_points']) . '</strong></div>';
    }
    if ($spLevel) {
        echo '<div class="metric"><span>Status</span><strong style="color:#dc2626;">' . e($spLevel['label']) . '</strong></div>';
    }
    echo '</div>';
    table_panel('Riwayat Pelanggaran', ['Tanggal', 'Jenis', 'Deskripsi', 'Poin', 'Tindak Lanjut'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['type']) ?></td>
        <td><?= e($row['description']) ?></td>
        <td><?= e($row['points']) ?></td>
        <td><?= e($row['action_taken']) ?></td>
    <?php });
    if ($rewards) {
        table_panel('Reward & Prestasi', ['Tanggal', 'Prestasi', 'Potongan Poin'], $rewards, function ($row) { ?>
            <td><?= e($row['date']) ?></td>
            <td><?= e($row['title']) ?></td>
            <td><?= e($row['discount_percent']) ?>%</td>
        <?php });
    }
    render_footer();
}

function page_student_documents(): void
{
    require_role(['siswa']);
    $student = current_student();
    $grade = (int)preg_replace('/\D+/', '', (string)($student['grade'] ?? '0'));
    $graduation = fetch_one('SELECT * FROM graduations WHERE student_id = ?', [(int)$student['id']]) ?: [];
    render_header('Dokumen Kelulusan');
    ?>
    <section class="panel">
        <?php panel_title('Dokumen Kelulusan'); ?>
        <?php if ($grade < 9): ?>
            <p class="empty">Dokumen SKL, ijazah, dan transkrip dibuka untuk siswa kelas 9.</p>
        <?php else: ?>
            <div class="grid two">
                <div>
                    <p>Status: <strong><?= e($graduation['status'] ?? 'belum diinput') ?></strong></p>
                    <p>No SKL/Ijazah: <?= e($graduation['certificate_no'] ?? '-') ?></p>
                    <p>No Transkrip: <?= e($graduation['transcript_no'] ?? '-') ?></p>
                </div>
                <div class="quick-links">
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'skl'])) ?>">Download SKL</a>
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'ijazah'])) ?>">Download Ijazah</a>
                    <a href="<?= e(route_url('student-document-download', ['kind' => 'transkrip'])) ?>">Download Transkrip Nilai</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php render_footer();
}
