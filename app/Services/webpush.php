<?php

declare(strict_types=1);

/**
 * Web Push / VAPID Service
 *
 * Implementasi VAPID manual (tanpa library eksternal) agar bisa jalan
 * di shared hosting PHP murni tanpa Composer.
 *
 * Referensi: RFC 8292 (VAPID), RFC 8030 (Web Push)
 */

// ─── VAPID Key Management ──────────────────────────────────────────────────────

function webpush_openssl_config_path(): ?string
{
    $env = getenv('OPENSSL_CONF');
    if ($env && file_exists($env)) {
        return $env;
    }
    static $cached = null;
    if ($cached !== null && file_exists($cached)) {
        return $cached;
    }
    $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl_webpush.cnf';
    if (!file_exists($temp)) {
        @file_put_contents($temp, "default_bits = 2048\n[req]\ndistinguished_name = req_dn\n[req_dn]\n");
    }
    $cached = $temp;
    return $cached;
}

function webpush_vapid_public_key(?string &$error = null): string
{
    $key = (string)get_app_setting('vapid_public_key', '');
    if ($key === '') {
        try {
            $generated = webpush_generate_vapid_keys();
            return (string)($generated['public'] ?? '');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            log_exception($e);
            return '';
        }
    }
    return $key;
}

function webpush_vapid_private_key(?string &$error = null): string
{
    $key = (string)get_app_setting('vapid_private_key', '');
    if ($key === '') {
        try {
            $generated = webpush_generate_vapid_keys();
            return (string)($generated['private'] ?? '');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            log_exception($e);
            return '';
        }
    }
    return $key;
}

/**
 * Generate VAPID keypair dan simpan ke app_settings.
 * Panggil sekali dari halaman setup atau install.
 */
function webpush_generate_vapid_keys(): array
{
    if (!extension_loaded('openssl')) {
        throw new RuntimeException('Extension openssl diperlukan untuk VAPID.');
    }

    if (!table_exists('app_settings') && function_exists('run_migrations')) {
        run_migrations();
    }

    $configArgs = [
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ];

    // Coba tanpa config khusus terlebih dahulu (default di Linux/macOS)
    $key = @openssl_pkey_new($configArgs);

    // Fallback jika gagal (misalnya di Windows tanpa openssl.cnf bawaan)
    if ($key === false) {
        $cnf = webpush_openssl_config_path();
        if ($cnf) {
            $configArgs['config'] = $cnf;
            $key = @openssl_pkey_new($configArgs);
        }
    }

    if ($key === false) {
        throw new RuntimeException('Gagal generate EC key: ' . (openssl_error_string() ?: 'openssl_pkey_new returned false'));
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || !isset($details['ec'])) {
        throw new RuntimeException('Gagal mengambil detail EC key.');
    }

    // Public key uncompressed point (04 || x || y) = 65 bytes
    $x   = str_pad((string)$details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y   = str_pad((string)$details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $pub = "\x04" . $x . $y;

    $privPem = '';
    $exported = @openssl_pkey_export($key, $privPem);
    if (!$exported || empty($privPem)) {
        $cnf = webpush_openssl_config_path();
        $exportArgs = $cnf ? ['config' => $cnf] : [];
        @openssl_pkey_export($key, $privPem, null, $exportArgs);
    }

    if (empty($privPem)) {
        throw new RuntimeException('Gagal export private key: ' . (openssl_error_string() ?: 'openssl_pkey_export returned empty'));
    }

    $publicKey  = webpush_base64url_encode($pub);
    $privateKey = webpush_base64url_encode($privPem);

    set_app_setting('vapid_public_key', $publicKey);
    set_app_setting('vapid_private_key', $privateKey);

    return ['public' => $publicKey, 'private' => $privateKey];
}

// ─── Helpers Base64url ─────────────────────────────────────────────────────────

function webpush_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webpush_base64url_decode(string $data): string
{
    $rem = strlen($data) % 4;
    if ($rem > 0) {
        $data .= str_repeat('=', 4 - $rem);
    }
    return (string)base64_decode(strtr($data, '-_', '+/'));
}

// ─── VAPID JWT ─────────────────────────────────────────────────────────────────

function webpush_build_vapid_jwt(string $audience, string $subject = 'mailto:admin@sekolah.sch.id'): string
{
    $privPem = webpush_base64url_decode(webpush_vapid_private_key());

    $header  = webpush_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = webpush_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 jam
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $signingInput = $header . '.' . $payload;

    $key = openssl_pkey_get_private($privPem);
    if ($key === false) {
        throw new RuntimeException('Gagal load private key VAPID.');
    }

    openssl_sign($signingInput, $derSig, $key, OPENSSL_ALGO_SHA256);

    // DER → raw r||s (64 bytes)
    $rawSig = webpush_der_to_raw_signature($derSig);

    return $signingInput . '.' . webpush_base64url_encode($rawSig);
}

/**
 * Konversi DER-encoded ECDSA signature ke raw 64-byte r||s.
 */
function webpush_der_to_raw_signature(string $der): string
{
    // Format: 0x30 len 0x02 rLen r 0x02 sLen s
    $offset = 2; // skip 0x30 dan total length
    $rLen   = ord($der[$offset + 1]);
    $r      = substr($der, $offset + 2, $rLen);
    $offset += 2 + $rLen;
    $sLen   = ord($der[$offset + 1]);
    $s      = substr($der, $offset + 2, $sLen);

    // Trim leading null bytes, lalu pad ke 32 bytes
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");

    return str_pad($r, 32, "\x00", STR_PAD_LEFT)
         . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

// ─── Enkripsi Payload (AES-128-GCM + ECDH) ────────────────────────────────────

/**
 * Enkripsi payload push sesuai RFC 8291 (Message Encryption for Web Push).
 * Mengembalikan encrypted body dan header Content-Encoding.
 */
function webpush_encrypt_payload(string $payload, string $p256dh, string $auth): array
{
    $salt    = random_bytes(16);
    $keyArgs = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    $serverKey = @openssl_pkey_new($keyArgs);
    if ($serverKey === false) {
        $cnf = webpush_openssl_config_path();
        if ($cnf) {
            $keyArgs['config'] = $cnf;
            $serverKey = @openssl_pkey_new($keyArgs);
        }
    }
    if ($serverKey === false) {
        throw new RuntimeException('Gagal generate server EC key: ' . (openssl_error_string() ?: 'openssl_pkey_new returned false'));
    }
    $serverDetails = openssl_pkey_get_details($serverKey);
    if ($serverDetails === false || !isset($serverDetails['ec'])) {
        throw new RuntimeException('Gagal mengambil detail server EC key.');
    }

    $serverX   = str_pad((string)$serverDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $serverY   = str_pad((string)$serverDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $serverPub = "\x04" . $serverX . $serverY;

    // Decode client public key (browser p256dh)
    $clientPubRaw = webpush_base64url_decode($p256dh);
    $clientKey    = openssl_pkey_get_public(webpush_raw_ec_to_pem($clientPubRaw));
    if ($clientKey === false) {
        throw new RuntimeException('Public key subscriber tidak valid.');
    }

    // ECDH shared secret
    $exportArgs = [];
    if ($cnf) {
        $exportArgs['config'] = $cnf;
    }
    openssl_pkey_export($serverKey, $serverPrivPem, null, $exportArgs);
    $serverPriv = openssl_pkey_get_private($serverPrivPem);

    // ext-openssl ECDH
    if (function_exists('openssl_pkey_derive')) {
        $sharedSecret = openssl_pkey_derive($clientKey, $serverKey, 32);
    } elseif ($serverPriv && function_exists('openssl_dh_compute_key')) {
        openssl_dh_compute_key($sharedSecret, $clientKey, $serverPriv);
    } else {
        $sharedSecret = hash('sha256', $clientPubRaw . $serverPub . $salt, true);
    }

    $authDecoded = webpush_base64url_decode($auth);
    $prk  = hash_hkdf('sha256', (string)$sharedSecret, 32, "Content-Encoding: auth\x00", $authDecoded);
    $cek  = hash_hkdf('sha256', $prk, 16, webpush_build_info('aesgcm', $clientPubRaw, $serverPub) . "\x00", $salt);
    $nonce= hash_hkdf('sha256', $prk, 12, webpush_build_info('nonce', $clientPubRaw, $serverPub) . "\x00", $salt);

    // Pad payload ke minimal 2 byte (aesgcm: 2 byte pad prefix)
    $padded = "\x00\x00" . $payload;

    $tag       = '';
    $encrypted = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($encrypted === false) {
        throw new RuntimeException('Enkripsi AES-128-GCM payload push gagal.');
    }

    return [
        'body'       => $encrypted . $tag,
        'salt'       => $salt,
        'server_pub' => $serverPub,
    ];
}

function webpush_build_info(string $type, string $clientPub, string $serverPub): string
{
    return "Content-Encoding: {$type}\x00P-256\x00"
        . pack('n', strlen($clientPub)) . $clientPub
        . pack('n', strlen($serverPub)) . $serverPub;
}

function webpush_raw_ec_to_pem(string $raw): string
{
    // Wrap 65-byte uncompressed EC point dalam SubjectPublicKeyInfo DER
    $oid  = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    $der  = $oid . $raw;
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

// ─── Kirim Push ────────────────────────────────────────────────────────────────

/**
 * Kirim Web Push ke satu subscription.
 *
 * @param array  $subscription  Row dari tabel push_subscriptions
 * @param string $title
 * @param string $body
 * @param string $url           URL yang dibuka saat notif diklik
 * @param string $type          'absensi' | 'jadwal' | 'jurnal' | 'info'
 * @param string $tag           Tag untuk replace notif sebelumnya
 */
function webpush_send(
    array  $subscription,
    string $title,
    string $body,
    string $url   = '/',
    string $type  = 'info',
    string $tag   = ''
): array {
    if (!extension_loaded('openssl') || !extension_loaded('curl')) {
        return ['ok' => false, 'error' => 'openssl dan curl diperlukan'];
    }

    $endpoint  = (string)$subscription['endpoint'];
    $p256dh    = (string)$subscription['p256dh'];
    $auth      = (string)$subscription['auth_token'];

    $vapidPublic = webpush_vapid_public_key();
    if ($vapidPublic === '' || webpush_vapid_private_key() === '') {
        return ['ok' => false, 'error' => 'VAPID keys belum di-generate'];
    }

    // Payload JSON
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
        'type'  => $type,
        'tag'   => $tag ?: $type,
    ], JSON_UNESCAPED_UNICODE);

    // Ambil audience dari endpoint (origin saja)
    $parsed   = parse_url($endpoint);
    $audience = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    if (!empty($parsed['port'])) {
        $audience .= ':' . $parsed['port'];
    }

    // VAPID JWT
    try {
        $jwt = webpush_build_vapid_jwt($audience);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'JWT error: ' . $e->getMessage()];
    }

    // Enkripsi payload
    try {
        $enc = webpush_encrypt_payload((string)$payload, $p256dh, $auth);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Enkripsi gagal: ' . $e->getMessage()];
    }

    // HTTP Request ke push endpoint browser
    $headers = [
        'Authorization: vapid t=' . $jwt . ',k=' . $vapidPublic,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aesgcm',
        'Encryption: salt=' . webpush_base64url_encode($enc['salt']),
        'Crypto-Key: dh=' . webpush_base64url_encode($enc['server_pub']) . ';p256ecdsa=' . $vapidPublic,
        'TTL: 86400',
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $enc['body'],
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response    = curl_exec($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);

    $ok = $httpCode >= 200 && $httpCode < 300;

    // HTTP 410 = subscription expired/unsubscribed → hapus dari DB
    if ($httpCode === 410 && isset($subscription['id'])) {
        try {
            execute_sql('DELETE FROM push_subscriptions WHERE id = ?', [(int)$subscription['id']]);
        } catch (Throwable) {}
    }

    return [
        'ok'        => $ok,
        'http_code' => $httpCode,
        'error'     => $ok ? '' : ($curlError ?: (string)$response),
    ];
}

/**
 * Kirim push ke semua subscription milik satu user.
 */
function webpush_send_to_user(
    int    $userId,
    string $title,
    string $body,
    string $url  = '/',
    string $type = 'info',
    string $tag  = ''
): array {
    if (!table_exists('push_subscriptions')) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    }

    $subscriptions = fetch_all(
        'SELECT * FROM push_subscriptions WHERE user_id = ?',
        [$userId]
    );

    $sent = $failed = 0;
    foreach ($subscriptions as $sub) {
        $result = webpush_send($sub, $title, $body, $url, $type, $tag);
        $result['ok'] ? $sent++ : $failed++;

        // Update last_used_at
        try {
            execute_sql(
                'UPDATE push_subscriptions SET last_used_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), (int)$sub['id']]
            );
        } catch (Throwable) {}
    }

    // Kirim juga ke app mobile (FCM)
    require_once __DIR__ . '/fcm.php';
    $fcm = fcm_send_to_user($userId, $title, $body, $url, $type, $tag);
    $sent   += (int)$fcm['sent'];
    $failed += (int)$fcm['failed'];

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => 0];
}

/**
 * Kirim push ke satu guru berdasarkan teacher_id.
 */
function webpush_send_to_teacher(
    int    $teacherId,
    string $title,
    string $body,
    string $url  = '/',
    string $type = 'info',
    string $tag  = ''
): array {
    $user = fetch_one('SELECT id FROM users WHERE teacher_id = ? AND active = 1 ORDER BY id ASC LIMIT 1', [$teacherId]);
    if (!$user) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
    }
    return webpush_send_to_user((int)$user['id'], $title, $body, $url, $type, $tag);
}

/**
 * Kirim push ke semua guru yang punya subscription aktif.
 */
function webpush_send_to_all_teachers(
    string $title,
    string $body,
    string $url  = '/',
    string $type = 'info',
    string $tag  = ''
): array {
    if (!table_exists('push_subscriptions')) {
        return ['sent' => 0, 'failed' => 0];
    }

    $subscriptions = fetch_all(
        "SELECT ps.* FROM push_subscriptions ps
         JOIN users u ON u.id = ps.user_id
         WHERE u.role IN ('guru', 'admin') AND u.active = 1"
    );

    $sent = $failed = 0;
    foreach ($subscriptions as $sub) {
        $result = webpush_send($sub, $title, $body, $url, $type, $tag);
        $result['ok'] ? $sent++ : $failed++;
    }

    // Kirim juga ke app mobile (FCM)
    require_once __DIR__ . '/fcm.php';
    $fcm = fcm_send_to_all_teachers($title, $body, $url, $type, $tag);
    $sent   += (int)$fcm['sent'];
    $failed += (int)$fcm['failed'];

    return ['sent' => $sent, 'failed' => $failed];
}

/**
 * Cek apakah VAPID sudah di-setup.
 */
function webpush_is_configured(): bool
{
    return webpush_vapid_public_key() !== '' && webpush_vapid_private_key() !== '';
}
