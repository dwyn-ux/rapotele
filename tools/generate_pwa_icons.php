<?php

declare(strict_types=1);

$srcPath = __DIR__ . '/../mobile/assets/logo.png';
if (!file_exists($srcPath)) {
    echo "Source logo not found at $srcPath\n";
    exit(1);
}

$srcInfo = getimagesize($srcPath);
echo "Source: " . $srcInfo[0] . "x" . $srcInfo[1] . " " . $srcInfo['mime'] . "\n";

$src = imagecreatefrompng($srcPath);
imagealphablending($src, true);
imagesavealpha($src, true);

// 1. Copy original logo to public/assets/logo.png
$publicAssetsDir = __DIR__ . '/../public/assets';
if (!is_dir($publicAssetsDir)) {
    mkdir($publicAssetsDir, 0777, true);
}
copy($srcPath, $publicAssetsDir . '/logo.png');
echo "Copied to public/assets/logo.png\n";

// Function to generate 'any' icon (transparent background preserved)
function generateAnyIcon($src, int $size, string $destPath): void {
    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);

    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $srcW, $srcH);
    imagesavealpha($dst, true);

    imagepng($dst, $destPath, 9);
    imagedestroy($dst);
    echo "Generated $destPath ($size x $size)\n";
}

// Function to generate 'maskable' icon (with padding + white or solid background so launcher crop doesn't cut logo)
function generateMaskableIcon($src, int $size, string $destPath): void {
    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $dst = imagecreatetruecolor($size, $size);
    // Background putih bersih untuk maskable Android
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $size, $size, $white);

    // Safe zone is 80% (10% padding on each side)
    $pad = (int)round($size * 0.10);
    $innerSize = $size - ($pad * 2);

    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, $pad, $pad, 0, 0, $innerSize, $innerSize, $srcW, $srcH);

    imagepng($dst, $destPath, 9);
    imagedestroy($dst);
    echo "Generated Maskable $destPath ($size x $size)\n";
}

$publicDir = __DIR__ . '/../public';

// Standard PWA icons (purpose: any)
generateAnyIcon($src, 192, $publicDir . '/icon-192.png');
generateAnyIcon($src, 512, $publicDir . '/icon-512.png');

// Maskable PWA icons (purpose: maskable)
generateMaskableIcon($src, 192, $publicDir . '/icon-maskable-192.png');
generateMaskableIcon($src, 512, $publicDir . '/icon-maskable-512.png');

// Also generate 96px for shortcuts and apple touch icon
generateMaskableIcon($src, 96, $publicDir . '/icon-96.png');
generateMaskableIcon($src, 180, $publicDir . '/apple-touch-icon.png');
generateMaskableIcon($src, 192, $publicDir . '/apple-touch-icon-precomposed.png');

imagedestroy($src);
echo "All PWA icons generated successfully!\n";
