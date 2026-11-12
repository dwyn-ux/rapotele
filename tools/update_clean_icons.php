<?php
$srcFile = 'public/assets/logo.png';
$im = imagecreatefrompng($srcFile);
$w = imagesx($im);
$h = imagesy($im);

// 1. BFS to detect all connected outer black background pixels
$visited = array_fill(0, $h, array_fill(0, $w, false));
$queue = new SplQueue();

for ($x = 0; $x < $w; $x++) {
    $c = imagecolorsforindex($im, imagecolorat($im, $x, 0));
    if ($c['red'] < 30 && $c['green'] < 30 && $c['blue'] < 30) {
        $queue->enqueue([$x, 0]); $visited[0][$x] = true;
    }
    $c = imagecolorsforindex($im, imagecolorat($im, $x, $h - 1));
    if ($c['red'] < 30 && $c['green'] < 30 && $c['blue'] < 30) {
        $queue->enqueue([$x, $h - 1]); $visited[$h - 1][$x] = true;
    }
}
for ($y = 0; $y < $h; $y++) {
    $c = imagecolorsforindex($im, imagecolorat($im, 0, $y));
    if (!$visited[$y][0] && $c['red'] < 30 && $c['green'] < 30 && $c['blue'] < 30) {
        $queue->enqueue([0, $y]); $visited[$y][0] = true;
    }
    $c = imagecolorsforindex($im, imagecolorat($im, $w - 1, $y));
    if (!$visited[$y][$w - 1] && $c['red'] < 30 && $c['green'] < 30 && $c['blue'] < 30) {
        $queue->enqueue([$w - 1, $y]); $visited[$y][$w - 1] = true;
    }
}

$dirs = [[1,0], [-1,0], [0,1], [0,-1]];
while (!$queue->isEmpty()) {
    [$cx, $cy] = $queue->dequeue();
    foreach ($dirs as [$dx, $dy]) {
        $nx = $cx + $dx; $ny = $cy + $dy;
        if ($nx >= 0 && $nx < $w && $ny >= 0 && $ny < $h && !$visited[$ny][$nx]) {
            $c = imagecolorsforindex($im, imagecolorat($im, $nx, $ny));
            if ($c['red'] < 35 && $c['green'] < 35 && $c['blue'] < 35) {
                $visited[$ny][$nx] = true;
                $queue->enqueue([$nx, $ny]);
            }
        }
    }
}

// 2. Find bounding box of logo
$minX = $w; $maxX = 0; $minY = $h; $maxY = 0;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if (!$visited[$y][$x]) {
            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

$bw = $maxX - $minX + 1;
$bh = $maxY - $minY + 1;

// 3. Create cropped transparent logo with smooth edges
$cropped = imagecreatetruecolor($bw, $bh);
imagealphablending($cropped, false);
imagesavealpha($cropped, true);

for ($y = 0; $y < $bh; $y++) {
    for ($x = 0; $x < $bw; $x++) {
        $srcX = $minX + $x;
        $srcY = $minY + $y;
        if ($visited[$srcY][$srcX]) {
            $color = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        } else {
            $c = imagecolorsforindex($im, imagecolorat($im, $srcX, $srcY));
            $nearBg = 0;
            foreach ($dirs as [$dx, $dy]) {
                $adjX = $srcX + $dx; $adjY = $srcY + $dy;
                if ($adjX >= 0 && $adjX < $w && $adjY >= 0 && $adjY < $h && $visited[$adjY][$adjX]) {
                    $nearBg++;
                }
            }
            if ($nearBg > 0 && ($c['red'] < 50 && $c['green'] < 50 && $c['blue'] < 50)) {
                $alpha = min(127, (int)(127 * (1 - (max($c['red'], $c['green'], $c['blue']) / 50))));
                $color = imagecolorallocatealpha($cropped, $c['red'], $c['green'], $c['blue'], $alpha);
            } else {
                $color = imagecolorallocatealpha($cropped, $c['red'], $c['green'], $c['blue'], 0);
            }
        }
        imagesetpixel($cropped, $x, $y, $color);
    }
}

function buildIcon($src, $size, $bgHex = null, $paddingRatio = 0.08) {
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    if ($bgHex !== null) {
        $r = hexdec(substr($bgHex, 1, 2));
        $g = hexdec(substr($bgHex, 3, 2));
        $b = hexdec(substr($bgHex, 5, 2));
        $bgColor = imagecolorallocatealpha($dst, $r, $g, $b, 0);
    } else {
        $bgColor = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    }
    imagefilledrectangle($dst, 0, 0, $size, $size, $bgColor);
    imagealphablending($dst, true);

    $pad = (int)round($size * $paddingRatio);
    $availSize = $size - ($pad * 2);

    $scale = min($availSize / $srcW, $availSize / $srcH);
    $targetW = (int)round($srcW * $scale);
    $targetH = (int)round($srcH * $scale);
    $targetX = (int)round(($size - $targetW) / 2);
    $targetY = (int)round(($size - $targetH) / 2);

    imagecopyresampled($dst, $src, $targetX, $targetY, 0, 0, $targetW, $targetH, $srcW, $srcH);
    return $dst;
}

// 1. Primary app logo: 1024x1024 transparent
$cleanLogo = buildIcon($cropped, 1024, null, 0.04);
imagepng($cleanLogo, 'public/assets/logo.png');
if (is_dir('mobile/assets')) {
    imagepng($cleanLogo, 'mobile/assets/logo.png');
}
echo "Updated public/assets/logo.png and mobile/assets/logo.png\n";

// 2. PWA Standard Icons (transparent for splash screen & app use)
$i512 = buildIcon($cropped, 512, null, 0.06);
imagepng($i512, 'public/icon-512.png');

$i192 = buildIcon($cropped, 192, null, 0.06);
imagepng($i192, 'public/icon-192.png');

$i96 = buildIcon($cropped, 96, null, 0.05);
imagepng($i96, 'public/icon-96.png');
echo "Updated icon-512.png, icon-192.png, icon-96.png\n";

// 3. Maskable Icons (Android launcher adaptive icons with white safe-zone background)
$m512 = buildIcon($cropped, 512, '#ffffff', 0.14);
imagepng($m512, 'public/icon-maskable-512.png');

$m192 = buildIcon($cropped, 192, '#ffffff', 0.14);
imagepng($m192, 'public/icon-maskable-192.png');
echo "Updated icon-maskable-512.png, icon-maskable-192.png\n";

// 4. Apple Touch Icons (iOS uses white background with safe margin)
$appleIcon = buildIcon($cropped, 180, '#ffffff', 0.10);
imagepng($appleIcon, 'public/apple-touch-icon.png');
imagepng($appleIcon, 'public/apple-touch-icon-precomposed.png');
echo "Updated apple-touch-icon.png and apple-touch-icon-precomposed.png\n";
