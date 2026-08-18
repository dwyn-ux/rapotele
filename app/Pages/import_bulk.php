<?php declare(strict_types=1);

function page_import_bulk(): void
{
    require_role(['admin']);

    if (!empty($_GET['download'])) {
        action_import_bulk_download();
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
        </div>
    </section>
    <section class="panel">
        <h3>Upload CSV</h3>
        <form method="post" enctype="multipart/form-data" class="grid two">
            <?= csrf_field() ?><input type="hidden" name="action" value="import_bulk">
            <label>Jenis Data <select name="data_type">
                <option value="guru">Guru</option>
                <option value="siswa">Siswa</option>
            </select></label>
            <label>File CSV <input type="file" name="csv_file" accept=".csv,text/csv" required></label>
            <div class="wide actions">
                <button class="button primary" onclick="return confirm('Import data ini?')">Import Sekarang</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}

function action_import_bulk_download(): void
{
    $type = (string)($_GET['download'] ?? '');
    if (!in_array($type, ['guru', 'siswa'], true)) {
        return;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_' . $type . '.csv"');

    $output = fopen('php://output', 'w');
    if ($type === 'guru') {
        fputcsv($output, ['username', 'password', 'name', 'nip', 'nuptk', 'gender', 'phone', 'email', 'position', 'telegram_chat_id']);
        fputcsv($output, ['guru_budi', 'guru123', 'Budi Santoso, S.Pd', '198410102009011002', '', 'L', '70010002', 'budi@sekolah.local', 'Guru Mapel', '']);
        fputcsv($output, ['guru_rina', 'guru123', 'Rina Lestari, S.Pd', '199003032015022003', '', 'P', '70010003', 'rina@sekolah.local', 'Guru Mapel', '']);
    } else {
        fputcsv($output, ['username', 'password', 'name', 'nis', 'nisn', 'gender', 'birth_place', 'birth_date', 'religion', 'address', 'phone', 'father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'guardian_name', 'class_name']);
        fputcsv($output, ['0081234001', 'siswa123', 'Ahmad Rizki', '0081234001', '0081234001', 'L', 'Jakarta', '2010-05-15', 'Islam', 'Jl. Merdeka No. 10, Kel. Sukamaju, Bandung 40123', '081234567890', 'H. Rizki Pratama', 'Wiraswasta', 'Siti Aminah', 'Guru', '', '7A']);
        fputcsv($output, ['0081234002', 'siswa123', 'Siti Nurhaliza', '0081234002', '0081234002', 'P', 'Bandung', '2010-03-20', 'Islam', 'Jl. Cendana No. 5, Kel. Mekar Jaya, Bandung 40124', '081234567891', 'Nursalam', 'Petani', 'Rohmah', 'IRT', '', '7A']);
    }
    fclose($output);
    exit;
}
