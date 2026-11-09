<?php declare(strict_types=1);

function page_telegram(): void
{
    require_role(['admin']);

    // Handle VAPID generate action
    if (($_POST['action'] ?? '') === 'generate_vapid' && csrf_valid()) {
        require_once dirname(__DIR__) . '/Services/webpush.php';
        try {
            webpush_generate_vapid_keys();
            flash('success', 'VAPID keys berhasil di-generate. Guru sekarang bisa aktifkan notifikasi browser.');
        } catch (Throwable $e) {
            flash('error', 'Gagal generate VAPID keys: ' . $e->getMessage());
        }
        redirect_to('telegram');
    }

    $webhook = app_url('telegram_webhook.php');
    $reminderUrl = schedule_reminder_url();
    $hasReminderSecret = schedule_reminder_secret() !== '';
    $reminderMinutes = schedule_reminder_minutes_before();
    $hasToken = config('telegram.bot_token', '') !== '';
    $users = fetch_all('SELECT u.*, t.name AS teacher_name FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id ORDER BY u.role, u.name');
    $logs = fetch_all('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT 30');

    require_once dirname(__DIR__) . '/Services/webpush.php';
    $vapidConfigured = webpush_is_configured();
    $vapidPublicKey  = $vapidConfigured ? webpush_vapid_public_key() : '';
    $pushSubCount    = table_exists('push_subscriptions')
        ? (int)(fetch_one('SELECT COUNT(*) AS n FROM push_subscriptions')['n'] ?? 0)
        : 0;

    render_header('Bot Telegram');
    ?>
    <section class="panel">
        <h3>Status Bot</h3>
        <div class="grid two">
            <div>
                <p>Token bot: <?= $hasToken ? '<span class="badge ok">Terisi</span>' : '<span class="badge off">Belum diisi</span>' ?></p>
                <p>Webhook URL:</p>
                <code class="block"><?= e($webhook) ?></code>
            </div>
            <div>
                <p>Set webhook dari browser setelah token di config diisi:</p>
                <code class="block">https://api.telegram.org/botTOKEN_ANDA/setWebhook?url=<?= e(urlencode($webhook)) ?></code>
                <p class="hint">Jika memakai webhook_secret, set juga header secret melalui dashboard Bot API atau curl.</p>
            </div>
        </div>
    </section>
    <section class="panel">
        <h3>Reminder Jadwal Guru</h3>
        <div class="grid two">
            <div>
                <p>Reminder: <?= e($reminderMinutes) ?> menit sebelum jam pelajaran.</p>
                <p>Secret cron: <?= $hasReminderSecret ? '<span class="badge ok">Terisi</span>' : '<span class="badge off">Belum diisi</span>' ?></p>
                <p class="hint">Jalankan cron tiap 1 menit atau 5 menit agar guru mendapat notifikasi sebelum kelas dimulai.</p>
            </div>
            <div>
                <p>URL cron reminder:</p>
                <code class="block"><?= e($reminderUrl) ?></code>
                <p class="hint">Atau jalankan via CLI: <code>php tools/schedule_reminders.php</code></p>
            </div>
        </div>
    </section>

    <!-- ── Web Push / PWA Setup ──────────────────────────────────────────── -->
    <section class="panel">
        <h3>⚡ Web Push Notification (PWA)</h3>
        <div class="grid two">
            <div>
                <p>Status VAPID:
                    <?= $vapidConfigured
                        ? '<span class="badge ok">✅ Sudah di-generate</span>'
                        : '<span class="badge off">❌ Belum di-generate</span>' ?>
                </p>
                <p>Subscription aktif: <strong><?= $pushSubCount ?> perangkat</strong></p>
                <?php if ($vapidConfigured): ?>
                    <p style="font-size:.82rem;color:#64748b;margin-top:.5rem">
                        VAPID Public Key (untuk info):<br>
                        <code style="font-size:.75rem;word-break:break-all"><?= e(substr($vapidPublicKey, 0, 40)) ?>...</code>
                    </p>
                <?php endif; ?>
                <p class="hint" style="margin-top:.75rem">
                    Cron <code>schedule_reminders.php</code> yang sama juga mengirim Web Push otomatis ke
                    browser guru — tidak perlu cron terpisah.
                </p>
            </div>
            <div>
                <?php if (!$vapidConfigured): ?>
                    <p>Generate VAPID keys sekali untuk mengaktifkan Web Push di semua browser guru.</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="generate_vapid">
                        <button class="button primary" type="submit">🔑 Generate VAPID Keys</button>
                    </form>
                    <p class="hint" style="margin-top:.5rem">Memerlukan ekstensi PHP <code>openssl</code>.</p>
                <?php else: ?>
                    <p>✅ Web Push sudah aktif. Guru bisa aktifkan notifikasi dari halaman <strong>Profil Pengguna</strong>.</p>
                    <p style="margin-top:.75rem">Kirim test push ke semua perangkat yang terdaftar:</p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="generate_vapid">
                        <button class="button" type="submit" onclick="return confirm('Generate ulang VAPID keys? Semua subscription lama akan tidak valid dan perlu diaktifkan ulang oleh guru.')">
                            🔄 Regenerate Keys
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>Daftar Guru via Telegram</h3>
        <div class="grid two">
            <div>
                <p>Guru bisa daftar langsung dari Telegram. Chat ID otomatis disimpan ke akun guru yang dibuat.</p>
                <code class="block">/daftar Fahmi Dwi Payana, S.H Fiqih</code>
            </div>
            <div>
                <p>Untuk mapel banyak kata atau langsung buat pembelajaran kelas, pakai pemisah garis.</p>
                <code class="block">/daftar Fahmi Dwi Payana, S.H | Bahasa Indonesia | 1A</code>
            </div>
        </div>
    </section>
    <?php table_panel('Akun dan Telegram Chat ID', ['Username', 'Nama', 'Role', 'Guru', 'Chat ID'], $users, function ($row) { ?>
        <td><?= e($row['username']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= e($row['teacher_name']) ?></td><td><?= e($row['telegram_chat_id']) ?></td>
    <?php }); ?>
    <?php table_panel('Log Telegram', ['Waktu', 'Chat ID', 'Pesan', 'Respon'], $logs, function ($row) { ?>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['chat_id']) ?></td><td><?= e(mb_strimwidth((string)$row['message'], 0, 70, '...')) ?></td><td><?= e(mb_strimwidth((string)$row['response'], 0, 90, '...')) ?></td>
    <?php }); ?>
    <?php render_footer();
}
