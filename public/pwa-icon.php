<?php

declare(strict_types=1);

/**
 * PWA Icon Generator
 * Menghasilkan ikon PNG dari logo sekolah, atau fallback ke canvas teks.
 * URL: /pwa-icon.php?size=192
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

$validSizes = [48, 72, 96, 128, 144, 152, 192, 384, 512];
$size = (int)($_GET['size'] ?? 192);
if (!in_array($size, $validSizes, true)) {
    $size = 192;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$logoPath = '';
$isCustom = false;
try {
    if (function_exists('signature_asset')) {
        $asset = signature_asset('logo');
        if ($asset && !empty($asset['file_path'])) {
            $candidate = app_file_path((string)$asset['file_path'], ['storage/uploads/signatures', 'storage']);
            if (is_file($candidate)) {
                $logoPath = $candidate;
                $isCustom = true;
            }
        }
    }
} catch (Throwable) {
    $logoPath = '';
}

// Fallback ke logo sekolah bawaan jika belum ada upload kustom
if ($logoPath === '') {
    $staticCandidates = [
        APP_ROOT . '/public/assets/logo.png',
        APP_ROOT . '/mobile/assets/logo.png',
    ];
    foreach ($staticCandidates as $sc) {
        if (is_file($sc)) {
            $logoPath = $sc;
            break;
        }
    }
}

// Jika bukan kustom dan file statis ukuran yang cocok sudah ada, stream langsung
if (!$isCustom) {
    $staticIcon = APP_ROOT . '/public/icon-maskable-' . $size . '.png';
    if (is_file($staticIcon)) {
        readfile($staticIcon);
        exit;
    }
}

// 1. Jika ada logo sekolah dan GD aktif, resize logo dengan safe padding untuk launcher Android
if ($logoPath !== '' && extension_loaded('gd')) {
    try {
        $info = @getimagesize($logoPath);
        if ($info && !empty($info['mime'])) {
            $src = match ($info['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($logoPath),
                'image/png'  => @imagecreatefrompng($logoPath),
                'image/webp' => @imagecreatefromwebp($logoPath),
                'image/gif'  => @imagecreatefromgif($logoPath),
                default      => false,
            };
            if ($src !== false) {
                $dst = imagecreatetruecolor($size, $size);
                // Background putih bersih untuk Android launcher
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefill($dst, 0, 0, $white);

                // Safe zone 80% (10% padding tiap sisi agar tidak terpotong launcher circle/squircle)
                $pad = (int)round($size * 0.10);
                $innerSize = $size - ($pad * 2);

                imagealphablending($dst, true);
                imagecopyresampled($dst, $src, $pad, $pad, 0, 0, $innerSize, $innerSize, (int)$info[0], (int)$info[1]);
                imagepng($dst);
                imagedestroy($src);
                imagedestroy($dst);
                exit;
            }
        }
    } catch (Throwable) {
        // Fallback ke generator di bawah
    }
}

// 2. Fallback: buat ikon profesional pakai GD
if (extension_loaded('gd')) {
    try {
        $img = imagecreatetruecolor($size, $size);
        $bg  = imagecolorallocate($img, 37, 99, 235);   // Primary Blue (#2563eb)
        $fg  = imagecolorallocate($img, 255, 255, 255); // White
        imagefill($img, 0, 0, $bg);

        // Ambil inisial nama sekolah jika ada
        $label = 'ER';
        try {
            if (function_exists('get_school_profile')) {
                $sc = get_school_profile();
                if (!empty($sc['name'])) {
                    $words = preg_split('/\s+/', trim((string)$sc['name']));
                    if (count($words) >= 2) {
                        $label = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                    }
                }
            }
        } catch (Throwable) {}

        $font = 5; // Built-in GD font
        $tw   = imagefontwidth($font) * strlen($label);
        $th   = imagefontheight($font);
        imagestring($img, $font, (int)(($size - $tw) / 2), (int)(($size - $th) / 2), $label, $fg);
        imagepng($img);
        imagedestroy($img);
        exit;
    } catch (Throwable) {
        // Fallback ke PNG transparan
    }
}

// 3. Fallback terakhir: PNG 1x1 transparan
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
