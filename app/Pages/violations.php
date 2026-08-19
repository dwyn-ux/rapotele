<?php declare(strict_types=1);

function page_download_template_violation_rules(): void
{
    require_role(['admin']);
    $csv = "kode,kategori,deskripsi,poin,aktif\n"
        . "P-01,Kehadiran,Terlambat tanpa surat keterangan,5,1\n"
        . "P-02,Kehadiran,Tanpa keterangan (alpha),10,1\n"
        . "P-03,Umum,Tidak memakai seragam sekolah,3,1\n"
        . "P-04,Umum,Membawa barang terlarang di sekolah,8,1\n"
        . "P-05,Akademik,Kecurangan saat ujian,15,1\n";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_pasal_pelanggaran.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

function page_violation_rules(): void
{
    require_role(['admin']);
    $edit = edit_row('violation_rules') ?: [];
    $rows = fetch_all('SELECT * FROM violation_rules ORDER BY category, code');
    $thresholds = table_exists('sp_thresholds') ? fetch_all('SELECT * FROM sp_thresholds ORDER BY level') : [];

    render_header('Aturan Pelanggaran & SP');

    input_panel_start($edit ? 'Edit Pasal' : 'Tambah Pasal Pelanggaran', 'Tambah Pasal', (bool)$edit || isset($_GET['add']));
    ?>
    <form method="post" class="grid four">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_violation_rule"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <label>Kode Pasal <input type="text" name="code" required value="<?= e($edit['code'] ?? '') ?>" placeholder="Cth: P-01"></label>
        <label>Kategori <input type="text" name="category" value="<?= e($edit['category'] ?? 'Umum') ?>" placeholder="Umum / Kehadiran / Akademik"></label>
        <label>Poin <input type="number" name="points" required min="0" value="<?= e($edit['points'] ?? 0) ?>"></label>
        <label class="check"><input type="checkbox" name="active" <?= isset($edit['active']) ? checked((int)$edit['active']) : 'checked' ?>> Aktif</label>
        <label class="wide span-4">Deskripsi Pelanggaran <input type="text" name="description" required value="<?= e($edit['description'] ?? '') ?>" placeholder="Deskripsi lengkap pelanggaran"></label>
        <div class="wide actions span-4"><button class="button primary">Simpan Pasal</button><a class="button" href="<?= e(route_url('violation-rules')) ?>">Reset</a></div>
    </form>
    <?php input_panel_end(); ?>

    <section class="panel">
        <?php panel_title('Import Bulk CSV', ''); ?>
        <p style="color:var(--muted,#64748b);font-size:.875rem;margin-bottom:1rem;">
            Format CSV: <code>kode, kategori, deskripsi, poin, aktif(1/0)</code> — Baris pertama header akan dilewati otomatis jika ada tulisan <code>kode</code>.
        </p>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
            <a href="<?= e(route_url('download-template-violation-rules')) ?>" class="button small" style="white-space:nowrap;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Template CSV
            </a>
        </div>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_violation_rules">
            <?php render_file_upload_inline('csv_file', '.csv', true) ?>
            <button type="submit" class="button primary">Import CSV</button>
        </form>
    </section>

    <?php table_panel('Daftar Pasal Pelanggaran', ['Kode', 'Kategori', 'Deskripsi', 'Poin', 'Status', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['code']) ?></td>
        <td><?= e($row['category']) ?></td>
        <td><?= e($row['description']) ?></td>
        <td><strong><?= e($row['points']) ?></strong></td>
        <td><?= status_badge((int)$row['active']) ?></td>
        <td><?= row_actions('violation-rules', (int)$row['id'], 'delete_violation_rule') ?></td>
    <?php }); ?>

    <section class="panel">
        <?php panel_title('Threshold Surat Peringatan (SP)', ''); ?>
        <p style="color:var(--muted,#64748b);font-size:.875rem;margin-bottom:1rem;">
            Atur batas poin bersih (setelah reward) yang memicu surat peringatan. Siswa yang mencapai batas poin akan otomatis masuk level SP.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_sp_threshold">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Level</th><th>Label SP</th><th>Min Poin Bersih</th></tr></thead>
                    <tbody>
                    <?php foreach ($thresholds as $i => $t): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="levels[]" value="<?= e($t['level']) ?>">
                                <?= e($t['level']) ?>
                            </td>
                            <td><input type="text" name="labels[]" value="<?= e($t['label']) ?>" style="width:100px;"></td>
                            <td><input type="number" name="min_points[]" value="<?= e($t['min_points']) ?>" min="0" style="width:100px;"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="actions" style="margin-top:.75rem;"><button class="button primary">Simpan Threshold</button></div>
        </form>
    </section>
    <?php
    render_footer();
}

function render_sp_badge(?array $sp): string
{
    if (!$sp) {
        return '<span style="padding:.2rem .5rem;border-radius:4px;background:#dcfce7;color:#166534;font-size:.8rem;font-weight:600;">Baik</span>';
    }
    $colors = [
        1 => ['bg' => '#fef9c3', 'text' => '#854d0e'],
        2 => ['bg' => '#ffedd5', 'text' => '#9a3412'],
        3 => ['bg' => '#fee2e2', 'text' => '#991b1b'],
    ];
    $c = $colors[(int)$sp['level']] ?? ['bg' => '#fee2e2', 'text' => '#991b1b'];
    return '<span style="padding:.2rem .5rem;border-radius:4px;background:' . $c['bg'] . ';color:' . $c['text'] . ';font-size:.8rem;font-weight:700;">' . e($sp['label']) . '</span>';
}

function page_violations(): void
{
    require_bk();
    $edit = edit_row('student_violations') ?: [];
    $editReward = isset($_GET['edit_reward']) ? (fetch_one('SELECT * FROM student_rewards WHERE id = ?', [(int)$_GET['edit_reward']]) ?: []) : [];
    $students = array_column_map(fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY name'), 'id', 'name');
    $rules = table_exists('violation_rules') ? fetch_all('SELECT * FROM violation_rules WHERE active = 1 ORDER BY category, code') : [];

    $rulesByCategory = [];
    foreach ($rules as $r) {
        $rulesByCategory[(string)$r['category']][] = $r;
    }

    $thresholds = table_exists('sp_thresholds') ? fetch_all('SELECT * FROM sp_thresholds ORDER BY level') : [];

    $rows = fetch_all(
        'SELECT v.*, s.name AS student_name, s.nis, c.name AS class_name
         FROM student_violations v
         JOIN students s ON s.id = v.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         ORDER BY v.date DESC, v.id DESC'
    );

    $rewardRows = fetch_all(
        'SELECT r.*, s.name AS student_name, c.name AS class_name
         FROM student_rewards r
         JOIN students s ON s.id = r.student_id
         LEFT JOIN classes c ON c.id = s.class_id
         ORDER BY r.date DESC, r.id DESC'
    );

    render_header('Pelanggaran Siswa');
    ?>

    <?php if ($rules): ?>
    <script>
    const violationRules = <?= json_encode(array_values($rules)) ?>;
    function onRuleChange(sel) {
        if (!sel.value) return;
        const rule = violationRules.find(r => r.id == sel.value);
        if (!rule) return;
        const form = sel.closest('form');
        const typeInput = form.querySelector('[name="type"]');
        const pointsInput = form.querySelector('[name="points"]');
        if (typeInput && !typeInput.value) typeInput.value = rule.description;
        if (typeInput && typeInput.value === '') typeInput.value = rule.description;
        if (pointsInput) pointsInput.value = rule.points;
    }
    </script>
    <?php endif; ?>

    <?php input_panel_start($edit ? 'Edit Pelanggaran' : 'Input Pelanggaran', 'Catat Pelanggaran', (bool)$edit || isset($_GET['add'])); ?>
    <form method="post" class="grid four">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_violation"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <label>Siswa <select name="student_id" required><?= options($students, $edit['student_id'] ?? '') ?></select></label>
        <label>Tanggal <input type="date" name="date" required value="<?= e($edit['date'] ?? date('Y-m-d')) ?>"></label>
        <?php if ($rules): ?>
        <label>Pasal Pelanggaran
            <select name="rule_id" onchange="onRuleChange(this)">
                <option value="">— Pilih Pasal —</option>
                <?php foreach ($rulesByCategory as $cat => $catRules): ?>
                    <optgroup label="<?= e($cat) ?>">
                        <?php foreach ($catRules as $r): ?>
                            <option value="<?= e($r['id']) ?>" data-points="<?= e($r['points']) ?>" <?= ($edit['rule_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                                [<?= e($r['code']) ?>] <?= e($r['description']) ?> (<?= e($r['points']) ?> poin)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>Jenis/Keterangan <input type="text" name="type" value="<?= e($edit['type'] ?? '') ?>" placeholder="Otomatis dari pasal atau isi manual"></label>
        <label>Poin <input type="number" name="points" value="<?= e($edit['points'] ?? 0) ?>"></label>
        <label class="wide">Deskripsi <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
        <label class="wide">Tindak Lanjut <textarea name="action_taken"><?= e($edit['action_taken'] ?? '') ?></textarea></label>
        <label class="checkbox"><input type="checkbox" name="queue_whatsapp" value="1"> Tambahkan pemberitahuan WhatsApp ke antrian</label>
        <div class="wide actions"><button class="button primary">Simpan Pelanggaran</button><a class="button" href="<?= e(route_url('violations')) ?>">Reset</a></div>
    </form>
    <?php input_panel_end(); ?>

    <?php table_panel('Daftar Pelanggaran', ['Tanggal', 'Siswa', 'Kelas', 'Jenis', 'Poin', 'Tindak Lanjut', 'Aksi'], $rows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['type']) ?></td>
        <td><strong><?= e($row['points']) ?></strong></td>
        <td><?= e(mb_strimwidth((string)$row['action_taken'], 0, 80, '...')) ?></td>
        <td>
            <div class="row-actions">
                <?= row_actions('violations', (int)$row['id'], 'delete_violation') ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="queue_whatsapp_violation">
                    <input type="hidden" name="violation_id" value="<?= e($row['id']) ?>">
                    <input type="hidden" name="return_page" value="violations">
                    <button class="button small success">Kirim WA</button>
                </form>
            </div>
        </td>
    <?php }); ?>

    <section class="panel">
        <?php panel_title('Rekap Poin Siswa & Status SP', ''); ?>
        <?php
        $studentPoints = fetch_all(
            'SELECT s.id, s.name, c.name AS class_name,
                    COALESCE((SELECT SUM(v2.points) FROM student_violations v2 WHERE v2.student_id = s.id), 0) AS gross_points
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.active = 1
             ORDER BY gross_points DESC, s.name'
        );
        ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Poin Kotor</th><th>Diskon Reward</th><th>Poin Bersih</th><th>Status SP</th><th>Surat Peringatan</th></tr></thead>
                <tbody>
                <?php foreach ($studentPoints as $sp):
                    $net = violation_net_points((int)$sp['id']);
                    $spLevel = violation_sp_level($net['net_points']);
                    if ($net['gross_points'] === 0 && !$spLevel) continue;
                ?>
                    <tr>
                        <td><?= e($sp['name']) ?></td>
                        <td><?= e($sp['class_name']) ?></td>
                        <td><?= e($net['gross_points']) ?></td>
                        <td><?= $net['discount_pct'] > 0 ? '-' . e($net['discount_pct']) . '%' : '—' ?></td>
                        <td><strong><?= e($net['net_points']) ?></strong></td>
                        <td><?= render_sp_badge($spLevel) ?></td>
                        <td>
                            <?php if ($spLevel): ?>
                                <a class="button small warning" href="<?= e(route_url('cetak-sp', ['student_id' => $sp['id']])) ?>" target="_blank">
                                    Cetak <?= e($spLevel['label']) ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!array_filter($studentPoints, fn($s) => $s['gross_points'] > 0)): ?>
                    <tr><td colspan="7" class="empty">Belum ada pelanggaran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php
    input_panel_start($editReward ? 'Edit Reward' : 'Tambah Reward Siswa', 'Tambah Reward', (bool)$editReward || isset($_GET['add_reward']));
    ?>
    <form method="post" class="grid four">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_reward"><input type="hidden" name="id" value="<?= e($editReward['id'] ?? 0) ?>">
        <label>Siswa <select name="student_id" required><?= options($students, $editReward['student_id'] ?? '') ?></select></label>
        <label>Tanggal <input type="date" name="date" required value="<?= e($editReward['date'] ?? date('Y-m-d')) ?>"></label>
        <label>Potongan Poin (%) <input type="number" name="discount_percent" required min="1" max="100" value="<?= e($editReward['discount_percent'] ?? 10) ?>"></label>
        <label class="wide">Judul Prestasi <input type="text" name="title" required value="<?= e($editReward['title'] ?? '') ?>" placeholder="Contoh: Juara 1 Olimpiade Matematika"></label>
        <label class="wide">Keterangan <textarea name="description"><?= e($editReward['description'] ?? '') ?></textarea></label>
        <div class="wide actions"><button class="button primary">Simpan Reward</button><a class="button" href="<?= e(route_url('violations')) ?>">Reset</a></div>
    </form>
    <?php input_panel_end(); ?>

    <?php table_panel('Daftar Reward Siswa', ['Tanggal', 'Siswa', 'Kelas', 'Prestasi', 'Potongan Poin', 'Aksi'], $rewardRows, function ($row) { ?>
        <td><?= e($row['date']) ?></td>
        <td><?= e($row['student_name']) ?></td>
        <td><?= e($row['class_name']) ?></td>
        <td><?= e($row['title']) ?></td>
        <td><strong><?= e($row['discount_percent']) ?>%</strong></td>
        <td>
            <div class="row-actions">
                <a class="button small" href="<?= e(route_url('violations', ['edit_reward' => $row['id']])) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Hapus reward ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_reward">
                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                    <button class="button small danger">Hapus</button>
                </form>
            </div>
        </td>
    <?php }); ?>

    <?php
    render_footer();
}
