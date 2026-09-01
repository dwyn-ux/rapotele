<?php declare(strict_types=1);

function page_install(): void
{
    if (app_installed()) {
        render_public_header('Instalasi');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi sudah terpasang</h1>
            <p>Installer dikunci setelah database dan akun awal tersedia.</p>
        </div>
        <a class="button primary full" href="<?= e(route_url(current_user() ? 'dashboard' : 'login')) ?>">Lanjutkan</a>
        <?php
        render_public_footer();
        return;
    }

    if (is_post()) {
        verify_csrf();
        install_database();
        flash('success', 'Database siap. Login awal: administrator / administrator. Segera ganti password setelah masuk.');
        redirect_to('login');
    }

    render_public_header('Instalasi');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1>Instalasi E-Raport KumerBot</h1>
        <p>Aplikasi akan membuat tabel dan data contoh pada database yang terhubung di config/config.php.</p>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <button class="button primary full" type="submit">Jalankan Instalasi</button>
    </form>
    <?php
    render_public_footer();
}

function login_next_page(): string
{
    $next = trim((string)($_POST['next'] ?? $_GET['next'] ?? ''));
    if ($next !== '' && preg_match('/^[a-z0-9-]+$/', $next) && array_key_exists($next, app_routes()['private'] ?? [])) {
        return $next;
    }

    return 'dashboard';
}

function login_next_params(string $nextPage): array
{
    return $nextPage === 'dashboard' ? [] : ['next' => $nextPage];
}

function page_login(): void
{
    $nextPage = login_next_page();
    if (current_user()) {
        redirect_to($nextPage);
    }

    if (is_post()) {
        verify_csrf();
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rateIdentity = client_ip() . '|' . strtolower($username);
        $maxAttempts = (int)config('security.login_max_attempts', 5);
        $windowSeconds = (int)config('security.login_window_seconds', 900);

        if (rate_limited('login', $rateIdentity, $maxAttempts, $windowSeconds)) {
            flash('danger', 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.');
            redirect_to('login', login_next_params($nextPage));
        }

        $user = fetch_one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);

        if ($user && password_verify($password, (string)$user['password_hash'])) {
            rate_limit_clear('login', $rateIdentity);
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);
            $_SESSION['user_id'] = (int)$user['id'];
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                execute_sql('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now_string(), (int)$user['id']]);
            }
            flash(is_weak_password($password) ? 'danger' : 'success', is_weak_password($password)
                ? 'Anda masih memakai password default/lemah. Segera ganti dari menu Profil.'
                : 'Selamat datang, ' . $user['name'] . '.');
            redirect_to($nextPage);
        }

        rate_limit_hit('login', $rateIdentity, $windowSeconds);
        flash('danger', 'Username atau password salah.');
    }

    render_public_header('Login');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1><?= e(config('app_name')) ?></h1>
        <p>Masuk untuk mengelola e-rapor, absensi, jurnal, dan bot Telegram.</p>
    </div>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <?php if ($nextPage !== 'dashboard'): ?>
            <input type="hidden" name="next" value="<?= e($nextPage) ?>">
        <?php endif; ?>
        <label>Username
            <input type="text" name="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="button primary full" type="submit">Login</button>
        <p style="text-align:center;margin-top:0.5rem"><a href="<?= e(route_url('lupa-password')) ?>">Lupa Password?</a></p>
    </form>
    <?php
    render_public_footer();
}

function page_telegram_register(): void
{
    if (!app_installed()) {
        render_public_header('Daftar Guru Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi belum siap</h1>
            <p>Database belum diinstall. Jalankan instalasi dulu sebelum membuka pendaftaran guru.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    $token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
    $registration = $token !== '' ? telegram_registration_token_row($token) : null;
    $error = '';
    $values = $_POST;

    if (!$registration) {
        render_public_header('Daftar Guru Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Link Tidak Berlaku</h1>
            <p>Ketik <strong>/daftar</strong> lagi di Telegram untuk mendapatkan link baru.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }
    if (!is_post() && !empty($registration['from_username'])) {
        $values['username'] = telegram_slug((string)$registration['from_username'], 'guru');
    }

    if (is_post()) {
        verify_csrf();
        try {
            $result = telegram_complete_registration($registration, $_POST);
            render_public_header('Daftar Guru Telegram');
            ?>
            <div class="center">
                <?= render_app_mark(true) ?>
                <h1>Pendaftaran Selesai</h1>
                <p><?= e($result['name']) ?> sudah dibuat sebagai guru dan pengguna.</p>
            </div>
            <div class="alert success"><?= e($result['assignment_message']) ?></div>
            <div class="stack">
                <label>Username Web
                    <input readonly value="<?= e($result['username']) ?>">
                </label>
                <label>Login Bot Telegram
                    <input readonly value="/login password-yang-dibuat">
                </label>
                <a class="button primary full" href="<?= e(route_url('login')) ?>">Buka Login Web</a>
            </div>
            <?php
            render_public_footer();
            return;
        } catch (Throwable $exception) {
            $error = friendly_error($exception);
            $registration = telegram_registration_token_row($token);
            if (!$registration) {
                render_public_header('Daftar Guru Telegram');
                ?>
                <div class="center">
                    <?= render_app_mark(true) ?>
                    <h1>Link Tidak Berlaku</h1>
                    <p>Ketik <strong>/daftar</strong> lagi di Telegram untuk mendapatkan link baru.</p>
                </div>
                <?php
                render_public_footer();
                return;
            }
        }
    }

    $subjects = array_column_map(
        fetch_all('SELECT id, name, level FROM subjects WHERE active = 1 ORDER BY group_name, name'),
        'id',
        'name'
    );
    $subjectLabels = [];
    foreach ($subjects as $sid => $sname) {
        $lv = fetch_one('SELECT level FROM subjects WHERE id = ?', [(int)$sid]);
        $lvs = trim((string)($lv['level'] ?? ''));
        $subjectLabels[$sid] = $sname . ($lvs !== '' ? ' [' . $lvs . ']' : '');
    }
    $classes = array_column_map(
        fetch_all('SELECT id, name FROM classes WHERE active = 1 ORDER BY grade, name'),
        'id',
        'name'
    );
    $selectedClassIds = array_map('strval', (array)($values['class_ids'] ?? []));

    render_public_header('Daftar Guru Telegram');
    ?>
    <div class="center">
        <?= render_app_mark(true) ?>
        <h1>Daftar Guru</h1>
        <p>Isi data guru dan akun pengguna untuk menghubungkan Telegram dengan e-rapor.</p>
    </div>
    <?php if ($error !== ''): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nama Lengkap
            <input type="text" name="name" required autofocus value="<?= e($values['name'] ?? '') ?>">
        </label>
        <label>Username Web
            <input type="text" name="username" required pattern="[A-Za-z0-9_.-]{3,64}" value="<?= e($values['username'] ?? '') ?>">
        </label>
        <label>Password
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Ulangi Password
            <input type="password" name="password_confirm" required minlength="8">
        </label>
        <label>Email
            <input type="email" name="email" value="<?= e($values['email'] ?? '') ?>">
        </label>
        <label>NIP
            <input type="text" name="nip" value="<?= e($values['nip'] ?? '') ?>">
        </label>
        <label>NUPTK
            <input type="text" name="nuptk" value="<?= e($values['nuptk'] ?? '') ?>">
        </label>
        <label>JK
            <select name="gender">
                <option value="">-</option>
                <?= options(['L' => 'Laki-laki', 'P' => 'Perempuan'], $values['gender'] ?? '') ?>
            </select>
        </label>
        <label>Telepon
            <input type="text" name="phone" value="<?= e($values['phone'] ?? '') ?>">
        </label>
        <label>Jabatan
            <input type="text" name="position" value="<?= e($values['position'] ?? 'Guru Mapel') ?>">
        </label>
        <label>Jenjang Mapel
            <select id="tg-level">
                <option value="">Semua Jenjang</option>
                <?php foreach (school_levels() as $lv): ?>
                    <option value="<?= e($lv) ?>"><?= e($lv) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Mapel Utama
            <select name="subject_id" id="tg-subject">
                <option value="">Pilih mapel</option>
                <?php foreach ($subjects as $sid => $sname): ?>
                    <?php $lv = fetch_one('SELECT level FROM subjects WHERE id = ?', [(int)$sid]); $lvs = trim((string)($lv['level'] ?? '')); ?>
                    <option value="<?= e((string)$sid) ?>" data-level="<?= e($lvs) ?>"<?= (string)($values['subject_id'] ?? '') === (string)$sid ? ' selected' : '' ?>><?= e($sname) . ($lvs !== '' ? ' [' . $lvs . ']' : '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <fieldset class="check-group">
            <legend>Kelas</legend>
            <?php if (!$classes): ?>
                <p class="hint">Belum ada data kelas aktif.</p>
            <?php else: ?>
                <?php foreach ($classes as $classId => $className): ?>
                    <label class="check"><input type="checkbox" name="class_ids[]" value="<?= e($classId) ?>" <?= in_array((string)$classId, $selectedClassIds, true) ? 'checked' : '' ?>> <?= e($className) ?></label>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>
        <button class="button primary full" type="submit">Daftar</button>
    </form>
    <script>
    (function(){
        var levelEl = document.getElementById('tg-level');
        var subjectEl = document.getElementById('tg-subject');
        if (!levelEl || !subjectEl) return;
        var allSubjectOpts = Array.from(subjectEl.options).map(function(o){ return {value:o.value, level:o.getAttribute('data-level')||'', text:o.textContent}; });
        var preSubject = subjectEl.value;
        function rebuild() {
            var prev = preSubject;
            subjectEl.innerHTML = '<option value="">Pilih mapel</option>';
            allSubjectOpts.filter(function(o){ return !o.value || !levelEl.value || (o.level && o.level.split(',').map(function(s){return s.trim();}).indexOf(levelEl.value) !== -1); }).forEach(function(o){
                var opt = document.createElement('option');
                opt.value = o.value; opt.textContent = o.text;
                opt.setAttribute('data-level', o.level);
                subjectEl.appendChild(opt);
            });
            if (prev && subjectEl.querySelector('option[value="' + prev + '"]')) subjectEl.value = prev;
        }
        levelEl.addEventListener('change', function(){ preSubject = subjectEl.value; rebuild(); });
    })();
    </script>
    <?php
    render_public_footer();
}

function page_telegram_web_login(): void
{
    if (!app_installed()) {
        render_public_header('Miniweb Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Aplikasi belum siap</h1>
            <p>Database belum diinstall. Jalankan instalasi dulu sebelum membuka miniweb Telegram.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    $token = trim((string)($_GET['token'] ?? ''));
    $login = telegram_web_login_consume_token($token);
    if (!$login) {
        render_public_header('Miniweb Telegram');
        ?>
        <div class="center">
            <?= render_app_mark(true) ?>
            <h1>Link Tidak Berlaku</h1>
            <p>Kembali ke Telegram lalu tekan tombol menu lagi.</p>
        </div>
        <?php
        render_public_footer();
        return;
    }

    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user_id'] = (int)$login['user_id'];
    redirect_to((string)$login['next_page']);
}

function page_logout(): void
{
    if (!is_post()) {
        redirect_to(current_user() ? 'dashboard' : 'login');
    }

    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    session_start();
    flash('success', 'Anda sudah keluar.');
    redirect_to('login');
}

function page_forgot_password(): void
{
    if (is_post()) {
        verify_csrf();
        try {
            $username = trim((string)($_POST['username'] ?? ''));
            if ($username === '') {
                throw new RuntimeException('Masukkan username atau email.');
            }

            $user = fetch_one(
                'SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1',
                [$username, $username]
            );
            if (!$user) {
                throw new RuntimeException('Akun tidak ditemukan.');
            }

            $chatId = trim((string)($user['telegram_chat_id'] ?? ''));
            $hasBotToken = trim((string)config('telegram.bot_token', '')) !== '';

            if ($chatId !== '' && $hasBotToken) {
                $token = telegram_web_login_create_token((int)$user['id'], 'dashboard');
                $url = telegram_web_route_url('telegram-web-login', ['token' => $token]);

                $message = implode("\n", [
                    '<b>Reset Password E-Raport</b>',
                    'Login ke akun ' . e($user['name']) . ' dengan tombol di bawah.',
                    'Link berlaku 5 menit dan hanya sekali pakai.',
                    'Setelah login, ganti password di menu Profil.',
                ]);
                $buttons = [
                    [telegram_web_app_button('Login Sekarang', $url)],
                ];
                $response = telegram_card_response($message, $buttons);
                telegram_send_message($chatId, $response);

                flash('success', 'Link login sudah dikirim ke akun Telegram Anda. Cek chat Telegram dari bot.');
                redirect_to('login');
            }

            if (!empty($user['email'])) {
                flash('warning', 'Fitur reset via email belum tersedia. Hubungi admin untuk reset password.');
                redirect_to('login');
            }

            flash('warning', 'Akun ini belum terhubung Telegram. Hubungi admin untuk reset password.');
            redirect_to('login');
        } catch (Throwable $exception) {
            flash('danger', friendly_error($exception));
            redirect_to('lupa-password');
        }
    }

    render_public_header('Lupa Password');
    echo '<h1>Lupa Password</h1>
    <p>Masukkan username atau email. Jika akun terhubung Telegram, link login akan dikirim ke Telegram.</p>
    <form method="post" class="public-form">
        ' . csrf_field() . '
        <label>Username atau Email <input type="text" name="username" required autofocus></label>
        <button class="button primary">Kirim Link Login</button>
        <p><a href="' . e(route_url('login')) . '">Kembali ke Login</a></p>
    </form>';
    render_public_footer();
}
