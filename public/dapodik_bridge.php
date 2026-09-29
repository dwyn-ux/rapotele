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

    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > max_upload_bytes()) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'message' => 'Payload terlalu besar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (trim($raw) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Payload JSON tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Payload JSON tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_array($payload) || array_is_list($payload)) {
        throw new InvalidArgumentException('Payload JSON harus berupa objek.');
    }
    if (array_key_exists('token', $payload) && !is_string($payload['token'])) {
        throw new InvalidArgumentException('Token payload tidak valid.');
    }
    if (array_key_exists('npsn', $payload) && !is_string($payload['npsn'])) {
        throw new InvalidArgumentException('NPSN payload tidak valid.');
    }

    if (!app_installed()) {
        throw new RuntimeException('Aplikasi e-rapor belum diinstall.');
    }

    $itemsKey = '';
    $items = [];
    if (array_key_exists('items', $payload)) {
        $itemsKey = 'items';
    } elseif (array_key_exists('payloads', $payload)) {
        $itemsKey = 'payloads';
    }
    if ($itemsKey !== '') {
        if (!is_array($payload[$itemsKey]) || !array_is_list($payload[$itemsKey])) {
            throw new InvalidArgumentException('Daftar data Dapodik tidak valid.');
        }
        $items = $payload[$itemsKey];
    }

    $expectedBridgeToken = trim((string)get_app_setting('dapodik_bridge_token', ''));
    $expectedDapodikToken = trim((string)get_app_setting('dapodik_token', ''));
    $expectedNpsn = trim((string)get_app_setting('dapodik_npsn', ''));
    $payloadNpsn = trim((string)($payload['npsn'] ?? ''));
    if ($payloadNpsn === '' && isset($items[0]) && is_array($items[0])) {
        if (array_key_exists('npsn', $items[0]) && !is_string($items[0]['npsn'])) {
            throw new InvalidArgumentException('NPSN payload tidak valid.');
        }
        $payloadNpsn = trim((string)($items[0]['npsn'] ?? ''));
    }

    $headerToken = trim((string)($_SERVER['HTTP_X_ERAPORT_TOKEN'] ?? ''));
    $bodyToken = trim((string)($payload['token'] ?? ''));
    $givenTokens = array_values(array_filter([$headerToken, $bodyToken], static fn (string $token): bool => $token !== ''));
    $tokenAccepted = false;
    foreach ($givenTokens as $givenToken) {
        if ($expectedBridgeToken !== '' && hash_equals($expectedBridgeToken, $givenToken)) {
            $tokenAccepted = true;
            break;
        }
        if ($expectedDapodikToken !== '' && hash_equals($expectedDapodikToken, $givenToken)
            && ($expectedNpsn === '' || ($payloadNpsn !== '' && hash_equals($expectedNpsn, $payloadNpsn)))) {
            $tokenAccepted = true;
            break;
        }
    }

    if (!$tokenAccepted) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Token sinkron tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $typeValue = array_key_exists('type', $payload) ? $payload['type'] : 'sekolah';
    if (!is_string($typeValue)) {
        throw new InvalidArgumentException('Jenis data Dapodik tidak valid.');
    }
    $type = trim($typeValue);
    if (!array_key_exists($type, dapodik_data_types(true))) {
        throw new InvalidArgumentException('Jenis data Dapodik tidak valid.');
    }

    if ($type === 'all') {
        if ($itemsKey === '' || $items === []) {
            throw new InvalidArgumentException('Paket semua data Dapodik tidak valid.');
        }
        foreach ($items as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException('Item data Dapodik tidak valid.');
            }
            $itemType = $item['type'] ?? null;
            if (!is_string($itemType) || !array_key_exists(trim($itemType), dapodik_data_types(false))) {
                throw new InvalidArgumentException('Jenis data Dapodik tidak valid.');
            }
            if (array_key_exists('data', $item) && !is_array($item['data'])) {
                throw new InvalidArgumentException('Data Dapodik tidak valid.');
            }
            if (array_key_exists('npsn', $item) && !is_string($item['npsn'])) {
                throw new InvalidArgumentException('NPSN payload tidak valid.');
            }
        }

        run_migrations();
        try {
            $summary = dapodik_import_items($items);
        } catch (PDOException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('Data Dapodik tidak valid.', 0, $exception);
        } catch (RuntimeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Import ') && stripos($exception->getMessage(), 'SQLSTATE') === false) {
                throw new InvalidArgumentException('Data Dapodik tidak valid.', 0, $exception);
            }
            throw $exception;
        }
        $message = 'Bridge menerima semua data. ' . dapodik_summary_text($summary) . '.';
        execute_sql(
            'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            ['offline-bridge', 'all', 'dapodik_bridge.php', 'success', $message, null]
        );

        $warningPayload = [];
        foreach (array_keys($summary) as $summaryType) {
            $warnings = dapodik_import_warning_payload((string)$summaryType);
            if ($warnings) {
                $warningPayload[$summaryType] = ['warning_count' => (int)($warnings['warning_count'] ?? 0)];
            }
        }

        $response = ['ok' => true, 'type' => 'all', 'summary' => $summary, 'count' => array_sum($summary), 'warnings' => $warningPayload];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!array_key_exists('data', $payload) || !is_array($payload['data'])) {
        throw new InvalidArgumentException('Data Dapodik tidak valid.');
    }
    $data = $payload['data'];
    unset($data['token'], $data['type'], $data['npsn']);

    run_migrations();
    try {
        $count = dapodik_import($type, $data);
    } catch (PDOException $exception) {
        throw $exception;
    } catch (InvalidArgumentException $exception) {
        throw new InvalidArgumentException('Data Dapodik tidak valid.', 0, $exception);
    } catch (RuntimeException $exception) {
        if (str_starts_with($exception->getMessage(), 'Import ') && stripos($exception->getMessage(), 'SQLSTATE') === false) {
            throw new InvalidArgumentException('Data Dapodik tidak valid.', 0, $exception);
        }
        throw $exception;
    }
    execute_sql(
        'INSERT INTO dapodik_sync_logs (mode, data_type, endpoint, status, message, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        ['offline-bridge', $type, 'dapodik_bridge.php', 'success', "Bridge menerima $type. Data diproses: $count.", null]
    );

    $warningPayload = dapodik_import_warning_payload($type);
    $response = ['ok' => true, 'type' => $type, 'count' => $count];
    if ($warningPayload) {
        $response['warning_count'] = (int)($warningPayload['warning_count'] ?? 0);
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    log_exception($exception);
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    $message = app_debug() ? $exception->getMessage() : 'Terjadi kesalahan internal.';
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
}
