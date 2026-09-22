<?php declare(strict_types=1);

function page_import_bulk(): void
{
    require_role(['admin']);

    if (!empty($_GET['download'])) {
        action_import_bulk_download();
        return;
    }

    // ── Show validation table if pending ──
    if (!empty($_GET['batal'])) {
        unset($_SESSION['import_dapodik_pending'], $_SESSION['import_dapodik_preview'], $_SESSION['import_bulk_pending']);
        redirect_to('import-bulk');
    }
    $preview = $_SESSION['import_dapodik_preview'] ?? null;
    if ($preview) {
        render_header('Import Dapodik — Validasi');
        $rows = $preview['rows'];
        $file = $preview['file'] ?? '';
        $newCount = 0;
        $existCount = 0;
        $noClassCount = 0;
        foreach ($rows as $r) {
            if ($r['existing']) { $existCount++; } else { $newCount++; }
            if (!$r['rombel'] || !$r['class_id']) { $noClassCount++; }
        }
        ?>
        <section class="panel">
            <h3>📋 Preview File Dapodik</h3>
            <p>File: <strong><?= e($file) ?></strong> — <strong><?= count($rows) ?></strong> siswa terbaca
                (<strong><?= $newCount ?></strong> baru, <strong><?= $existCount ?></strong> sudah ada<?= $preview['opts']['update'] ? ' — akan diupdate' : ' — dilewati' ?>).
                <?php if ($noClassCount > 0): ?>⚠️ <?= $noClassCount ?> baris <?= $preview['opts']['create_classes'] ? 'rombelnya belum ada — kelas otomatis dibuat' : 'rombelnya belum ada di aplikasi' ?>.<?php endif; ?>
            </p>
            <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_siswa_dapodik_confirm">
                <button class="button primary" data-confirm="Import <?= count($rows) ?> siswa dari file Dapodik?" data-confirm-title="Import siswa?" data-confirm-label="Ya, import" data-confirm-tone="primary">✅ Import <?= count($rows) ?> Siswa</button>
            </form>
            <a href="<?= e(route_url('import-bulk', ['batal' => '1'])) ?>" class="button">⬅ Kembali</a>
        </section>
        <section class="panel">
            <h3>Preview Data (10 pertama)</h3>
            <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>#</th><th>Nama</th><th>NIS / NISN</th><th>JK</th><th>TTL</th><th>Rombel</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($rows, 0, 10) as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['nis'] ?: '-') ?> / <?= e($r['nisn'] ?: '-') ?></td>
                        <td><?= e($r['gender'] ?: '-') ?></td>
                        <td><?= e($r['birth']) ?></td>
                        <td><?= e($r['rombel'] ?: '-') ?><?= $r['class_id'] ? '' : ' ⚠️' ?></td>
                        <td><?= $r['existing'] ? '<span style="color:var(--text-muted)">sudah ada (id:' . (int)$r['existing'] . ')</span>' : '<span style="color:var(--color-success,#16a34a)">baru</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($rows) > 10): ?><p>... dan <?= count($rows) - 10 ?> baris lainnya.</p><?php endif; ?>
            </div>
        </section>
        <?php
        render_footer();
        return;
    }
    $pending = $_SESSION['import_bulk_pending'] ?? null;
    if ($pending && $pending['type'] === 'jadwal') {
        render_header('Import Jadwal — Validasi');
        $rows = $pending['rows'];
        $validCount = 0;
        $invalidCount = 0;
        foreach ($rows as $r) {
            if ($r['valid']) { $validCount++; } else { $invalidCount++; }
        }
        ?>
        <section class="panel">
            <h3>📋 Hasil Validasi CSV Jadwal</h3>
            <p><strong><?= $validCount ?></strong> baris valid, <strong><?= $invalidCount ?></strong> baris bermasalah.</p>
            <?php if ($validCount > 0): ?>
            <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_bulk_confirm">
                <button class="button primary" data-confirm="Import <?= $validCount ?> jadwal yang sudah valid?" data-confirm-title="Import jadwal?" data-confirm-label="Ya, import" data-confirm-tone="primary">✅ Import <?= $validCount ?> Jadwal</button>
            </form>
            <?php endif; ?>
            <a href="<?= e(route_url('import-bulk')) ?>" class="button">⬅ Kembali</a>
        </section>
        <section class="panel">
            <h3>Preview Data</h3>
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Hari</th><th>Jam Ke</th><th>Kelas</th><th>Mapel</th><th>Guru</th><th>Mulai</th><th>Selesai</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr style="<?= $r['valid'] ? '' : 'background:var(--bg-danger,#fff0f0);' ?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['hari']) ?></td>
                        <td><?= e($r['jam_ke']) ?></td>
                        <td><?= e($r['kelas']) ?> <?= $r['class_id'] ? '<span style="color:var(--text-muted)">(id:' . $r['class_id'] . ')</span>' : '' ?></td>
                        <td><?= e($r['mapel']) ?> <?= $r['subject_id'] ? '<span style="color:var(--text-muted)">(id:' . $r['subject_id'] . ')</span>' : '' ?></td>
                        <td><?= e($r['guru']) ?> <?= $r['teacher_id'] ? '<span style="color:var(--text-muted)">(id:' . $r['teacher_id'] . ')</span>' : '' ?></td>
                        <td><?= e($r['jam_mulai'] ?? '-') ?></td>
                        <td><?= e($r['jam_selesai'] ?? '-') ?></td>
                        <td><?= $r['valid'] ? '<span style="color:var(--color-success,#16a34a)">✅ OK</span>' : '<span style="color:var(--color-danger,#dc2626)">❌ ' . e($r['error']) . '</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
        <?php
        render_footer();
        return;
    }

    render_header('Import Data Bulk');
    ?>
    <section class="panel">
        <h3>Download Template CSV</h3>
        <p>Download template, isi datanya, lalu upload kembali.</p>
        <div class="quick-links">
            <a href="<?= e(route_url('import-bulk', ['download' => 'guru'])) ?>">Template Guru (CSV)</a>
            <a href="<?= e(route_url('import-bulk', ['download' => 'siswa'])) ?>">Template Siswa (CSV)</a>
            <a href="<?= e(route_url('import-bulk', ['download' => 'jadwal'])) ?>">Template Jadwal (CSV)</a>
        </div>
    </section>
    <section class="panel">
        <h3>Upload CSV</h3>
        <form method="post" enctype="multipart/form-data" class="grid two" id="importForm">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_bulk" id="importAction">
            <label>Jenis Data <select name="data_type" id="dataType" onchange="document.getElementById('importAction').value = this.value === 'jadwal' ? 'import_bulk_validate' : 'import_bulk';">
                <option value="guru">Guru</option>
                <option value="siswa">Siswa</option>
                <option value="jadwal">Jadwal Pelajaran</option>
            </select></label>
            <?php render_file_upload('csv_file', '.csv,text/csv', 'File CSV', true, 'Format: username, password, name, gender, nip, dst.') ?>
            <div class="wide actions">
                <button class="button primary" id="importBtn" data-confirm="Import data ini?" data-confirm-title="Import data?" data-confirm-label="Lanjutkan" data-confirm-tone="primary">Import Sekarang</button>
            </div>
        </form>
        <script>
        (function(){
            var sel = document.getElementById('dataType');
            var btn = document.getElementById('importBtn');
            function updateBtn(){
                if(sel.value === 'jadwal'){
                    btn.textContent = '📋 Validasi Jadwal';
                    btn.dataset.confirm = 'Validasi data jadwal ini sebelum diimpor?';
                    btn.dataset.confirmTitle = 'Validasi jadwal?';
                    btn.dataset.confirmLabel = 'Ya, validasi';
                } else {
                    btn.textContent = 'Import Sekarang';
                    btn.dataset.confirm = 'Import data dari file CSV yang dipilih?';
                    btn.dataset.confirmTitle = 'Import data?';
                    btn.dataset.confirmLabel = 'Ya, import';
                }
            }
            sel.addEventListener('change', updateBtn);
            updateBtn();
        })();
        </script>
    </section>
    <section class="panel">
        <h3>📥 Import Siswa dari File Export Dapodik</h3>
        <p>Langsung upload file <strong>daftar_pd-NAMA SEKOLAH-tanggal.xlsx</strong> hasil unduhan Dapodik — tanpa template khusus. Header & kolom terdeteksi otomatis (Nama, NIPD→NIS, JK, NISN, TTL, alamat, ortu, Rombel Saat Ini).</p>
        <form method="post" enctype="multipart/form-data" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_siswa_dapodik_validate">
            <?php render_file_upload('xlsx_file', '.xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'File Export Dapodik (.xlsx)', true, 'Contoh: daftar_pd-SMP MUHAMMADIYAH ... .xlsx') ?>
            <div>
                <label class="check"><input type="checkbox" name="opt_update" checked> Update data siswa yang sudah ada (cocok NISN/NIS/nama)</label>
                <label class="check"><input type="checkbox" name="opt_create_classes" checked> Buatkan kelas otomatis bila rombel belum ada</label>
                <label class="check"><input type="checkbox" name="opt_create_users" checked> Buatkan akun login siswa (username NISN, password default)</label>
            </div>
            <div class="wide actions">
                <button class="button primary" data-confirm="Validasi file Dapodik ini sebelum diimpor?" data-confirm-title="Validasi Dapodik?" data-confirm-label="Ya, validasi" data-confirm-tone="primary">📋 Validasi File Dapodik</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}

function action_import_bulk_download(): void
{
    $type = (string)($_GET['download'] ?? '');
    if (!in_array($type, ['guru', 'siswa', 'jadwal'], true)) {
        return;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_' . $type . '.csv"');

    $output = fopen('php://output', 'w');
    if ($type === 'guru') {
        fputcsv($output, ['username', 'password', 'name', 'nip', 'nuptk', 'gender', 'phone', 'email', 'position', 'telegram_chat_id']);
        fputcsv($output, ['guru_budi', 'guru123', 'Budi Santoso, S.Pd', '198410102009011002', '', 'L', '70010002', 'budi@sekolah.local', 'Guru Mapel', '']);
        fputcsv($output, ['guru_rina', 'guru123', 'Rina Lestari, S.Pd', '199003032015022003', '', 'P', '70010003', 'rina@sekolah.local', 'Guru Mapel', '']);
    } elseif ($type === 'jadwal') {
        fputcsv($output, ['hari', 'jam_ke', 'kelas', 'mapel', 'guru', 'jam_mulai', 'jam_selesai']);
        fputcsv($output, ['Senin', '1', '7A', 'PJOK', 'Kasfaril Ramadani', '07:00', '07:35']);
        fputcsv($output, ['Senin', '1', '8A', 'PJOK', 'Kasfaril Ramadani', '07:00', '07:35']);
        fputcsv($output, ['Senin', '2', '7A', 'Matematika', 'Kodir', '07:35', '08:10']);
        fputcsv($output, ['Senin', '3', '7A', 'Bahasa Indonesia', 'Mekha Eka Sari', '08:25', '09:00']);
    } else {
        fputcsv($output, ['username', 'password', 'name', 'nis', 'nisn', 'gender', 'birth_place', 'birth_date', 'religion', 'address', 'phone', 'father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'guardian_name', 'class_name']);
        fputcsv($output, ['0081234001', 'siswa123', 'Ahmad Rizki', '0081234001', '0081234001', 'L', 'Jakarta', '2010-05-15', 'Islam', 'Jl. Merdeka No. 10, Kel. Sukamaju, Bandung 40123', '081234567890', 'H. Rizki Pratama', 'Wiraswasta', 'Siti Aminah', 'Guru', '', '7A']);
        fputcsv($output, ['0081234002', 'siswa123', 'Siti Nurhaliza', '0081234002', '0081234002', 'P', 'Bandung', '2010-03-20', 'Islam', 'Jl. Cendana No. 5, Kel. Mekar Jaya, Bandung 40124', '081234567891', 'Nursalam', 'Petani', 'Rohmah', 'IRT', '', '7A']);
    }
    fclose($output);
    exit;
}
