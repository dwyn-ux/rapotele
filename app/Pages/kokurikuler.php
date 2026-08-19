<?php declare(strict_types=1);

function page_data_ekskul(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('extracurriculars');
    $teachers = map_options('teachers', 'name');
    $students = fetch_all('SELECT id, name, nisn FROM students WHERE active = 1 ORDER BY name');
    $members = $edit && table_exists('extracurricular_members') ? array_map('intval', array_column(fetch_all('SELECT student_id FROM extracurricular_members WHERE extracurricular_id = ?', [(int)$edit['id']]), 'student_id')) : [];
    $memberCountSql = table_exists('extracurricular_members') ? '(SELECT COUNT(*) FROM extracurricular_members em WHERE em.extracurricular_id = e.id)' : '0';
    $rows = fetch_all('SELECT e.*, t.name AS teacher_name, ' . $memberCountSql . ' AS member_count FROM extracurriculars e LEFT JOIN teachers t ON t.id = e.teacher_id ORDER BY e.name');
    render_header('Data Ekskul');
    ext_simple_form_start('save_extracurricular', $edit, 'Data Ekskul');
    ?>
        <label>Nama Rombel Ekskul <input type="text" name="class_name" required value="<?= e($edit['class_name'] ?? '') ?>"></label>
        <label>Jenis Ekskul <input type="text" name="type" value="<?= e($edit['type'] ?? '') ?>"></label>
        <label>Nama Ekskul <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Pembina <select name="teacher_id"><option value="">-</option><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
        <label class="wide">Anggota Siswa
            <div style="border:1px solid var(--border-color,#d1d5db);border-radius:6px;padding:8px 10px;max-height:450px;overflow-y:auto;background:var(--bg-card,#fff);">
                <input type="text" id="ekskul-member-search" placeholder="🔍 Cari nama atau NISN..." style="width:100%;margin-bottom:8px;padding:7px 10px;border:1px solid var(--border-color,#d1d5db);border-radius:6px;font-size:0.9rem;box-sizing:border-box;">
                <label style="display:flex;align-items:center;gap:6px;padding:4px 0 8px;border-bottom:1px solid var(--border-color,#e5e7eb);margin-bottom:4px;cursor:pointer;font-weight:600;font-size:0.85rem;color:var(--text-muted,#6b7280);">
                    <input type="checkbox" id="ekskul-check-all"> Pilih Semua / Batal Semua
                </label>
                <?php foreach ($students as $student): ?>
                    <label class="ekskul-member-item" style="display:flex;align-items:center;gap:8px;padding:5px 4px;border-radius:4px;cursor:pointer;font-size:0.9rem;" onmouseover="this.style.background='var(--bg-hover,#f3f4f6)'" onmouseout="this.style.background=''">
                        <input type="checkbox" name="members[]" value="<?= e($student['id']) ?>" <?= in_array((int)$student['id'], $members, true) ? 'checked' : '' ?> style="width:16px;height:16px;">
                        <span><?= e($student['name']) ?> <small style="color:var(--text-muted,#6b7280);">(<?= e($student['nisn'] ?? '') ?>)</small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color:var(--text-muted,#6b7280);">Centang siswa yang menjadi anggota ekskul. Kosongkan semua jika semua siswa ikut.</small>
            <script>
            document.getElementById('ekskul-member-search').addEventListener('input', function() {
                var q = this.value.toLowerCase();
                document.querySelectorAll('.ekskul-member-item').forEach(function(el) {
                    el.style.display = (q === '' || el.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                });
            });
            document.getElementById('ekskul-check-all').addEventListener('change', function() {
                var checked = this.checked;
                document.querySelectorAll('.ekskul-member-item input[type=checkbox]').forEach(function(cb) {
                    if (cb.offsetParent !== null) cb.checked = checked;
                });
            });
            </script>
        </label>
    <?php ext_simple_form_end('data-ekskul'); ?>
    <?php table_panel('Daftar Ekskul', ['Rombel', 'Jenis', 'Nama', 'Pembina', 'Anggota', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['class_name']) ?></td><td><?= e($row['type']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['member_count'] ?? 0) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('extracurriculars', 'data-ekskul', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function ext_simple_form_start(string $action, array $edit, string $title, string $class = 'grid four'): void
{
    $isOpen = (bool)$edit || isset($_GET['add']);
    $button = '<button type="button" class="button primary input-panel-toggle" data-toggle-label="' . e('Tambah ' . $title) . '" data-close-label="Tutup Form">' . e($isOpen ? 'Tutup Form' : 'Tambah ' . $title) . '</button>';
    echo '<section class="panel input-panel ' . ($isOpen ? 'is-open' : '') . '" id="form-data">';
    panel_title(($edit ? 'Edit ' : 'Input ') . $title, '', $button);
    echo '<div class="input-panel-body"><form method="post" enctype="multipart/form-data" class="' . e($class) . '">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . e($edit['id'] ?? 0) . '">';
}

function ext_simple_form_end(string $page): void
{
    echo '<div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="' . e(route_url($page)) . '">Reset</a></div></form></div></section>';
}

function page_data_kelompok(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('subject_groups');
    $rows = fetch_all('SELECT * FROM subject_groups ORDER BY display_order, code');
    render_header('Data Kelompok Mapel');
    ext_simple_form_start('save_subject_group', $edit, 'Kelompok Mapel');
    ?>
        <label>Kode <input type="text" name="code" required value="<?= e($edit['code'] ?? '') ?>"></label>
        <label>Nama Kelompok <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Status <select name="status"><?= options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], $edit['status'] ?? 'aktif') ?></select></label>
        <label>Urutan <input type="number" name="display_order" value="<?= e($edit['display_order'] ?? 0) ?>"></label>
    <?php ext_simple_form_end('data-kelompok'); ?>
    <?php table_panel('Kelompok Mapel Rapor', ['Kode', 'Nama', 'Status', 'Urutan', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['code']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['display_order']) ?></td><td><?= ext_delete_button('subject_groups', 'data-kelompok', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_gabung_mapel(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('merged_subjects');
    $subjects = map_options('subjects', 'name');
    $rows = fetch_all('SELECT m.*, a.name AS source_name, b.name AS target_name FROM merged_subjects m JOIN subjects a ON a.id = m.source_subject_id JOIN subjects b ON b.id = m.target_subject_id ORDER BY m.grade, a.name');
    render_header('Gabung Mapel');
    ext_simple_form_start('save_merged_subject', $edit, 'Gabung Mapel');
    ?>
        <label>Tingkat <input type="text" name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Mapel Asal <select name="source_subject_id"><?= options($subjects, $edit['source_subject_id'] ?? '') ?></select></label>
        <label>Digabung ke Mapel <select name="target_subject_id"><?= options($subjects, $edit['target_subject_id'] ?? '') ?></select></label>
    <?php ext_simple_form_end('gabung-mapel'); ?>
    <?php table_panel('Daftar Gabung Mapel', ['Tingkat', 'Mapel Asal', 'Mapel Gabung', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['source_name']) ?></td><td><?= e($row['target_name']) ?></td><td><?= ext_delete_button('merged_subjects', 'gabung-mapel', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_mapping(string $title = 'Data Mapping Rapor', string $page = 'data-mapping'): void
{
    require_role(['admin']);
    $edit = ext_edit_row('report_mappings');
    $subjects = map_options('subjects', 'name');
    $groups = array_column_map(fetch_all("SELECT id, name FROM subject_groups WHERE status = 'aktif' ORDER BY display_order, name"), 'id', 'name');
    $rows = fetch_all('SELECT rm.*, s.name AS subject_name, g.name AS group_name FROM report_mappings rm JOIN subjects s ON s.id = rm.subject_id LEFT JOIN subject_groups g ON g.id = rm.group_id ORDER BY rm.grade, rm.display_order');
    render_header($title);
    ext_simple_form_start($page === 'mapping-mapel-skl' ? 'save_skl_mapping' : 'save_report_mapping', $edit, $title);
    ?>
        <label>Kurikulum <input type="text" name="curriculum" required value="<?= e($edit['curriculum'] ?? 'Kurikulum Merdeka') ?>"></label>
        <label>Tingkat <input type="text" name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Mapel <select name="subject_id"><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
        <label>Kelompok <select name="group_id"><option value="">-</option><?= options($groups, $edit['group_id'] ?? '') ?></select></label>
        <label>Urutan <input type="number" name="display_order" value="<?= e($edit['display_order'] ?? 0) ?>"></label>
        <label class="check"><input type="checkbox" name="include_in_report" <?= checked($edit['include_in_report'] ?? 1) ?>> Tampil</label>
    <?php ext_simple_form_end($page); ?>
    <?php table_panel($title, ['Kurikulum', 'Tingkat', 'Mapel', 'Kelompok', 'Urutan', 'Tampil', 'Aksi'], $rows, function ($row) use ($page) { ?>
        <td><?= e($row['curriculum']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= e($row['display_order']) ?></td><td><?= status_badge((int)$row['include_in_report']) ?></td><td><?= ext_delete_button('report_mappings', $page, (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_logo_ttd(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('signatures');
    $users = array_column_map(fetch_all('SELECT id, name FROM users ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT s.*, u.name AS user_name FROM signatures s LEFT JOIN users u ON u.id = s.user_id ORDER BY s.type, s.id DESC');
    $types = [
        'logo' => 'Logo Sekolah',
        'logo_dinas' => 'Logo Dinas',
        'ttd_kepsek' => 'TTD Kepala Sekolah',
        'ttd_wali' => 'TTD Wali Kelas',
        'stempel' => 'Stempel',
    ];
    render_header('Data Logo dan TTD');
    ext_simple_form_start('save_signature', $edit, 'Logo/TTD');
    ?>
        <label>Tipe <select name="type"><?= options($types, $edit['type'] ?? 'logo') ?></select></label>
        <label>User/Guru <select name="user_id"><option value="">-</option><?= options($users, $edit['user_id'] ?? '') ?></select></label>
        <label>Jabatan <input type="text" name="title" value="<?= e($edit['title'] ?? '') ?>"></label>
        <label>Nama <input type="text" name="person_name" value="<?= e($edit['person_name'] ?? '') ?>"></label>
        <label>NIP <input type="text" name="nip" value="<?= e($edit['nip'] ?? '') ?>"></label>
        <?php render_file_upload('userfile', 'image/*', 'File Gambar') ?>
    <?php ext_simple_form_end('data-logo-ttd'); ?>
    <?php table_panel('Daftar Logo/TTD', ['Tipe', 'Preview', 'User', 'Nama', 'NIP', 'File', 'Aksi'], $rows, function ($row) use ($types) { ?>
        <td><?= e($types[$row['type']] ?? $row['type']) ?></td>
        <td>
            <?php $previewUrl = signature_media_url((string)$row['type'], (int)$row['id']); ?>
            <?= $previewUrl !== '' ? '<img class="signature-preview" src="' . e($previewUrl) . '" alt="' . e($row['type']) . '">' : '<span class="hint">-</span>' ?>
        </td>
        <td><?= e($row['user_name']) ?></td><td><?= e($row['person_name']) ?></td><td><?= e($row['nip']) ?></td><td><?= e($row['file_path']) ?></td><td><?= ext_delete_button('signatures', 'data-logo-ttd', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_tanggal_rapor(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('report_dates');
    $rows = fetch_all('SELECT * FROM report_dates ORDER BY grade, report_date DESC');
    render_header('Data Tanggal Rapor');
    ext_simple_form_start('save_report_date', $edit, 'Tanggal Rapor');
    ?>
        <label>Tingkat <input type="text" name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Tanggal Rapor <input type="date" name="report_date" required value="<?= e($edit['report_date'] ?? date('Y-m-d')) ?>"></label>
        <label>Tempat TTD Wali <input type="text" name="homeroom_place" value="<?= e($edit['homeroom_place'] ?? '') ?>"></label>
        <label>Tempat TTD Kepsek <input type="text" name="principal_place" value="<?= e($edit['principal_place'] ?? '') ?>"></label>
        <label class="span-2">Catatan <input type="text" name="note" value="<?= e($edit['note'] ?? '') ?>"></label>
    <?php ext_simple_form_end('tanggal-rapor'); ?>
    <?php table_panel('Tanggal Rapor', ['Tingkat', 'Tanggal', 'TTD Wali', 'TTD Kepsek', 'Catatan', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['report_date']) ?></td><td><?= e($row['homeroom_place']) ?></td><td><?= e($row['principal_place']) ?></td><td><?= e($row['note']) ?></td><td><?= ext_delete_button('report_dates', 'tanggal-rapor', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_foto_siswa(): void
{
    require_role(['admin']);

    // Handle bulk upload result from session
    $bulkResult = $_SESSION['bulk_photo_result'] ?? null;
    unset($_SESSION['bulk_photo_result']);

    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT p.*, s.name AS student_name, s.nisn FROM student_photos p JOIN students s ON s.id = p.student_id ORDER BY s.name');
    render_header('Foto Siswa');

    // Bulk upload result notification
    if ($bulkResult): ?>
        <section class="panel">
            <h3>📦 Hasil Upload Bulk Foto</h3>
            <p><strong><?= $bulkResult['success'] ?></strong> foto berhasil diupload, <strong><?= $bulkResult['failed'] ?> foto gagal.</strong></p>
            <?php if (!empty($bulkResult['errors'])): ?>
                <div style="margin-top:10px;">
                    <strong>Detail:</strong>
                    <ul style="margin:5px 0; padding-left:20px;">
                    <?php foreach ($bulkResult['errors'] as $err): ?>
                        <li style="color:var(--color-danger,#dc2626);">
                            <strong><?= e($err['filename']) ?></strong> — <?= e($err['reason']) ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($bulkResult['matched'])): ?>
                <div style="margin-top:10px;">
                    <strong>Berhasil:</strong>
                    <ul style="margin:5px 0; padding-left:20px;">
                    <?php foreach ($bulkResult['matched'] as $m): ?>
                        <li style="color:var(--color-success,#16a34a);">NISN <strong><?= e($m['nisn']) ?></strong> → <?= e($m['name']) ?> (<?= e($m['file']) ?>)</li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Single upload -->
    <section class="panel">
        <h3>Upload Satu Foto</h3>
        <form method="post" enctype="multipart/form-data" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_student_photo">
            <label>Siswa <select name="student_id"><?= options($students, '') ?></select></label>
            <?php render_file_upload_inline('userfile', 'image/*', true) ?>
            <div class="actions"><button class="button primary">Upload</button></div>
        </form>
    </section>

    <!-- Bulk upload via ZIP -->
    <section class="panel">
        <h3>📦 Upload Bulk Foto (ZIP)</h3>
        <p style="color:var(--text-muted,#6b7280); margin-bottom:12px;">
            Upload file <strong>.zip</strong> yang berisi foto siswa.<br>
            <strong>Nama file gambar harus sesuai dengan NISN siswa</strong> (tanpa ekstensi).<br>
            Contoh: <code>0081234001.jpg</code>, <code>0081234002.png</code>, <code>0081234003.jpeg</code>, <code>0081234004.webp</code>
        </p>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="action" value="bulk_student_photo">
            <?php render_file_upload('zip_file', '.zip,application/zip', 'File ZIP Foto', true, 'Nama file gambar harus sesuai NISN siswa. Contoh: 0081234001.jpg') ?>
            <div class="actions" style="margin-top:10px;">
                <button class="button primary" onclick="return confirm('Upload foto bulk dari file ZIP ini? Nama file gambar akan dicocokkan dengan NISN siswa.')">
                    Upload Bulk Foto
                </button>
            </div>
        </form>
    </section>

    <?php table_panel('Foto Siswa', ['Nama', 'NISN', 'File', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['student_name']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['file_path']) ?></td><td><?= ext_delete_button('student_photos', 'foto-siswa', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_tema_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_themes');
    $rows = fetch_all('SELECT * FROM cocurricular_themes ORDER BY name');
    render_header('Daftar Tema Kokurikuler');
    ext_simple_form_start('save_cocurricular_theme', $edit, 'Tema');
    ?>
        <label class="span-2">Nama Tema <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Status <select name="status"><?= options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], $edit['status'] ?? 'aktif') ?></select></label>
    <?php ext_simple_form_end('tema-kokurikuler'); ?>
    <?php table_panel('Tema Kokurikuler', ['Tema', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['status']) ?></td><td><?= ext_delete_button('cocurricular_themes', 'tema-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_kegiatan_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_activities');
    $themes = array_column_map(fetch_all('SELECT id, name FROM cocurricular_themes ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT a.*, t.name AS theme_name FROM cocurricular_activities a JOIN cocurricular_themes t ON t.id = a.theme_id ORDER BY t.name, a.phase, a.title');
    render_header('Kegiatan Kokurikuler');
    ext_simple_form_start('save_cocurricular_activity', $edit, 'Kegiatan Kokurikuler', 'grid two');
    ?>
        <label>Tema <select name="theme_id"><?= options($themes, $edit['theme_id'] ?? '') ?></select></label>
        <label>Fase <input type="text" name="phase" required value="<?= e($edit['phase'] ?? '') ?>"></label>
        <label class="wide">Judul Kegiatan <input type="text" name="title" required value="<?= e($edit['title'] ?? '') ?>"></label>
        <label class="wide">Deskripsi <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="wide">Tujuan <textarea name="objective"><?= e($edit['objective'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
    <?php ext_simple_form_end('kegiatan-kokurikuler'); ?>
    <?php table_panel('Kegiatan', ['Tema', 'Fase', 'Judul', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['theme_name']) ?></td><td><?= e($row['phase']) ?></td><td><?= e($row['title']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('cocurricular_activities', 'kegiatan-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_kelompok_kokurikuler(): void
{
    require_role(['admin']);
    $edit = ext_edit_row('cocurricular_groups');
    $themes = array_column_map(fetch_all('SELECT id, name FROM cocurricular_themes ORDER BY name'), 'id', 'name');
    $teachers = map_options('teachers', 'name');
    $students = fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name');
    $members = $edit ? array_map('intval', array_column(fetch_all('SELECT student_id FROM cocurricular_members WHERE group_id = ?', [(int)$edit['id']]), 'student_id')) : [];
    $rows = fetch_all('SELECT g.*, t.name AS theme_name, p.name AS teacher_name, COUNT(m.id) AS member_count FROM cocurricular_groups g LEFT JOIN cocurricular_themes t ON t.id = g.theme_id LEFT JOIN teachers p ON p.id = g.coordinator_teacher_id LEFT JOIN cocurricular_members m ON m.group_id = g.id GROUP BY g.id ORDER BY g.grade, g.name');
    render_header('Kelompok Kokurikuler');
    ext_simple_form_start('save_cocurricular_group', $edit, 'Kelompok Kokurikuler', 'grid two');
    ?>
        <label>Nama Kelompok <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Tingkat <input type="text" name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
        <label>Fase <input type="text" name="phase" required value="<?= e($edit['phase'] ?? '') ?>"></label>
        <label>Tema <select name="theme_id"><option value="">-</option><?= options($themes, $edit['theme_id'] ?? '') ?></select></label>
        <label>Koordinator <select name="coordinator_teacher_id"><option value="">-</option><?= options($teachers, $edit['coordinator_teacher_id'] ?? '') ?></select></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
        <label class="wide">Anggota
            <select name="members[]" multiple size="8">
                <?php foreach ($students as $student): ?>
                    <option value="<?= e($student['id']) ?>" <?= in_array((int)$student['id'], $members, true) ? 'selected' : '' ?>><?= e($student['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php ext_simple_form_end('kelompok-kokurikuler'); ?>
    <?php table_panel('Kelompok Kokurikuler', ['Nama', 'Tingkat', 'Fase', 'Tema', 'Koordinator', 'Anggota', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['phase']) ?></td><td><?= e($row['theme_name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['member_count']) ?></td><td><?= ext_delete_button('cocurricular_groups', 'kelompok-kokurikuler', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_data_tp(): void
{
    require_role(['admin', 'guru']);
    $edit = ext_edit_row('learning_objectives');
    $subjects = map_options('subjects', 'name');
    $classes = learning_objective_class_options();
    $rows = fetch_all('SELECT lo.*, s.name AS subject_name FROM learning_objectives lo JOIN subjects s ON s.id = lo.subject_id ORDER BY lo.grade, s.name, lo.id DESC');
    render_header('Tujuan Pembelajaran');
    ext_simple_form_start('save_learning_objective', $edit, 'Tujuan Pembelajaran', 'grid two');
    ?>
        <label>Mapel <select name="subject_id"><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
        <label>Kelas <select name="grade" required><option value="">Pilih kelas</option><?= options($classes, $edit['grade'] ?? '') ?></select></label>
        <label class="wide">Tujuan Pembelajaran <textarea name="description" required><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
    <?php ext_simple_form_end('data-tp'); ?>
    <?php table_panel('Daftar TP', ['Kelas', 'Mapel', 'Tujuan', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['grade']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e(mb_strimwidth((string)$row['description'], 0, 120, '...')) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= ext_delete_button('learning_objectives', 'data-tp', (int)$row['id']) ?></td>
    <?php }); render_footer();
}

function page_status_penilaian(): void
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
    $rows = $classId ? fetch_all(
        'SELECT ta.*, t.name AS teacher_name, s.name AS subject_name, c.name AS class_name
         FROM teaching_assignments ta JOIN teachers t ON t.id = ta.teacher_id JOIN subjects s ON s.id = ta.subject_id JOIN classes c ON c.id = ta.class_id
         WHERE ta.class_id = ? ORDER BY s.name',
        [$classId]
    ) : [];
    render_header('Status Penilaian');
    echo '<section class="panel"><form method="get" class="grid four"><input type="hidden" name="page" value="status-penilaian"><label>Kelas <select name="class_id">' . options(array_column_map($classes, 'id', 'name'), $classId) . '</select></label><div class="actions"><button class="button">Tampilkan</button></div></form></section>';
    table_panel('Status Nilai Guru Mapel', ['Kelas', 'Mapel', 'Guru', 'Siswa', 'Nilai Masuk', 'Progress'], $rows, function ($row) {
        $studentCount = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ? AND active = 1', [(int)$row['class_id']])['c'];
        $gradeCount = (int)fetch_one('SELECT COUNT(*) AS c FROM grades WHERE assignment_id = ? AND score IS NOT NULL', [(int)$row['id']])['c'];
        $percent = $studentCount > 0 ? round(($gradeCount / $studentCount) * 100) : 0;
        ?><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($studentCount) ?></td><td><?= e($gradeCount) ?></td><td><span class="badge <?= $percent >= 100 ? 'ok' : 'off' ?>"><?= e($percent) ?>%</span></td><?php
    });
    render_footer();
}

function page_input_nilai_ekskul(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $teacherId = (int)(current_user()['teacher_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }
    $class = $classId ? fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]) : null;
    $className = $class ? (string)$class['name'] : '';
    $ekskuls = fetch_all('SELECT * FROM extracurriculars WHERE active = 1 ORDER BY name');
    // Filter siswa: tampilkan hanya yang punya minimal 1 ekskul (ada di extracurricular_members)
    $hasMembers = table_exists('extracurricular_members');
    if ($classId && $hasMembers) {
        $memberStudentIds = array_column(fetch_all(
            'SELECT DISTINCT em.student_id FROM extracurricular_members em JOIN students s ON s.id = em.student_id WHERE s.class_id = ? AND s.active = 1',
            [$classId]
        ), 'student_id');
        $students = $memberStudentIds ? fetch_all(
            'SELECT * FROM students WHERE class_id = ? AND active = 1 AND id IN (' . implode(',', array_fill(0, count($memberStudentIds), '?')) . ') ORDER BY name',
            array_merge([$classId], $memberStudentIds)
        ) : [];
    } else {
        $students = $classId ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    }
    render_header('Input Nilai Ekstrakurikuler');
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="input-nilai-ekskul">
            <label>Kelas <select name="class_id"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <div class="actions"><button class="button">Tampilkan</button></div>
        </form>
    </section>
    <?php if ($classId > 0 && $ekskuls): ?>
    <?php if ($students): ?>
    <section class="panel">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_extracurricular_scores">
            <input type="hidden" name="class_id" value="<?= e($classId) ?>">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <?php foreach ($ekskuls as $ekskul): ?>
                                <th><?= e($ekskul['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= e($student['name']) ?></td>
                                <?php foreach ($ekskuls as $ekskul): ?>
                                    <?php
                                    $score = fetch_one(
                                        'SELECT score FROM extracurricular_scores WHERE student_id = ? AND extracurricular_id = ?',
                                        [(int)$student['id'], (int)$ekskul['id']]
                                    );
                                    ?>
                                    <td><input class="small-input" name="scores[<?= e($student['id']) ?>][<?= e($ekskul['id']) ?>]" value="<?= e($score['score'] ?? '') ?>" placeholder="Nilai"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="actions"><button class="button primary">Simpan Nilai</button></div>
        </form>
    </section>
    <?php else: ?>
    <section class="panel">
        <p style="color:var(--text-muted,#6b7280);">Belum ada siswa yang ditambahkan sebagai anggota ekskul. Silakan atur anggota di halaman <a href="<?= e(route_url('data-ekskul')) ?>">Data Ekskul</a>.</p>
    </section>
    <?php endif; ?>
    <?php endif; ?>
    <?php render_footer();
}
