<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('Access-Control-Allow-Origin: *');

$appName = (string)config('app_name', 'E-Raport KumerBot');
$schoolName = '';

try {
    if (function_exists('table_exists') && table_exists('school_profile')) {
        $row = fetch_one('SELECT name FROM school_profile ORDER BY id LIMIT 1');
        if (!empty($row['name'])) {
            $schoolName = trim((string)$row['name']);
        }
    }
} catch (Throwable) {
    $schoolName = '';
}

$title = $schoolName !== '' ? $schoolName : $appName;
$short = $schoolName !== '' ? mb_substr($schoolName, 0, 20) : 'E-Raport';

$startUrl = app_url('index.php');
$scopeUrl = app_url('');

$manifest = [
    'name'             => $title,
    'short_name'       => $short,
    'description'      => 'Sistem Administrasi Akademik — Absensi, Jurnal, dan E-Rapor',
    'start_url'        => $startUrl,
    'scope'            => $scopeUrl,
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#ffffff',
    'theme_color'      => '#2563eb',
    'lang'             => 'id',
    'dir'              => 'ltr',
    'categories'       => ['education', 'productivity'],
    'icons'            => [
        [
            'src'     => app_url('icon-192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => app_url('icon-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => app_url('icon-maskable-192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src'     => app_url('icon-maskable-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
    'shortcuts'        => [
        [
            'name'       => 'Absensi Guru',
            'short_name' => 'Absensi',
            'url'        => route_url('teacher-attendance-self'),
            'icons'      => [
                ['src' => app_url('icon-96.png'), 'sizes' => '96x96'],
            ],
        ],
        [
            'name'       => 'Alur Mengajar',
            'short_name' => 'Mengajar',
            'url'        => route_url('teaching-wizard'),
            'icons'      => [
                ['src' => app_url('icon-96.png'), 'sizes' => '96x96'],
            ],
        ],
        [
            'name'       => 'Jurnal Harian',
            'short_name' => 'Jurnal',
            'url'        => route_url('journals'),
            'icons'      => [
                ['src' => app_url('icon-96.png'), 'sizes' => '96x96'],
            ],
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
