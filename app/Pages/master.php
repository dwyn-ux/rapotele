<?php declare(strict_types=1);

function page_school(): void
{
    require_role(['admin']);
    $school = get_school_profile();
    render_header('Data Sekolah');
    ?>
    <section class="panel">
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_school">
            <label>Nama Sekolah <input name="name" required value="<?= e($school['name'] ?? '') ?>"></label>
            <label>NPSN <input name="npsn" value="<?= e($school['npsn'] ?? '') ?>"></label>
            <label class="wide">Alamat <textarea name="address"><?= e($school['address'] ?? '') ?></textarea></label>
            <label>Nama Kepala Sekolah <input name="principal_name" value="<?= e($school['principal_name'] ?? '') ?>"></label>
            <label>NIP Kepala Sekolah <input name="principal_nip" value="<?= e($school['principal_nip'] ?? '') ?>"></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($school['academic_year'] ?? '') ?>"></label>
            <label>Semester <input name="semester" required value="<?= e($school['semester'] ?? '') ?>"></label>
            <label>Lat. Sekolah <input type="number" step="any" name="location_lat" value="<?= e($school['location_lat'] ?? '') ?>" placeholder="-6.2088"></label>
            <label>Lng. Sekolah <input type="number" step="any" name="location_lng" value="<?= e($school['location_lng'] ?? '') ?>" placeholder="106.8456"></label>
            <label>Radius Absensi (meter) <input type="number" min="0" name="attendance_radius_meters" value="<?= e($school['attendance_radius_meters'] ?? '500') ?>" placeholder="500"></label>
            <label class="check"><input type="checkbox" name="promotion_enabled" value="1" <?= checked(get_app_setting('promotion.enabled', '1') === '1') ?>> Aktifkan Keterangan Naik Kelas (semester genap)</label>
            <div class="wide actions"><button class="button primary">Simpan</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function edit_row(string $table): ?array
{
    $id = (int)($_GET['edit'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    return fetch_one("SELECT * FROM $table WHERE id = ?", [$id]);
}

function page_teachers(): void
{
    require_role(['admin']);
    $edit = edit_row('teachers') ?: [];
    $rows = fetch_all('SELECT * FROM teachers ORDER BY active DESC, name');
    render_header('Data Guru');
    input_panel_start($edit ? 'Edit Guru' : 'Input Guru', 'Tambah Guru', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>NIP <input name="nip" value="<?= e($edit['nip'] ?? '') ?>"></label>
            <label>NUPTK <input name="nuptk" value="<?= e($edit['nuptk'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Telepon <input name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Jabatan <input name="position" value="<?= e($edit['position'] ?? '') ?>"></label>
            <label>Telegram Chat ID <input name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('teachers')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Guru', ['Nama', 'NIP', 'JK', 'Jabatan', 'Telegram', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nip']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['position']) ?></td><td><?= e($row['telegram_chat_id']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('teachers', (int)$row['id'], 'delete_teacher') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_classes(): void
{
    require_role(['admin']);
    $edit = edit_row('classes') ?: [];
    $teachers = map_options('teachers', 'name');
    $rows = fetch_all('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id ORDER BY c.grade, c.name');
    render_header('Data Kelas');
    input_panel_start($edit ? 'Edit Kelas' : 'Input Kelas', 'Tambah Kelas', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_class"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Nama Kelas <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Tingkat <input name="grade" required value="<?= e($edit['grade'] ?? '') ?>"></label>
            <label>Jurusan/Fase <input name="major" value="<?= e($edit['major'] ?? '') ?>"></label>
            <label>Wali Kelas <select name="homeroom_teacher_id"><option value="">-</option><?= options($teachers, $edit['homeroom_teacher_id'] ?? '') ?></select></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($edit['academic_year'] ?? config('school.academic_year')) ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('classes')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Kelas', ['Kelas', 'Tingkat', 'Wali Kelas', 'Jumlah Siswa', 'Status', 'Aksi'], $rows, function ($row) {
        $count = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ?', [(int)$row['id']])['c'];
        ?><td><?= e($row['name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($count) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('classes', (int)$row['id'], 'delete_class') ?></td><?php
    }); ?>
    <?php render_footer();
}

function page_students(): void
{
    require_role(['admin']);
    $edit = edit_row('students') ?: [];
    $classes = map_options('classes', 'name');
    $rows = fetch_all('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.grade, c.name, s.name');
    render_header('Data Siswa');
    input_panel_start($edit ? 'Edit Siswa' : 'Input Siswa', 'Tambah Siswa', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>NIS <input name="nis" value="<?= e($edit['nis'] ?? '') ?>"></label>
            <label>NISN <input name="nisn" value="<?= e($edit['nisn'] ?? '') ?>"></label>
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Tempat Lahir <input name="birth_place" value="<?= e($edit['birth_place'] ?? '') ?>"></label>
            <label>Tanggal Lahir <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>"></label>
            <label>Agama <input name="religion" value="<?= e($edit['religion'] ?? '') ?>"></label>
            <label>Kelas <select name="class_id"><option value="">-</option><?= options($classes, $edit['class_id'] ?? '') ?></select></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('students')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Siswa', ['Nama', 'NIS', 'NISN', 'JK', 'Kelas', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nis']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['class_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('students', (int)$row['id'], 'delete_student') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_subjects(): void
{
    require_role(['admin']);
    $edit = edit_row('subjects') ?: [];
    $rows = fetch_all('SELECT * FROM subjects ORDER BY group_name, name');
    render_header('Data Mapel');
    input_panel_start($edit ? 'Edit Mapel' : 'Input Mapel', 'Tambah Mapel', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama Mapel <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Nama Singkat <input name="short_name" value="<?= e($edit['short_name'] ?? '') ?>"></label>
            <label>Kelompok <input name="group_name" value="<?= e($edit['group_name'] ?? '') ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('subjects')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Mapel', ['Nama Mapel', 'Singkat', 'Kelompok', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['short_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('subjects', (int)$row['id'], 'delete_subject') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_assignments(): void
{
    require_role(['admin']);
    $edit = edit_row('teaching_assignments') ?: [];
    $teachers = map_options('teachers', 'name');
    $classes = map_options('classes', 'name');
    $subjects = map_options('subjects', 'name');
    $rows = assignment_rows();
    render_header('Data Pembelajaran');
    input_panel_start($edit ? 'Edit Pembelajaran' : 'Input Pembelajaran', 'Tambah Pembelajaran', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_assignment"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Guru <select name="teacher_id" required><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Kelas <select name="class_id" required><?= options($classes, $edit['class_id'] ?? '') ?></select></label>
            <label>Mapel <select name="subject_id" required><?= options($subjects, $edit['subject_id'] ?? '') ?></select></label>
            <label>Tahun Ajaran <input name="academic_year" required value="<?= e($edit['academic_year'] ?? config('school.academic_year')) ?>"></label>
            <label>Semester <input name="semester" required value="<?= e($edit['semester'] ?? config('school.semester')) ?>"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('assignments')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pembelajaran', ['Guru', 'Kelas', 'Mapel', 'Tahun', 'Semester', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['teacher_name']) ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['academic_year']) ?></td><td><?= e($row['semester']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('assignments', (int)$row['id'], 'delete_assignment') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_users(): void
{
    require_role(['admin']);
    $edit = edit_row('users') ?: [];
    $teachers = map_options('teachers', 'name');
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rows = fetch_all('SELECT u.*, t.name AS teacher_name, s.name AS student_name FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id LEFT JOIN students s ON s.id = u.student_id ORDER BY u.role, u.name');
    render_header('Data Pengguna');
    input_panel_start($edit ? 'Edit Pengguna' : 'Input Pengguna', 'Tambah Pengguna', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Username <input name="username" required value="<?= e($edit['username'] ?? '') ?>"></label>
            <label>Password <input type="password" name="password" placeholder="<?= $edit ? 'Kosongkan jika tidak diganti' : '' ?>"></label>
            <label class="span-2">Nama <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Role <select name="role"><?= options(['admin' => 'Admin', 'guru' => 'Guru', 'operator' => 'Operator', 'siswa' => 'Siswa'], $edit['role'] ?? 'guru') ?></select></label>
            <label>Guru Terkait <select name="teacher_id"><option value="">-</option><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Siswa Terkait <select name="student_id"><option value="">-</option><?= options($students, $edit['student_id'] ?? '') ?></select></label>
            <label>Telegram Chat ID <input name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>" placeholder="Kosong untuk role siswa"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('users')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pengguna', ['Username', 'Nama', 'Role', 'Guru/Siswa', 'Telegram', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['username']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= e($row['role'] === 'siswa' ? $row['student_name'] : $row['teacher_name']) ?></td><td><?= e($row['telegram_chat_id']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('users', (int)$row['id'], 'delete_user') ?></td>
    <?php }); ?>
    <?php render_footer();
}

function page_profile(): void
{
    $user = current_user();
    render_header('Profil Pengguna');
    ?>
    <section class="panel">
        <form method="post" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
            <label>Nama <input name="name" required value="<?= e($user['name']) ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($user['email']) ?>"></label>
            <label>Password Baru <input type="password" name="password" placeholder="Kosongkan jika tidak diganti"></label>
            <?php if (($user['role'] ?? '') !== 'siswa'): ?>
                <label>Telegram Chat ID <input readonly value="<?= e($user['telegram_chat_id']) ?>"></label>
            <?php endif; ?>
            <div class="wide actions"><button class="button primary">Simpan Profil</button></div>
        </form>
    </section>
    <?php
    render_footer();
}
