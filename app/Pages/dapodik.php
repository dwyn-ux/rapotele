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
            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore akan mengganti data saat ini. Lanjut?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="restore_backup">
                <label>File Backup JSON <input type="file" name="userfile" accept=".json,application/json" required></label>
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
    $logs = fetch_all('SELECT * FROM dapodik_sync_logs ORDER BY id DESC LIMIT 25');
    $reportUrl = app_url('');
    render_header('Update Data E Rapor');
    page_dapodik_settings_form('update-data');
    ?>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="info"></i>
                Mode Update dari Dapodik
            </div>
        </div>
        <div style="padding:var(--space-5) var(--space-6)">
            <p style="margin:0 0 var(--space-2)">Isi Link Dapodik Lokal, Token / Key Webservice, dan NPSN di atas, lalu gunakan tombol <strong>Simpan & Unduh</strong> agar konfigurasi server tersimpan sebelum helper portable dibuat.</p>
            <p class="hint" style="margin:0">Link Web Raport: <code style="background:var(--surface-secondary);padding:2px 8px;border-radius:var(--radius-sm);font-size:13px"><?= e($reportUrl) ?></code></p>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="upload"></i>
                Import JSON Offline dari Helper
            </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="grid three" style="padding:var(--space-6)">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_dapodik_offline">
            <label>
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="list" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    Jenis Data
                </span>
                <select name="data_type"><?= options(dapodik_data_types(true), 'all') ?></select>
            </label>
            <label>
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="file-json" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    File JSON
                </span>
                <input type="file" name="json_file" accept=".json,application/json" style="height:auto;padding:var(--space-3)">
            </label>
            <label class="wide">
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="clipboard" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    Tempel JSON
                </span>
                <textarea name="json_payload" rows="4" placeholder='{"type":"all","items":[{"type":"guru","data":[...]}]}'></textarea>
            </label>
            <div class="actions wide" style="margin-top:var(--space-2)">
                <button type="submit" class="button primary">
                    <i data-lucide="upload"></i> Import Offline
                </button>
            </div>
        </form>
    </section>
    <?php table_panel('Log Sinkronisasi', ['Waktu', 'Mode', 'Jenis', 'Status', 'Pesan'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['mode']) ?></td><td><?= e($row['data_type']) ?></td><td><?= e($row['status']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 140, '...')) ?></td>
    <?php }); render_footer();
}

function page_kirim_data_dapodik(): void
{
    require_role(['admin']);
    $logs = fetch_all("SELECT * FROM dapodik_sync_logs WHERE mode = 'push' ORDER BY id DESC LIMIT 25");
    render_header('Kirim Data ke Dapodik');
    page_dapodik_settings_form('kirim-data-dapodik');
    ?>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="send"></i>
                Upload Nilai ke Dapodik
            </div>
        </div>
        <div class="grid two" style="padding:var(--space-6)">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_dapodik">
                <input type="hidden" name="kind" value="matev">
                <button type="submit" class="button primary" style="width:100%">
                    <i data-lucide="send"></i> Kirim Data Matev
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_dapodik">
                <input type="hidden" name="kind" value="nilai">
                <button type="submit" class="button primary" style="width:100%">
                    <i data-lucide="send"></i> Kirim Data Nilai
                </button>
            </form>
        </div>
        <p class="hint" style="padding:0 var(--space-6) var(--space-6)">Endpoint Dapodik resmi bisa berbeda antar instalasi. Aplikasi menyiapkan payload dan mencatat respons HTTP agar mudah disesuaikan.</p>
    </section>
    <?php table_panel('Log Kirim Dapodik', ['Waktu', 'Jenis', 'Endpoint', 'Status', 'Pesan'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['data_type']) ?></td><td><?= e($row['endpoint']) ?></td><td><?= e($row['status']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 130, '...')) ?></td>
    <?php }); render_footer();
}

function page_dapodik_settings_form(string $returnPage): void
{
    $showDownloadButtons = $returnPage === 'update-data';
    ?>
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <i data-lucide="server"></i>
                Konfigurasi Web Service Dapodik
            </div>
        </div>
        <form method="post" class="grid three" style="padding:var(--space-6)">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_dapodik_settings">
            <input type="hidden" name="return_page" value="<?= e($returnPage) ?>">
            <label>
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="link" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    Link Dapodik Lokal
                </span>
                <input type="url" name="url" placeholder="http://127.0.0.1:5774" value="<?= e(get_app_setting('dapodik_url', '')) ?>">
            </label>
            <label>
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="key" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    Token / Key Webservice
                </span>
                <input type="password" name="token" placeholder="Masukkan token webservice" value="<?= e(get_app_setting('dapodik_token', '')) ?>">
            </label>
            <label>
                <span style="display:flex;align-items:center;gap:6px">
                    <i data-lucide="hash" style="width:14px;height:14px;color:var(--text-muted)"></i>
                    NPSN
                </span>
                <input type="text" name="npsn" placeholder="Masukkan NPSN sekolah" value="<?= e(get_app_setting('dapodik_npsn', '')) ?>">
            </label>
            <div class="actions wide" style="margin-top:var(--space-2)">
                <button type="submit" class="button primary">
                    <i data-lucide="save"></i> Simpan
                </button>
                <?php if ($showDownloadButtons): ?>
                    <button type="submit" class="button success" name="download_after_save" value="portable">
                        <i data-lucide="download"></i> Simpan & Unduh Portable ZIP
                    </button>
                    <button type="submit" class="button" name="download_after_save" value="config">
                        <i data-lucide="file-down"></i> Simpan & Unduh Config Portable
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php
}
