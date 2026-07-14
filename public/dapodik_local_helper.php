<?php

declare(strict_types=1);

const HELPER_VERSION = 'v2.5';
const HELPER_DEFAULT_DAPODIK_URL_B64 = '__ERAPORT_DAPODIK_URL_B64__';
const HELPER_DEFAULT_DAPODIK_TOKEN_B64 = '__ERAPORT_DAPODIK_TOKEN_B64__';
const HELPER_DEFAULT_NPSN_B64 = '__ERAPORT_NPSN_B64__';
const HELPER_DEFAULT_BRIDGE_URL_B64 = '__ERAPORT_BRIDGE_URL_B64__';
const HELPER_DEFAULT_BRIDGE_TOKEN_B64 = '__ERAPORT_BRIDGE_TOKEN_B64__';
const HELPER_DEFAULT_TYPE_B64 = '__ERAPORT_TYPE_B64__';

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $string, int $start, int $width, string $trimMarker = '', ?string $encoding = null): string
    {
        if ($width <= 0) {
            return '';
        }

        $slice = substr($string, max(0, $start), $width);
        if (strlen($string) <= max(0, $start) + $width) {
            return $slice;
        }

        $markerLength = strlen($trimMarker);
        if ($markerLength >= $width) {
            return substr($trimMarker, 0, $width);
        }

        return substr($slice, 0, $width - $markerLength) . $trimMarker;
    }
}

function helper_is_local_request(): bool
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($remote, ['127.0.0.1', '::1'], true);
}

function helper_normalize_http_url(string $url): string
{
    $url = trim($url);
    $parts = parse_url($url);
    if (!is_array($parts)) {
        throw new RuntimeException('URL harus HTTP/HTTPS valid.');
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('URL harus HTTP/HTTPS valid.');
    }
    return rtrim($url, '/');
}

function helper_bridge_endpoint(string $url): string
{
    $url = helper_normalize_http_url($url);
    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    if (preg_match('/\.php$/i', $path)) {
        return $url;
    }

    return rtrim($url, '/') . '/dapodik_bridge.php';
}

function helper_require_curl(): void
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL belum aktif. Aktifkan extension=curl di php.ini, lalu jalankan helper lagi.');
    }
}

function helper_embedded_default(string $encoded, string $fallback = ''): string
{
    if ($encoded === '' || str_starts_with($encoded, '__ERAPORT_')) {
        return $fallback;
    }

    $decoded = base64_decode($encoded, true);
    return $decoded === false ? $fallback : $decoded;
}

function helper_request_defaults(): array
{
    $defaults = [];
    foreach (['dapodik_url', 'dapodik_token', 'npsn', 'bridge_url', 'bridge_token', 'type'] as $key) {
        $value = trim((string)($_GET[$key] ?? ''));
        if ($value !== '') {
            $defaults[$key] = $value;
        }
    }
    return $defaults;
}

function helper_personalized_source(array $defaults): string
{
    $source = (string)file_get_contents(__FILE__);
    $replacements = [
        '__ERAPORT_DAPODIK_URL_B64__' => base64_encode((string)($defaults['dapodik_url'] ?? 'http://127.0.0.1:5774')),
        '__ERAPORT_DAPODIK_TOKEN_B64__' => base64_encode((string)($defaults['dapodik_token'] ?? '')),
        '__ERAPORT_NPSN_B64__' => base64_encode((string)($defaults['npsn'] ?? '')),
        '__ERAPORT_BRIDGE_URL_B64__' => base64_encode((string)($defaults['bridge_url'] ?? '')),
        '__ERAPORT_BRIDGE_TOKEN_B64__' => base64_encode((string)($defaults['bridge_token'] ?? '')),
        '__ERAPORT_TYPE_B64__' => base64_encode((string)($defaults['type'] ?? 'all')),
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $source);
}

function helper_exe_config_blob(array $defaults): string
{
    $config = helper_config_values($defaults);

    return "\n__ERAPORT_BRIDGE_CONFIG__"
        . base64_encode((string)json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        . "__END_ERAPORT_BRIDGE_CONFIG__\n";
}

function helper_config_values(array $defaults): array
{
    return [
        'version' => HELPER_VERSION,
        'dapodik_url' => (string)($defaults['dapodik_url'] ?? 'http://127.0.0.1:5774'),
        'dapodik_token' => (string)($defaults['dapodik_token'] ?? ''),
        'npsn' => (string)($defaults['npsn'] ?? ''),
        'bridge_url' => (string)($defaults['bridge_url'] ?? ''),
        'bridge_token' => (string)($defaults['bridge_token'] ?? ''),
        'type' => (string)($defaults['type'] ?? 'all'),
    ];
}

function helper_config_text(array $defaults): string
{
    $lines = [];
    foreach (helper_config_values($defaults) as $key => $value) {
        $lines[] = $key . '=' . base64_encode((string)$value);
    }
    return implode("\r\n", $lines) . "\r\n";
}

function helper_portable_launcher(): string
{
    return <<<'BAT'
@echo off
setlocal EnableExtensions
title E-Raport Dapodik Bridge Portable v2.5
cd /d "%~dp0"

set "EXE=%~dp0eraport-dapodik-bridge-helper-v2.5.exe"
set "CONFIG=%~dp0eraport-bridge-config.txt"

echo E-Raport Dapodik Bridge Helper Portable v2.5
echo Folder: %~dp0
echo.

if not exist "%EXE%" (
    echo [ERROR] File EXE tidak ditemukan:
    echo %EXE%
    echo.
    echo Extract ulang eraport-dapodik-bridge-portable-v2.5.zip ke satu folder tetap.
    pause
    exit /b 1
)

if not exist "%CONFIG%" (
    echo [INFO] File config portable belum ada:
    echo %CONFIG%
    echo.
    echo Helper tetap bisa jalan. Setelah mengisi token/URL, klik Simpan Konfigurasi.
    echo Jika ingin config otomatis, download "Unduh Config Portable" dari menu Update Data.
    echo.
)

start "E-Raport Dapodik Bridge Helper" "%EXE%"
if errorlevel 1 (
    echo [ERROR] Gagal menjalankan helper.
    pause
    exit /b 1
)

exit /b 0
BAT;
}

function helper_portable_readme(): string
{
    return <<<'TXT'
E-Raport Dapodik Bridge Helper Portable v2.5

Cara pakai:
1. Extract ZIP ini ke satu folder tetap, misalnya Desktop\Bridge-Dapodik.
2. Jalankan jalankan-bridge-portable.bat atau eraport-dapodik-bridge-helper-v2.5.exe.
3. Isi cukup NPSN, Link Web Raport, dan Token Web Service Dapodik. Token ini dipakai untuk baca Dapodik lokal dan kirim ke server e-rapor.
4. Jika token berubah, tidak perlu download EXE lagi. Edit field token di aplikasi lalu klik Simpan Konfigurasi, atau ganti file eraport-bridge-config.txt dengan file config terbaru dari menu Update Data.

Catatan:
- File eraport-bridge-config.txt harus satu folder dengan EXE. Launcher akan tetap membuka helper jika config belum ada.
- Sinkron Data Dasar mengambil sekolah, guru, rombel, siswa, anggota rombel, dan pembelajaran.
- Mapel dibuat dari data pembelajaran yang punya guru. Rombel ekskul baru masuk jika ada anggota rombelnya.
TXT;
}

function helper_zip_dos_time(): array
{
    $date = getdate();
    $dosTime = ((int)$date['hours'] << 11) | ((int)$date['minutes'] << 5) | intdiv((int)$date['seconds'], 2);
    $year = max(1980, (int)$date['year']);
    $dosDate = (($year - 1980) << 9) | ((int)$date['mon'] << 5) | (int)$date['mday'];
    return [$dosTime, $dosDate];
}

function helper_zip_store(array $files): string
{
    [$dosTime, $dosDate] = helper_zip_dos_time();
    $body = '';
    $central = '';
    $offset = 0;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', (string)$name);
        $data = (string)$data;
        $crc = hexdec(hash('crc32b', $data));
        $size = strlen($data);
        $nameLength = strlen($name);

        $local = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0
        ) . $name . $data;

        $body .= $local;
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;
        $offset += strlen($local);
    }

    $centralOffset = strlen($body);
    $centralSize = strlen($central);
    $count = count($files);

    return $body . $central . pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $count,
        $count,
        $centralSize,
        $centralOffset,
        0
    );
}

function helper_download_url(string $query): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/dapodik_local_helper.php');

    return $scheme . '://' . $host . $script . '?' . ltrim($query, '?');
}

function helper_windows_launcher(string $helperUrl, string $openQuery = ''): string
{
    $bat = <<<'BAT'
@echo off
setlocal EnableExtensions
title Helper Sinkron Dapodik Lokal v2.5
cd /d "%~dp0"

set "PORTABLE_EXE=%~dp0eraport-dapodik-bridge-helper-v2.5.exe"
if exist "%PORTABLE_EXE%" (
    echo Menjalankan helper portable...
    start "E-Raport Dapodik Bridge Helper" "%PORTABLE_EXE%"
    exit /b 0
)

set "HELPER=%~dp0dapodik-local-helper-v2.5.php"
set "HELPER_URL=__HELPER_URL__"
set "OPEN_URL=http://127.0.0.1:8088/__OPEN_QUERY__"
set "POWERSHELL_EXE="

echo Memastikan helper Dapodik terbaru...
where powershell.exe >nul 2>nul
if not errorlevel 1 set "POWERSHELL_EXE=powershell.exe"
if not defined POWERSHELL_EXE if exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" set "POWERSHELL_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"

if defined POWERSHELL_EXE (
    "%POWERSHELL_EXE%" -NoProfile -ExecutionPolicy Bypass -Command "try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri $env:HELPER_URL -OutFile $env:HELPER -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }"
)
if not defined POWERSHELL_EXE (
    echo PowerShell tidak ditemukan. Melewati download otomatis dan mencari helper lokal...
)
if not exist "%HELPER%" (
    echo Gagal mengunduh helper terbaru. Mencari file helper di folder ini...
    set "HELPER="
    for %%F in ("%~dp0dapodik-local-helper-v2*.php" "%~dp0dapodik-local-helper*.php") do (
        if exist "%%~fF" set "HELPER=%%~fF"
    )
    if not defined HELPER (
        echo File dapodik-local-helper-v2.php tidak ditemukan.
        echo Unduh helper dari menu Update Data, lalu jalankan launcher ini lagi.
        pause
        exit /b 1
    )
)

set "PHP_EXE="
where php >nul 2>nul
if not errorlevel 1 set "PHP_EXE=php"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\laragon\bin\php\php.exe" set "PHP_EXE=C:\laragon\bin\php\php.exe"
if not defined PHP_EXE (
    for /f "delims=" %%P in ('where /R C:\laragon php.exe 2^>nul') do (
        if not defined PHP_EXE set "PHP_EXE=%%P"
    )
)
if not defined PHP_EXE (
    for /f "delims=" %%P in ('where /R C:\xampp php.exe 2^>nul') do (
        if not defined PHP_EXE set "PHP_EXE=%%P"
    )
)
if not defined PHP_EXE (
    for /f "delims=" %%P in ('where /R C:\wamp64 php.exe 2^>nul') do (
        if not defined PHP_EXE set "PHP_EXE=%%P"
    )
)

if not defined PHP_EXE (
    echo PHP belum ditemukan.
    echo Install XAMPP/Laragon, atau tambahkan php.exe ke PATH Windows.
    pause
    exit /b 1
)

echo Menjalankan helper dengan PHP: %PHP_EXE%
echo File helper: %HELPER%
echo.
echo Jendela server akan terbuka. Tutup jendela server jika sudah selesai sinkron.
start "Dapodik Helper Server" "%PHP_EXE%" -S 127.0.0.1:8088 "%HELPER%"
timeout /t 2 /nobreak >nul
start "" "%OPEN_URL%"
exit /b 0
BAT;

    return str_replace(["__HELPER_URL__", "__OPEN_QUERY__"], [$helperUrl, $openQuery], $bat);
}

if (isset($_GET['download'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    if ((string)$_GET['download'] === 'config') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="eraport-bridge-config.txt"');
        echo helper_config_text(helper_request_defaults());
        exit;
    }

    if ((string)$_GET['download'] === 'portable') {
        $exePath = __DIR__ . '/downloads/eraport-dapodik-bridge-helper-base.exe';
        if (!is_file($exePath)) {
            http_response_code(404);
            echo 'File EXE helper belum tersedia di server.';
            exit;
        }

        $defaults = helper_request_defaults();
        $zip = helper_zip_store([
            'eraport-dapodik-bridge-helper-v2.5.exe' => (string)file_get_contents($exePath) . helper_exe_config_blob($defaults),
            'eraport-bridge-config.txt' => helper_config_text($defaults),
            'jalankan-bridge-portable.bat' => str_replace("\n", "\r\n", helper_portable_launcher()),
            'README-PORTABLE.txt' => str_replace("\n", "\r\n", helper_portable_readme()),
        ]);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="eraport-dapodik-bridge-portable-v2.5.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }

    if ((string)$_GET['download'] === 'exe') {
        $exePath = __DIR__ . '/downloads/eraport-dapodik-bridge-helper-base.exe';
        if (!is_file($exePath)) {
            http_response_code(404);
            echo 'File EXE helper belum tersedia di server.';
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="eraport-dapodik-bridge-helper-v2.5.exe"');
        header('Content-Length: ' . (filesize($exePath) + strlen(helper_exe_config_blob(helper_request_defaults()))));
        readfile($exePath);
        echo helper_exe_config_blob(helper_request_defaults());
        exit;
    }

    if ((string)$_GET['download'] === 'bat') {
        $openParams = helper_request_defaults();
        $downloadParams = ['download' => '1'] + $openParams;
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="jalankan-helper-dapodik-v2.5.bat"');
        echo str_replace("\n", "\r\n", helper_windows_launcher(helper_download_url(http_build_query($downloadParams)), $openParams ? '?' . http_build_query($openParams) : ''));
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="dapodik-local-helper-v2.5.php"');
    echo helper_personalized_source(helper_request_defaults());
    exit;
}

if (!helper_is_local_request()) {
    http_response_code(403);
    echo 'Helper ini hanya boleh dijalankan dari localhost. Unduh file helper, lalu jalankan di komputer yang bisa mengakses Dapodik lokal.';
    exit;
}

$result = null;
$payloadPreview = '';

function helper_post(string $url, array $payload, string $bridgeToken): array
{
    helper_require_curl();
    $payload['token'] = $bridgeToken;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init(helper_normalize_http_url($url));
    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Eraport-Token: ' . $bridgeToken,
        ],
        CURLOPT_POSTFIELDS => $body,
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['code' => $code, 'error' => $error, 'body' => (string)$response];
}

function helper_get_json(string $url, array $headers = []): array
{
    helper_require_curl();
    $ch = curl_init(helper_normalize_http_url($url));
    $httpHeaders = array_merge([
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
    ], $headers);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => $httpHeaders,
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($response === false || $error !== '') {
        throw new RuntimeException('Gagal membaca Dapodik lokal: ' . $error);
    }
    $json = json_decode((string)$response, true);
    if (!is_array($json)) {
        $preview = mb_strimwidth(trim((string)$response), 0, 160, '...');
        throw new RuntimeException('Respons Dapodik HTTP ' . $code . ' bukan JSON valid' . ($preview !== '' ? ': ' . $preview : '.') );
    }
    return $json;
}

function helper_endpoint_name(string $type): string
{
    $map = [
        'sekolah' => 'getSekolah',
        'guru' => 'getGtk',
        'siswa' => 'getPesertaDidik',
        'rombel' => 'getRombonganBelajar',
        'anggota_rombel' => 'getAnggotaRombel',
        'mapel' => 'getMataPelajaran',
        'pembelajaran' => 'getPembelajaran',
    ];

    return $map[$type] ?? $type;
}

function helper_endpoint(string $baseUrl, string $type, string $npsn, ?string $token = null): string
{
    $params = [
        'npsn' => $npsn,
    ];
    if ($token !== null && $token !== '') {
        $params['token'] = $token;
    }

    return helper_normalize_http_url($baseUrl) . '/WebService/' . helper_endpoint_name($type) . '?' . http_build_query($params);
}

function helper_data_types(bool $includeAll = false): array
{
    $types = [
        'sekolah' => 'Sekolah',
        'guru' => 'Guru/GTK',
        'siswa' => 'Siswa',
        'anggota_rombel' => 'Anggota Rombel',
        'mapel' => 'Mapel',
        'rombel' => 'Rombel',
        'pembelajaran' => 'Pembelajaran',
    ];

    return $includeAll ? ['all' => 'Semua Data Dasar'] + $types : $types;
}

function helper_default_sync_types(): array
{
    return ['sekolah', 'guru', 'rombel', 'siswa', 'anggota_rombel', 'pembelajaran'];
}

function helper_collect_payload(string $dapodikUrl, string $dapodikToken, string $npsn, string $type): array
{
    if (!array_key_exists($type, helper_data_types(false))) {
        throw new RuntimeException('Jenis data Dapodik tidak valid.');
    }

    $endpoint = helper_endpoint($dapodikUrl, $type, $npsn);
    try {
        $json = helper_get_json($endpoint, ['Authorization: Bearer ' . $dapodikToken]);
        $authLabel = 'Authorization Bearer';
    } catch (RuntimeException $bearerException) {
        if (helper_is_optional_endpoint_missing_response($type, $bearerException->getMessage())) {
            throw $bearerException;
        }

        $fallbackEndpoint = helper_endpoint($dapodikUrl, $type, $npsn, $dapodikToken);
        try {
            $json = helper_get_json($fallbackEndpoint);
            $endpoint = $fallbackEndpoint;
            $authLabel = 'query token';
        } catch (RuntimeException $queryException) {
            throw new RuntimeException(
                'Dapodik menolak akses dengan Authorization Bearer dan query token. Bearer: '
                . $bearerException->getMessage() . ' Query: ' . $queryException->getMessage()
            );
        }
    }

    return [
        'endpoint' => $endpoint . ' [' . $authLabel . ']',
        'payload' => ['type' => $type, 'npsn' => $npsn, 'data' => helper_records($json)],
    ];
}

function helper_records(array $json): array
{
    foreach (['data', 'rows', 'result'] as $key) {
        if (isset($json[$key]) && is_array($json[$key])) {
            return $json[$key];
        }
    }
    return array_is_list($json) ? $json : [$json];
}

function helper_response_label(string $type, array $response): string
{
    $decoded = json_decode((string)$response['body'], true);
    if (is_array($decoded)) {
        if (!empty($decoded['ok'])) {
            $count = isset($decoded['count']) ? (int)$decoded['count'] : 0;
            $warningCount = isset($decoded['warning_count']) ? (int)$decoded['warning_count'] : 0;
            return "$type: OK, diproses $count data" . ($warningCount > 0 ? ", $warningCount baris dilewati" : "") . ".";
        }
        if (isset($decoded['message'])) {
            $message = (string)$decoded['message'];
            if (stripos($message, 'Token bridge') !== false || stripos($message, 'Token sinkron') !== false) {
                $message .= ' Pastikan NPSN dan Token Web Service Dapodik sama dengan konfigurasi Update Data di server e-rapor tujuan.';
            }
            return "$type: gagal, " . $message;
        }
    }

    $message = $response['error'] ?: trim((string)$response['body']);
    return "$type: HTTP " . (int)$response['code'] . ($message !== '' ? ', ' . $message : '.');
}

function helper_is_bridge_token_error(array $response): bool
{
    if ((int)$response['code'] !== 403) {
        return false;
    }

    $decoded = json_decode((string)$response['body'], true);
    $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : (string)$response['body'];
    return stripos($message, 'Token bridge') !== false || stripos($message, 'Token sinkron') !== false;
}

function helper_is_optional_endpoint_missing(string $type, Throwable $exception): bool
{
    return helper_is_optional_endpoint_missing_response($type, $exception->getMessage());
}

function helper_is_optional_endpoint_missing_response(string $type, string $message): bool
{
    if (!in_array($type, ['anggota_rombel', 'pembelajaran'], true)) {
        return false;
    }

    return stripos($message, '404') !== false || stripos($message, 'Not Found') !== false;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $dapodikUrl = helper_normalize_http_url((string)($_POST['dapodik_url'] ?? 'http://127.0.0.1:5774'));
        $dapodikToken = trim((string)($_POST['dapodik_token'] ?? ''));
        $npsn = trim((string)($_POST['npsn'] ?? ''));
        $type = trim((string)($_POST['type'] ?? 'sekolah'));
        $mode = (string)($_POST['mode'] ?? 'sync');
        $bridgeUrl = trim((string)($_POST['bridge_url'] ?? ''));
        if ($bridgeUrl !== '') {
            $bridgeUrl = helper_bridge_endpoint($bridgeUrl);
        }
        $bridgeToken = $dapodikToken;

        if ($dapodikToken === '' || $npsn === '') {
            throw new RuntimeException('Token Dapodik dan NPSN wajib diisi.');
        }

        $isAllMode = $type === 'all' || in_array($mode, ['sync_all', 'export_all'], true);
        $types = $isAllMode
            ? helper_default_sync_types()
            : [$type];

        $payloads = [];
        $summary = [];
        foreach ($types as $currentType) {
            try {
                $collected = helper_collect_payload($dapodikUrl, $dapodikToken, $npsn, $currentType);
            } catch (RuntimeException $exception) {
                if (helper_is_optional_endpoint_missing($currentType, $exception)) {
                    $summary[] = $currentType . ': dilewati karena endpoint tidak tersedia di Web Service Dapodik lokal.';
                    continue;
                }
                throw $exception;
            }
            $payloads[] = $collected['payload'];
            $summary[] = $currentType . ': ' . count($collected['payload']['data']) . ' data dari ' . $collected['endpoint'];
        }

        $payloadPreview = json_encode([
            'mode' => $mode,
            'summary' => $summary,
            'payload' => count($payloads) === 1 ? $payloads[0] : ['type' => 'all', 'items' => $payloads],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($mode === 'export' || $mode === 'export_all') {
            $exportPayload = count($payloads) === 1 ? $payloads[0] : ['type' => 'all', 'items' => $payloads];
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="dapodik-' . ($isAllMode ? 'semua' : preg_replace('/[^a-z0-9_-]/i', '-', $type)) . '.json"');
            echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($bridgeUrl === '' || $bridgeToken === '') {
            throw new RuntimeException('Link Web Raport dan Token Web Service Dapodik wajib diisi untuk sinkron langsung.');
        }

        $responses = [];
        $stoppedForBridgeToken = false;
        foreach ($payloads as $payload) {
            $response = helper_post($bridgeUrl, $payload, $bridgeToken);
            $responses[] = helper_response_label((string)$payload['type'], $response);
            if (helper_is_bridge_token_error($response)) {
                $stoppedForBridgeToken = true;
                break;
            }
        }
        $result = ($stoppedForBridgeToken
            ? "Sinkron dihentikan karena token sinkron tidak valid.\n"
            : "Sinkron selesai.\n")
            . implode("\n", $responses);
    }
} catch (Throwable $exception) {
    $result = 'Gagal: ' . $exception->getMessage();
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$defaults = [
    'dapodik_url' => $_POST['dapodik_url'] ?? $_GET['dapodik_url'] ?? helper_embedded_default(HELPER_DEFAULT_DAPODIK_URL_B64, 'http://127.0.0.1:5774'),
    'dapodik_token' => $_POST['dapodik_token'] ?? $_GET['dapodik_token'] ?? helper_embedded_default(HELPER_DEFAULT_DAPODIK_TOKEN_B64),
    'npsn' => $_POST['npsn'] ?? $_GET['npsn'] ?? helper_embedded_default(HELPER_DEFAULT_NPSN_B64),
    'bridge_url' => $_POST['bridge_url'] ?? $_GET['bridge_url'] ?? helper_embedded_default(HELPER_DEFAULT_BRIDGE_URL_B64),
    'bridge_token' => $_POST['bridge_token'] ?? $_GET['bridge_token'] ?? helper_embedded_default(HELPER_DEFAULT_BRIDGE_TOKEN_B64),
    'type' => $_POST['type'] ?? $_GET['type'] ?? helper_embedded_default(HELPER_DEFAULT_TYPE_B64, 'all'),
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Helper Sinkron Dapodik Lokal <?= h(HELPER_VERSION) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #eef2f7; color: #111827; }
        main { max-width: 960px; margin: 32px auto; background: #fff; border: 1px solid #d7deea; border-radius: 8px; padding: 24px; }
        h1 { margin-top: 0; font-size: 24px; }
        form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: grid; gap: 6px; font-weight: 700; }
        input, select, textarea { border: 1px solid #bfccdc; border-radius: 6px; padding: 10px; font: inherit; }
        textarea { grid-column: 1 / -1; min-height: 220px; font-family: Consolas, monospace; font-size: 12px; }
        .wide { grid-column: 1 / -1; }
        .actions { display: flex; gap: 10px; align-items: end; }
        button { border: 0; border-radius: 6px; padding: 11px 16px; background: #0b63f6; color: #fff; font-weight: 700; cursor: pointer; }
        button.success { background: #059669; }
        button.secondary { background: #0f172a; }
        .result { margin: 18px 0; padding: 12px; background: #f8fafc; border-left: 4px solid #0b63f6; white-space: pre-wrap; }
        code { display: block; background: #f8fafc; padding: 10px; border-radius: 6px; overflow: auto; }
    </style>
</head>
<body>
<main>
    <h1>Helper Sinkron Dapodik Lokal <?= h(HELPER_VERSION) ?></h1>
    <p>Jalankan file ini di komputer yang bisa mengakses Web Service Dapodik lokal. Isi NPSN, Link Web Raport, dan Token Web Service Dapodik seperti model RaportKu. Sinkron Semua mengambil sekolah, guru, rombel, siswa, anggota rombel, dan pembelajaran. Mapel dibuat dari pembelajaran yang ada gurunya.</p>
    <?php if ($result): ?><div class="result"><?= h($result) ?></div><?php endif; ?>
    <form method="post">
        <label>URL Dapodik Lokal
            <input name="dapodik_url" value="<?= h($defaults['dapodik_url']) ?>" placeholder="http://127.0.0.1:5774">
        </label>
        <label>Token Web Service Dapodik / Token Sinkron
            <input name="dapodik_token" value="<?= h($defaults['dapodik_token']) ?>">
        </label>
        <label>NPSN
            <input name="npsn" value="<?= h($defaults['npsn']) ?>">
        </label>
        <label>Jenis Data
            <select name="type">
                <?php foreach (helper_data_types(true) as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $defaults['type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Link Web Raport
            <input name="bridge_url" value="<?= h($defaults['bridge_url']) ?>" placeholder="https://domain-erapor">
        </label>
        <div class="actions wide">
            <button name="mode" value="sync">Sinkron Jenis Ini</button>
            <button class="success" name="mode" value="sync_all">Sinkron Semua Data Dasar</button>
            <button class="secondary" name="mode" value="export">Export Jenis Ini</button>
            <button class="secondary" name="mode" value="export_all">Export Semua JSON</button>
        </div>
        <textarea readonly><?= h($payloadPreview) ?></textarea>
    </form>
</main>
</body>
</html>
