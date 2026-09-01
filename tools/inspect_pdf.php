<?php

declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php tools/inspect_pdf.php path/to/file.pdf\n");
    exit(1);
}

$pdf = file_get_contents($path);
preg_match_all('/\/MediaBox\s*\[([^\]]+)\]/', $pdf, $boxes);
echo 'media=' . implode(' | ', array_unique($boxes[1] ?? [])) . PHP_EOL;
preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
echo 'pages=' . count($pages[0]) . PHP_EOL;
preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
echo 'streams=' . count($streams[1]) . PHP_EOL;

foreach ($streams[1] as $index => $stream) {
    $decoded = @gzuncompress($stream);
    if ($decoded === false) {
        $decoded = @gzinflate(substr($stream, 2));
    }
    if ($decoded === false || !preg_match('/[A-Za-z]{4}/', $decoded)) {
        continue;
    }

    $printable = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $decoded);
    echo "--- stream {$index} len=" . strlen($decoded) . " ---" . PHP_EOL;
    echo $printable . PHP_EOL;
}
