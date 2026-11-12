<?php

declare(strict_types=1);

/**
 * Firebase Cloud Messaging (HTTP v1) untuk app mobile.
 * Tanpa library eksternal. Butuh ekstensi openssl dan curl.
 *
 * Service account JSON diletakkan di storage/fcm-service-account.json
 * atau path lain lewat env FCM_SERVICE_ACCOUNT.
 */

function fcm_b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function fcm_service_account(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;
    $path = getenv('FCM_SERVICE_ACCOUNT') ?: (APP_ROOT . '/storage/fcm-service-account.json');
    if (is_file($path)) {
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data) && !empty($data['project_id']) && !empty($data['private_key']) && !empty($data['client_email'])) {
            $cached = $data;
        }
    }
    return $cached;
}

function fcm_is_configured(): bool
{
    return fcm_service_account() !== null
        && extension_loaded('openssl')
        && extension_loaded('curl')
        && table_exists('fcm_tokens');
}

/**
 * Ambil OAuth2 access token (di-cache di app_settings sampai hampir kedaluwarsa).
 */
function fcm_access_token(): string
{
    $sa = fcm_service_account();
    if ($sa === null) {
        throw new RuntimeException('Service account FCM belum dipasang.');
    }

    $cache = json_decode((string)get_app_setting('fcm_access_token', ''), true);
    if (is_array($cache) && !empty($cache['token']) && (int)($cache['exp'] ?? 0) > time() + 60) {
        return (string)$cache['token'];
    }

    $now    = time();
    $header = fcm_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim  = fcm_b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    $input = $header . '.' . $claim;

    $signature = '';
    if (!openssl_sign($input, $signature, (string)$sa['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Gagal menandatangani JWT FCM: ' . (openssl_error_string() ?: 'unknown'));
    }
    $jwt = $input . '.' . fcm_b64url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    $data = json_decode((string)$response, true);
    if ($code !== 200 || !is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Gagal ambil access token FCM (HTTP ' . $code . '): ' . ($err ?: (string)$response));
    }

    set_app_setting('fcm_access_token', json_encode([
        'token' => $data['access_token'],
        'exp'   => $now + (int)($data['expires_in'] ?? 3600),
    ]));

    return (string)$data['access_token'];
}

/**
 * Kirim satu pesan ke satu token FCM.
 *
 * @param array $row Row dari tabel fcm_tokens
 */
function fcm_send(array $row, string $title, string $body, string $url = '/', string $type = 'info', string $tag = ''): array
{
    $sa = fcm_service_account();
    if ($sa === null) {
        return ['ok' => false, 'error' => 'Service account FCM belum dipasang'];
    }

    try {
        $accessToken = fcm_access_token();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $tag = $tag !== '' ? $tag : $type;

    $message = [
        'message' => [
            'token'        => (string)$row['token'],
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => [
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'type'  => $type,
                'tag'   => $tag,
            ],
            'android' => [
                'priority'     => 'HIGH',
                'notification' => [
                    'channel_id' => 'erapot_schedule_channel',
                    'tag'        => $tag,
                ],
            ],
        ],
    ];

    $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode((string)$sa['project_id']) . '/messages:send';

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($message, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    $ok = $code >= 200 && $code < 300;

    // Token sudah tidak valid (app di-uninstall dsb.) -> hapus dari DB
    if (!$ok && isset($row['id']) && ($code === 404 || str_contains((string)$response, 'UNREGISTERED'))) {
        try {
            execute_sql('DELETE FROM fcm_tokens WHERE id = ?', [(int)$row['id']]);
        } catch (Throwable) {}
    }

    return [
        'ok'        => $ok,
        'http_code' => $code,
        'error'     => $ok ? '' : ($err ?: (string)$response),
    ];
}

/**
 * Kirim ke semua perangkat milik satu user.
 */
function fcm_send_to_user(int $userId, string $title, string $body, string $url = '/', string $type = 'info', string $tag = ''): array
{
    if (!fcm_is_configured()) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
    }

    $sent = $failed = 0;
    foreach (fetch_all('SELECT * FROM fcm_tokens WHERE user_id = ?', [$userId]) as $row) {
        $result = fcm_send($row, $title, $body, $url, $type, $tag);
        $result['ok'] ? $sent++ : $failed++;
        if ($result['ok']) {
            try {
                execute_sql('UPDATE fcm_tokens SET last_used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int)$row['id']]);
            } catch (Throwable) {}
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => 0];
}

/**
 * Kirim ke semua guru/admin aktif yang punya token FCM.
 */
function fcm_send_to_all_teachers(string $title, string $body, string $url = '/', string $type = 'info', string $tag = ''): array
{
    if (!fcm_is_configured()) {
        return ['sent' => 0, 'failed' => 0];
    }

    $rows = fetch_all(
        "SELECT ft.* FROM fcm_tokens ft
         JOIN users u ON u.id = ft.user_id
         WHERE u.role IN ('guru', 'admin') AND u.active = 1"
    );

    $sent = $failed = 0;
    foreach ($rows as $row) {
        $result = fcm_send($row, $title, $body, $url, $type, $tag);
        $result['ok'] ? $sent++ : $failed++;
    }

    return ['sent' => $sent, 'failed' => $failed];
}
