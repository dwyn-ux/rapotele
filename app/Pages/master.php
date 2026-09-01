<?php declare(strict_types=1);

function page_school(): void
{
    require_role(['admin']);
    $school = get_school_profile();
    render_header('Data Sekolah');
    ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <!-- Page Header -->
    <div class="school-page-header">
        <div class="school-page-header-left">
            <div class="school-page-header-icon">
                <i data-lucide="building-2"></i>
            </div>
            <div>
                <h1 class="school-page-title">Data Sekolah</h1>
                <p class="school-page-subtitle">Lengkapi dan perbarui data sekolah sesuai ketentuan</p>
            </div>
        </div>
        <div class="school-page-header-info">
            <i data-lucide="info"></i>
            <span>Pastikan data sekolah sudah sesuai dengan dokumen resmi dari sekolah.</span>
        </div>
    </div>

    <form method="post" id="school-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_school">

        <!-- Section 1: Informasi Umum -->
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon">
                    <i data-lucide="school"></i>
                </div>
                <h2 class="school-section-title">Informasi Umum</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Nama Sekolah <span class="required">*</span></label>
                        <input type="text" class="school-input" name="name" required value="<?= e($school['name'] ?? '') ?>" placeholder="Masukkan nama sekolah">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">NPSN <span class="required">*</span></label>
                        <input type="text" class="school-input" name="npsn" value="<?= e($school['npsn'] ?? '') ?>" placeholder="Masukkan NPSN Sekolah">
                    </div>
                </div>
                <div class="school-form-group">
                    <label class="school-label">Alamat <span class="required">*</span></label>
                    <textarea class="school-input school-textarea" name="address" rows="3" placeholder="Masukkan alamat lengkap sekolah"><?= e($school['address'] ?? '') ?></textarea>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Kelurahan/Desa</label>
                        <input type="text" class="school-input" name="village" value="<?= e($school['village'] ?? '') ?>" placeholder="Masukkan nama kelurahan/desa">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Kecamatan</label>
                        <input type="text" class="school-input" name="district" value="<?= e($school['district'] ?? '') ?>" placeholder="Masukkan nama kecamatan">
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Kota/Kabupaten</label>
                        <input type="text" class="school-input" name="regency" value="<?= e($school['regency'] ?? '') ?>" placeholder="Masukkan nama kota/kabupaten">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Provinsi</label>
                        <input type="text" class="school-input" name="province" value="<?= e($school['province'] ?? '') ?>" placeholder="Masukkan nama provinsi">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Informasi Kepala Sekolah & Legalitas -->
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon">
                    <i data-lucide="user-check"></i>
                </div>
                <h2 class="school-section-title">Informasi Kepala Sekolah &amp; Legalitas</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Nama Kepala Sekolah <span class="required">*</span></label>
                        <input type="text" class="school-input" name="principal_name" value="<?= e($school['principal_name'] ?? '') ?>" placeholder="Masukkan nama kepala sekolah">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">NIP Kepala Sekolah</label>
                        <input type="text" class="school-input" name="principal_nip" value="<?= e($school['principal_nip'] ?? '') ?>" placeholder="Masukkan NIP Kepala Sekolah">
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Tahun Ajaran <span class="required">*</span></label>
                        <input type="text" class="school-input" name="academic_year" required value="<?= e($school['academic_year'] ?? '') ?>" placeholder="Contoh: 2026/2027">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Semester <span class="required">*</span></label>
                        <select class="school-input school-select" name="semester" required>
                            <option value="Ganjil" <?= ($school['semester'] ?? '') === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= ($school['semester'] ?? '') === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Data Teknis Sekolah -->
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon">
                    <i data-lucide="settings-2"></i>
                </div>
                <h2 class="school-section-title">Data Teknis Sekolah</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Latitude Sekolah <span class="required">*</span></label>
                        <div class="school-input-with-icon">
                            <i data-lucide="map-pin"></i>
                            <input type="number" step="any" class="school-input" name="location_lat" id="school_lat" value="<?= e($school['location_lat'] ?? '') ?>" placeholder="-6.2088">
                        </div>
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Longitude Sekolah <span class="required">*</span></label>
                        <div class="school-input-with-icon">
                            <i data-lucide="map-pin"></i>
                            <input type="number" step="any" class="school-input" name="location_lng" id="school_lng" value="<?= e($school['location_lng'] ?? '') ?>" placeholder="106.8456">
                        </div>
                    </div>
                </div>
                <div class="school-form-grid three-col">
                    <div class="school-form-group">
                        <label class="school-label">Radius Absensi (meter)</label>
                        <input type="number" min="0" class="school-input" name="attendance_radius_meters" value="<?= e($school['attendance_radius_meters'] ?? '500') ?>" placeholder="500">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Jam Pelajaran Normal (menit)</label>
                        <input type="number" min="10" max="60" class="school-input" name="regular_period_minutes" value="<?= e($school['regular_period_minutes'] ?? '35') ?>" placeholder="35">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Jam Pelajaran Pendek (menit)</label>
                        <input type="number" min="10" max="60" class="school-input" name="short_period_minutes" value="<?= e($school['short_period_minutes'] ?? '25') ?>" placeholder="25">
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Hari Pendek</label>
                        <input type="text" class="school-input" name="short_days" value="<?= e($school['short_days'] ?? '') ?>" placeholder="Contoh: 5 atau 5,6 (Jumat, Sabtu)">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Jam Mulai Pelajaran</label>
                        <input type="time" class="school-input" name="start_time" value="<?= e($school['start_time'] ?? '07:00') ?>">
                    </div>
                </div>

                <!-- Jam & Istirahat -->
                <div class="school-subsection-divider">
                    <span>Pengaturan Jam &amp; Istirahat</span>
                </div>
                <div class="school-form-grid three-col">
                    <div class="school-form-group">
                        <label class="school-label">Istirahat 1 Setelah Jam</label>
                        <select class="school-input school-select" name="break1_after">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>" <?= ($school['break1_after'] ?? 3) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Durasi Istirahat 1 (menit)</label>
                        <input type="number" min="5" max="60" class="school-input" name="break1_minutes" value="<?= e($school['break1_minutes'] ?? '15') ?>" placeholder="15">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Istirahat 2 Setelah Jam</label>
                        <select class="school-input school-select" name="break2_after">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>" <?= ($school['break2_after'] ?? 6) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Durasi Istirahat 2 (menit)</label>
                        <input type="number" min="5" max="60" class="school-input" name="break2_minutes" value="<?= e($school['break2_minutes'] ?? '15') ?>" placeholder="15">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Jam Maksimal</label>
                        <input type="number" min="1" max="12" class="school-input" name="max_periods" value="<?= e($school['max_periods'] ?? '10') ?>" placeholder="10">
                    </div>
                </div>

                <!-- Map -->
                <div class="school-subsection-divider">
                    <span>Peta Lokasi Sekolah</span>
                    <small>(klik peta untuk set lokasi)</small>
                </div>
                <div class="school-map-wrap">
                    <div id="school-map"></div>
                </div>

                <!-- Checkbox -->
                <div class="school-check-group">
                    <label class="school-check">
                        <input type="checkbox" name="promotion_enabled" value="1" <?= checked(get_app_setting('promotion.enabled', '1') === '1') ?>>
                        <span class="school-check-mark"></span>
                        <span>Aktifkan Keterangan Naik Kelas (semester genap)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="school-form-actions">
            <a href="<?= e(route_url('dashboard')) ?>" class="school-btn school-btn-secondary">
                <i data-lucide="x"></i>
                <span>Batal</span>
            </a>
            <button type="submit" class="school-btn school-btn-primary">
                <i data-lucide="save"></i>
                <span>Simpan Data</span>
            </button>
        </div>
    </form>

    <script>
    (function(){
        var lat = parseFloat(document.getElementById('school_lat').value) || -2.5;
        var lng = parseFloat(document.getElementById('school_lng').value) || 118.0;
        var map = L.map('school-map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        var icon = L.divIcon({html:'<svg width="25" height="41" viewBox="0 0 25 41" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 0C5.6 0 0 5.6 0 12.5S12.5 41 12.5 41 25 19.4 25 12.5 19.4 0 12.5 0z" fill="#2563EB"/><circle cx="12.5" cy="12.5" r="5" fill="#fff"/></svg>', className:'', iconSize:[25,41], iconAnchor:[12,41]});
        var marker = (document.getElementById('school_lat').value && document.getElementById('school_lng').value)
            ? L.marker([lat, lng], {icon:icon}).addTo(map) : null;
        map.on('click', function(e){
            var mlat = e.latlng.lat.toFixed(8);
            var mlng = e.latlng.lng.toFixed(8);
            document.getElementById('school_lat').value = mlat;
            document.getElementById('school_lng').value = mlng;
            if(marker){ marker.setLatLng(e.latlng); }
            else { marker = L.marker(e.latlng, {icon:icon}).addTo(map); }
        });
        setTimeout(function(){ map.invalidateSize(); }, 200);
    })();
    </script>
    <?php
    render_footer();
}

function page_schools(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT sp.*, (SELECT COUNT(*) FROM classes c WHERE c.school_id = sp.id) AS class_count FROM school_profile sp ORDER BY sp.id');
    render_header('Data Sekolah');
    ?>
    <div class="school-page-header">
        <div class="school-page-header-left">
            <div class="school-page-header-icon"><i data-lucide="building-2"></i></div>
            <div>
                <h1 class="school-page-title">Data Sekolah</h1>
                <p class="school-page-subtitle">Kelola beberapa sekolah dalam satu aplikasi</p>
            </div>
        </div>
        <div>
            <a class="button primary" href="<?= e(route_url('school-edit')) ?>">
                <i data-lucide="plus"></i> Tambah Sekolah
            </a>
        </div>
    </div>
    <?php table_panel('Daftar Sekolah', ['Nama', 'NPSN', 'Jenjang Dominan', 'Alamat', 'Jumlah Kelas', 'Status', 'Aksi'], $rows, function ($row) {
        $levels = fetch_all('SELECT level, COUNT(*) AS c FROM classes WHERE school_id = ? AND level IS NOT NULL GROUP BY level', [(int)$row['id']]);
        $levelStr = [];
        foreach ($levels as $l) {
            $levelStr[] = $l['level'] . ' (' . $l['c'] . ')';
        }
        ?><td><?= e($row['name']) ?></td>
        <td><?= e($row['npsn'] ?? '-') ?></td>
        <td><?= e(implode(', ', $levelStr) ?: '-') ?></td>
        <td><?= e($row['address'] ?? '-') ?></td>
        <td><?= e($row['class_count']) ?></td>
        <td><?= status_badge(1) ?></td>
        <td>
            <a href="<?= e(route_url('school-edit', ['id' => (int)$row['id']])) ?>" class="button small primary">Edit</a>
            <?php if ((int)$row['class_count'] === 0): ?>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_school">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="button small danger" type="submit" onclick="return confirm('Hapus sekolah ini?')">Hapus</button>
                </form>
            <?php else: ?>
                <button class="button small danger" disabled title="Sekolah masih dipakai <?= (int)$row['class_count'] ?> kelas">Hapus</button>
            <?php endif; ?>
        </td><?php
    }, '', true);
    render_footer();
}

function page_school_edit(): void
{
    require_role(['admin']);
    $id = (int)($_GET['id'] ?? 0);
    $school = $id > 0 ? fetch_one('SELECT * FROM school_profile WHERE id = ?', [$id]) : null;
    if ($id > 0 && !$school) {
        flash('error', 'Sekolah tidak ditemukan.');
        redirect_to('schools');
        return;
    }
    $school = $school ?: [
        'name' => '', 'npsn' => '', 'address' => '', 'principal_name' => '', 'principal_nip' => '',
        'academic_year' => current_academic_year(), 'semester' => current_semester(),
        'location_lat' => '', 'location_lng' => '', 'attendance_radius_meters' => 500,
        'regular_period_minutes' => 35, 'short_period_minutes' => 25,
        'short_days' => '', 'max_periods' => 10, 'start_time' => '07:00',
        'break1_after' => 3, 'break1_minutes' => 15, 'break2_after' => 6, 'break2_minutes' => 15,
        'village' => '', 'district' => '', 'regency' => '', 'province' => '',
    ];
    render_header($id > 0 ? 'Edit Sekolah' : 'Tambah Sekolah');
    ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <div class="school-page-header">
        <div class="school-page-header-left">
            <div class="school-page-header-icon"><i data-lucide="building-2"></i></div>
            <div>
                <h1 class="school-page-title"><?= $id > 0 ? 'Edit Sekolah' : 'Tambah Sekolah' ?></h1>
                <p class="school-page-subtitle">Lengkapi data sekolah</p>
            </div>
        </div>
    </div>
    <form method="post" id="school-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_school">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon"><i data-lucide="school"></i></div>
                <h2 class="school-section-title">Informasi Umum</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Nama Sekolah <span class="required">*</span></label>
                        <input type="text" class="school-input" name="name" required value="<?= e($school['name']) ?>" placeholder="Contoh: SMA Negeri 1 Bandung">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">NPSN</label>
                        <input type="text" class="school-input" name="npsn" value="<?= e($school['npsn'] ?? '') ?>" placeholder="NPSN Sekolah">
                    </div>
                </div>
                <div class="school-form-group">
                    <label class="school-label">Alamat <span class="required">*</span></label>
                    <textarea class="school-input school-textarea" name="address" rows="3" placeholder="Alamat lengkap"><?= e($school['address'] ?? '') ?></textarea>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Kelurahan/Desa</label>
                        <input type="text" class="school-input" name="village" value="<?= e($school['village'] ?? '') ?>">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Kecamatan</label>
                        <input type="text" class="school-input" name="district" value="<?= e($school['district'] ?? '') ?>">
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Kota/Kabupaten</label>
                        <input type="text" class="school-input" name="regency" value="<?= e($school['regency'] ?? '') ?>">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Provinsi</label>
                        <input type="text" class="school-input" name="province" value="<?= e($school['province'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon"><i data-lucide="user-check"></i></div>
                <h2 class="school-section-title">Informasi Kepala Sekolah</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Nama Kepala Sekolah <span class="required">*</span></label>
                        <input type="text" class="school-input" name="principal_name" required value="<?= e($school['principal_name'] ?? '') ?>">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">NIP Kepala Sekolah</label>
                        <input type="text" class="school-input" name="principal_nip" value="<?= e($school['principal_nip'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="school-section-card">
            <div class="school-section-header">
                <div class="school-section-icon"><i data-lucide="calendar"></i></div>
                <h2 class="school-section-title">Tahun Ajaran & Semester</h2>
            </div>
            <div class="school-section-body">
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Tahun Ajaran <span class="required">*</span></label>
                        <input type="text" class="school-input" name="academic_year" required value="<?= e($school['academic_year']) ?>" placeholder="2025/2026">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Semester <span class="required">*</span></label>
                        <select class="school-input" name="semester" required>
                            <option value="Ganjil" <?= $school['semester'] === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= $school['semester'] === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="actions wide" style="margin-top: 16px;">
            <button class="button primary" type="submit"><i data-lucide="save"></i> Simpan</button>
            <a class="button" href="<?= e(route_url('schools')) ?>"><i data-lucide="x"></i> Batal</a>
        </div>
    </form>
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
    $existingUser = $edit ? fetch_one('SELECT id, username FROM users WHERE teacher_id = ?', [(int)$edit['id']]) : null;
    render_header('Data Guru');
    input_panel_start($edit ? 'Edit Guru' : 'Input Guru', 'Tambah Guru', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>NIP <input type="text" name="nip" value="<?= e($edit['nip'] ?? '') ?>"></label>
            <label>NUPTK <input type="text" name="nuptk" value="<?= e($edit['nuptk'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Telepon <input type="text" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Jabatan <input type="text" name="position" value="<?= e($edit['position'] ?? '') ?>"></label>
            <label>Telegram Chat ID <input type="text" name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>"></label>
            <?php if ($existingUser): ?>
                <label>Username Login <input type="text" name="username" value="<?= e($existingUser['username'] ?? '') ?>" readonly></label>
                <label>Password Baru <input type="password" name="password" placeholder="Kosongkan jika tidak diganti"></label>
            <?php else: ?>
                <?php $autoUsername = $edit['name'] ? generate_username_from_name($edit['name']) : ''; ?>
                <label>Username Login <input type="text" name="username" data-autofill-username value="<?= e($edit['username'] ?? '') ?>" placeholder="otomatis: <?= e($autoUsername) ?>"></label>
                <label>Password Login <input type="password" name="password" value="<?= e(config('default_teacher_password', 'guru123')) ?>" placeholder="default: <?= e(config('default_teacher_password', 'guru123')) ?>"></label>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="is_bk" <?= checked((int)($edit['is_bk'] ?? 0)) ?>> Guru BK (Bimbingan Konseling)</label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('teachers')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Guru', ['Nama', 'NIP', 'JK', 'Jabatan', 'BK', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nip']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['position']) ?></td><td><?= (int)($row['is_bk'] ?? 0) ? '<span class="badge ok">BK</span>' : '—' ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('teachers', (int)$row['id'], 'delete_teacher') ?></td>
    <?php }, '', true); ?>
    <?php render_footer();
}

function page_classes(): void
{
    require_role(['admin']);
    $edit = edit_row('classes') ?: [];
    $teachers = map_options('teachers', 'name');
    $schools = fetch_all('SELECT id, name FROM school_profile ORDER BY name');
    $rows = fetch_all('SELECT c.*, t.name AS teacher_name, sp.name AS school_name FROM classes c LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id LEFT JOIN school_profile sp ON sp.id = c.school_id ORDER BY sp.name, c.grade, c.name');
    render_header('Data Kelas');
    input_panel_start($edit ? 'Edit Kelas' : 'Input Kelas', 'Tambah Kelas', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid two form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_class">
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>
                <span class="field-icon"><i data-lucide="hash"></i> Nama Kelas</span>
                <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Contoh: 7A, 8B, 9C">
            </label>
            <label>
                <span class="field-icon"><i data-lucide="building"></i> Sekolah <span style="color:#c00">*</span></span>
                <select name="school_id" required>
                    <option value="">- Pilih Sekolah -</option>
                    <?php foreach ($schools as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"<?= (int)($edit['school_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="field-icon"><i data-lucide="layers"></i> Tingkat / Grade</span>
                <input type="text" name="grade" required value="<?= e($edit['grade'] ?? '') ?>" placeholder="Contoh: 7, 8, 9, 10, 11, 12">
            </label>
            <label>
                <span class="field-icon"><i data-lucide="graduation-cap"></i> Jenjang</span>
                <select name="level">
                    <option value="">- Pilih Jenjang -</option>
                    <?php foreach (['SD' => 'SD (Sekolah Dasar)', 'SMP' => 'SMP (Sekolah Menengah Pertama)', 'MTS' => 'MTs (Madrasah Tsanawiyah)', 'SMA' => 'SMA (Sekolah Menengah Atas)', 'MA' => 'MA (Madrasah Aliyah)'] as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= (string)($edit['level'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="field-icon"><i data-lucide="bookmark"></i> Jurusan / Program (khusus SMA/MA)</span>
                <input type="text" name="major" value="<?= e($edit['major'] ?? '') ?>" placeholder="Contoh: IPA, IPS, Bahasa, Reguler">
            </label>
            <label>
                <span class="field-icon"><i data-lucide="user"></i> Wali Kelas</span>
                <select name="homeroom_teacher_id"><option value="">Pilih Wali Kelas</option><?= options($teachers, $edit['homeroom_teacher_id'] ?? '') ?></select>
            </label>
            <label>
                <span class="field-icon"><i data-lucide="calendar"></i> Tahun Ajaran</span>
                <input type="text" name="academic_year" required value="<?= e($edit['academic_year'] ?? current_academic_year()) ?>" readonly>
            </label>
            <label class="check field-check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> <span>Kelas Aktif</span></label>
            <div class="wide actions form-actions-top"><button type="submit" class="button primary"><i data-lucide="save"></i> Simpan</button><a class="button" href="<?= e(route_url('classes')) ?>"><i data-lucide="rotate-ccw"></i> Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Kelas', ['Sekolah', 'Kelas', 'Tingkat', 'Jenjang', 'Jurusan', 'Wali Kelas', 'Jumlah Siswa', 'Status', 'Aksi'], $rows, function ($row) {
        $count = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ?', [(int)$row['id']])['c'];
        ?><td><?= e($row['school_name'] ?? '-') ?></td><td><?= e($row['name']) ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['level'] ?? '-') ?></td><td><?= e($row['major'] ?? '-') ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($count) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('classes', (int)$row['id'], 'delete_class') ?></td><?php
    }, '', true); ?>
    <?php render_footer();
}

function page_students(): void
{
    require_role(['admin']);
    $edit = edit_row('students') ?: [];
    $classes = map_options('classes', 'name');
    $rows = fetch_all('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.grade, c.name, s.name');
    $existingUser = $edit ? fetch_one('SELECT id, username FROM users WHERE student_id = ?', [(int)$edit['id']]) : null;
    render_header('Data Siswa');
    input_panel_start($edit ? 'Edit Siswa' : 'Input Siswa', 'Tambah Siswa', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>NIS <input type="text" name="nis" value="<?= e($edit['nis'] ?? '') ?>"></label>
            <label>NISN <input type="text" name="nisn" value="<?= e($edit['nisn'] ?? '') ?>"></label>
            <label class="span-2">Nama <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>JK <select name="gender"><?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $edit['gender'] ?? '') ?></select></label>
            <label>Tempat Lahir <input type="text" name="birth_place" value="<?= e($edit['birth_place'] ?? '') ?>"></label>
            <label>Tanggal Lahir <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>"></label>
            <label>Agama <input type="text" name="religion" value="<?= e($edit['religion'] ?? '') ?>"></label>
            <label>Alamat <input type="text" name="address" value="<?= e($edit['address'] ?? '') ?>"></label>
            <label>No. HP <input type="text" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
            <label>Nama Ayah <input type="text" name="father_name" value="<?= e($edit['father_name'] ?? '') ?>"></label>
            <label>Pekerjaan Ayah <input type="text" name="father_occupation" value="<?= e($edit['father_occupation'] ?? '') ?>"></label>
            <label>Nama Ibu <input type="text" name="mother_name" value="<?= e($edit['mother_name'] ?? '') ?>"></label>
            <label>Pekerjaan Ibu <input type="text" name="mother_occupation" value="<?= e($edit['mother_occupation'] ?? '') ?>"></label>
            <label>Nama Wali <input type="text" name="guardian_name" value="<?= e($edit['guardian_name'] ?? '') ?>"></label>
            <label>Kelas <select name="class_id"><option value="">-</option><?= options($classes, $edit['class_id'] ?? '') ?></select></label>
            <?php if ($existingUser): ?>
                <label>Username Login <input type="text" name="username" value="<?= e($existingUser['username'] ?? '') ?>" readonly></label>
                <label>Password Baru <input type="password" name="password" placeholder="Kosongkan jika tidak diganti"></label>
            <?php else: ?>
                <label>Username Login <input type="text" name="username" value="<?= e($edit['nisn'] ?? '') ?>" placeholder="default: NISN siswa"></label>
                <label>Password Login <input type="password" name="password" value="<?= e(config('default_student_password', 'siswa123')) ?>" placeholder="default: <?= e(config('default_student_password', 'siswa123')) ?>"></label>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('students')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Siswa', ['Nama', 'NIS', 'NISN', 'JK', 'Kelas', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nis']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['class_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('students', (int)$row['id'], 'delete_student') ?></td>
    <?php }, '', true); ?>
    <?php render_footer();
}

function page_subjects(): void
{
    require_role(['admin']);
    $edit = edit_row('subjects') ?: [];
    $rows = fetch_all('SELECT * FROM subjects ORDER BY group_name, name');
    $selectedLevels = [];
    if (!empty($edit['level'])) {
        $selectedLevels = array_values(array_intersect(explode(',', (string)$edit['level']), SUBJECT_LEVELS));
    }
    render_header('Data Mapel');
    input_panel_start($edit ? 'Edit Mapel' : 'Input Mapel', 'Tambah Mapel', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama Mapel <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Nama Singkat <input type="text" name="short_name" value="<?= e($edit['short_name'] ?? '') ?>"></label>
            <label>Kelompok <input type="text" name="group_name" value="<?= e($edit['group_name'] ?? '') ?>"></label>
            <?= subject_levels_input($selectedLevels) ?>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('subjects')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Mapel', ['Nama Mapel', 'Singkat', 'Kelompok', 'Jenjang', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['short_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= subject_levels_badge($row['level'] ?? '') ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('subjects', (int)$row['id'], 'delete_subject') ?></td>
    <?php }, '', true); ?>
    <?php render_footer();
}

function page_assignments(): void
{
    require_role(['admin']);
    $edit = edit_row('teaching_assignments') ?: [];
    $teachers = map_options('teachers', 'name');
    $allClasses = fetch_all('SELECT id, name, level FROM classes WHERE active = 1 ORDER BY grade, name');
    $allSubjects = fetch_all('SELECT id, name, level FROM subjects WHERE active = 1 ORDER BY name');
    $editClass = $edit ? fetch_one('SELECT id, name, level FROM classes WHERE id = ?', [(int)$edit['class_id']]) : null;
    $editLevel = (string)($editClass['level'] ?? '');
    $rows = assignment_rows();
    $subjectsJson = json_encode($allSubjects, JSON_UNESCAPED_UNICODE);
    $classesJson = json_encode($allClasses, JSON_UNESCAPED_UNICODE);
    render_header('Data Pembelajaran');
    input_panel_start($edit ? 'Edit Pembelajaran' : 'Input Pembelajaran', 'Tambah Pembelajaran', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_assignment"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label>Guru <select name="teacher_id" required><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Jenjang
                <select id="asg-level" data-edit-level="<?= e($editLevel) ?>">
                    <option value="">Pilih Jenjang</option>
                    <?php foreach (SUBJECT_LEVELS as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $editLevel === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kelas
                <select name="class_id" id="asg-class" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-level="<?= e($c['level'] ?? '') ?>"<?= (int)($edit['class_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Mapel
                <select name="subject_id" id="asg-subject" required>
                    <option value="">Pilih Mapel</option>
                    <?php foreach ($allSubjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" data-level="<?= e($s['level'] ?? '') ?>"<?= (int)($edit['subject_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tahun Ajaran <input type="text" name="academic_year" required value="<?= e($edit['academic_year'] ?? current_academic_year()) ?>" readonly></label>
            <label>Semester <input type="text" name="semester" required value="<?= e($edit['semester'] ?? current_semester()) ?>" readonly></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('assignments')) ?>">Reset</a></div>
        </form>
        <script>
        (function(){
            var levelEl = document.getElementById('asg-level');
            var classEl = document.getElementById('asg-class');
            var subjectEl = document.getElementById('asg-subject');
            var allClassOpts = Array.from(classEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; });
            var allSubjectOpts = Array.from(subjectEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; });
            var preLevel = levelEl.value || levelEl.getAttribute('data-edit-level') || '';
            function rebuild(selectEl, allOpts, level) {
                var prev = selectEl.value;
                selectEl.innerHTML = '<option value="">' + (selectEl === classEl ? 'Pilih Kelas' : 'Pilih Mapel') + '</option>';
                allOpts.filter(function(o){ return !o.value || !level || subjectMatch(o.level, level); }).forEach(function(o){
                    var opt = document.createElement('option');
                    opt.value = o.value; opt.textContent = o.text;
                    opt.setAttribute('data-level', o.level);
                    selectEl.appendChild(opt);
                });
                if (prev && selectEl.querySelector('option[value="' + prev + '"]')) selectEl.value = prev;
            }
            function subjectMatch(subjectLevel, classLevel) {
                if (!subjectLevel) return true;
                return subjectLevel.split(',').map(function(s){return s.trim();}).indexOf(classLevel) !== -1;
            }
            levelEl.addEventListener('change', function(){ rebuild(classEl, allClassOpts, levelEl.value); rebuild(subjectEl, allSubjectOpts, levelEl.value); });
            if (preLevel) { rebuild(classEl, allClassOpts, preLevel); rebuild(subjectEl, allSubjectOpts, preLevel); }
        })();
        </script>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pembelajaran', ['Guru', 'Jenjang', 'Kelas', 'Mapel', 'Tahun', 'Semester', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['teacher_name']) ?></td><td><?= e($row['class_level'] ?? '-') ?></td><td><?= e($row['class_name']) ?></td><td><?= e($row['subject_name']) ?></td><td><?= e($row['academic_year']) ?></td><td><?= e($row['semester']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('assignments', (int)$row['id'], 'delete_assignment') ?></td>
    <?php }, '', true); ?>
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
            <label>Username <input type="text" name="username" required value="<?= e($edit['username'] ?? '') ?>"></label>
            <label>Password <input type="password" name="password" placeholder="<?= $edit ? 'Kosongkan jika tidak diganti' : '' ?>"></label>
            <label class="span-2">Nama <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Role <select name="role"><?= options(['admin' => 'Admin', 'guru' => 'Guru', 'operator' => 'Operator', 'siswa' => 'Siswa'], $edit['role'] ?? 'guru') ?></select></label>
            <label>Guru Terkait <select name="teacher_id"><option value="">-</option><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label>Siswa Terkait <select name="student_id"><option value="">-</option><?= options($students, $edit['student_id'] ?? '') ?></select></label>
            <label>Telegram Chat ID <input type="text" name="telegram_chat_id" value="<?= e($edit['telegram_chat_id'] ?? '') ?>" placeholder="Kosong untuk role siswa"></label>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('users')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pengguna', ['Username', 'Nama', 'Role', 'Guru/Siswa', 'Telegram', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['username']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= e($row['role'] === 'siswa' ? $row['student_name'] : $row['teacher_name']) ?></td><td><?= e($row['telegram_chat_id']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('users', (int)$row['id'], 'delete_user') ?></td>
    <?php }, '', true); ?>
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
            <label>Nama <input type="text" name="name" required value="<?= e($user['name']) ?>"></label>
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
