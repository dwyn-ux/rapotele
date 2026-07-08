<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaPath = $root . '/database/schema.sql';
$outputPath = $root . '/database/import_clean_mysql.sql';

if (!is_file($schemaPath)) {
    fwrite(STDERR, "Schema tidak ditemukan: $schemaPath\n");
    exit(1);
}

$schema = trim((string)file_get_contents($schemaPath));
if (!preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/i', $schema, $matches)) {
    fwrite(STDERR, "Tidak menemukan daftar tabel di schema.\n");
    exit(1);
}

$tableNames = array_values(array_unique($matches[1]));
$dropStatements = [];
foreach (array_reverse($tableNames) as $tableName) {
    $dropStatements[] = 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $tableName) . '`;';
}

$now = date('Y-m-d H:i:s');
$adminHash = password_hash('administrator', PASSWORD_DEFAULT);
$dapodikBridgeToken = 'bridge-' . bin2hex(random_bytes(16));
$whatsappCronSecret = bin2hex(random_bytes(12));

function sql_quote(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
}

$inserts = [];
$inserts[] = "INSERT INTO school_profile (name, academic_year, semester, created_at, updated_at) VALUES ('Nama Sekolah', '2025/2026', 'Genap', " . sql_quote($now) . ', ' . sql_quote($now) . ');';
$inserts[] = "INSERT INTO users (username, password_hash, name, email, role, teacher_id, student_id, telegram_chat_id, active, created_at, updated_at) VALUES ('administrator', " . sql_quote($adminHash) . ", 'Administrator', 'adminrapor@sekolah.local', 'admin', NULL, NULL, NULL, 1, " . sql_quote($now) . ', ' . sql_quote($now) . ');';

$settings = [
    'dapodik_url' => '',
    'dapodik_token' => '',
    'dapodik_npsn' => '',
    'dapodik_bridge_token' => $dapodikBridgeToken,
    'whatsapp_mode' => 'simulate',
    'whatsapp_access_token' => '',
    'whatsapp_phone_number_id' => '',
    'whatsapp_waba_id' => '',
    'whatsapp_graph_version' => 'v23.0',
    'whatsapp_cloud_delivery' => 'text',
    'whatsapp_fonnte_token' => '',
    'whatsapp_fonnte_country_code' => '62',
    'whatsapp_cron_secret' => $whatsappCronSecret,
];

foreach ($settings as $key => $value) {
    $inserts[] = 'INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (' . sql_quote($key) . ', ' . sql_quote($value) . ', ' . sql_quote($now) . ');';
}

$sql = "-- E-Raport KumerBot clean MySQL import\n"
    . "-- Generated: $now\n"
    . "-- Initial login: administrator / administrator\n"
    . "-- This dump drops existing app tables, then creates schema + minimal required data only.\n"
    . "-- No demo students, teachers, grades, or attendance.\n\n"
    . "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
    . "SET FOREIGN_KEY_CHECKS=0;\n\n"
    . "-- Bersihkan tabel aplikasi agar data dummy lama tidak ikut terbawa.\n"
    . implode("\n", $dropStatements) . "\n\n"
    . $schema . "\n\n"
    . implode("\n", $inserts) . "\n\n"
    . "SET FOREIGN_KEY_CHECKS=1;\n";

if (file_put_contents($outputPath, $sql, LOCK_EX) === false) {
    fwrite(STDERR, "Gagal menulis: $outputPath\n");
    exit(1);
}

echo $outputPath . PHP_EOL;
