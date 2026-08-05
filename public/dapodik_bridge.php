<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/web.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['ok' => false, 'message' => 'Method tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!app_installed()) {
        throw new RuntimeException('Aplikasi e-rapor belum diinstall.');
    }
    run_migrations();

    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > max_upload_bytes()) {
        throw new RuntimeException('Payload terlalu besar.');
    }
    $payload = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($payload)) {
        throw new RuntimeException('Payload JSON tidak valid.');
    }

    $expectedBridgeToken = trim((string)get_app_setting('dapodik_bridge_token', ''));
    $expectedDapodikToken = trim((string)get_app_setting('dapodik_token', ''));
    $expectedNpsn = trim((string)get_app_setting('dapodik_npsn', ''));
    $payloadNpsn = trim((string)($payload['npsn'] ?? ''));
    if ($payloadNpsn === '') {
        $itemsForNpsn = isset($payload['items']) && is_array($payload['items'])
            ? $payload['items']
            : (isset($payload['payloads']) && is_array($payload['payloads']) ? $payload['payloads'] : []);
        if (isset($itemsForNpsn[0]) && is_array($itemsForNpsn[0])) {
            $payloadNpsn = trim((string)($itemsForNpsn[0]['npsn'] ?? ''));
        }
    }
    $tokenCandidates = [];
    $headerToken = trim((string)($_SERVER['HTTP_X_ERAPORT_TOKEN'] ?? ''));
    $bodyToken = trim((string)($payload['token'] ?? ''));
    if ($headerToken !== '') {
        $tokenCandidates['header'] = $headerToken;
    }
    if ($bodyToken !== '') {
        $tokenCandidates['body'] = $bodyToken;
    }

    $tokenAccepted = false;
    $matchedTokenSource = '';
    foreach ($tokenCandidates as $source => $givenToken) {
        if ($expectedBridgeToken !== '' && hash_equals($expectedBridgeToken, $givenToken)) {
            $tokenAccepted = true;
            $matchedTokenSource = $source;
            break;
        }
        if ($expectedDapodikToken !== '' && hash_equals($expectedDapodikToken, $givenToken)) {
            if ($expectedNpsn === '' || ($payloadNpsn !== '' && hash_equals($expectedNpsn, $payloadNpsn))) {
                $tokenAccepted = true;
                $matchedTokenSource = $source;
                break;
            }
        }
    }

    if (!$tokenAccepted) {
        $reasons = [];
        if (!$tokenCandidates) {
            $reasons[] = 'token tidak diterima oleh server';
        }
        if ($expectedDapodikToken === '' && $expectedBridgeToken === '') {
            $reasons[] = 'Token / Key Webservice belum dikonfigurasi di menu Update Data';
        } elseif ($expectedDapodikToken === '' && $expectedBridgeToken !== '') {
            $reasons[] = 'Token bridge diterima, namun Token / Key Webservice di server masih kosong. Isi di menu Update Data.';
        } elseif (!in_array(true, array_map(fn (string $token): bool => hash_equals($expectedDapodikToken, $token), $tokenCandidates), true)) {
            $reasons[] = 'token yang dikirim helper tidak sama dengan Token / Key Webservice di server';
        }
        if ($expectedNpsn !== '' && ($payloadNpsn === '' || !hash_equals($expectedNpsn, $payloadNpsn))) {
            $reasons[] = 'NPSN helper tidak sama dengan NPSN di server';
        }

        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Token sinkron tidak valid. ' . ($reasons ? 'Penyebab: ' . implode('; ', $reasons) . '. ' : '') . 'Pakai Token Web Service Dapodik dan NPSN yang sama dengan konfigurasi Update Data di server e-rapor tujuan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $type = dapodik_validate_type((string)($payload['type'] ?? 'sekolah'), true);
    if ($type === 'all') {
        $items = isset($payload['items']) && is_array($payload['items'])
            ? $payload['items']
            : (isset($payload['payloads']) && is_array($payload['payloads']) ? $payload['payloads'] : []);
        $summary = dapodik_import_items($items);
        $message = 'Bridge menerima semua data. ' . dapodik_summary_text($summary) . '.';
        execute_sql(
            'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            ['offline-bridge', 'all', 'dapodik_bridge.php', 'success', $message, null]
        );

        $warningPayload = [];
        foreach (array_keys($summary) as $summaryType) {
            $warnings = dapodik_import_warning_payload((string)$summaryType);
            if ($warnings) {
                $warningPayload[$summaryType] = $warnings;
            }
        }

        echo json_encode(['ok' => true, 'type' => 'all', 'summary' => $summary, 'count' => array_sum($summary), 'warnings' => $warningPayload], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    unset($data['token'], $data['type'], $data['npsn']);

    $count = dapodik_import($type, $data);
    execute_sql(
        'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        ['offline-bridge', $type, 'dapodik_bridge.php', 'success', "Bridge menerima $type. Data diproses: $count.", null]
    );

    echo json_encode(['ok' => true, 'type' => $type, 'count' => $count] + dapodik_import_warning_payload($type), JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['ok' => false, 'message' => friendly_error($exception)], JSON_UNESCAPED_UNICODE);
}
