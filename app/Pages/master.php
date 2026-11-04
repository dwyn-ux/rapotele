<?php declare(strict_types=1);

function page_school(): void
{
    require_role(['admin']);
    $id = (int)($_GET['id'] ?? 0);
    $school = $id > 0 ? fetch_one('SELECT * FROM school_profile WHERE id = ?', [$id]) : get_school_profile();
    if ($id > 0 && !$school) {
        flash('error', 'Sekolah tidak ditemukan.');
        redirect_to('schools');
    }
    $nipLabel = report_nip_label();
    render_header('Profil & Lokasi Sekolah');
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
                <h1 class="school-page-title">Profil &amp; Lokasi Sekolah</h1>
                <p class="school-page-subtitle">Lengkapi dan perbarui data sekolah sesuai ketentuan</p>
            </div>
        </div>
        <div class="school-page-header-info">
            <i data-lucide="info"></i>
            <span>Pastikan data sekolah sudah sesuai dengan dokumen resmi dari sekolah.</span>
        </div>
    </div>

    <form method="post" id="school-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_school">
        <input type="hidden" name="id" value="<?= (int)($school['id'] ?? 0) ?>">

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
                    <div class="school-form-group">
                        <label class="school-label">Label Identitas Kepala Sekolah</label>
                        <select class="school-input school-select" name="nip_label">
                            <?php $currentNipLabel = (string)($nipLabel ?? 'NIP'); ?>
                            <?php foreach (['NIP' => 'NIP (Nomor Induk Pegawai)', 'NBM' => 'NBM (Nomor Badan Madrasah)', 'NIK' => 'NIK (Nomor Induk Kependudukan)', 'NUP' => 'NUP (Nomor Urut Pegawai)'] as $val => $lbl): ?>
                                <option value="<?= e($val) ?>" <?= $currentNipLabel === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                            <?php if (!in_array($currentNipLabel, ['NIP', 'NBM', 'NIK', 'NUP'], true)): ?>
                                <option value="<?= e($currentNipLabel) ?>" selected><?= e($currentNipLabel) ?> (custom)</option>
                            <?php endif; ?>
                            <option value="custom" data-custom> custom (isi sendiri)…</option>
                        </select>
                        <input type="text" class="school-input" name="nip_label_custom" id="nip-label-custom" style="margin-top:6px;display:none;" placeholder="Label lain, contoh: NIP/NIK" value="<?= e(in_array($currentNipLabel, ['NIP', 'NBM', 'NIK', 'NUP'], true) ? '' : $currentNipLabel) ?>">
                    </div>
                </div>
                <script>
                (function() {
                    var sel = document.querySelector('select[name="nip_label"]');
                    var custom = document.getElementById('nip-label-custom');
                    if (!sel || !custom) return;
                    function sync() {
                        if (sel.value === 'custom') { custom.style.display = ''; custom.required = true; }
                        else { custom.style.display = 'none'; custom.required = false; custom.value = ''; }
                    }
                    sel.addEventListener('change', sync); sync();
                })();
                </script>
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
                        <input type="number" min="0" class="school-input" name="attendance_radius_meters" id="school_radius" value="<?= e($school['attendance_radius_meters'] ?? '500') ?>" placeholder="500">
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
                <div class="school-subsection-divider" id="section-jam-istirahat">
                    <span>Pengaturan Jam &amp; Istirahat Standar (Hari Biasa)</span>
                </div>
                <div class="school-form-grid three-col">
                    <div class="school-form-group">
                        <label class="school-label">Istirahat 1 Setelah Jam</label>
                        <select class="school-input school-select" name="break1_after">
                            <option value="0">Tidak Ada</option>
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>" <?= ($school['break1_after'] ?? 3) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Durasi Istirahat 1 (menit)</label>
                        <input type="number" min="0" max="60" class="school-input" name="break1_minutes" value="<?= e($school['break1_minutes'] ?? '15') ?>" placeholder="15">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Istirahat 2 Setelah Jam</label>
                        <select class="school-input school-select" name="break2_after">
                            <option value="0">Tidak Ada</option>
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>" <?= ($school['break2_after'] ?? 6) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="school-form-grid two-col">
                    <div class="school-form-group">
                        <label class="school-label">Durasi Istirahat 2 (menit)</label>
                        <input type="number" min="0" max="60" class="school-input" name="break2_minutes" value="<?= e($school['break2_minutes'] ?? '15') ?>" placeholder="15">
                    </div>
                    <div class="school-form-group">
                        <label class="school-label">Jam Maksimal Per Hari</label>
                        <input type="number" min="1" max="12" class="school-input" name="max_periods" value="<?= e($school['max_periods'] ?? '10') ?>" placeholder="10">
                    </div>
                </div>

                <!-- Pengaturan Istirahat Khusus (Senin, Rabu, Jum'at, dll.) -->
                <div style="margin-top:1.5rem;padding:16px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                        <label class="school-check" style="font-weight:700;font-size:0.95rem;color:#0f172a;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="custom_break_enabled" id="toggle_custom_break" value="1" <?= checked(!empty($school['custom_break_enabled'])) ?> onchange="document.getElementById('custom_break_fields').style.display = this.checked ? 'block' : 'none';">
                            <span class="school-check-mark"></span>
                            <span>⭐ Aktifkan Jadwal Istirahat Khusus Hari Tertentu (Senin, Rabu, Jum'at, dll.)</span>
                        </label>
                        <span style="font-size:0.8rem;background:#e0f2fe;color:#0369a1;font-weight:600;padding:3px 10px;border-radius:12px;">
                            Opsi Fleksibel
                        </span>
                    </div>

                    <div id="custom_break_fields" style="display: <?= !empty($school['custom_break_enabled']) ? 'block' : 'none' ?>;margin-top:12px;padding-top:12px;border-top:1px dashed #cbd5e1;">
                        <div style="margin-bottom:14px;">
                            <label class="school-label" style="font-weight:600;margin-bottom:6px;display:block;">Pilih Hari yang Menerapkan Jadwal Istirahat Khusus Ini:</label>
                            <?php
                            $activeCustomDays = explode(',', (string)($school['custom_break_days'] ?? '1,3,5'));
                            $daysMap = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => "Jum'at", 6 => 'Sabtu'];
                            ?>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
                                <?php foreach ($daysMap as $dNum => $dName): ?>
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.88rem;cursor:pointer;background:#fff;border:1px solid #cbd5e1;padding:6px 12px;border-radius:6px;">
                                        <input type="checkbox" name="custom_break_days[]" value="<?= $dNum ?>" <?= in_array((string)$dNum, $activeCustomDays, true) ? 'checked' : '' ?>>
                                        <strong><?= e($dName) ?></strong>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:#64748b;margin-top:4px;display:block;">Centang hari-hari yang jadwal istirahatnya berbeda (misal: <strong>Senin, Rabu, dan Jum'at</strong>).</small>
                        </div>

                        <div class="school-form-grid three-col">
                            <div class="school-form-group">
                                <label class="school-label">Istirahat Khusus 1 Setelah Jam</label>
                                <select class="school-input school-select" name="custom_break1_after">
                                    <option value="0">Tidak Ada</option>
                                    <?php for ($i = 1; $i <= 9; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($school['custom_break1_after'] ?? 2) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="school-form-group">
                                <label class="school-label">Durasi Istirahat Khusus 1 (menit)</label>
                                <input type="number" min="0" max="60" class="school-input" name="custom_break1_minutes" value="<?= e($school['custom_break1_minutes'] ?? '15') ?>" placeholder="15">
                            </div>
                            <div class="school-form-group">
                                <label class="school-label">Istirahat Khusus 2 Setelah Jam</label>
                                <select class="school-input school-select" name="custom_break2_after">
                                    <option value="0">Tidak Ada</option>
                                    <?php for ($i = 1; $i <= 9; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($school['custom_break2_after'] ?? 5) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="school-form-grid two-col" style="margin-top:8px;">
                            <div class="school-form-group">
                                <label class="school-label">Durasi Istirahat Khusus 2 (menit)</label>
                                <input type="number" min="0" max="60" class="school-input" name="custom_break2_minutes" value="<?= e($school['custom_break2_minutes'] ?? '15') ?>" placeholder="15">
                            </div>
                        </div>

                        <!-- Opsi Khusus Hari Jum'at jika ingin beda lagi -->
                        <div style="margin-top:14px;padding-top:12px;border-top:1px dashed #e2e8f0;">
                            <label class="school-check" style="font-weight:600;font-size:0.88rem;color:#334155;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="friday_break_custom" id="toggle_friday_break" value="1" <?= checked(!empty($school['friday_break_custom'])) ?> onchange="document.getElementById('friday_break_fields').style.display = this.checked ? 'block' : 'none';">
                                <span class="school-check-mark"></span>
                                <span>Atur Hari Jum'at secara terpisah (opsional jika jeda Sholat Jum'at berbeda lagi)</span>
                            </label>

                            <div id="friday_break_fields" style="display: <?= !empty($school['friday_break_custom']) ? 'block' : 'none' ?>;margin-top:10px;">
                                <div class="school-form-grid three-col">
                                    <div class="school-form-group">
                                        <label class="school-label">Istirahat Jum'at 1 Setelah Jam</label>
                                        <select class="school-input school-select" name="friday_break1_after">
                                            <option value="0">Tidak Ada</option>
                                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($school['friday_break1_after'] ?? 2) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="school-form-group">
                                        <label class="school-label">Durasi Istirahat Jum'at 1 (menit)</label>
                                        <input type="number" min="0" max="90" class="school-input" name="friday_break1_minutes" value="<?= e($school['friday_break1_minutes'] ?? '15') ?>" placeholder="15">
                                    </div>
                                    <div class="school-form-group">
                                        <label class="school-label">Istirahat Jum'at 2 Setelah Jam</label>
                                        <select class="school-input school-select" name="friday_break2_after">
                                            <option value="0">Tidak Ada</option>
                                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($school['friday_break2_after'] ?? 0) == $i ? 'selected' : '' ?>>Jam <?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="school-subsection-divider" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span>Peta Lokasi Sekolah</span>
                        <small>(klik atau geser pin untuk set titik koordinat)</small>
                    </div>
                    <button type="button" id="btn-detect-school-location" class="button small secondary" style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.8rem; padding: 4px 10px; border-radius: var(--radius-md);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>
                        Gunakan Lokasi Saya Saat Ini
                    </button>
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
            <a href="<?= e(route_url('schools')) ?>" class="school-btn school-btn-secondary">
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
        var latInput = document.getElementById('school_lat');
        var lngInput = document.getElementById('school_lng');
        var radiusInput = document.getElementById('school_radius');

        var hasCoord = (latInput.value.trim() !== '' && lngInput.value.trim() !== '');
        var lat = parseFloat(latInput.value) || -2.5;
        var lng = parseFloat(lngInput.value) || 118.0;
        var initialZoom = hasCoord ? 16 : 5;

        var map = L.map('school-map').setView([lat, lng], initialZoom);

        // Enterprise reliable basemaps (100% Free, No API Key, No 403 Rate-limit)
        var streetLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '&copy; Esri, HERE, Garmin, USGS, OpenStreetMap'
        });

        var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '&copy; Esri, Maxar, Earthstar Geographics'
        });

        streetLayer.addTo(map);

        // Layer Switcher (Peta Jalan / Foto Satelit)
        L.control.layers({
            "🗺️ Peta Jalan": streetLayer,
            "🛰️ Foto Satelit": satelliteLayer
        }, null, { position: 'topright' }).addTo(map);

        var icon = L.divIcon({
            html: '<svg width="28" height="42" viewBox="0 0 25 41" xmlns="http://www.w3.org/2000/svg"><path d="M12.5 0C5.6 0 0 5.6 0 12.5S12.5 41 12.5 41 25 19.4 25 12.5 19.4 0 12.5 0z" fill="#2563EB" stroke="#ffffff" stroke-width="1.5"/><circle cx="12.5" cy="12.5" r="5" fill="#fff"/></svg>',
            className: '',
            iconSize: [28, 42],
            iconAnchor: [14, 42]
        });

        var marker = hasCoord ? L.marker([lat, lng], {icon: icon, draggable: true}).addTo(map) : null;
        var radiusCircle = null;

        function updateRadiusCircle(pos) {
            var r = parseInt(radiusInput ? radiusInput.value : 500, 10) || 500;
            if (radiusCircle) {
                radiusCircle.setLatLng(pos);
                radiusCircle.setRadius(r);
            } else {
                radiusCircle = L.circle(pos, {
                    radius: r,
                    color: '#2563eb',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.15,
                    weight: 1.5
                }).addTo(map);
            }
        }

        if (hasCoord) {
            updateRadiusCircle([lat, lng]);
        }

        function setCoordinates(cLat, cLng, zoomTo) {
            latInput.value = cLat.toFixed(8);
            lngInput.value = cLng.toFixed(8);
            if (marker) {
                marker.setLatLng([cLat, cLng]);
            } else {
                marker = L.marker([cLat, cLng], {icon: icon, draggable: true}).addTo(map);
                marker.on('dragend', function(e){
                    var pos = e.target.getLatLng();
                    setCoordinates(pos.lat, pos.lng, false);
                });
            }
            updateRadiusCircle([cLat, cLng]);
            if (zoomTo) {
                map.setView([cLat, cLng], 17);
            }
        }

        if (marker) {
            marker.on('dragend', function(e){
                var pos = e.target.getLatLng();
                setCoordinates(pos.lat, pos.lng, false);
            });
        }

        map.on('click', function(e){
            setCoordinates(e.latlng.lat, e.latlng.lng, false);
        });

        if (radiusInput) {
            radiusInput.addEventListener('input', function() {
                if (marker) {
                    updateRadiusCircle(marker.getLatLng());
                }
            });
        }

        // "Gunakan Lokasi Saya Saat Ini" button
        var btnDetect = document.getElementById('btn-detect-school-location');
        if (btnDetect && navigator.geolocation) {
            btnDetect.addEventListener('click', function() {
                var originalHtml = btnDetect.innerHTML;
                btnDetect.disabled = true;
                btnDetect.textContent = 'Mencari lokasi GPS...';
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        var ulat = pos.coords.latitude;
                        var ulng = pos.coords.longitude;
                        setCoordinates(ulat, ulng, true);
                        btnDetect.disabled = false;
                        btnDetect.innerHTML = '<span style="color:#15803d; font-weight:600;">✓ Lokasi Terpasang!</span>';
                        setTimeout(function() { btnDetect.innerHTML = originalHtml; }, 3000);
                    },
                    function(err) {
                        btnDetect.disabled = false;
                        btnDetect.textContent = 'Gagal: ' + err.message;
                        setTimeout(function() { btnDetect.innerHTML = originalHtml; }, 3000);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            });
        }

        setTimeout(function(){ map.invalidateSize(); }, 250);
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
            <a href="<?= e(route_url('school', ['id' => (int)$row['id']])) ?>" class="button small">Profil &amp; Lokasi</a>
            <a href="<?= e(route_url('school-edit', ['id' => (int)$row['id']])) ?>" class="button small primary">Edit</a>
            <?php if ((int)$row['class_count'] === 0): ?>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_school">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="button small danger" type="submit" data-confirm="Hapus data sekolah ini? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus sekolah?" data-confirm-label="Ya, hapus" data-confirm-tone="danger">Hapus</button>
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
        'custom_break_enabled' => 0, 'custom_break_days' => '1,3,5', 'custom_break1_after' => 2, 'custom_break1_minutes' => 15, 'custom_break2_after' => 5, 'custom_break2_minutes' => 15,
        'friday_break_custom' => 0, 'friday_break1_after' => 2, 'friday_break1_minutes' => 15, 'friday_break2_after' => 0, 'friday_break2_minutes' => 0,
        'village' => '', 'district' => '', 'regency' => '', 'province' => '',
    ];
    render_header($id > 0 ? 'Edit Sekolah' : 'Tambah Sekolah');
    $nipLabel = report_nip_label();
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
                    <div class="school-form-group">
                        <label class="school-label">Label Identitas Kepala Sekolah</label>
                        <select class="school-input school-select" name="nip_label">
                            <?php foreach (['NIP' => 'NIP (Nomor Induk Pegawai)', 'NBM' => 'NBM (Nomor Badan Madrasah)', 'NIK' => 'NIK (Nomor Induk Kependudukan)', 'NUP' => 'NUP (Nomor Urut Pegawai)'] as $val => $lbl): ?>
                                <option value="<?= e($val) ?>" <?= $nipLabel === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                            <?php if (!in_array($nipLabel, ['NIP', 'NBM', 'NIK', 'NUP'], true)): ?>
                                <option value="<?= e($nipLabel) ?>" selected><?= e($nipLabel) ?> (custom)</option>
                            <?php endif; ?>
                            <option value="custom" data-custom> custom (isi sendiri)…</option>
                        </select>
                        <input type="text" class="school-input" name="nip_label_custom" id="nip-label-custom" style="margin-top:6px;display:none;" placeholder="Label lain, contoh: NIP/NIK" value="<?= e(in_array($nipLabel, ['NIP', 'NBM', 'NIK', 'NUP'], true) ? '' : $nipLabel) ?>">
                    </div>
                </div>
                <script>
                (function() {
                    var sel = document.querySelector('select[name="nip_label"]');
                    var custom = document.getElementById('nip-label-custom');
                    if (!sel || !custom) return;
                    function sync() {
                        if (sel.value === 'custom') { custom.style.display = ''; custom.required = true; }
                        else { custom.style.display = 'none'; custom.required = false; custom.value = ''; }
                    }
                    sel.addEventListener('change', sync); sync();
                })();
                </script>
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
                <?php $autoUsername = !empty($edit['name']) ? generate_username_from_name((string)$edit['name']) : ''; ?>
                <label>Username Login <input type="text" name="username" data-autofill-username value="<?= e($edit['username'] ?? '') ?>" placeholder="otomatis: <?= e($autoUsername) ?>"></label>
                <label>Password Login <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter, jangan mudah ditebak"></label>
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
    $orderClause = function_exists('class_order_by_clause') ? class_order_by_clause('c') : 'c.display_order ASC, c.grade, c.name';
    $rows = fetch_all('SELECT c.*, t.name AS teacher_name, sp.name AS school_name FROM classes c LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id LEFT JOIN school_profile sp ON sp.id = c.school_id ORDER BY sp.name, ' . $orderClause);
    render_header('Data Kelas');
    ?>
    <div style="background: rgba(14, 165, 233, 0.08); border-left: 4px solid #0284c7; border-radius: 8px; padding: 10px 14px; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem; color: #0369a1;">
            <i data-lucide="link-2" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
            <div>
                <strong>Sekolah Boarding / Kelas Paralel?</strong>
                <span style="color: #0284c7;">Jika beberapa rombel Dapodik diajar bersamaan dalam 1 ruangan fisik, Anda bisa menggabungkannya secara cepat.</span>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <button type="button" class="button small secondary" onclick="document.getElementById('modal-reorder-classes').style.display='flex';" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                <i data-lucide="arrow-up-down" style="width: 14px; height: 14px;"></i> Atur Urutan Kelas
            </button>
            <a href="<?= e(route_url('gabung-kelas')) ?>" class="button small secondary" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600; text-decoration: none;">
                <i data-lucide="users" style="width: 14px; height: 14px;"></i> Buka Menu Gabung Kelas
            </a>
        </div>
    </div>
    <?php
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
                <span class="field-icon"><i data-lucide="printer"></i> Nama Cetak Rapor</span>
                <input type="text" name="report_name" value="<?= e($edit['report_name'] ?? '') ?>" placeholder="Opsional (misal: VII, 7A, X-E1). Kosongkan jika sama">
            </label>
            <label>
                <span class="field-icon"><i data-lucide="users"></i> Rombel Lapangan / Kelompok KBM (Boarding)</span>
                <input type="text" name="physical_group" value="<?= e($edit['physical_group'] ?? '') ?>" placeholder="Opsional (misal: X E untuk rombel X E1 & X E2). Kosongkan jika mandiri">
            </label>
            <label>
                <span class="field-icon"><i data-lucide="arrow-up-down"></i> Nomor Urut Tampilan</span>
                <input type="number" name="display_order" min="0" value="<?= e($edit['display_order'] ?? 0) ?>" placeholder="Contoh: 1, 2, 3... (angka kecil tampil lebih awal)">
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
    <?php table_panel('Daftar Kelas', ['Urutan', 'Sekolah', 'Kelas', 'Tingkat', 'Jenjang', 'Jurusan', 'Wali Kelas', 'Jumlah Siswa', 'Status', 'Aksi'], $rows, function ($row) {
        $count = (int)fetch_one('SELECT COUNT(*) AS c FROM students WHERE class_id = ?', [(int)$row['id']])['c'];
        ?><td><span class="badge" style="font-weight:700;background:rgba(2,132,199,0.1);color:#0284c7;"><?= (int)($row['display_order'] ?? 0) ?></span></td><td><?= e($row['school_name'] ?? '-') ?></td><td><strong><?= e($row['name']) ?></strong><?php if (!empty($row['report_name']) && $row['report_name'] !== $row['name']): ?><br><small style="color:var(--text-muted);font-size:0.82em;display:inline-flex;align-items:center;gap:3px;margin-top:2px;" title="Nama yang dicetak pada lembar rapor"><i data-lucide="printer" style="width:11px;height:11px"></i> Rapor: <strong><?= e($row['report_name']) ?></strong></small><?php endif; ?><?php if (!empty($row['physical_group'])): ?><br><small style="color:#0284c7;font-size:0.82em;display:inline-flex;align-items:center;gap:3px;margin-top:2px;" title="Digabung dalam KBM fisik/lapangan"><i data-lucide="users" style="width:11px;height:11px"></i> KBM: <strong><?= e($row['physical_group']) ?></strong></small><?php endif; ?></td><td><?= e($row['grade']) ?></td><td><?= e($row['level'] ?? '-') ?></td><td><?= e($row['major'] ?? '-') ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($count) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('classes', (int)$row['id'], 'delete_class') ?></td><?php
    }, '', true); ?>

    <!-- Modal Atur Urutan Kelas Cepat -->
    <div id="modal-reorder-classes" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:10px;max-width:650px;width:100%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h3 style="margin:0;font-size:1.05rem;font-weight:700;display:flex;align-items:center;gap:8px;">
                        <i data-lucide="arrow-up-down" style="color:#0284c7;"></i> Atur Urutan Tampilan Kelas
                    </h3>
                    <p style="margin:2px 0 0 0;font-size:0.78rem;color:var(--text-muted);">
                        Urutan ini menentukan posisi kolom kelas di Jadwal Pelajaran dan daftar rapor.
                    </p>
                </div>
                <button type="button" onclick="document.getElementById('modal-reorder-classes').style.display='none';" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">&times;</button>
            </div>
            <form method="post" action="<?= e(route_url('classes')) ?>" style="display:flex;flex-direction:column;overflow:hidden;flex:1;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reorder_classes">
                <input type="hidden" name="redirect_back" value="classes">
                <div style="padding:10px 18px;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">Isi nomor urut (1, 2, 3...) sesuai urutan yang Anda inginkan:</span>
                    <button type="button" class="button small" onclick="autoSortClassInputs('modal-reorder-classes')" style="font-size:0.75rem;padding:3px 8px;">
                        🪄 Urutkan Otomatis (7-12)
                    </button>
                </div>
                <div style="overflow-y:auto;padding:14px 18px;flex:1;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);text-align:left;">
                                <th style="padding:6px;">Kelas</th>
                                <th style="padding:6px;">Tingkat</th>
                                <th style="padding:6px;">Jenjang</th>
                                <th style="padding:6px;width:100px;text-align:center;">Urutan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $c): ?>
                                <tr style="border-bottom:1px solid var(--border);" data-grade="<?= e($c['grade']) ?>" data-name="<?= e($c['name']) ?>">
                                    <td style="padding:8px 6px;"><strong><?= e($c['name']) ?></strong></td>
                                    <td style="padding:8px 6px;"><?= e($c['grade']) ?></td>
                                    <td style="padding:8px 6px;"><?= e($c['level'] ?? '-') ?></td>
                                    <td style="padding:8px 6px;text-align:center;">
                                        <input type="number" name="class_order[<?= (int)$c['id'] ?>]" value="<?= (int)($c['display_order'] ?? 0) ?>" min="1" class="class-order-field" style="width:70px;text-align:center;padding:4px;border:1px solid #cbd5e1;border-radius:4px;font-weight:700;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;background:var(--surface);">
                    <button type="button" class="button" onclick="document.getElementById('modal-reorder-classes').style.display='none';">Batal</button>
                    <button type="submit" class="button primary" style="font-weight:600;"><i data-lucide="save"></i> Simpan Urutan Kelas</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function autoSortClassInputs(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        var rows = Array.from(modal.querySelectorAll('tbody tr'));
        rows.sort(function(a, b) {
            var gradeA = a.getAttribute('data-grade') || '';
            var gradeB = b.getAttribute('data-grade') || '';
            var mapRoman = { 'vii': 7, 'viii': 8, 'ix': 9, 'x': 10, 'xi': 11, 'xii': 12 };
            var numA = mapRoman[gradeA.toLowerCase()] || parseInt(gradeA, 10) || 99;
            var numB = mapRoman[gradeB.toLowerCase()] || parseInt(gradeB, 10) || 99;
            if (numA !== numB) return numA - numB;
            var nameA = a.getAttribute('data-name') || '';
            var nameB = b.getAttribute('data-name') || '';
            return nameA.localeCompare(nameB);
        });
        rows.forEach(function(row, idx) {
            var input = row.querySelector('.class-order-field');
            if (input) input.value = idx + 1;
        });
    }
    </script>
    <?php render_footer();
}

function page_gabung_kelas(): void
{
    require_role(['admin']);

    $classes = fetch_all(
        'SELECT c.*, sp.name AS school_name, t.name AS teacher_name, 
         (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.active = 1) AS student_count
         FROM classes c 
         LEFT JOIN school_profile sp ON sp.id = c.school_id 
         LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id
         WHERE c.active = 1 
         ORDER BY sp.name, c.grade, c.name'
    );

    $groupedClasses = [];
    foreach ($classes as $c) {
        $pg = trim((string)($c['physical_group'] ?? ''));
        if ($pg !== '') {
            $groupedClasses[$pg][] = $c;
        }
    }

    render_header('Gabung Kelas KBM (Boarding)');
    ?>
    <section class="panel" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap;">
            <div style="background: rgba(37, 99, 235, 0.1); color: #2563eb; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="link-2" style="width: 24px; height: 24px;"></i>
            </div>
            <div style="flex: 1; min-width: 280px;">
                <h3 style="margin: 0 0 6px;">Penggabungan Rombel KBM (Boarding School / Paralel)</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem; line-height: 1.5;">
                    Fitur ini digunakan bila di lapangan (boarding/pesantren) beberapa rombel Dapodik belajar bersama dalam <strong>1 ruang fisik KBM yang sama</strong> (misal <strong>X E1</strong> dan <strong>X E2</strong> digabung menjadi kelas lapangan <strong>X E</strong>).
                </p>
                <div style="margin-top: 10px; display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.85rem; color: #1e40af;">
                    <span>✓ <strong>Cetak Rapor Tetap Sesuai Dapodik</strong> (Lembar fisik & TTD Wali Kelas masing-masing tidak berubah)</span>
                    <span>✓ <strong>Guru Cukup Absen & Input Nilai 1 Kali</strong> (Otomatis tersalurkan ke masing-masing rombel)</span>
                    <span>✓ <strong>Jadwal Pelajaran Bebas Bentrok Jam</strong></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Form Gabung Kelas Baru -->
    <section class="panel" style="margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 1rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="plus-circle" style="width: 18px; height: 18px; color: var(--primary);"></i>
            Gabungkan Kelas KBM Baru
        </h4>
        <form method="post" class="form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="merge_kbm_classes">

            <div class="grid two" style="margin-bottom: 1rem;">
                <label>
                    <span class="field-icon"><i data-lucide="tag"></i> Nama Rombel Fisik Lapangan (KBM Bersama) <span style="color:#c00">*</span></span>
                    <input type="text" name="group_name" required placeholder="Contoh: X E (untuk rombel X E1 & X E2)">
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 3px;">
                        Nama ruangan/kelas fisik yang digunakan guru saat KBM harian di lapangan.
                    </small>
                </label>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <span style="font-size: 0.9rem; font-weight: 600; display: block; margin-bottom: 8px;">
                    Pilih Minimal 2 Kelas yang Ingin Digabung: <span style="color:#c00">*</span>
                </span>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; max-height: 280px; overflow-y: auto; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-secondary);">
                    <?php foreach ($classes as $c): ?>
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; font-size: 0.88rem; cursor: pointer; padding: 6px 10px; background: #fff; border: 1px solid var(--border); border-radius: 6px;">
                            <input type="checkbox" name="class_ids[]" value="<?= (int)$c['id'] ?>">
                            <span style="flex: 1;">
                                <strong><?= e($c['name']) ?></strong>
                                <small style="color: var(--text-muted); display: block; font-size: 0.78rem;">
                                    Tingkat <?= e($c['grade']) ?> &bull; <?= e($c['student_count']) ?> Siswa &bull; Wali: <?= e($c['teacher_name'] ?? '-') ?>
                                    <?php if (!empty($c['physical_group'])): ?>
                                        <span style="color: #0284c7; font-weight: 600;">(Saat ini: <?= e($c['physical_group']) ?>)</span>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="button primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i data-lucide="link-2" style="width: 16px; height: 16px;"></i>
                    Gabungkan Kelas Terpilih
                </button>
                <a href="<?= e(route_url('classes')) ?>" class="button">Kembali ke Data Kelas</a>
            </div>
        </form>
    </section>

    <!-- Daftar Kelas yang Sedang Digabung -->
    <section class="panel">
        <h4 style="margin: 0 0 1rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="list" style="width: 18px; height: 18px; color: var(--primary);"></i>
            Daftar Rombel KBM yang Sedang Digabung (<?= count($groupedClasses) ?> Kelompok)
        </h4>
        <?php if (empty($groupedClasses)): ?>
            <p class="empty" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">
                Belum ada kelas yang digabung. Silakan pilih kelas pada form di atas untuk menggabungkan rombel KBM.
            </p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($groupedClasses as $groupName => $gClasses): ?>
                    <?php 
                        $totalSiswa = array_sum(array_column($gClasses, 'student_count'));
                    ?>
                    <div style="border: 1px solid var(--border); border-left: 4px solid #0284c7; border-radius: 8px; padding: 14px 18px; background: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.1rem; font-weight: 700; color: #0369a1;">
                                    <?= e($groupName) ?>
                                </span>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;">
                                    <?= count($gClasses) ?> Rombel Dapodik &bull; Total <?= $totalSiswa ?> Siswa
                                </span>
                            </div>
                            <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                <span style="font-size: 0.82rem; color: var(--text-muted);">Rombel Tergabung:</span>
                                <?php foreach ($gClasses as $gc): ?>
                                    <span style="font-size: 0.82rem; padding: 3px 10px; border-radius: 999px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                        <strong><?= e($gc['name']) ?></strong> (<?= e($gc['student_count']) ?> siswa &bull; Wali: <?= e($gc['teacher_name'] ?? '-') ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <form method="post" style="margin: 0;" onsubmit="return confirm('Lepas penggabungan rombel KBM <?= e(addslashes($groupName)) ?>? Kelas-kelas di dalamnya akan kembali berdiri mandiri.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unmerge_kbm_classes">
                                <input type="hidden" name="group_name" value="<?= e($groupName) ?>">
                                <button type="submit" class="button small danger" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <i data-lucide="unlink" style="width: 14px; height: 14px;"></i>
                                    Lepas Gabungan
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function page_students(): void
{
    require_role(['admin']);
    $edit = edit_row('students') ?: [];
    $allClasses = classes_with_level();
    $classes = array_column_map($allClasses, 'id', 'name');
    $levelFilter = (string)($_GET['level'] ?? '');
    $editClassLevel = '';
    if ($edit && !empty($edit['class_id'])) {
        foreach ($allClasses as $classRow) {
            if ((int)$classRow['id'] === (int)$edit['class_id']) {
                $editClassLevel = trim((string)($classRow['level'] ?? ''));
                break;
            }
        }
    }
    $formLevel = $editClassLevel !== '' ? $editClassLevel : $levelFilter;
    try {
        $rows = fetch_all('SELECT s.*, c.name AS class_name, c.level AS class_level FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.grade, c.name, s.name');
    } catch (Throwable) {
        $rows = fetch_all('SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.grade, c.name, s.name');
        foreach ($rows as &$r) { $r['class_level'] = null; }
        unset($r);
    }
    if ($levelFilter !== '') {
        $rows = array_values(array_filter($rows, function ($r) use ($levelFilter) {
            return ($r['class_level'] ?? '') === $levelFilter;
        }));
    }
    $existingUser = $edit ? fetch_one('SELECT id, username FROM users WHERE student_id = ?', [(int)$edit['id']]) : null;
    render_header('Data Siswa');
    ?>
    <section class="panel no-print">
        <form method="get" class="grid three" style="margin-bottom:1rem">
            <input type="hidden" name="page" value="students">
            <label>Filter Jenjang
                <select name="level" onchange="this.form.submit()">
                    <option value="">Semua Jenjang</option>
                    <?php foreach (school_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $levelFilter === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($levelFilter !== ''): ?>
                <div class="actions" style="align-self:end"><a class="button" href="?page=students">Reset Filter</a></div>
            <?php endif; ?>
        </form>
    </section>
    <?php
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
            <label>Jenjang
                <select id="stu-level" data-edit-level="<?= e($formLevel) ?>">
                    <option value="">Pilih Jenjang</option>
                    <?php foreach (school_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $formLevel === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kelas
                <select name="class_id" id="stu-class">
                    <option value="">-</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-level="<?= e($c['level'] ?? '') ?>"<?= (int)($edit['class_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($existingUser): ?>
                <label>Username Login <input type="text" name="username" value="<?= e($existingUser['username'] ?? '') ?>" readonly></label>
                <label>Password Baru <input type="password" name="password" placeholder="Kosongkan jika tidak diganti"></label>
            <?php else: ?>
                <label>Username Login <input type="text" name="username" value="" placeholder="default: NISN siswa (opsional)"></label>
                <label>Password Login <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Isi bila ingin membuat akun login siswa"></label>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('students')) ?>">Reset</a></div>
        </form>
        <script>
        (function(){
            var levelEl = document.getElementById('stu-level');
            var classEl = document.getElementById('stu-class');
            if (!levelEl || !classEl) return;
            var allClassOpts = Array.from(classEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; });
            var preLevel = levelEl.value || levelEl.getAttribute('data-edit-level') || '';
            var editClassId = '<?= e((string)($edit['class_id'] ?? '')) ?>';
            function rebuild() {
                var prev = editClassId || classEl.value;
                classEl.innerHTML = '<option value="">-</option>';
                allClassOpts.filter(function(o){ return !o.value || !levelEl.value || o.level === levelEl.value; }).forEach(function(o){
                    var opt = document.createElement('option');
                    opt.value = o.value; opt.textContent = o.text;
                    opt.setAttribute('data-level', o.level);
                    classEl.appendChild(opt);
                });
                if (prev && classEl.querySelector('option[value="' + prev + '"]')) classEl.value = prev;
            }
            levelEl.addEventListener('change', rebuild);
            if (preLevel) { levelEl.value = preLevel; rebuild(); }
            classEl.form.addEventListener('submit', function(event){
                if (levelEl.value && !classEl.value) {
                    event.preventDefault();
                    window.alert('Pilih kelas yang sesuai dengan jenjang terlebih dahulu.');
                    classEl.focus();
                }
            });
        })();
        </script>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Siswa' . ($levelFilter !== '' ? ' — Jenjang ' . $levelFilter : ''), ['Nama', 'NIS', 'NISN', 'JK', 'Kelas', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['nis']) ?></td><td><?= e($row['nisn']) ?></td><td><?= e($row['gender']) ?></td><td><?= e($row['class_name']) ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('students', (int)$row['id'], 'delete_student') ?></td>
    <?php }, '', true); ?>
    <?php render_footer();
}

function page_subjects(): void
{
    require_role(['admin']);
    $edit = edit_row('subjects') ?: [];
    $hasLevel = subject_level_column();
    $rows = fetch_all('SELECT * FROM subjects ORDER BY group_name, name');
    foreach ($rows as &$rl) { if (!array_key_exists('level', $rl)) { $rl['level'] = null; } }
    unset($rl);
    $selectedLevels = [];
    if ($hasLevel && !empty($edit['level'])) {
        $selectedLevels = array_values(array_filter(array_map('trim', explode(',', (string)$edit['level']))));
    }
    $levelOptions = array_values(array_unique(array_merge(school_levels(), $selectedLevels)));
    $levelFilter = (string)($_GET['level'] ?? '');
    if ($levelFilter !== '') {
        $rows = array_values(array_filter($rows, function ($r) use ($levelFilter) {
            $lv = (string)($r['level'] ?? '');
            return $lv === '' || in_array($levelFilter, array_map('trim', explode(',', $lv)), true);
        }));
    }
    render_header('Data Mapel');
    ?>
    <section class="panel no-print">
        <form method="get" class="grid three" style="margin-bottom:0">
            <input type="hidden" name="page" value="subjects">
            <label>Filter Jenjang
                <select name="level" onchange="this.form.submit()">
                    <option value="">Semua Jenjang</option>
                    <?php foreach (school_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $levelFilter === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($levelFilter !== ''): ?>
                <div class="actions" style="align-self:end"><a class="button" href="?page=subjects">Reset Filter</a></div>
            <?php endif; ?>
        </form>
    </section>
    <?php
    input_panel_start($edit ? 'Edit Mapel' : 'Input Mapel', 'Tambah Mapel', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_subject"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Nama Mapel <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
            <label>Nama Singkat <input type="text" name="short_name" value="<?= e($edit['short_name'] ?? '') ?>"></label>
            <label>Kelompok <input type="text" name="group_name" value="<?= e($edit['group_name'] ?? '') ?>"></label>
            <?php if ($hasLevel): ?>
            <?= subject_levels_input($selectedLevels, $levelOptions) ?>
            <?php else: ?>
            <p class="hint" style="grid-column:1/-1">Kolom jenjang belum ada di database — mapel tersimpan tanpa jenjang. Tambahkan kolom <code>subjects.level VARCHAR(64) NULL</code> bila perlu filter jenjang.</p>
            <?php endif; ?>
            <label class="check"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>
            <div class="actions span-2"><button class="button primary">Simpan</button><a class="button" href="<?= e(route_url('subjects')) ?>">Reset</a></div>
        </form>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Mapel' . ($levelFilter !== '' ? ' — Jenjang ' . $levelFilter : ''), ['Nama Mapel', 'Singkat', 'Kelompok', 'Jenjang', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['name']) ?></td><td><?= e($row['short_name']) ?></td><td><?= e($row['group_name']) ?></td><td><?= subject_levels_badge($row['level'] ?? '') ?></td><td><?= status_badge((int)$row['active']) ?></td><td><?= row_actions('subjects', (int)$row['id'], 'delete_subject') ?></td>
    <?php }, '', true); ?>
    <?php render_footer();
}

function page_assignments(): void
{
    require_role(['admin']);
    $edit = edit_row('teaching_assignments') ?: [];
    $teachers = map_options('teachers', 'name');
    $allClasses = classes_with_level();
    $allSubjects = subjects_with_level();
    $editClass = null;
    if (!empty($edit) && !empty($edit['class_id'])) {
        try {
            $editClass = fetch_one('SELECT id, name, level FROM classes WHERE id = ?', [(int)$edit['class_id']]);
        } catch (Throwable) {
            $editClass = fetch_one('SELECT id, name FROM classes WHERE id = ?', [(int)$edit['class_id']]);
            if ($editClass) { $editClass['level'] = ''; }
        }
    }
    $editLevel = (string)($editClass['level'] ?? '');
    $rows = assignment_rows();
    $levelFilter = (string)($_GET['level'] ?? '');
    if ($levelFilter !== '') {
        $rows = array_values(array_filter($rows, function ($r) use ($levelFilter) {
            return (string)($r['class_level'] ?? '') === $levelFilter;
        }));
    }
    render_header('Data Pembelajaran');
    ?>
    <section class="panel no-print">
        <form method="get" class="grid three" style="margin-bottom:0">
            <input type="hidden" name="page" value="assignments">
            <label>Filter Jenjang
                <select name="level" onchange="this.form.submit()">
                    <option value="">Semua Jenjang</option>
                    <?php foreach (school_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $levelFilter === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($levelFilter !== ''): ?>
                <div class="actions" style="align-self:end"><a class="button" href="?page=assignments">Reset Filter</a></div>
            <?php endif; ?>
        </form>
    </section>
    <?php
    input_panel_start($edit ? 'Edit Pembelajaran' : 'Input Pembelajaran', 'Tambah Pembelajaran', (bool)$edit || isset($_GET['add']));
    ?>
        <form method="post" class="grid four" id="asg-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_assignment"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <label class="span-2">Guru <select name="teacher_id" required><?= options($teachers, $edit['teacher_id'] ?? '') ?></select></label>
            <label class="span-2">Mapel
                <select name="subject_id" id="asg-subject" required>
                    <option value="">Pilih Mapel</option>
                    <?php foreach ($allSubjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" data-level="<?= e($s['level'] ?? '') ?>"<?= (int)($edit['subject_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Filter Jenjang
                <select id="asg-level" data-edit-level="<?= e($editLevel) ?>">
                    <option value="">Semua Jenjang</option>
                    <?php foreach (school_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>"<?= $editLevel === $lv ? ' selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tahun Ajaran <input type="text" name="academic_year" required value="<?= e($edit['academic_year'] ?? current_academic_year()) ?>" readonly></label>
            <label>Semester <input type="text" name="semester" required value="<?= e($edit['semester'] ?? current_semester()) ?>" readonly></label>
            <label class="check" style="align-self:center;margin-top:1.5rem;"><input type="checkbox" name="active" <?= checked($edit['active'] ?? 1) ?>> Aktif</label>

            <?php if ($edit): ?>
                <label class="span-4">Kelas
                    <select name="class_id" id="asg-class" required>
                        <option value="">Pilih Kelas</option>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-level="<?= e($c['level'] ?? '') ?>"<?= (int)($edit['class_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <div class="span-4 asg-classes-card">
                    <div class="asg-classes-header">
                        <div class="asg-classes-title">
                            <i data-lucide="layers"></i>
                            <strong>Pilih Kelas</strong>
                            <span class="hint">(Centang satu atau beberapa kelas sekaligus untuk guru &amp; mapel ini)</span>
                        </div>
                        <div class="asg-classes-actions">
                            <button type="button" class="button" id="btn-select-all-classes" style="padding:4px 10px;font-size:0.8rem;">
                                <i data-lucide="check-square"></i> Pilih Semua
                            </button>
                            <button type="button" class="button" id="btn-deselect-all-classes" style="padding:4px 10px;font-size:0.8rem;">
                                Batal Semua
                            </button>
                        </div>
                    </div>
                    <div class="asg-classes-grid" id="asg-classes-container">
                        <?php if (empty($allClasses)): ?>
                            <p class="hint" style="grid-column:1/-1;text-align:center;padding:12px 0;">Belum ada data kelas aktif. Silakan tambahkan kelas terlebih dahulu di menu Data Kelas.</p>
                        <?php else: ?>
                            <?php foreach ($allClasses as $c): ?>
                                <label class="asg-class-item" data-level="<?= e($c['level'] ?? '') ?>">
                                    <input type="checkbox" name="class_ids[]" value="<?= (int)$c['id'] ?>" class="asg-class-cb">
                                    <span class="asg-class-name"><?= e($c['name']) ?></span>
                                    <?php if (!empty($c['level'])): ?>
                                        <span class="badge muted asg-class-level"><?= e($c['level']) ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="asg-classes-empty" class="hint" style="display:none;padding:12px;text-align:center;">
                        Tidak ada kelas yang sesuai dengan jenjang yang dipilih.
                    </div>
                </div>
            <?php endif; ?>

            <div class="actions span-4">
                <button class="button primary"><i data-lucide="save"></i> <?= $edit ? 'Simpan Perubahan' : 'Simpan Pembelajaran' ?></button>
                <a class="button" href="<?= e(route_url('assignments')) ?>"><i data-lucide="rotate-ccw"></i> Reset</a>
            </div>
        </form>
        <script>
        (function(){
            var levelEl = document.getElementById('asg-level');
            var classEl = document.getElementById('asg-class');
            var subjectEl = document.getElementById('asg-subject');
            var form = document.getElementById('asg-form');
            var classItems = Array.from(document.querySelectorAll('.asg-class-item'));
            var emptyNotice = document.getElementById('asg-classes-empty');

            var allClassOpts = classEl ? Array.from(classEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; }) : [];
            var allSubjectOpts = Array.from(subjectEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; });
            var preLevel = levelEl.value || levelEl.getAttribute('data-edit-level') || '';

            function subjectMatch(subjectLevel, classLevel) {
                if (!subjectLevel) return true;
                return subjectLevel.split(',').map(function(s){return s.trim();}).indexOf(classLevel) !== -1;
            }

            function rebuildSubjects(level) {
                var prev = subjectEl.value;
                subjectEl.innerHTML = '<option value="">Pilih Mapel</option>';
                allSubjectOpts.filter(function(o){ return !o.value || !level || subjectMatch(o.level, level); }).forEach(function(o){
                    var opt = document.createElement('option');
                    opt.value = o.value; opt.textContent = o.text;
                    opt.setAttribute('data-level', o.level);
                    subjectEl.appendChild(opt);
                });
                if (prev && subjectEl.querySelector('option[value="' + prev + '"]')) subjectEl.value = prev;
            }

            function filterClasses(level) {
                if (classEl) {
                    var prev = classEl.value;
                    classEl.innerHTML = '<option value="">Pilih Kelas</option>';
                    allClassOpts.filter(function(o){ return !o.value || !level || subjectMatch(o.level, level); }).forEach(function(o){
                        var opt = document.createElement('option');
                        opt.value = o.value; opt.textContent = o.text;
                        opt.setAttribute('data-level', o.level);
                        classEl.appendChild(opt);
                    });
                    if (prev && classEl.querySelector('option[value="' + prev + '"]')) classEl.value = prev;
                    return;
                }

                if (!classItems.length) return;
                var visibleCount = 0;
                classItems.forEach(function(item) {
                    var itemLevel = item.getAttribute('data-level') || '';
                    var match = !level || !itemLevel || itemLevel === level;
                    item.style.display = match ? '' : 'none';
                    if (!match) {
                        var cb = item.querySelector('.asg-class-cb');
                        if (cb && cb.checked) {
                            cb.checked = false;
                            item.classList.remove('is-checked');
                        }
                    } else {
                        visibleCount++;
                    }
                });
                if (emptyNotice) {
                    emptyNotice.style.display = visibleCount === 0 ? '' : 'none';
                }
            }

            document.querySelectorAll('.asg-class-cb').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var item = cb.closest('.asg-class-item');
                    if (item) item.classList.toggle('is-checked', cb.checked);
                });
            });

            var btnAll = document.getElementById('btn-select-all-classes');
            if (btnAll) {
                btnAll.addEventListener('click', function(e) {
                    e.preventDefault();
                    classItems.forEach(function(item) {
                        if (item.style.display !== 'none') {
                            var cb = item.querySelector('.asg-class-cb');
                            if (cb) {
                                cb.checked = true;
                                item.classList.add('is-checked');
                            }
                        }
                    });
                });
            }

            var btnNone = document.getElementById('btn-deselect-all-classes');
            if (btnNone) {
                btnNone.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.asg-class-cb').forEach(function(cb) {
                        cb.checked = false;
                        var item = cb.closest('.asg-class-item');
                        if (item) item.classList.remove('is-checked');
                    });
                });
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!classEl) {
                        var checked = form.querySelectorAll('input[name="class_ids[]"]:checked');
                        if (!checked.length) {
                            e.preventDefault();
                            alert('Pilih minimal satu kelas untuk pembelajaran.');
                            var container = document.getElementById('asg-classes-container');
                            if (container) container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
            }

            levelEl.addEventListener('change', function(){
                rebuildSubjects(levelEl.value);
                filterClasses(levelEl.value);
            });

            if (preLevel) {
                rebuildSubjects(preLevel);
                filterClasses(preLevel);
            }
        })();
        </script>
    <?php input_panel_end(); ?>
    <?php table_panel('Daftar Pembelajaran' . ($levelFilter !== '' ? ' — Jenjang ' . $levelFilter : ''), ['Guru', 'Jenjang', 'Kelas', 'Mapel', 'Tahun', 'Semester', 'Status', 'Aksi'], $rows, function ($row) { ?>
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
