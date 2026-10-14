<?php declare(strict_types=1);

function page_backup_restore(): void
{
    require_role(['admin']);
    $rows = fetch_all('SELECT * FROM backups ORDER BY id DESC');
    render_header('Backup dan Restore');
    ?>
    <section class="panel">
        <div class="grid two">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create_backup"><button class="button primary">Backup Data</button></form>
            <form method="post" enctype="multipart/form-data" data-confirm="Restore akan mengganti data yang digunakan saat ini. Pastikan file backup sudah benar sebelum melanjutkan." data-confirm-title="Restore data?" data-confirm-label="Ya, restore" data-confirm-tone="warning">
                <?= csrf_field() ?><input type="hidden" name="action" value="restore_backup">
                <?php render_file_upload('userfile', '.json,application/json', 'File Backup JSON', true, 'Pilih file backup .json untuk direstore.') ?>
                <div class="actions"><button class="button danger">Restore Data</button></div>
            </form>
        </div>
    </section>
    <?php table_panel('File Backup', ['File', 'Created at', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['file_name']) ?></td><td><?= e($row['created_at']) ?></td><td><?= e($row['status']) ?></td><td><a class="button small" href="<?= e(route_url('backup-download', ['id' => (int)$row['id']])) ?>">Download</a></td>
    <?php }); render_footer();
}

function page_backup_download(): void
{
    require_role(['admin']);
    $row = fetch_one('SELECT * FROM backups WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    if (!$row) {
        http_response_code(404);
        exit('Backup tidak ditemukan.');
    }
    $path = app_file_path((string)$row['file_path'], ['storage/backups']);
    if (!is_file($path)) {
        http_response_code(404);
        exit('File backup tidak ada.');
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

function page_update_data(): void
{
    require_role(['admin']);
    $logs = fetch_all("SELECT * FROM dapodik_sync_logs WHERE mode = 'offline-import' ORDER BY id DESC LIMIT 25");
    render_header('Import Data Dapodik');
    ?>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title"><i data-lucide="shield-check"></i> Import File Dapodik</div>
        </div>
        <div class="form-info">
            <p style="margin:0 0 var(--space-2)"><strong>Import ini hanya membaca file yang Anda unggah.</strong> Aplikasi tidak lagi terhubung langsung ke Dapodik, tidak menyimpan token Dapodik, dan tidak menyediakan helper/bridge pihak ketiga.</p>
            <p class="hint" style="margin:0">Gunakan file JSON berformat data Dapodik. Untuk siswa, menu <strong>Import Data</strong> juga menerima export <code>daftar_pd-*.xlsx</code> dari Dapodik.</p>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title"><i data-lucide="file-json"></i> Upload atau Tempel JSON</div>
        </div>
        <form method="post" enctype="multipart/form-data" class="grid three form-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_dapodik_offline">
            <label><span class="field-icon"><i data-lucide="list"></i> Jenis Data</span>
                <select name="data_type"><?= options(dapodik_data_types(true), 'all') ?></select>
            </label>
            <label><span class="field-icon"><i data-lucide="file"></i> File JSON</span>
                <?php render_file_upload_inline('json_file', '.json,application/json', false) ?>
            </label>
            <label class="wide"><span class="field-icon"><i data-lucide="clipboard"></i> Tempel JSON</span>
                <textarea name="json_payload" rows="5" placeholder='{"type":"all","items":[{"type":"guru","data":[...]}]}'></textarea>
            </label>
            <div class="actions wide form-actions-top"><button type="submit" class="button primary"><i data-lucide="upload"></i> Import Data</button></div>
        </form>
    </section>
    <?php table_panel('Riwayat Import', ['Waktu', 'Jenis', 'Status', 'Pesan'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['data_type']) ?></td><td><?= e($row['status']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 140, '...')) ?></td>
    <?php }); render_footer();
}
