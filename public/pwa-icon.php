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

// Coba ambil logo sekolah dari storage
$logoPath = '';
if (function_exists('fetch_one')) {
    $logo = fetch_one(
        "SELECT file_path FROM media_files WHERE media_type = 'logo' ORDER BY id DESC LIMIT 1"
    );
    if ($logo && !empty($logo['file_path'])) {
        $candidate = APP_ROOT . '/storage/' . ltrim((string)$logo['file_path'], '/');
        if (is_file($candidate)) {
            $logoPath = $candidate;
        }
    }
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

if ($logoPath !== '' && extension_loaded('gd')) {
    // Resize logo ke ukuran yang diminta
    $info = @getimagesize($logoPath);
    if ($info) {
        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($logoPath),
            'image/png'  => @imagecreatefrompng($logoPath),
            'image/webp' => @imagecreatefromwebp($logoPath),
            'image/gif'  => @imagecreatefromgif($logoPath),
            default      => false,
        };
        if ($src !== false) {
            $dst = imagecreatetruecolor($size, $size);
            // Latar putih
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, (int)$info[0], (int)$info[1]);
            imagepng($dst);
            imagedestroy($src);
            imagedestroy($dst);
            exit;
        }
    }
}

// Fallback: buat ikon teks sederhana pakai GD
if (extension_loaded('gd')) {
    $img  = imagecreatetruecolor($size, $size);
    $bg   = imagecolorallocate($img, 37, 99, 235);   // blue-600
    $fg   = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);
    $label = 'ER';
    $font  = 5; // built-in GD font
    $tw    = imagefontwidth($font) * strlen($label);
    $th    = imagefontheight($font);
    $scale = min((int)($size * 0.4 / $tw), (int)($size * 0.4 / $th));
    $scale = max(1, $scale);
    // GD tidak support scale font natively, cukup tulis di tengah
    imagestring($img, $font, (int)(($size - $tw) / 2), (int)(($size - $th) / 2), $label, $fg);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// Fallback terakhir: PNG 1x1 transparan
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
