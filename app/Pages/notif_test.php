<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Services/fcm.php';

/**
 * Halaman admin untuk mengetes notifikasi aplikasi mobile (FCM) ke satu user.
 */
function page_notif_test(): void
{
    require_role(['admin']);
    render_header('Tes Notifikasi Aplikasi');

    $configured = fcm_is_configured();
    $users = [];
    if (table_exists('fcm_tokens')) {
        $users = fetch_all(
            'SELECT u.*, (SELECT COUNT(*) FROM fcm_tokens ft WHERE ft.user_id = u.id) AS devices
             FROM users u
             WHERE u.active = 1
             ORDER BY devices DESC, u.id ASC'
        );
    }
    ?>
    <section class="panel">
        <?php panel_title('Status FCM'); ?>
        <p>
            <?php if ($configured): ?>
                <span class="badge ok">Siap</span> Service account terbaca dan tabel token tersedia.
            <?php else: ?>
                <span class="badge off">Belum siap</span>
                Pastikan file <code>storage/fcm-service-account.json</code> ada (nama file persis, tanpa ekstensi ganda), ekstensi PHP <code>openssl</code> dan <code>curl</code> aktif, dan tabel <code>fcm_tokens</code> sudah dibuat.
            <?php endif; ?>
        </p>
        <p style="font-size:0.85rem;color:var(--text-muted);">User hanya bisa menerima notifikasi jika kolom Perangkat lebih dari 0, yaitu aplikasi mobile sudah login dan mengirim token ke server.</p>
    </section>

    <section class="panel">
        <?php panel_title('Kirim Notifikasi Tes ke Satu User'); ?>
        <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Username</th><th>Nama</th><th>Role</th><th>Perangkat</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['id']) ?></td>
                    <td><?= e($u['username'] ?? '-') ?></td>
                    <td><?= e($u['name'] ?? ($u['full_name'] ?? '-')) ?></td>
                    <td><?= e($u['role'] ?? '-') ?></td>
                    <td><?= e($u['devices']) ?></td>
                    <td>
                        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send_test_notification">
                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                            <input type="text" name="title" value="Tes Notifikasi" maxlength="80" style="min-width:140px;">
                            <input type="text" name="body" value="Ini notifikasi tes dari admin." maxlength="200" style="min-width:220px;">
                            <button class="button small primary" <?= ((int)$u['devices'] === 0) ? 'disabled title="Belum ada perangkat terdaftar"' : '' ?>>Kirim Tes</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="6">Belum ada data user, atau tabel fcm_tokens belum dibuat.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php
    render_footer();
}

function action_send_test_notification(): void
{
    require_role(['admin']);

    $userId = (int)($_POST['user_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? '')) ?: 'Tes Notifikasi';
    $body = trim((string)($_POST['body'] ?? '')) ?: 'Ini notifikasi tes dari admin.';

    if (!fetch_one('SELECT id FROM users WHERE id = ? AND active = 1', [$userId])) {
        flash('danger', 'User tidak ditemukan atau tidak aktif.');
        redirect_to('notif-test');
    }
    if (!fcm_is_configured()) {
        flash('danger', 'FCM belum siap. Cek file storage/fcm-service-account.json, ekstensi openssl dan curl, serta tabel fcm_tokens.');
        redirect_to('notif-test');
    }

    $rows = fetch_all('SELECT * FROM fcm_tokens WHERE user_id = ?', [$userId]);
    if (!$rows) {
        flash('warning', 'User ini belum punya perangkat terdaftar. Minta user login di aplikasi mobile dulu.');
        redirect_to('notif-test');
    }

    $sent = 0;
    $errors = [];
    foreach ($rows as $row) {
        $result = fcm_send($row, $title, $body, '/', 'info', 'tes-' . time());
        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $errors[] = 'HTTP ' . (int)($result['http_code'] ?? 0) . ': ' . mb_strimwidth((string)($result['error'] ?? ''), 0, 160, '...');
        }
    }

    if ($errors) {
        flash($sent > 0 ? 'warning' : 'danger', 'Terkirim ' . $sent . ' dari ' . count($rows) . ' perangkat. Error: ' . implode(' | ', array_unique($errors)));
    } else {
        flash('success', 'Notifikasi tes terkirim ke ' . $sent . ' perangkat.');
    }
    redirect_to('notif-test');
}
