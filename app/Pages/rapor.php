<?php declare(strict_types=1);

function page_print_document(string $type): void
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
    $titles = [
        'cetak-pelengkap-rapor' => 'Cetak Biodata/Pelengkap Rapor',
        'cetak-nilai-rapor' => 'Cetak Nilai Rapor',
        'cetak-leger-rapor' => 'Leger Rapor',
        'cetak-leger-pts' => 'Leger PTS',
        'cetak-nilai-rapor-pts' => 'Rapor PTS',
        'cetak-buku-induk' => 'Buku Induk',
        'cetak-skl' => 'Cetak SKL',
        'cetak-transkrip-ijazah' => 'Cetak Transkrip Ijazah',
    ];
    render_header($titles[$type] ?? 'Cetak');
    ?>
    <section class="panel no-print">
        <?php panel_title(($titles[$type] ?? 'Cetak') . ' Siswa'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="<?= e($type) ?>">
            <label>Pilih Kelas <select name="class_id"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <label>Posisi Tanda Tangan KS <select name="posisittdks"><?= options(['sejajar' => 'Sejajar Wali Kelas', 'bawah' => 'Di Bawah Wali Kelas'], $_GET['posisittdks'] ?? 'sejajar') ?></select></label>
            <label>Posisi Tanda Tangan <select name="isittd"><?= options(['tanpa' => 'Tanpa Tanda Tangan', 'dengan' => 'Dengan Tanda Tangan'], $_GET['isittd'] ?? 'tanpa') ?></select></label>
            <label>Ukuran Kertas <select name="kertas"><?= options(['A4' => 'A4', 'F4' => 'F4'], $_GET['kertas'] ?? 'A4') ?></select></label>
            <label>Batas Kiri (mm) <input type="number" name="kiri" value="<?= e($_GET['kiri'] ?? 20) ?>"></label>
            <label>Batas Kanan (mm) <input type="number" name="kanan" value="<?= e($_GET['kanan'] ?? 20) ?>"></label>
            <label>Batas Atas (mm) <input type="number" name="atas" value="<?= e($_GET['atas'] ?? 20) ?>"></label>
            <label>Batas Bawah (mm) <input type="number" name="bawah" value="<?= e($_GET['bawah'] ?? 10) ?>"></label>
            <div class="actions wide"><button class="button primary">Tampilkan</button></div>
        </form>
    </section>
    <section class="panel print-panel">
        <?php panel_title($titles[$type] ?? 'Cetak', '', '<button type="button" class="button warning" onclick="window.print()">Generate ' . e($titles[$type] ?? 'Cetak') . '</button><button type="button" class="button success" onclick="window.print()">Download/Cetak</button>'); ?>
        <h2><?= e($titles[$type] ?? 'Cetak') ?> <?= e($class['name'] ?? '') ?></h2>
        <?php if (in_array($type, ['cetak-leger-rapor', 'cetak-leger-pts'], true)): ?>
            <?php render_leger_table($students); ?>
        <?php elseif ($type === 'cetak-buku-induk' || $type === 'cetak-pelengkap-rapor'): ?>
            <?php render_biodata_table($students); ?>
        <?php elseif (in_array($type, ['cetak-skl', 'cetak-transkrip-ijazah'], true)): ?>
            <?php render_skl_table($students); ?>
        <?php else: ?>
            <?php foreach ($students as $student): render_student_report_card($student, $type); endforeach; ?>
        <?php endif; ?>
    </section>
    <?php render_footer();
}

function render_biodata_table(array $students): void
{
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th><th>NIS</th><th>NISN</th><th>JK</th><th>TTL</th><th>Agama</th></tr></thead><tbody>';
    foreach ($students as $s) {
        echo '<tr><td>' . e($s['name']) . '</td><td>' . e($s['nis']) . '</td><td>' . e($s['nisn']) . '</td><td>' . e($s['gender']) . '</td><td>' . e(trim(($s['birth_place'] ?? '') . ', ' . ($s['birth_date'] ?? ''), ', ')) . '</td><td>' . e($s['religion']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_leger_table(array $students): void
{
    $subjects = fetch_all('SELECT * FROM subjects WHERE active = 1 ORDER BY name');
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th>';
    foreach ($subjects as $subject) {
        echo '<th>' . e($subject['short_name'] ?: $subject['name']) . '</th>';
    }
    echo '<th>Rata-rata</th></tr></thead><tbody>';
    foreach ($students as $student) {
        echo '<tr><td>' . e($student['name']) . '</td>';
        $scores = [];
        foreach ($subjects as $subject) {
            $score = fetch_one('SELECT AVG(g.score) AS score FROM grades g JOIN teaching_assignments ta ON ta.id = g.assignment_id WHERE g.student_id = ? AND ta.subject_id = ?', [(int)$student['id'], (int)$subject['id']]);
            $value = $score && $score['score'] !== null ? (float)$score['score'] : null;
            if ($value !== null) {
                $scores[] = $value;
            }
            echo '<td>' . e($value !== null ? number_format($value, 0) : '-') . '</td>';
        }
        echo '<td>' . e($scores ? number_format(array_sum($scores) / count($scores), 2) : '-') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_skl_table(array $students): void
{
    echo '<div class="table-wrap"><table><thead><tr><th>Nama</th><th>NISN</th><th>Status</th><th>No Ijazah</th><th>No Transkrip</th><th>Tgl Lulus</th></tr></thead><tbody>';
    foreach ($students as $student) {
        $g = fetch_one('SELECT * FROM graduations WHERE student_id = ?', [(int)$student['id']]) ?: [];
        echo '<tr><td>' . e($student['name']) . '</td><td>' . e($student['nisn']) . '</td><td>' . e($g['status'] ?? '-') . '</td><td>' . e($g['certificate_no'] ?? '-') . '</td><td>' . e($g['transcript_no'] ?? '-') . '</td><td>' . e($g['graduation_date'] ?? '-') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_student_report_card(array $student, string $type): void
{
    echo '<article class="report-card"><h3>' . e($student['name']) . '</h3><p>NIS/NISN: ' . e($student['nis'] . ' / ' . $student['nisn']) . '</p>';
    $rows = fetch_all('SELECT sub.name, g.score, g.description FROM grades g JOIN teaching_assignments ta ON ta.id = g.assignment_id JOIN subjects sub ON sub.id = ta.subject_id WHERE g.student_id = ? ORDER BY sub.name', [(int)$student['id']]);
    if (!$rows) {
        echo '<p class="empty">Belum ada nilai.</p></article>';
        return;
    }
    echo '<table><thead><tr><th>Mapel</th><th>Nilai</th><th>Deskripsi</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['score']) . '</td><td>' . e($row['description']) . '</td></tr>';
    }
    echo '</tbody></table></article>';
}

function page_input_kelulusan(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT s.*, c.name AS class_name, g.status, g.certificate_no, g.transcript_no, g.graduation_date, g.notes FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN graduations g ON g.student_id = s.id ORDER BY c.grade, c.name, s.name');
    render_header('Input Kelulusan');
    table_panel('Data Kelulusan Siswa', ['Nama', 'Kelas', 'Status', 'No Ijazah', 'No Transkrip', 'Tanggal', 'Simpan'], $rows, function ($row) { ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_graduation"><input type="hidden" name="student_id" value="<?= e($row['id']) ?>">
        <td><?= e($row['name']) ?></td><td><?= e($row['class_name']) ?></td><td><select name="status"><?= options(['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', 'naik' => 'Naik Kelas', 'tinggal' => 'Tinggal Kelas'], $row['status'] ?? 'lulus') ?></select></td><td><input name="certificate_no" value="<?= e($row['certificate_no']) ?>"></td><td><input name="transcript_no" value="<?= e($row['transcript_no']) ?>"></td><td><input type="date" name="graduation_date" value="<?= e($row['graduation_date']) ?>"></td><td><button class="button small primary">Simpan</button></td></form>
    <?php }); render_footer();
}

function page_import_nomor_ijazah(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT s.name, s.nisn, c.name AS class_name, g.certificate_no, g.transcript_no, g.graduation_date FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN graduations g ON g.student_id = s.id ORDER BY c.grade, c.name, s.name');
    render_header('Import Nomor Ijazah');
    ?>
    <section class="panel">
        <form method="post" enctype="multipart/form-data" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_certificate_numbers">
            <label>CSV: nisn, nomor_ijazah, nomor_transkrip, tgl_lulus <input type="file" name="userfile" accept=".csv,text/csv" required></label>
            <div class="actions"><button class="button primary">Import Nomor Ijazah</button></div>
        </form>
    </section>
    <?php table_panel('Nomor Ijazah', ['Nama', 'NISN', 'Kelas', 'Nomor Ijazah', 'Nomor Transkrip', 'Tgl Lulus'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['certificate_no']) ?></td><td><?= e($row['transcript_no']) ?></td><td><?= e($row['graduation_date']) ?></td>
    <?php }); render_footer();
}

function page_setting_transkrip(): void
{
    page_settings_form('Setting Transkrip', 'setting-transkrip', 'save_transcript_settings', ['setnamasiswa', 'desimal_nilai', 'ada_ratarata', 'desimal_ratarata', 'ket_tempat_ttd', 'nama_kepsek', 'nip_kepsek', 'ada_ttd']);
}

function page_setting_skl(): void
{
    page_settings_form('Setting SKL', 'setting-skl', 'save_skl_settings', ['ada_kop', 'judul_1', 'nomor_skl', 'isi_text1', 'setnamasiswa', 'isi_text2', 'statuslulus', 'ada_nilai', 'judul_nilai', 'desimal_nilai', 'ada_ratarata', 'ket_tempat_ttd', 'nama_kepsek', 'nip_kepsek', 'ada_foto', 'ada_ttd']);
}

function page_settings_form(string $title, string $page, string $action, array $fields): void
{
    require_role(['admin']);
    render_header($title);
    echo '<section class="panel"><form method="post" class="grid two">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $field) {
        $value = get_app_setting($page . '.' . $field, '');
        if (str_starts_with($field, 'isi_')) {
            echo '<label class="wide">' . e($field) . '<textarea name="' . e($field) . '">' . e($value) . '</textarea></label>';
        } else {
            echo '<label>' . e($field) . '<input name="' . e($field) . '" value="' . e($value) . '"></label>';
        }
    }
    echo '<div class="wide actions"><button class="button primary">Simpan Data</button></div></form></section>';
    render_footer();
}

function page_input_nilai_skl(): void
{
    require_role(['admin']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = fetch_all('SELECT * FROM classes WHERE active = 1 ORDER BY grade, name');
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    $students = $classId ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    $subjects = fetch_all('SELECT * FROM subjects WHERE active = 1 ORDER BY name');
    render_header('Input Nilai SKL');
    echo '<section class="panel"><form method="get" class="grid four"><input type="hidden" name="page" value="input-nilai-skl"><label>Kelas <select name="class_id">' . options(array_column_map($classes, 'id', 'name'), $classId) . '</select></label><div class="actions"><button class="button">Tampilkan</button></div></form></section>';
    echo '<section class="panel"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="save_final_scores"><input type="hidden" name="class_id" value="' . e($classId) . '"><div class="table-wrap"><table><thead><tr><th>Nama</th>';
    foreach ($subjects as $subject) {
        echo '<th>' . e($subject['short_name'] ?: $subject['name']) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($students as $student) {
        echo '<tr><td>' . e($student['name']) . '</td>';
        foreach ($subjects as $subject) {
            $score = fetch_one('SELECT score FROM final_scores WHERE student_id = ? AND subject_id = ?', [(int)$student['id'], (int)$subject['id']]);
            echo '<td><input class="small-input" type="number" min="0" max="100" step="0.01" name="score[' . e($student['id']) . '][' . e($subject['id']) . ']" value="' . e($score['score'] ?? '') . '"></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div><div class="actions"><button class="button primary">Simpan Nilai Akhir</button></div></form></section>';
    render_footer();
}

function page_naik_kelas(): void
{
    require_role(['admin', 'guru']);
    $semester = semester_number();
    if ($semester !== '2') {
        echo '<section class="panel"><p class="empty">Keterangan naik kelas hanya tersedia di semester genap.</p></section>';
        render_footer();
        return;
    }
    if (!get_app_setting('promotion.enabled', '1')) {
        echo '<section class="panel"><p class="empty">Fitur keterangan naik kelas dinonaktifkan oleh administrator.</p></section>';
        render_footer();
        return;
    }
    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    $classId = (int)($_GET['class_id'] ?? 0);
    if ($classId <= 0) {
        $homeroom = fetch_one('SELECT id, name FROM classes WHERE homeroom_teacher_id = ? AND active = 1', [$teacherId]);
        if (!$homeroom) {
            render_header('Keterangan Naik Kelas');
            echo '<section class="panel"><p class="empty">Anda tidak terdaftar sebagai wali kelas.</p></section>';
            render_footer();
            return;
        }
        $classId = (int)$homeroom['id'];
    }
    require_class_access($classId);
    $class = fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]);
    $students = fetch_all('SELECT s.*, g.status, g.notes FROM students s LEFT JOIN graduations g ON g.student_id = s.id WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name', [$classId]);
    $gradeNum = (int)preg_replace('/\D+/', '', (string)$class['grade']);
    render_header('Keterangan Naik Kelas');
    ?>
    <section class="panel">
        <h3><?= e($class['name']) ?></h3>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_promotion">
            <div class="table-wrap">
                <table><thead><tr><th>Nama</th><th>NIS</th><th>Status Naik Kelas</th><th>Catatan</th></tr></thead><tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['name']) ?></td>
                            <td><?= e($student['nis']) ?></td>
                            <td>
                                <select name="status[<?= e($student['id']) ?>]">
                                    <?= options(['naik' => 'Naik Kelas', 'tinggal' => 'Tinggal Kelas'], $student['status'] ?? 'naik') ?>
                                </select>
                            </td>
                            <td><input name="notes[<?= e($student['id']) ?>]" value="<?= e($student['notes']) ?>" placeholder="Catatan (opsional)"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
            <div class="actions"><button class="button primary">Simpan</button></div>
        </form>
    </section>
    <?php
    render_footer();
}
