<?php declare(strict_types=1);

function page_telegram(): void
{
    require_role(['admin']);
    $webhook = app_url('telegram_webhook.php');
    $reminderUrl = schedule_reminder_url();
    $hasReminderSecret = schedule_reminder_secret() !== '';
    $reminderMinutes = schedule_reminder_minutes_before();
    $hasToken = config('telegram.bot_token', '') !== '';
    $users = fetch_all('SELECT u.*, t.name AS teacher_name FROM users u LEFT JOIN teachers t ON t.id = u.teacher_id ORDER BY u.role, u.name');
    $logs = fetch_all('SELECT * FROM telegram_logs ORDER BY id DESC LIMIT 30');
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
