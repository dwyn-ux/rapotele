<?php

declare(strict_types=1);

final class SimplePdf
{
    private array $pages = [];
    private string $content = '';
    private array $images = [];
    private int $imageSeq = 1;
    private float $width = 595.28;
    private float $height = 841.89;
    private string $font = 'Helvetica';
    private float $fontSize = 10.0;
    private bool $italic = false;

    public function addPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }
        $this->content = "2 J\n0.57 w\n";
        $this->setTextColor(0, 0, 0);
        $this->setFillColor(1, 1, 1);
        $this->setFont('Helvetica', 10);
    }

    public function output(): string
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->content = '';
        }

        $objects = [];
        $pageObjects = [];
        $fontRegular = 3;
        $fontBold = 4;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '';
        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

        $next = 6;
        $imageResources = '';
        foreach ($this->images as $name => &$image) {
            $imageObject = $next++;
            $image['object'] = $imageObject;
            $objects[$imageObject] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "\nendstream";
            $imageResources .= ' /' . $name . ' ' . $imageObject . ' 0 R';
        }
        unset($image);
        $xObjectResource = $imageResources !== '' ? ' /XObject <<' . $imageResources . ' >>' : '';

        foreach ($this->pages as $pageContent) {
            $contentObject = $next++;
            $pageObject = $next++;
            $compressed = gzcompress($pageContent, 6);
            $objects[$contentObject] = "<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n" . $compressed . "\nendstream";
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 5 0 R >>%s >> /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $fontRegular,
                $fontBold,
                $xObjectResource,
                $contentObject
            );
            $pageObjects[] = $pageObject . ' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageObjects) . ' /Kids [' . implode(' ', $pageObjects) . '] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    public function setFont(string $family, float $size, bool $bold = false): void
    {
        $this->font = $bold ? 'Helvetica-Bold' : 'Helvetica';
        $this->fontSize = $size;
        $this->content .= sprintf("BT /%s %.2F Tf ET\n", $bold ? 'F2' : 'F1', $size);
    }

    public function setTextColor(float $r, float $g, float $b): void
    {
        $this->content .= sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    public function setFillColor(float $r, float $g, float $b): void
    {
        $this->content .= sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    public function text(float $x, float $y, string $text, ?float $size = null, bool $bold = false): void
    {
        if ($size !== null || ($bold && $this->font !== 'Helvetica-Bold') || ($bold && $this->italic)) {
            $this->italic = false;
            $this->setFont('Helvetica', $size ?? $this->fontSize, $bold);
        }
        $this->content .= sprintf("q 0 g BT %.2F %.2F Td (%s) Tj ET Q\n", $x, $y, $this->escape($text));
    }

    public function italicText(float $x, float $y, string $text, float $size): void
    {
        $this->font = 'Helvetica-Oblique';
        $this->fontSize = $size;
        $this->italic = true;
        $this->content .= sprintf("q 0 g BT /F3 %.2F Tf %.2F %.2F Td (%s) Tj ET Q\n", $size, $x, $y, $this->escape($text));
    }

    public function centerText(float $x, float $y, float $w, string $text, ?float $size = null, bool $bold = false): void
    {
        $fontSize = $size ?? $this->fontSize;
        $textWidth = $this->stringWidth($text, $fontSize, $bold);
        $this->text($x + max(0, ($w - $textWidth) / 2), $y, $text, $fontSize, $bold);
    }

    public function justifyText(float $x, float $y, float $w, string $text, float $size = 9, bool $bold = false): array
    {
        $words = explode(' ', $text);
        $lines = $this->wrapText($text, $w, $size);
        $lineY = $y;
        foreach ($lines as $idx => $line) {
            $lineWords = explode(' ', $line);
            if (count($lineWords) <= 1 || $idx === count($lines) - 1) {
                $this->text($x, $lineY, $line, $size, $bold);
            } else {
                $lineWidth = $this->stringWidth($line, $size, $bold);
                $spaceCount = count($lineWords) - 1;
                $spaceWidth = ($w - ($lineWidth - $this->stringWidth(' ', $size, $bold) * $spaceCount)) / $spaceCount;
                $cx = $x;
                foreach ($lineWords as $wi => $word) {
                    $this->text($cx, $lineY, $word, $size, $bold);
                    $cx += $this->stringWidth($word, $size, $bold) + ($wi < $spaceCount ? $spaceWidth : 0);
                }
            }
            $lineY -= 11.34;
        }
        return $lines;
    }

    public function justifyTextClamped(float $x, float $y, float $w, string $text, float $size, int $maxLines, bool $bold = false): void
    {
        $allLines = $this->wrapText($text, $w, $size);
        $lines = array_slice($allLines, 0, $maxLines);
        $lineY = $y;
        $total = count($lines);
        foreach ($lines as $idx => $line) {
            $lineWords = explode(' ', $line);
            $isLast = $idx === $total - 1;
            if (count($lineWords) <= 1 || $isLast) {
                $this->text($x, $lineY, $line, $size, $bold);
            } else {
                $lineWidth = $this->stringWidth($line, $size, $bold);
                $spaceCount = count($lineWords) - 1;
                $spaceWidth = ($w - ($lineWidth - $this->stringWidth(' ', $size, $bold) * $spaceCount)) / $spaceCount;
                $cx = $x;
                foreach ($lineWords as $wi => $word) {
                    $this->text($cx, $lineY, $word, $size, $bold);
                    $cx += $this->stringWidth($word, $size, $bold) + ($wi < $spaceCount ? $spaceWidth : 0);
                }
            }
            $lineY -= 11.34;
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->content .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    }

    public function rect(float $x, float $y, float $w, float $h, string $style = 'S', array $fill = [1, 1, 1]): void
    {
        if ($style === 'F' || $style === 'B') {
            $this->content .= sprintf("%.3F %.3F %.3F rg\n", $fill[0], $fill[1], $fill[2]);
        }
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $y, $w, $h, $style);
    }

    public function image(string $path, float $x, float $topY, float $w, float $h): bool
    {
        $name = $this->registerImage($path);
        if ($name === null) {
            return false;
        }

        $this->content .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, $topY - $h, $name);
        return true;
    }

    public function wrapText(string $text, float $width, float $fontSize = 9): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->stringWidth($candidate, $fontSize, false) <= $width || $line === '') {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines ?: [''];
    }

    public function stringWidth(string $text, float $fontSize = 10, bool $bold = false): float
    {
    $factor = $bold ? 0.56 : 0.52;
    return mb_strlen($text) * $fontSize * $factor;
    }

    private function escape(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], (string)$text);
    }

    private function registerImage(string $path): ?string
    {
        if (!is_file($path) || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        $key = realpath($path) ?: $path;
        foreach ($this->images as $name => $image) {
            if (($image['path'] ?? '') === $key) {
                return $name;
            }
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            return null;
        }

        $source = match ((int)($info[2] ?? 0)) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 88);
        $data = (string)ob_get_clean();
        imagedestroy($canvas);
        if ($data === '') {
            return null;
        }

        $name = 'Im' . $this->imageSeq++;
        $this->images[$name] = [
            'path' => $key,
            'width' => $width,
            'height' => $height,
            'data' => $data,
        ];

        return $name;
    }
}

function report_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/reports';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function report_file_path(int $studentId): string
{
    return report_storage_dir() . '/rapor-siswa-' . $studentId . '.pdf';
}

function report_file_name(array $student): string
{
    $name = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$student['name']);
    $name = trim((string)$name) ?: 'Siswa';
    return 'Rapor_' . str_replace(' ', '_', $name) . '.pdf';
}

function class_phase(string $grade): string
{
    $number = (int)preg_replace('/\D+/', '', $grade);
    return match (true) {
        $number <= 2 => 'A',
        $number <= 4 => 'B',
        default => 'C',
    };
}

function semester_number(): string
{
    return current_semester_number();
}

function report_student_payload(int $studentId): array
{
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name, c.grade, c.homeroom_teacher_id, t.name AS homeroom_name, t.nip AS homeroom_nip
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id
         WHERE s.id = ?',
        [$studentId]
    );
    if (!$student) {
        throw new RuntimeException('Siswa tidak ditemukan.');
    }

    $school = report_get_school_profile();
    $subjects = report_subjects_for_student($student, $studentId);
    if (!$subjects) {
        $subjects = [
            ['name' => 'Bahasa Indonesia', 'group_name' => 'Kelompok A', 'score' => null, 'description' => ''],
            ['name' => 'Matematika', 'group_name' => 'Kelompok A', 'score' => null, 'description' => ''],
        ];
    }

    $attendance = report_attendance_summary_for_student($studentId);
    $reportDate = fetch_one('SELECT * FROM report_dates WHERE grade = ? ORDER BY report_date DESC LIMIT 1', [(string)($student['grade'] ?? '')]);
    $principal = [
        'name' => $school['principal_name'] ?: 'Nama Kepsek',
        'nip' => $school['principal_nip'] ?: '',
    ];

    return [
        'student' => $student,
        'school' => $school,
        'subjects' => $subjects,
        'attendance' => $attendance,
        'report_date' => $reportDate,
        'principal' => $principal,
        'photo' => report_student_photo($studentId),
        'logo' => report_logo_signature(),
        'signatures' => report_signatures_for_student($student),
        'cocurricular' => report_cocurricular_for_student($student),
        'extracurriculars' => report_extracurriculars_for_student($student),
        'homeroom_note' => trim((string)($reportDate['note'] ?? '')) ?: 'Menunjukkan sikap baik dan perlu terus dibiasakan belajar mandiri di rumah.',
        'graduation' => fetch_one('SELECT status, notes FROM graduations WHERE student_id = ?', [$studentId]),
    ];
}

function report_subjects_for_student(array $student, int $studentId): array
{
    $classId = (int)($student['class_id'] ?? 0);
    $grade = (string)($student['grade'] ?? '');
    $examWeight = (int)get_app_setting('grade.exam_weight', '40');
    $dailyWeight = 100 - $examWeight;
    $kkm = (int)get_app_setting('grade.kkm', '70');

    $subjects = [];

    $rows = [];
    $mapped = $grade !== ''
        ? (int)(fetch_one('SELECT COUNT(*) AS total FROM report_mappings WHERE grade = ? AND include_in_report = 1', [$grade])['total'] ?? 0)
        : 0;

    if ($mapped > 0) {
        $rows = fetch_all(
            'SELECT sub.id, sub.name, sub.group_name, COALESCE(sg.name, sub.group_name, ?) AS group_name,
                    AVG(g.score) AS daily_avg, es.score AS exam_score, fs.score AS final_score,
                    MIN(rm.display_order) AS display_order, MIN(sg.display_order) AS group_order
             FROM report_mappings rm
             JOIN subjects sub ON sub.id = rm.subject_id
             LEFT JOIN subject_groups sg ON sg.id = rm.group_id
             LEFT JOIN teaching_assignments ta ON ta.subject_id = sub.id AND ta.class_id = ?
             LEFT JOIN grades g ON g.assignment_id = ta.id AND g.student_id = ?
             LEFT JOIN exam_scores es ON es.subject_id = sub.id AND es.student_id = ?
             LEFT JOIN final_scores fs ON fs.subject_id = sub.id AND fs.student_id = ?
             WHERE rm.grade = ? AND rm.include_in_report = 1 AND sub.active = 1
             GROUP BY sub.id, sub.name, sub.group_name, sg.name, es.score, fs.score
             ORDER BY COALESCE(MIN(sg.display_order), 999), MIN(rm.display_order), sub.name',
            ['Kelompok A', $classId, $studentId, $studentId, $studentId, $grade]
        );
    }

    if (!$rows) {
        $rows = fetch_all(
            'SELECT sub.id, sub.name, sub.group_name, COALESCE(sub.group_name, ?) AS group_name,
                    AVG(g.score) AS daily_avg, es.score AS exam_score, fs.score AS final_score
             FROM subjects sub
             LEFT JOIN teaching_assignments ta ON ta.subject_id = sub.id AND ta.class_id = ?
             LEFT JOIN grades g ON g.assignment_id = ta.id AND g.student_id = ?
             LEFT JOIN exam_scores es ON es.subject_id = sub.id AND es.student_id = ?
             LEFT JOIN final_scores fs ON fs.subject_id = sub.id AND fs.student_id = ?
             WHERE sub.active = 1
             GROUP BY sub.id, sub.name, sub.group_name, es.score, fs.score
             ORDER BY sub.group_name, sub.name',
            ['Kelompok A', $classId, $studentId, $studentId, $studentId]
        );
    }

    $allObjectives = fetch_all(
        'SELECT lo.id, lo.subject_id, lo.description
         FROM learning_objectives lo
         WHERE lo.grade = ? AND lo.active = 1',
        [(string)$grade]
    );
    $objectivesBySubject = [];
    foreach ($allObjectives as $lo) {
        $sid = (int)$lo['subject_id'];
        $objectivesBySubject[$sid][] = $lo['description'];
    }

    foreach ($rows as $r) {
        $daily = (float)($r['daily_avg'] ?? 0);
        $exam = (float)($r['exam_score'] ?? 0);
        $final = (float)($r['final_score'] ?? 0);

        if ($final <= 0 && $daily > 0) {
            if ($exam > 0) {
                $final = ($daily * $dailyWeight / 100) + ($exam * $examWeight / 100);
            } else {
                $final = $daily;
            }
        } elseif ($final <= 0 && $exam > 0) {
            $final = $exam;
        }

        $finalRounded = $final > 0 ? (int)round($final) : 0;

        $desc = '';
        $sid = (int)$r['id'];
        $objectives = $objectivesBySubject[$sid] ?? [];
        if ($objectives) {
            $achieved = [];
            $needHelp = [];
            foreach ($objectives as $obj) {
                if ($finalRounded >= $kkm) {
                    $achieved[] = $obj;
                } else {
                    $needHelp[] = $obj;
                }
            }
            $parts = [];
            if ($achieved) {
                $parts[] = 'Mencapai kompetensi baik dalam ' . implode(', ', array_slice($achieved, 0, 3));
            }
            if ($needHelp) {
                $parts[] = 'Perlu peningkatan dalam memahami ' . implode(', ', array_slice($needHelp, 0, 2));
            }
            $desc = $parts ? implode('. ', $parts) . '.' : '';
        }
        if ($desc === '') {
            $desc = $finalRounded >= $kkm
                ? 'Mencapai kompetensi dengan baik.'
                : 'Perlu peningkatan dalam memahami kompetensi dasar.';
        }

        $subjects[] = [
            'name' => $r['name'],
            'group_name' => $r['group_name'],
            'kkm' => $kkm,
            'score' => $finalRounded > 0 ? $finalRounded : null,
            'predikat' => $finalRounded > 0 ? predikat_from_score($finalRounded) : '-',
            'description' => $desc,
        ];
    }

    return $subjects;
}

function predikat_from_score(int $score): string
{
    $predikatSetting = get_app_setting('grade.predikat', '');
    if ($predikatSetting !== '') {
        $ranges = explode(',', $predikatSetting);
        foreach ($ranges as $range) {
            $parts = explode('=', trim($range));
            if (count($parts) === 2) {
                $label = trim($parts[0]);
                $bounds = explode('-', trim($parts[1]));
                if (count($bounds) === 2 && $score >= (int)$bounds[0] && $score <= (int)$bounds[1]) {
                    return $label;
                }
            }
        }
    }

    if ($score >= 86) return 'A';
    if ($score >= 71) return 'B';
    if ($score >= 56) return 'C';
    return 'D';
}

function report_student_photo(int $studentId): ?array
{
    return table_exists('student_photos')
        ? fetch_one('SELECT * FROM student_photos WHERE student_id = ?', [$studentId])
        : null;
}

function report_logo_signature(): ?array
{
    return report_signature_by_type('logo');
}

function report_agency_logo_signature(): ?array
{
    return report_signature_by_type('logo_dinas');
}

function report_signature_by_type(string $type): ?array
{
    return table_exists('signatures')
        ? fetch_one('SELECT * FROM signatures WHERE type = ? ORDER BY id DESC LIMIT 1', [$type])
        : null;
}

function report_asset_path(string $filePath): string
{
    if ($filePath === '') {
        return '';
    }

    try {
        return app_file_path($filePath, ['storage/uploads/signatures', 'storage/uploads/student-photos']);
    } catch (Throwable) {
        return '';
    }
}

function report_signatures_for_student(array $student): array
{
    if (!table_exists('signatures')) {
        return ['principal' => null, 'homeroom' => null];
    }
    $principal = fetch_one("SELECT * FROM signatures WHERE type = 'ttd_kepsek' ORDER BY id DESC LIMIT 1");
    $homeroom = null;
    $homeroomTeacherId = (int)($student['homeroom_teacher_id'] ?? 0);
    if ($homeroomTeacherId > 0 && table_exists('users')) {
        $user = fetch_one('SELECT id FROM users WHERE teacher_id = ? ORDER BY id LIMIT 1', [$homeroomTeacherId]);
        if ($user) {
            $homeroom = fetch_one("SELECT * FROM signatures WHERE type = 'ttd_wali' AND user_id = ? ORDER BY id DESC LIMIT 1", [(int)$user['id']]);
        }
    }
    if (!$homeroom && !empty($student['homeroom_name'])) {
        $homeroom = fetch_one("SELECT * FROM signatures WHERE type = 'ttd_wali' AND person_name = ? ORDER BY id DESC LIMIT 1", [(string)$student['homeroom_name']]);
    }
    $homeroom = $homeroom ?: fetch_one("SELECT * FROM signatures WHERE type = 'ttd_wali' ORDER BY id DESC LIMIT 1");

    return ['principal' => $principal, 'homeroom' => $homeroom];
}

function report_cocurricular_for_student(array $student): array
{
    if (!table_exists('cocurricular_members')) {
        return [];
    }
    return fetch_one(
        'SELECT cg.name AS group_name, ct.name AS theme_name, ca.title AS activity_title,
                ca.description, ca.objective, t.name AS coordinator_name
         FROM cocurricular_members cm
         JOIN cocurricular_groups cg ON cg.id = cm.group_id
         LEFT JOIN cocurricular_themes ct ON ct.id = cg.theme_id
         LEFT JOIN cocurricular_activities ca ON ca.theme_id = cg.theme_id AND ca.phase = cg.phase AND ca.active = 1
         LEFT JOIN teachers t ON t.id = cg.coordinator_teacher_id
         WHERE cm.student_id = ? AND cg.active = 1
         ORDER BY cg.id DESC, ca.id DESC
         LIMIT 1',
        [(int)$student['id']]
    ) ?: [];
}

function report_cocurricular_text(array $payload): string
{
    $row = $payload['cocurricular'] ?? [];
    if (!$row) {
        return 'Pada semester ini, ananda menunjukkan capaian yang baik dalam penguatan profil lulusan melalui kegiatan kokurikuler. Ananda aktif mengikuti kegiatan, mampu bekerja sama, dan mulai menunjukkan tanggung jawab dalam proses belajar.';
    }
    $parts = [];
    $theme = trim((string)($row['theme_name'] ?? ''));
    $activity = trim((string)($row['activity_title'] ?? ''));
    if ($theme !== '' || $activity !== '') {
        $parts[] = 'Pada semester ini, ananda mengikuti kokurikuler ' . trim($theme . ' - ' . $activity, ' -') . '.';
    }
    foreach (['description', 'objective'] as $key) {
        $text = trim((string)($row[$key] ?? ''));
        if ($text !== '') {
            $parts[] = $text;
        }
    }
    return implode(' ', $parts) ?: 'Ananda mengikuti kegiatan kokurikuler dengan baik.';
}

function report_extracurriculars_for_student(array $student): array
{
    if (!table_exists('extracurriculars')) {
        return [];
    }
    $studentId = (int)($student['id'] ?? 0);
    if ($studentId > 0 && table_exists('extracurricular_members')) {
        $memberRows = fetch_all(
            'SELECT e.*, t.name AS teacher_name, es.score AS keterangan
             FROM extracurricular_members em
             JOIN extracurriculars e ON e.id = em.extracurricular_id
             LEFT JOIN teachers t ON t.id = e.teacher_id
             LEFT JOIN extracurricular_scores es ON es.student_id = em.student_id AND es.extracurricular_id = e.id
             WHERE e.active = 1 AND em.student_id = ?
             ORDER BY e.type, e.name
             LIMIT 2',
            [$studentId]
        );
        if ($memberRows) {
            return $memberRows;
        }
    }
    $className = (string)($student['class_name'] ?? '');
    $rows = fetch_all(
        'SELECT e.*, t.name AS teacher_name, es.score AS keterangan
         FROM extracurriculars e
         LEFT JOIN teachers t ON t.id = e.teacher_id
         LEFT JOIN extracurricular_scores es ON es.student_id = ? AND es.extracurricular_id = e.id
         WHERE e.active = 1 AND (e.class_name = ? OR e.class_name = ? OR e.class_name = ?)
         ORDER BY e.type, e.name
         LIMIT 2',
        [$studentId, $className, 'Semua Kelas', 'Pramuka Reguler']
    );
    if ($rows) {
        return $rows;
    }
    return fetch_all(
        'SELECT e.*, t.name AS teacher_name, es.score AS keterangan
         FROM extracurriculars e
         LEFT JOIN teachers t ON t.id = e.teacher_id
         LEFT JOIN extracurricular_scores es ON es.student_id = ? AND es.extracurricular_id = e.id
         WHERE e.active = 1
         ORDER BY e.type, e.name
         LIMIT 2',
        [$studentId]
    );
}

function report_attendance_summary_for_student(int $studentId): array
{
    $summary = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'terlambat' => 0];
    if (!table_exists('student_attendance_entries')) {
        return $summary;
    }
    $rows = fetch_all('SELECT status, COUNT(*) AS total FROM student_attendance_entries WHERE student_id = ? GROUP BY status', [$studentId]);
    foreach ($rows as $row) {
        $summary[(string)$row['status']] = (int)$row['total'];
    }
    return $summary;
}

function report_get_school_profile(): array
{
    if (!table_exists('school_profile')) {
        return [
            'name' => config('school.name'),
            'academic_year' => current_academic_year(),
            'semester' => current_semester(),
            'address' => '',
            'principal_name' => '',
            'principal_nip' => '',
        ];
    }

    $school = fetch_one('SELECT * FROM school_profile ORDER BY id LIMIT 1');
    return $school ?: [
        'name' => config('school.name'),
        'academic_year' => current_academic_year(),
        'semester' => current_semester(),
        'address' => '',
        'principal_name' => '',
        'principal_nip' => '',
    ];
}

function generate_student_report_pdf(int $studentId): string
{
    $payload = report_student_payload($studentId);
    $pdf = new SimplePdf();
    $lastLearningPage = draw_report_page_one($pdf, $payload);
    draw_report_page_two($pdf, $payload, $lastLearningPage + 1);
    $path = report_file_path($studentId);
    file_put_contents($path, $pdf->output());
    return $path;
}

function draw_report_identity(SimplePdf $pdf, array $payload, int $pageNo): void
{
    $student = $payload['student'];
    $school = $payload['school'];
    $class = $student['class_name'] ?: '-';

    $pdf->setFont('Helvetica', 10);
    $pdf->text(59.53, 775.11, 'Nama Murid');
    $pdf->text(161.58, 775.11, ':');
    $pdf->text(170.08, 775.11, (string)$student['name']);
    $pdf->text(391.18, 775.11, 'Kelas');
    $pdf->text(476.22, 775.11, ':');
    $pdf->text(484.72, 775.11, $class);

    $pdf->text(59.53, 760.94, 'NIS/NISN');
    $pdf->text(161.58, 760.94, ':');
    $pdf->text(170.08, 760.94, trim(($student['nis'] ?: '-') . ' / ' . ($student['nisn'] ?: '-')));
    $pdf->text(391.18, 760.94, 'Semester');
    $pdf->text(476.22, 760.94, ':');
    $pdf->text(484.72, 760.94, semester_number());

    $pdf->text(59.53, 746.76, 'Sekolah');
    $pdf->text(161.58, 746.76, ':');
    $schoolNameWidth = 391.18 - 170.08 - 10.0; // ruang sebelum label "Tahun Ajaran"

// stringWidth() meremehkan lebar huruf KAPITAL (~0.52/char vs realita ~0.70/char
// untuk Helvetica all-caps). Nama sekolah selalu huruf besar, jadi pakai font
// size "fiktif" 14pt khusus untuk HITUNG titik wrap-nya saja (bukan buat render) —
// ini bikin estimasi lebar (14 * 0.52 ≈ 7.28/char) mendekati lebar asli huruf kapital.
    $schoolNameLines = array_slice($pdf->wrapText((string)$school['name'], $schoolNameWidth, 14), 0, 2);
    $pdf->text(170.08, 746.76, $schoolNameLines[0] ?? '', 10);
    if (isset($schoolNameLines[1])) {
    $pdf->text(170.08, 746.76 - 11.34, $schoolNameLines[1], 10);
    }
    $pdf->text(391.18, 746.76, 'Tahun Ajaran');
    $pdf->text(476.22, 746.76, ':');
    $pdf->text(484.72, 746.76, (string)$school['academic_year']);
    $pdf->line(56.69, 722.83, 538.58, 722.83);

    $pdf->line(56.69, 36.85, 538.58, 36.85);
    $pdf->italicText(59.53, 20.43, $class . '  | ' . $student['name'] . ' | ' . $student['nis'], 7.5);
    $pdf->italicText(489.08, 20.43, 'Halaman : ' . $pageNo, 7.5);
}

function draw_report_asset_badge(SimplePdf $pdf, float $x, float $topY, float $w, float $h, string $label, string $filePath = ''): void
{
    $pdf->rect($x, $topY, $w, -$h, 'B', [1.000, 1.000, 1.000]);
    $assetPath = report_asset_path($filePath);
    if ($assetPath !== '' && $pdf->image($assetPath, $x + 2.00, $topY - 2.00, max(1, $w - 4.00), max(1, $h - 4.00))) {
        return;
    }

    $pdf->rect($x, $topY, $w, -$h, 'B', [0.949, 0.973, 1.000]);
    $pdf->setFont('Helvetica', 6.5, true);
    $pdf->centerText($x, $topY - ($h / 2) - 2.5, $w, $label, 6.5, true);
    if ($filePath !== '') {
        $pdf->setFont('Helvetica', 4.5);
        $pdf->centerText($x, $topY - $h + 4.5, $w, 'data', 4.5);
    }
}

function report_student_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials ?: 'FS';
}

/**
 * Single source of truth for the learning-results table geometry, shared by
 * the header row and the per-subject body rows so they never drift apart.
 * 'Predikat' was widened (28.35 -> 53.00, taken from 'Nilai' and 'Capaian
 * Kompetensi') because the label "Predikat" in bold 10pt does not fit in
 * 28.35pt and was being sliced by the column border.
 */
function report_learning_table_columns(): array
{
    return [
        'no'       => ['x' => 56.69,  'w' => 22.68,  'label' => 'No', 'fill' => true],
        'mapel'    => ['x' => 79.37,  'w' => 120.00, 'label' => 'Mata Pelajaran', 'fill' => true],
        'nilai'    => ['x' => 199.37, 'w' => 55.00,  'label' => 'Nilai Akhir', 'fill' => false],
        'capaian'  => ['x' => 254.37, 'w' => 284.21, 'label' => 'Capaian Kompetensi', 'fill' => false],
    ];
}


function draw_report_learning_table_header(SimplePdf $pdf): float
{
    $y = 715.00;
    $titleHeight = 17.01;
    // Title band: plain text, no fill and no border.
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText(56.69, $y - 11.50, 481.89, 'LAPORAN HASIL BELAJAR', 12, true);
    $y -= $titleHeight;

    // Column header row: only "No" and "Mata Pelajaran" get the light-blue fill.
    $headerRowHeight = 22.68;
    $pdf->setFont('Helvetica', 10, true);
    $labelY = $y - ($headerRowHeight / 2) - 3.5;
    foreach (report_learning_table_columns() as $col) {
        $style = $col['fill'] ? 'B' : 'S';
        $pdf->rect($col['x'], $y, $col['w'], -$headerRowHeight, $style, [0.973, 0.973, 1.000]);
        $pdf->centerText($col['x'], $labelY, $col['w'], $col['label'], 10, true);
    }
    return $y - $headerRowHeight;
}

function draw_report_page_one(SimplePdf $pdf, array $payload): int
{
    $pageNo = 1;
    $pdf->addPage();
    draw_report_identity($pdf, $payload, $pageNo);

    $y = draw_report_learning_table_header($pdf);
    $pageBottomLimit = 70.0;
    $currentGroup = '';
    $no = 1;
    foreach ($payload['subjects'] as $subject) {
        $group = $subject['group_name'] ?: 'Kelompok A';
        $description = trim((string)($subject['description'] ?? ''));
        if ($description === '') {
            $description = 'Menunjukkan perkembangan belajar yang baik pada mata pelajaran ' . $subject['name'] . '.';
        }
        $cols = report_learning_table_columns();
        $capaianTextWidth = $cols['capaian']['w'] - 10.78;
        $capaianLines = min(4, count($pdf->wrapText($description, $capaianTextWidth, 9)));
        $mapelLines = min(2, count($pdf->wrapText((string)$subject['name'], $cols['mapel']['w'] - 11.34, 9)));
        $height = 28.35 + ($capaianLines - 1) * 11.34;
        if ($mapelLines > 1) {
            $height = max($height, 28.35 + 11.34);
        }
        $needsGroupHeader = $group !== $currentGroup;
        $neededHeight = $height + ($needsGroupHeader ? 17.01 : 0);
        if ($y - $neededHeight < $pageBottomLimit) {
            $pageNo++;
            $pdf->addPage();
            draw_report_identity($pdf, $payload, $pageNo);
            $y = draw_report_learning_table_header($pdf);
            $currentGroup = '';
            $needsGroupHeader = true;
        }
        if ($needsGroupHeader) {
            $currentGroup = $group;
            $pdf->setFont('Helvetica', 9, true);
            $pdf->rect(56.69, $y, 481.89, -17.01, 'S');
            $pdf->text(59.53, $y - 11.21, $group, 9, true);
            $y -= 17.01;
        }
        $pdf->setFont('Helvetica', 9);
        $pdf->rect($cols['no']['x'], $y, $cols['no']['w'], -$height, 'S');
        $pdf->centerText($cols['no']['x'], $y - ($height / 2) - 3, $cols['no']['w'], (string)$no, 9);
        $pdf->rect($cols['mapel']['x'], $y, $cols['mapel']['w'], -$height, 'S');
        $mapelLines = $pdf->wrapText((string)$subject['name'], $cols['mapel']['w'] - 11.34, 9);
        $mapelLines = array_slice($mapelLines, 0, 2);
        $mapelLineCount = count($mapelLines);
        $mapelStartY = $y - ($height / 2) - 3;
        if ($mapelLineCount === 2) {
            $mapelStartY = $y - ($height / 2) + 2;
        }
        foreach ($mapelLines as $mlIdx => $ml) {
            $pdf->text($cols['mapel']['x'] + 5.67, $mapelStartY - ($mlIdx * 11.34), $ml, 9);
        }
        $pdf->rect($cols['nilai']['x'], $y, $cols['nilai']['w'], -$height, 'S');
        $score = $subject['score'] !== null ? (string)$subject['score'] : '';
        $pdf->centerText($cols['nilai']['x'], $y - ($height / 2) - 3, $cols['nilai']['w'], $score, 9);
        $pdf->rect($cols['capaian']['x'], $y, $cols['capaian']['w'], -$height, 'S');
        $pdf->justifyText($cols['capaian']['x'] + 5.67, $y - 11.55, $capaianTextWidth, $description, 9);
        $y -= $height;
        $no++;
    }

    return $pageNo;
}

function draw_kokurikuler_section(SimplePdf $pdf, array $payload, float $topY = 547.09): float
{
    $text = report_cocurricular_text($payload);
    $headerHeight = 22.68;
    $bodyHeight = 62.36;
    $bodyTop = $topY - $headerHeight;
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, $topY, 481.89, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(56.69, $topY - 14.34, 481.89, 'Kokurikuler', 10, true);
    $pdf->setFont('Helvetica', 9);
    $pdf->justifyTextClamped(62.36, $topY - 33.88, 460.00, $text, 9, 5);
    $pdf->rect(56.69, $bodyTop, 481.89, -$bodyHeight, 'S');

    return $bodyTop - $bodyHeight;
}

function draw_extrakurikuler_section(SimplePdf $pdf, array $payload, float $topY = 447.87): float
{
    $headerHeight = 22.68;
    $rowHeight = 17.01;
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, $topY, 22.68, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(56.69, $topY - 14.33, 22.68, 'No', 10, true);
    $pdf->rect(79.37, $topY, 141.73, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(79.37, $topY - 14.33, 141.73, 'Ekstrakurikuler', 10, true);
    $pdf->rect(221.10, $topY, 317.48, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(221.10, $topY - 14.33, 317.48, 'Keterangan ', 10, true);
    $pdf->setFont('Helvetica', 10);
    $rowTop = $topY - 22.67;
    $rows = array_values($payload['extracurriculars'] ?? []);
    for ($no = 1; $no <= 2; $no++) {
        $row = $rows[$no - 1] ?? [];
        $pdf->rect(56.69, $rowTop, 22.68, -$rowHeight, 'S');
        $pdf->centerText(56.69, $rowTop - 11.5, 22.68, (string)$no, 10);
        $pdf->rect(79.37, $rowTop, 141.73, -$rowHeight, 'S');
        if ($row) {
            $pdf->text(85.04, $rowTop - 11.5, trim((string)($row['name'] ?? '')), 9);
        }
        $pdf->rect(221.10, $rowTop, 317.48, -$rowHeight, 'S');
        if ($row) {
            $keterangan = trim((string)($row['keterangan'] ?? ''));
            if ($keterangan !== '') {
                $pdf->text(226.77, $rowTop - 11.5, $keterangan, 9);
            } else {
                $note = trim((string)($row['type'] ?? ''));
                $teacher = trim((string)($row['teacher_name'] ?? ''));
                $text = trim(($note !== '' ? $note : 'Aktif') . ($teacher !== '' ? ' - Pembina: ' . $teacher : ''));
                $pdf->text(226.77, $rowTop - 11.5, $text, 9);
            }
        }
        $rowTop -= $rowHeight;
    }

    return $rowTop;
}

function draw_attendance_note_section(SimplePdf $pdf, array $payload, float $topY = 377.01): float
{
    $att = $payload['attendance'];
    $headerHeight = 22.68;
    $rowHeight = 19.84;
    $parentGap = 14.18;
    $noteTop = $topY - $headerHeight;
    $parentHeaderTop = $topY - $headerHeight - (3 * $rowHeight) - $parentGap;
    $parentBodyTop = $parentHeaderTop - $headerHeight;
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, $topY, 150.24, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(56.69, $topY - 14.34, 150.24, 'Ketidakhadiran', 10, true);
    $pdf->rect(221.10, $topY, 317.48, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(221.10, $topY - 14.34, 317.48, 'Catatan Wali Kelas', 10, true);
    $pdf->setFont('Helvetica', 9);
    $rows = [
        [' Sakit', $att['sakit'] ?? 0, $noteTop],
        [' Izin ', $att['izin'] ?? 0, $noteTop - $rowHeight],
        [' Tanpa Keterangan ', $att['alpa'] ?? 0, $noteTop - (2 * $rowHeight)],
    ];
    foreach ($rows as [$label, $count, $y]) {
        $pdf->rect(56.69, $y, 90.71, -$rowHeight, 'S');
        $pdf->text(59.53, $y - 12.62, $label, 9);
        $pdf->rect(147.40, $y, 59.53, -$rowHeight, 'S');
        $pdf->text(150.24, $y - 12.62, ' : ' . ($count ?: '') . ' hari', 9);
    }
    $pdf->rect(221.10, $noteTop, 317.48, -59.53, 'S');
    $pdf->justifyTextClamped(226.77, $noteTop - 12.62, 300.00, (string)($payload['homeroom_note'] ?? ''), 9, 4);
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, $parentHeaderTop, 481.89, -$headerHeight, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(56.69, $parentHeaderTop - 14.34, 481.89, 'Tanggapan Orang Tua/Wali Murid', 10, true);
    $pdf->rect(56.69, $parentBodyTop, 481.89, -62.36, 'S');

    return $parentBodyTop - 62.36;
}

function draw_report_page_two(SimplePdf $pdf, array $payload, int $pageNo = 2): void
{
    $pdf->addPage();
    draw_report_identity($pdf, $payload, $pageNo);
    $student = $payload['student'];
    $reportDate = $payload['report_date'];
    $signatures = $payload['signatures'] ?? [];
    $principalSignature = $signatures['principal'] ?? [];
    $homeroomSignature = $signatures['homeroom'] ?? [];
    $principalName = trim((string)($principalSignature['person_name'] ?? '')) ?: (string)$payload['principal']['name'];
    $principalNip = trim((string)($principalSignature['nip'] ?? '')) ?: (string)$payload['principal']['nip'];
    $homeroomName = trim((string)($homeroomSignature['person_name'] ?? '')) ?: (string)($student['homeroom_name'] ?: 'Nama Guru');
    $homeroomNip = trim((string)($homeroomSignature['nip'] ?? '')) ?: (string)($student['homeroom_nip'] ?? '');
    $place = $reportDate['principal_place'] ?? 'Jakarta';
    $date = $reportDate['report_date'] ?? '2025-11-23';
    $dateText = $place . ', ' . format_indonesian_date($date);
    $showSignature = ($_GET['isittd'] ?? 'tanpa') === 'dengan';

    $gap = 14.17;
    $sectionTop = 711.50;

    $sectionBottom = draw_kokurikuler_section($pdf, $payload, $sectionTop);
    $sectionBottom = draw_extrakurikuler_section($pdf, $payload, $sectionBottom - $gap);
    $sectionBottom = draw_attendance_note_section($pdf, $payload, $sectionBottom - $gap);

    $promotionTop = $sectionBottom - $gap;
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, $promotionTop, 481.89, -28.35, 'S');
    $status = 'Naik/Tidak Naik';
    $nextGrade = '';
    $promotionEnabled = get_app_setting('promotion.enabled', '1') === '1' && semester_number() === '2';
    $graduation = $payload['graduation'] ?? null;
    if ($promotionEnabled && $graduation && in_array((string)$graduation['status'], ['naik', 'tinggal'], true)) {
        $label = $graduation['status'] === 'naik' ? 'Naik Kelas' : 'Tinggal Kelas';
        $status = $label;
        $gradeNum = (int)preg_replace('/\D+/', '', (string)($payload['student']['grade'] ?? ''));
        $nextGrade = $graduation['status'] === 'naik' ? ' ke kelas ' . ($gradeNum + 1) : '';
    }
    $pdf->centerText(56.69, $promotionTop - 17.18, 481.89, 'Keterangan Kenaikan Kelas  :  ' . $status . $nextGrade, 10, true);

    $sigTop = $promotionTop - 28.35 - $gap;
    $pdf->setFont('Helvetica', 10);
    $pdf->text(390.11, $sigTop, $dateText, 10);
    $pdf->text(81.30, $sigTop - 11.34, 'Orang Tua Murid,', 10);
    $pdf->text(237.51, $sigTop - 11.34, 'Kepala Sekolah,', 10);
    $pdf->text(427.49, $sigTop - 11.34, ' Wali Kelas', 10);
    if ($showSignature) {
        draw_report_signature_marker($pdf, 230.00, $sigTop - 32.34, 'TTD Digital', (string)($principalSignature['file_path'] ?? ''));
        draw_report_signature_marker($pdf, 418.00, $sigTop - 32.34, 'TTD Digital', (string)($homeroomSignature['file_path'] ?? ''));
    }
    $pdf->text(80.43, $sigTop - 73.73, '............................', 10);
    $pdf->text(234.28, $sigTop - 73.73, $principalName, 10, true);
    $pdf->text(419.02, $sigTop - 73.73, $homeroomName, 10, true);
    $pdf->setFont('Helvetica', 10);
    $pdf->text(211.80, $sigTop - 85.07, 'NIP. ' . $principalNip, 10);
    $pdf->text(410.67, $sigTop - 85.07, 'NIP. ' . $homeroomNip, 10);
}

function draw_report_signature_marker(SimplePdf $pdf, float $x, float $topY, string $label, string $filePath = ''): void
{
    if ($filePath === '') {
        return;
    }
    $assetPath = report_asset_path($filePath);
    if ($assetPath !== '' && $pdf->image($assetPath, $x, $topY, 82.00, 26.00)) {
        return;
    }

    $pdf->setFont('Helvetica', 7);
    $pdf->rect($x, $topY, 82.00, -26.00, 'S');
    $pdf->centerText($x, $topY - 16.00, 82.00, $label, 7);
}

function page_student_document_download(): void
{
    require_role(['siswa']);
    $student = current_student();
    $grade = (int)preg_replace('/\D+/', '', (string)($student['grade'] ?? '0'));
    if ($grade < 9) {
        http_response_code(403);
        exit('Dokumen kelulusan hanya tersedia untuk siswa kelas 9.');
    }
    $kind = strtolower((string)($_GET['kind'] ?? 'skl'));
    if (!in_array($kind, ['skl', 'ijazah', 'transkrip'], true)) {
        http_response_code(404);
        exit('Jenis dokumen tidak dikenal.');
    }
    $pdf = generate_student_graduation_document_pdf($student, $kind);
    $filePrefix = ['skl' => 'SKL', 'ijazah' => 'Ijazah', 'transkrip' => 'Transkrip'][$kind];
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filePrefix . '_' . report_file_name($student) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function generate_student_graduation_document_pdf(array $student, string $kind): string
{
    $pdf = new SimplePdf();
    $school = report_get_school_profile();
    $schoolLogo = report_logo_signature();
    $agencyLogo = report_agency_logo_signature();
    $graduation = fetch_one('SELECT * FROM graduations WHERE student_id = ?', [(int)$student['id']]) ?: [];
    $titles = [
        'skl' => 'SURAT KETERANGAN LULUS',
        'ijazah' => 'IJAZAH SEMENTARA',
        'transkrip' => 'TRANSKRIP NILAI',
    ];

    $pdf->addPage();
    draw_report_asset_badge($pdf, 56.69, 800.00, 46.00, 46.00, 'DINAS', (string)($agencyLogo['file_path'] ?? ''));
    draw_report_asset_badge($pdf, 492.58, 800.00, 46.00, 46.00, 'SEKOLAH', (string)($schoolLogo['file_path'] ?? ''));
    $pdf->setFont('Helvetica', 14, true);
    $pdf->centerText(112.00, 780.00, 371.00, (string)$school['name'], 14, true);
    $pdf->setFont('Helvetica', 9);
    $pdf->centerText(112.00, 762.00, 371.00, (string)($school['address'] ?: 'Alamat Sekolah'), 9);
    $pdf->line(56.69, 748.00, 538.58, 748.00);

    $pdf->setFont('Helvetica', 13, true);
    $pdf->centerText(56.69, 720.00, 481.89, $titles[$kind], 13, true);
    $pdf->setFont('Helvetica', 10);
    $pdf->centerText(56.69, 704.00, 481.89, 'Nomor: ' . (string)($kind === 'transkrip' ? ($graduation['transcript_no'] ?? '-') : ($graduation['certificate_no'] ?? '-')), 10);

    $y = 665.00;
    foreach ([
        'Nama Siswa' => (string)$student['name'],
        'NIS/NISN' => trim((string)$student['nis'] . ' / ' . (string)$student['nisn']),
        'Kelas' => (string)($student['class_name'] ?? '-'),
        'Status' => strtoupper((string)($graduation['status'] ?? 'belum diinput')),
        'Tanggal Kelulusan' => isset($graduation['graduation_date']) ? format_indonesian_date((string)$graduation['graduation_date']) : '-',
    ] as $label => $value) {
        $pdf->text(90.00, $y, $label, 10);
        $pdf->text(210.00, $y, ': ' . $value, 10);
        $y -= 18.00;
    }

    if ($kind === 'transkrip') {
        $pdf->setFont('Helvetica', 10, true);
        $pdf->rect(90.00, $y - 10, 300.00, -22.00, 'B', [0.973, 0.973, 1.000]);
        $pdf->text(100.00, $y - 24, 'Mata Pelajaran', 10, true);
        $pdf->rect(390.00, $y - 10, 80.00, -22.00, 'B', [0.973, 0.973, 1.000]);
        $pdf->centerText(390.00, $y - 24, 80.00, 'Nilai', 10, true);
        $y -= 32.00;
        $pdf->setFont('Helvetica', 9);
        foreach (student_score_rows((int)$student['id']) as $row) {
            $pdf->rect(90.00, $y, 300.00, -20.00, 'S');
            $pdf->text(100.00, $y - 13.00, (string)$row['subject_name'], 9);
            $pdf->rect(390.00, $y, 80.00, -20.00, 'S');
            $pdf->centerText(390.00, $y - 13.00, 80.00, (string)$row['score'], 9);
            $y -= 20.00;
        }
    } else {
        $body = $kind === 'skl'
            ? 'Berdasarkan hasil rapat dewan guru dan kriteria kelulusan satuan pendidikan, siswa tersebut dinyatakan lulus dari satuan pendidikan.'
            : 'Dokumen ijazah sementara ini diterbitkan untuk digunakan sebagaimana mestinya sampai ijazah resmi diterima.';
        $pdf->setFont('Helvetica', 10);
        foreach ($pdf->wrapText($body, 410, 10) as $line) {
            $pdf->text(90.00, $y, $line, 10);
            $y -= 15.00;
        }
    }

    $date = (string)($graduation['graduation_date'] ?? date('Y-m-d'));
    $pdf->text(360.00, 220.00, 'Jakarta, ' . format_indonesian_date($date), 10);
    $pdf->text(360.00, 205.00, 'Kepala Sekolah,', 10);
    $pdf->text(360.00, 145.00, (string)($school['principal_name'] ?: 'Kepala Sekolah'), 10, true);
    $pdf->text(360.00, 132.00, 'NIP. ' . (string)($school['principal_nip'] ?: '-'), 10);

    return $pdf->output();
}

function format_indonesian_date(string $date): string
{
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $time = strtotime($date) ?: time();
    return date('j', $time) . ' ' . $months[(int)date('n', $time)] . ' ' . date('Y', $time);
}

function page_cetak_nilai_rapor_pdf(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    $classes = classes_for_current_user();
    if (!$classId && $classes) {
        $classId = (int)$classes[0]['id'];
    }
    if ($classId > 0) {
        require_class_access($classId);
    }
    $class = $classId ? fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]) : null;
    $students = $class ? fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]) : [];
    render_header('Cetak Nilai Rapor');
    ?>
    <section class="panel no-print">
        <?php panel_title('Cetak Nilai Rapor Siswa'); ?>
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="cetak-nilai-rapor">
            <label>Pilih Kelas <select name="class_id"><?= options(array_column_map($classes, 'id', 'name'), $classId) ?></select></label>
            <label>Posisi Tanda Tangan KS <select name="posisittdks"><?= options(['sejajar' => 'Sejajar Wali Kelas', 'bawah' => 'Di Bawah Wali Kelas'], $_GET['posisittdks'] ?? 'sejajar') ?></select></label>
            <label>Posisi Tanda Tangan <select name="isittd"><?= options(['tanpa' => 'Tanpa Tanda Tangan', 'dengan' => 'Dengan Tanda Tangan'], $_GET['isittd'] ?? 'tanpa') ?></select></label>
            <label>Ukuran Kertas <select name="kertas"><?= options(['A4' => 'A4', 'F4' => 'F4'], $_GET['kertas'] ?? 'A4') ?></select></label>
            <label>Batas Kiri (mm) <input type="number" name="kiri" value="<?= e($_GET['kiri'] ?? 20) ?>"></label>
            <label>Batas Kanan (mm) <input type="number" name="kanan" value="<?= e($_GET['kanan'] ?? 20) ?>"></label>
            <label>Batas Atas (mm) <input type="number" name="atas" value="<?= e($_GET['atas'] ?? 20) ?>"></label>
            <label>Batas Bawah (mm) <input type="number" name="bawah" value="<?= e($_GET['bawah'] ?? 10) ?>"></label>
            <div class="actions wide"><button class="button primary">Tampilkan</button></div>
        </form>
    </section>
    <?php
    $actions = '<a class="button warning" href="' . e(route_url('rapor-generate-class', ['class_id' => $classId])) . '">Generate Rapor Kelas Ini</a>'
        . '<a class="button success" href="' . e(route_url('rapor-download-class', ['class_id' => $classId])) . '">Download Rapor Kelas Ini</a>';
    table_panel('Cetak Nilai Rapor', ['No', 'Nama Siswa', 'NISN', 'NIS', 'Rombel', 'File Rapor', 'Aksi'], $students, function ($student) use ($class) {
        static $no = 1;
        $path = report_file_path((int)$student['id']);
        $exists = is_file($path);
        ?>
        <td><?= e($no++) ?></td>
        <td><?= e($student['name']) ?></td>
        <td><?= e($student['nisn']) ?></td>
        <td><?= e($student['nis']) ?></td>
        <td><?= e($class['name'] ?? '-') ?></td>
        <td><?= $exists ? '<span class="badge ok">Siap</span>' : '<span class="badge off">Belum Ada</span>' ?></td>
        <td>
            <div class="row-actions">
                <details class="action-menu">
                    <summary class="button small warning">Aksi</summary>
                    <div class="action-menu-list">
                        <a href="<?= e(route_url('rapor-download-student', ['student_id' => (int)$student['id']])) ?>">Download File</a>
                        <a href="<?= e(route_url('rapor-generate-student', ['student_id' => (int)$student['id'], 'class_id' => (int)($student['class_id'] ?? 0)])) ?>">Generate Ulang</a>
                    </div>
                </details>
                <a class="button small primary" target="_blank" href="<?= e(route_url('rapor-download-student', ['student_id' => (int)$student['id'], 'inline' => 1])) ?>">Tampilkan pada Siswa</a>
            </div>
        </td>
        <?php
    }, $actions);
    render_footer();
}

function page_rapor_generate_class(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    require_class_access($classId);
    $students = fetch_all('SELECT id FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]);
    foreach ($students as $student) {
        generate_student_report_pdf((int)$student['id']);
    }
    flash('success', 'PDF rapor kelas berhasil digenerate.');
    redirect_to('cetak-nilai-rapor', ['class_id' => $classId]);
}

function page_rapor_generate_student(): void
{
    require_role(['admin', 'guru']);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $student = fetch_one('SELECT class_id FROM students WHERE id = ?', [$studentId]);
    if (!$student) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }
    require_class_access((int)$student['class_id']);
    generate_student_report_pdf($studentId);
    flash('success', 'PDF rapor siswa berhasil digenerate.');
    redirect_to('cetak-nilai-rapor', ['class_id' => (int)($_GET['class_id'] ?? 0)]);
}

function page_rapor_download_student(): void
{
    require_login();
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (user_role() === 'siswa') {
        $currentStudentId = (int)(current_user()['student_id'] ?? 0);
        if (!$currentStudentId || $studentId !== $currentStudentId) {
            http_response_code(403);
            exit('Akses ditolak.');
        }
    } else {
        require_role(['admin', 'guru']);
    }

    $student = fetch_one('SELECT * FROM students WHERE id = ?', [$studentId]);
    if (!$student) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }
    if (user_role() !== 'siswa') {
        require_class_access((int)$student['class_id']);
    }
    $path = report_file_path($studentId);
    if (!is_file($path)) {
        $path = generate_student_report_pdf($studentId);
    }
    header('Content-Type: application/pdf');
    $disposition = !empty($_GET['inline']) ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . report_file_name($student) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function page_rapor_download_class(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    require_class_access($classId);
    $class = fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]);
    $students = fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]);
    if (!$class || !$students) {
        http_response_code(404);
        exit('Kelas tidak ditemukan atau tidak punya siswa.');
    }
    $zipPath = report_storage_dir() . '/rapor-kelas-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$class['name']) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Gagal membuat file ZIP.');
    }
    foreach ($students as $student) {
        $pdfPath = report_file_path((int)$student['id']);
        if (!is_file($pdfPath)) {
            $pdfPath = generate_student_report_pdf((int)$student['id']);
        }
        $zip->addFile($pdfPath, report_file_name($student));
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Rapor_Kelas_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$class['name']) . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
}

function biodata_file_path(int $studentId): string
{
    return report_storage_dir() . '/biodata-siswa-' . $studentId . '.pdf';
}

function generate_student_biodata_pdf(int $studentId): string
{
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name, c.grade, c.homeroom_teacher_id, t.name AS homeroom_name, t.nip AS homeroom_nip
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id
         WHERE s.id = ?',
        [$studentId]
    );
    if (!$student) {
        throw new RuntimeException('Siswa tidak ditemukan.');
    }

    $school = report_get_school_profile();
    $schoolLogo = report_logo_signature();
    $agencyLogo = report_agency_logo_signature();
    $photo = report_student_photo($studentId);

    $pdf = new SimplePdf();
    $marginLeft = 56.69;
    $marginRight = 538.58;
    $contentWidth = $marginRight - $marginLeft;
    $centerX = $marginLeft + ($contentWidth / 2);

    // ==========================================
    // HALAMAN 1: SAMPUL DEPAN
    // ==========================================
    $pdf->addPage();

    // Logo 1 (Agency/Dinas/Kemdikbud) di atas
    $logoW = 75.00;
    $logoH = 75.00;
    $logoTopY = 750.00;
    if ($agencyLogo && ($assetPath = report_asset_path((string)($agencyLogo['file_path'] ?? ''))) !== '') {
        $pdf->image($assetPath, $centerX - ($logoW / 2), $logoTopY, $logoW, $logoH);
    }

    // Judul
    $pdf->setFont('Helvetica', 16, true);
    $pdf->centerText($marginLeft, $logoTopY - $logoH - 40.00, $contentWidth, 'SEKOLAH DASAR', 16, true);
    $pdf->centerText($marginLeft, $logoTopY - $logoH - 60.00, $contentWidth, '( SD )', 16, true);

    // Logo 2 (Sekolah) di tengah
    $logo2W = 100.00;
    $logo2H = 100.00;
    $logo2TopY = $logoTopY - $logoH - 100.00;
    if ($schoolLogo && ($assetPath = report_asset_path((string)($schoolLogo['file_path'] ?? ''))) !== '') {
        $pdf->image($assetPath, $centerX - ($logo2W / 2), $logo2TopY, $logo2W, $logo2H);
    } else {
        $pdf->rect($centerX - ($logo2W / 2), $logo2TopY, $logo2W, -$logo2H, 'S');
        $pdf->centerText($centerX - ($logo2W / 2), $logo2TopY - ($logo2H / 2) - 3, $logo2W, 'LOGO SEKOLAH', 10);
    }

    // Box Nama
    $boxTopY = $logo2TopY - $logo2H - 50.00;
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText($marginLeft, $boxTopY, $contentWidth, 'Nama Peserta Didik', 12, true);
    $pdf->rect($centerX - 150.00, $boxTopY - 15.00, 300.00, -30.00, 'S');
    $pdf->centerText($centerX - 150.00, $boxTopY - 35.00, 300.00, strtoupper((string)$student['name']), 12, true);

    // Box NISN / NIS
    $box2TopY = $boxTopY - 70.00;
    $pdf->centerText($marginLeft, $box2TopY, $contentWidth, 'NISN / NIS', 12, true);
    $pdf->rect($centerX - 150.00, $box2TopY - 15.00, 300.00, -30.00, 'S');
    $nisnNis = trim((string)($student['nisn'] ?: '-')) . ' / ' . trim((string)($student['nis'] ?: '-'));
    $pdf->centerText($centerX - 150.00, $box2TopY - 35.00, 300.00, $nisnNis, 12, true);

    // Footer
    $pdf->setFont('Helvetica', 14, true);
    $pdf->centerText($marginLeft, 100.00, $contentWidth, 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH', 14, true);
    $pdf->centerText($marginLeft, 80.00, $contentWidth, 'REPUBLIK INDONESIA', 14, true);


    // ==========================================
    // HALAMAN 2: IDENTITAS SEKOLAH & PESERTA DIDIK
    // ==========================================
    $pdf->addPage();

    $y = 780.00;
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText($marginLeft, $y, $contentWidth, 'SEKOLAH DASAR', 12, true);
    $pdf->centerText($marginLeft, $y - 15.00, $contentWidth, '( SD )', 12, true);
    $y -= 45.00;

    $pdf->setFont('Helvetica', 10);
    $schoolFields = [
        'Nama Sekolah' => (string)$school['name'],
        'NPSN' => (string)($school['npsn'] ?: '-'),
        'NIS/NSS/NDS' => '001098304321', // Dummy placeholder as requested
        'Alamat Sekolah' => (string)($school['address'] ?: '-'),
        'Kelurahan/Desa' => '-',
        'Kecamatan' => '-',
        'Kota/Kabupaten' => '-',
        'Provinsi' => '-',
        'Website' => '-',
        'E-mail' => '-',
    ];
    $fieldW = 120.00;
    foreach ($schoolFields as $label => $value) {
        $pdf->text($marginLeft, $y, $label, 10);
        $pdf->text($marginLeft + $fieldW, $y, ': ' . $value, 10);
        $y -= 14.00;
    }

    $y -= 20.00;
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText($marginLeft, $y, $contentWidth, 'IDENTITAS PESERTA DIDIK', 12, true);
    $y -= 25.00;

    $pdf->setFont('Helvetica', 10);
    $numX = $marginLeft;
    $labelX = $marginLeft + 15.00;
    $valX = $marginLeft + 170.00;

    $studentFields = [
        ['Nama Lengkap Peserta Didik', strtoupper((string)$student['name'])],
        ['Nomor Induk/NISN', $nisnNis],
        ['Tempat, Tanggal Lahir', trim(($student['birth_place'] ?: '-') . ', ' . ($student['birth_date'] ? format_indonesian_date((string)$student['birth_date']) : '-'), ', ')],
        ['Jenis Kelamin', (string)($student['gender'] === 'L' ? 'Laki-Laki' : ($student['gender'] === 'P' ? 'Perempuan' : '-'))],
        ['Agama', (string)($student['religion'] ?: '-')],
        ['Status dalam Keluarga', 'Anak Kandung'],
        ['Anak ke', ''],
        ['Alamat Peserta Didik', (string)($student['address'] ?: '-')],
        ['Nomor Telepon Rumah', (string)($student['phone'] ?: '-')],
        ['Sekolah Asal', ''],
        ['Diterima di sekolah ini', ''],
        ['Di kelas', (string)($student['class_name'] ?: '-')],
        ['Pada tanggal', ''],
        ['Nama Orang Tua', ''],
        ['  a. Ayah', (string)($student['father_name'] ?: '-')],
        ['  b. Ibu', (string)($student['mother_name'] ?: '-')],
        ['Alamat Orang Tua', (string)($student['address'] ?: '-')],
        ['Nomor Telepon Rumah', (string)($student['phone'] ?: '-')],
        ['Pekerjaan Orang Tua', ''],
        ['  a. Ayah', (string)($student['father_occupation'] ?: '-')],
        ['  b. Ibu', (string)($student['mother_occupation'] ?: '-')],
        ['Nama Wali Siswa', (string)($student['guardian_name'] ?: '-')],
        ['Alamat Wali Peserta Didik', '-'],
        ['Nomor Telepon Rumah', '-'],
        ['Pekerjaan Wali Peserta Didik', '-'],
    ];

    $no = 1;
    foreach ($studentFields as $idx => [$label, $val]) {
        if (!str_starts_with($label, '  ') && !str_starts_with($label, 'Di kelas') && !str_starts_with($label, 'Pada tanggal')) {
            $pdf->text($numX, $y, $no . '.', 10);
            $no++;
        }
        $pdf->text($labelX, $y, $label, 10);
        if ($val !== '') {
            $pdf->text($valX, $y, ': ' . $val, 10);
        }
        $y -= 14.00;
    }

    $y -= 10.00;

    // Foto
    $photoW = 85.04; // 3x4 ratio
    $photoH = 113.39;
    if ($photo && ($photoPath = report_asset_path((string)($photo['file_path'] ?? ''))) !== '') {
        $pdf->image($photoPath, $marginLeft, $y, $photoW, $photoH);
    } else {
        $pdf->rect($marginLeft, $y, $photoW, -$photoH, 'S');
        $pdf->setFont('Helvetica', 8);
        $pdf->centerText($marginLeft, $y - ($photoH / 2) - 3, $photoW, 'Pas Foto 3x4', 8);
    }

    // TTD Kepala Sekolah
    $sigX = $marginRight - 150.00;
    $pdf->setFont('Helvetica', 10);
    $pdf->text($sigX, $y - 10.00, '................, ....................', 10);
    $pdf->text($sigX, $y - 25.00, 'Kepala Sekolah,', 10);
    
    if (($_GET['isittd'] ?? 'tanpa') === 'dengan') {
        $principalSignature = report_signature_by_type('ttd_kepsek');
        draw_report_signature_marker($pdf, $sigX, $y - 35.00, 'TTD Digital', (string)($principalSignature['file_path'] ?? ''));
    }
    
    $pdf->text($sigX, $y - 95.00, (string)($school['principal_name'] ?: '............................'), 10, true);
    $pdf->text($sigX, $y - 109.00, 'NIP. ' . (string)($school['principal_nip'] ?? ''), 10);


    // ==========================================
    // HALAMAN 3: MUTASI KELUAR
    // ==========================================
    $pdf->addPage();
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText($marginLeft, 780.00, $contentWidth, 'KETERANGAN PINDAH SEKOLAH', 12, true);
    
    $pdf->setFont('Helvetica', 10);
    $pdf->text($marginLeft, 750.00, 'Nama Peserta Didik : ' . (string)$student['name'], 10);
    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($marginLeft, 730.00, 'KELUAR', 10, true);

    $y = 710.00;
    for ($i = 0; $i < 3; $i++) {
        // Table Header
        $pdf->setFont('Helvetica', 10, true);
        $pdf->rect($marginLeft, $y, 80.00, -25.00, 'S');
        $pdf->centerText($marginLeft, $y - 15.00, 80.00, 'Tanggal', 10, true);
        
        $pdf->rect($marginLeft + 80.00, $y, 100.00, -25.00, 'S');
        $pdf->centerText($marginLeft + 80.00, $y - 10.00, 100.00, 'Kelas yang', 10, true);
        $pdf->centerText($marginLeft + 80.00, $y - 20.00, 100.00, 'ditinggalkan', 10, true);
        
        $pdf->rect($marginLeft + 180.00, $y, $contentWidth - 180.00, -25.00, 'S');
        $pdf->centerText($marginLeft + 180.00, $y - 15.00, $contentWidth - 180.00, 'Sebab-sebab Keluar atau Atas Permintaan (Tertulis)', 10, true);
        
        $y -= 25.00;
        
        // Table Body
        $pdf->rect($marginLeft, $y, 80.00, -110.00, 'S');
        $pdf->rect($marginLeft + 80.00, $y, 100.00, -110.00, 'S');
        $pdf->rect($marginLeft + 180.00, $y, $contentWidth - 180.00, -110.00, 'S');
        
        $pdf->setFont('Helvetica', 10);
        $sigRight = $marginLeft + 190.00;
        $pdf->text($sigRight, $y - 15.00, '................, ....................', 10);
        $pdf->text($sigRight, $y - 30.00, 'Kepala Sekolah,', 10);
        $pdf->text($sigRight, $y - 80.00, '..............................................', 10);
        $pdf->text($sigRight, $y - 95.00, 'NIP.', 10);
        
        $sigParent = $marginLeft + 360.00;
        $pdf->text($sigParent, $y - 30.00, 'Orang Tua/Wali,', 10);
        $pdf->text($sigParent, $y - 80.00, '..............................................', 10);

        $y -= 130.00;
    }

    // ==========================================
    // HALAMAN 4: MUTASI MASUK
    // ==========================================
    $pdf->addPage();
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText($marginLeft, 780.00, $contentWidth, 'KETERANGAN PINDAH SEKOLAH', 12, true);
    
    $pdf->setFont('Helvetica', 10);
    $pdf->text($marginLeft, 750.00, 'Nama Peserta Didik : ' . (string)$student['name'], 10);
    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($marginLeft, 730.00, 'MASUK', 10, true);

    $y = 710.00;
    $pdf->setFont('Helvetica', 10);
    for ($i = 1; $i <= 3; $i++) {
        $pdf->line($marginLeft, $y, $marginRight, $y);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '1. Nama Siswa', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        
        $pdf->text($marginLeft + 250.00, $y, '................, ....................', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '2. Nomor Induk', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        
        $pdf->text($marginLeft + 250.00, $y, 'Kepala Sekolah,', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '3. Nama Sekolah', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '4. Masuk di Sekolah ini:', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '    a. Tanggal', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        
        $pdf->text($marginLeft + 250.00, $y - 5.00, '..............................................', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '    b. Di Kelas', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        
        $pdf->text($marginLeft + 250.00, $y - 5.00, 'NIP.', 10);
        $y -= 15.00;
        
        $pdf->text($marginLeft, $y, '5. Tahun Pelajaran', 10);
        $pdf->text($marginLeft + 100.00, $y, '________________________', 10);
        
        $y -= 25.00;
    }
    $pdf->line($marginLeft, $y + 15.00, $marginRight, $y + 15.00);

    $path = biodata_file_path($studentId);
    file_put_contents($path, $pdf->output());
    return $path;
}

function page_biodata_download_student(): void
{
    require_role(['admin', 'guru']);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $student = fetch_one('SELECT * FROM students WHERE id = ?', [$studentId]);
    if (!$student) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }
    require_class_access((int)$student['class_id']);
    $path = biodata_file_path($studentId);
    if (!is_file($path)) {
        $path = generate_student_biodata_pdf($studentId);
    }
    $name = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$student['name']);
    $name = trim((string)$name) ?: 'Siswa';
    header('Content-Type: application/pdf');
    $disposition = !empty($_GET['inline']) ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="Biodata_' . str_replace(' ', '_', $name) . '.pdf"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function page_biodata_generate_student(): void
{
    require_role(['admin', 'guru']);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $student = fetch_one('SELECT class_id FROM students WHERE id = ?', [$studentId]);
    if (!$student) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }
    require_class_access((int)$student['class_id']);
    generate_student_biodata_pdf($studentId);
    flash('success', 'PDF biodata siswa berhasil digenerate.');
    redirect_to('cetak-pelengkap-rapor', ['class_id' => (int)($_GET['class_id'] ?? 0)]);
}

function page_biodata_generate_class(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    require_class_access($classId);
    $students = fetch_all('SELECT id FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]);
    foreach ($students as $student) {
        generate_student_biodata_pdf((int)$student['id']);
    }
    flash('success', 'PDF biodata kelas berhasil digenerate.');
    redirect_to('cetak-pelengkap-rapor', ['class_id' => $classId]);
}

function page_biodata_download_class(): void
{
    require_role(['admin', 'guru']);
    $classId = (int)($_GET['class_id'] ?? 0);
    require_class_access($classId);
    $class = fetch_one('SELECT * FROM classes WHERE id = ?', [$classId]);
    $students = fetch_all('SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY name', [$classId]);
    if (!$class || !$students) {
        http_response_code(404);
        exit('Kelas tidak ditemukan atau tidak punya siswa.');
    }
    $zipPath = report_storage_dir() . '/biodata-kelas-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$class['name']) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Gagal membuat file ZIP.');
    }
    foreach ($students as $student) {
        $pdfPath = biodata_file_path((int)$student['id']);
        if (!is_file($pdfPath)) {
            $pdfPath = generate_student_biodata_pdf((int)$student['id']);
        }
        $name = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$student['name']);
        $name = trim((string)$name) ?: 'Siswa';
        $zip->addFile($pdfPath, 'Biodata_' . str_replace(' ', '_', $name) . '.pdf');
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Biodata_Kelas_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$class['name']) . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
}

function page_cetak_sp(): void
{
    require_bk();
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }
    $student = fetch_one(
        'SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?',
        [$studentId]
    );
    if (!$student) {
        http_response_code(404);
        exit('Siswa tidak ditemukan.');
    }

    $net = violation_net_points($studentId);
    $spLevel = violation_sp_level($net['net_points']);
    if (!$spLevel) {
        http_response_code(400);
        exit('Siswa belum mencapai threshold surat peringatan.');
    }

    $violations = fetch_all(
        'SELECT * FROM student_violations WHERE student_id = ? ORDER BY date DESC',
        [$studentId]
    );
    $rewards = fetch_all(
        'SELECT * FROM student_rewards WHERE student_id = ? ORDER BY date DESC',
        [$studentId]
    );
    $school = report_get_school_profile();
    $schoolLogo = report_logo_signature();
    $principalSig = report_signature_by_type('principal');

    $pdf = new SimplePdf();
    $pdf->addPage();

    $pageW = 595.28;
    $lm = 70.0;
    $rm = 525.0;
    $contentW = $rm - $lm;

    draw_report_asset_badge($pdf, $lm, 810.0, 50.0, 50.0, 'SEKOLAH', (string)($schoolLogo['file_path'] ?? ''));

    $pdf->setFont('Helvetica', 13, true);
    $pdf->centerText($lm + 55, 808.0, $contentW - 55, strtoupper((string)$school['name']), 13, true);
    $pdf->setFont('Helvetica', 9);
    $pdf->centerText($lm + 55, 792.0, $contentW - 55, (string)($school['address'] ?: ''), 9);
    $pdf->line($lm, 775.0, $rm, 775.0);
    $pdf->line($lm, 773.0, $rm, 773.0);

    $spLabel = (string)$spLevel['label'];
    $pdf->setFont('Helvetica', 13, true);
    $pdf->centerText($lm, 748.0, $contentW, 'SURAT PERINGATAN ' . strtoupper($spLabel), 13, true);

    $pdf->setFont('Helvetica', 10);
    $pdf->centerText($lm, 730.0, $contentW, 'Nomor: ........./........./'.date('Y'), 10);

    $pdf->setFont('Helvetica', 10);
    $y = 700.0;
    $pdf->justifyText($lm, $y, $contentW, 'Yang bertanda tangan di bawah ini, Kepala ' . (string)$school['name'] . ' memberikan Surat Peringatan kepada siswa berikut:', 10);
    $y -= 30.0;

    foreach ([
        'Nama Siswa'      => (string)$student['name'],
        'NIS / NISN'      => trim((string)$student['nis'] . ' / ' . (string)$student['nisn']),
        'Kelas'           => (string)($student['class_name'] ?? '-'),
        'Total Poin'      => $net['gross_points'] . ' poin (bersih: ' . $net['net_points'] . ' poin)',
        'Level Peringatan'=> $spLabel,
    ] as $label => $value) {
        $pdf->text($lm + 10, $y, $label, 10);
        $pdf->text($lm + 120, $y, ': ' . $value, 10);
        $y -= 17.0;
    }

    $y -= 8.0;
    $pdf->justifyText($lm, $y, $contentW, 'Sehubungan dengan akumulasi poin pelanggaran tata tertib sekolah yang telah mencapai batas ' . $spLabel . ' (' . $spLevel['min_points'] . ' poin), siswa tersebut diberikan peringatan untuk segera memperbaiki sikap dan perilaku.', 10);
    $y -= 45.0;

    if ($violations) {
        $pdf->setFont('Helvetica', 10, true);
        $pdf->text($lm, $y, 'Rincian Pelanggaran:', 10, true);
        $y -= 5.0;
        $pdf->line($lm, $y, $rm, $y);
        $y -= 14.0;
        $pdf->setFont('Helvetica', 9);
        $pdf->text($lm, $y, 'No', 9, true);
        $pdf->text($lm + 22, $y, 'Tanggal', 9, true);
        $pdf->text($lm + 80, $y, 'Jenis Pelanggaran', 9, true);
        $pdf->text($rm - 40, $y, 'Poin', 9, true);
        $y -= 4.0;
        $pdf->line($lm, $y, $rm, $y);
        $y -= 14.0;
        foreach (array_slice($violations, 0, 15) as $i => $v) {
            $pdf->text($lm, $y, (string)($i + 1), 9);
            $pdf->text($lm + 22, $y, (string)$v['date'], 9);
            $pdf->text($lm + 80, $y, mb_strimwidth((string)$v['type'], 0, 55, '...'), 9);
            $pdf->text($rm - 40, $y, (string)$v['points'], 9);
            $y -= 14.0;
            if ($y < 180) {
                break;
            }
        }
        $y -= 4.0;
        $pdf->line($lm, $y, $rm, $y);
        $y -= 14.0;
    }

    if ($rewards) {
        $y -= 5.0;
        $pdf->text($lm, $y, 'Reward/Prestasi (potongan poin): ' . $net['discount_pct'] . '%', 10, true);
        $y -= 16.0;
    }

    $y -= 5.0;
    $pdf->justifyText($lm, $y, $contentW, 'Surat peringatan ini dikeluarkan agar siswa dan orang tua/wali dapat mengambil langkah perbaikan. Apabila tidak ada perubahan, sekolah berhak mengambil tindakan lebih lanjut sesuai ketentuan yang berlaku.', 10);
    $y -= 48.0;

    $tanggal = format_indonesian_date(date('Y-m-d'));
    $pdf->text($rm - 180, $y, (string)($school['address'] ?: 'Sekolah') . ', ' . $tanggal, 10);
    $y -= 14.0;
    $pdf->text($rm - 180, $y, 'Kepala Sekolah,', 10);
    $y -= 60.0;
    if ($principalSig && !empty($principalSig['file_path'])) {
        $pdf->image(report_asset_path((string)$principalSig['file_path']), $rm - 180, $y + 60 - 50, 80, 40);
    }
    $pdf->text($rm - 180, $y, (string)($school['principal_name'] ?: '..................................'), 10, true);
    $y -= 14.0;
    $pdf->text($rm - 180, $y, 'NIP. ' . (string)($school['principal_nip'] ?: '...........................'), 9);

    $pdfContent = $pdf->output();
    $filename = 'SP_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string)$student['name']) . '_' . $spLabel . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

function page_laporan_belajar(): void
{
    require_role(['admin', 'guru']);
    $studentId = (int)($_GET['student_id'] ?? 0);
    $classId = (int)($_GET['class_id'] ?? 0);

    if ($studentId > 0) {
        $student = fetch_one('SELECT s.*, c.name AS class_name, c.grade, c.homeroom_teacher_id, t.name AS homeroom_name, t.nip AS homeroom_nip FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN teachers t ON t.id = c.homeroom_teacher_id WHERE s.id = ?', [$studentId]);
        if (!$student) {
            http_response_code(404);
            exit('Siswa tidak ditemukan.');
        }
        $pdfContent = generate_laporan_belajar_pdf($student);
        $filename = 'Laporan_Belajar_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string)$student['name']) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    $classes = assignments_for_current_user();
    $classId = $classId ?: ((int)($classes[0]['class_id'] ?? 0));
    $students = $classId ? fetch_all('SELECT s.id, s.name, s.nis FROM students s WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name', [$classId]) : [];
    render_header('Cetak Laporan Hasil Belajar');
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="laporan-belajar">
            <label>Kelas
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">Pilih Kelas</option>
                    <?php foreach (fetch_all('SELECT DISTINCT c.id, c.name FROM teaching_assignments ta JOIN classes c ON c.id = ta.class_id WHERE ta.active = 1 ORDER BY c.name') as $c): ?>
                        <option value="<?= e($c['id']) ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </section>
    <?php if ($students): ?>
        <section class="panel">
            <?php panel_title('Daftar Siswa', '', ''); ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>No</th><th>Nama Siswa</th><th>NIS</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($s['name']) ?></td>
                                <td><?= e($s['nis']) ?></td>
                                <td><a class="button small primary" href="<?= e(route_url('laporan-belajar', ['student_id' => (int)$s['id']])) ?>" target="_blank">Cetak PDF</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    <?php render_footer();
}

function generate_laporan_belajar_pdf(array $student): string
{
    $pdf = new SimplePdf();
    $pdf->addPage();

    $school = report_get_school_profile();
    $studentId = (int)$student['id'];
    $attendance = report_attendance_summary_for_student($studentId);
    $signatures = report_signatures_for_student($student);
    $principalSig = $signatures['principal'] ?? null;
    $homeroomSig = $signatures['homeroom'] ?? null;
    $principalName = trim((string)($principalSig['person_name'] ?? '')) ?: trim((string)($school['principal_name'] ?? ''));
    $principalNip = trim((string)($principalSig['nip'] ?? '')) ?: trim((string)($school['principal_nip'] ?? ''));
    $homeroomName = trim((string)($homeroomSig['person_name'] ?? '')) ?: trim((string)($student['homeroom_name'] ?? ''));
    $homeroomNip = trim((string)($homeroomSig['nip'] ?? '')) ?: trim((string)($student['homeroom_nip'] ?? ''));
    $reportDate = fetch_one('SELECT * FROM report_dates WHERE grade = ? ORDER BY report_date DESC LIMIT 1', [(string)($student['grade'] ?? '')]);
    $place = (string)($reportDate['principal_place'] ?? 'Jakarta');
    $dateStr = $reportDate['report_date'] ?? date('Y-m-d');
    $dateText = $place . ', ' . format_indonesian_date($dateStr);
    $graduation = fetch_one('SELECT status FROM graduations WHERE student_id = ?', [$studentId]);
    $gradeNum = (int)preg_replace('/\D+/', '', (string)($student['grade'] ?? '0'));
    $isFinalGrade = in_array($gradeNum, [6], true);

    $subjects = report_subjects_for_student($student, $studentId);
    $lm = 56.69;
    $rm = 538.58;
    $contentW = $rm - $lm;
    $colLeftX = $lm;
    $colRightX = $lm + $contentW / 2 + 10.0;
    $labelW = 105.0;
    $valLeftX = $colLeftX + $labelW;
    $valRightX = $colRightX + $labelW;
    $rowH = 16.0;
    $lineH = 11.34;
    $pageTop = 775.0;
    $pageBottom = 100.0;
    $pageNo = 1;

    // === HEADER IDENTITAS (fixed 2-column table layout) ===
    $y = $pageTop;
    $pdf->setFont('Helvetica', 10);
    $identityLeft = [
        ['Nama Murid', (string)($student['name'] ?? '...........')],
        ['NIS/NISN', trim((string)($student['nis'] ?? '')) . ' / ' . trim((string)($student['nisn'] ?? ''))],
        ['Sekolah', (string)($school['name'] ?? '...........')],
        ['Alamat', (string)($school['address'] ?? '...........')],
    ];
    $identityRight = [
        ['Kelas', (string)($student['class_name'] ?? '...........')],
        ['Fase', class_phase((string)($student['grade'] ?? '')) ?: '...........'],
        ['Semester', current_semester()],
        ['Tahun Ajaran', current_academic_year()],
    ];
    $leftMaxW = $contentW * 0.60;
    for ($i = 0; $i < count($identityLeft); $i++) {
        $leftVal = $identityLeft[$i][1] ?: '...........';
        $rightVal = $identityRight[$i][1] ?: '...........';
        $leftText = $identityLeft[$i][0] . '  :  ' . $leftVal;
        $leftTextW = $pdf->stringWidth($leftText, 10);
        if ($leftTextW > $leftMaxW) {
            while ($pdf->stringWidth($leftText . '...', 10) > $leftMaxW && strlen($leftText) > 5) {
                $leftText = substr($leftText, 0, -1);
            }
            $leftText .= '...';
        }
        $pdf->text($colLeftX, $y, $leftText, 10);
        $pdf->text($colRightX, $y, $identityRight[$i][0] . '  :  ' . $rightVal, 10);
        $y -= $rowH;
    }
    $y -= 4.0;
    $pdf->line($lm, $y, $rm, $y);
    $y -= 6.0;

    // === JUDUL ===
    $pdf->setFont('Helvetica', 13, true);
    $pdf->centerText($lm, $y, $contentW, 'LAPORAN HASIL BELAJAR', 13, true);
    $y -= 24.0;

    // === TABEL NILAI AKADEMIK ===
    // Mapel column = 25% of content (requirement #5)
    $tableW = $contentW;
    $mapelW = max(140.0, $tableW * 0.25);
    $noW = 28.0;
    $nilaiW = 65.0;
    $capaianW = $tableW - $noW - $mapelW - $nilaiW;

    $colNo = ['x' => $lm, 'w' => $noW];
    $colMapel = ['x' => $lm + $noW, 'w' => $mapelW];
    $colNilai = ['x' => $lm + $noW + $mapelW, 'w' => $nilaiW];
    $colCapaian = ['x' => $lm + $noW + $mapelW + $nilaiW, 'w' => $capaianW];

    $headerH = 18.0;
    $pdf->setFont('Helvetica', 9, true);
    foreach ([$colNo, $colMapel, $colNilai, $colCapaian] as $c) {
        $pdf->rect($c['x'], $y, $c['w'], -$headerH, 'S');
        $pdf->centerText($c['x'], $y - 12.0, $c['w'], $c['label'] ?? '', 9, true);
    }
    // Re-label with proper names
    $pdf->centerText($colNo['x'], $y - 12.0, $colNo['w'], 'No', 9, true);
    $pdf->centerText($colMapel['x'], $y - 12.0, $colMapel['w'], 'Mata Pelajaran', 9, true);
    $pdf->centerText($colNilai['x'], $y - 12.0, $colNilai['w'], 'Nilai Akhir', 9, true);
    $pdf->centerText($colCapaian['x'], $y - 12.0, $colCapaian['w'], 'Capaian Kompetensi', 9, true);
    $y -= $headerH;

    $pdf->setFont('Helvetica', 9);
    $no = 1;
    $currentGroup = '';
    $capaianTextW = $colCapaian['w'] - 11.0;
    $groupH = 18.0;

    function laporan_row_height(SimplePdf $pdf, array $subject, float $capaianTextW): float
    {
        $desc = trim((string)($subject['description'] ?? ''));
        if ($desc === '') {
            $desc = 'Menunjukkan perkembangan belajar yang baik pada mata pelajaran ' . (string)$subject['name'] . '.';
        }
        $capaianLines = $pdf->wrapText($desc, $capaianTextW, 9);
        $mapelLines = $pdf->wrapText((string)$subject['name'], 120.0, 9);
        $h = 10.0 + count($capaianLines) * $lineH;
        if (count($mapelLines) > 1) {
            $h = max($h, 10.0 + $lineH);
        }
        return max(20.0, $h);
    }

    foreach ($subjects as $subject) {
        $group = $subject['group_name'] ?: 'Kelompok A';
        $description = trim((string)($subject['description'] ?? ''));
        if ($description === '') {
            $description = 'Menunjukkan perkembangan belajar yang baik pada mata pelajaran ' . (string)$subject['name'] . '.';
        }
        $rowH = laporan_row_height($pdf, $subject, $capaianTextW);
        $needsGroup = $group !== $currentGroup;
        $neededH = $rowH + ($needsGroup ? $groupH : 0);

        // page-break: pastikan minimal muat group header + 1 baris
        if ($y - $neededH < $pageBottom) {
            $pageNo++;
            $pdf->addPage();
            draw_laporan_footer($pdf, $student, $pageNo);
            $y = $pageTop;
            $currentGroup = '';
            $needsGroup = true;
        }

        if ($needsGroup) {
            $currentGroup = $group;
            $pdf->setFont('Helvetica', 9, true);
            $pdf->rect($lm, $y, $tableW, -$groupH, 'S');
            $pdf->text($lm + 5.5, $y - 12.0, $group, 9, true);
            $y -= $groupH;
            $pdf->setFont('Helvetica', 9);
        }

        // draw row cells
        $pdf->rect($colNo['x'], $y, $colNo['w'], -$rowH, 'S');
        $pdf->centerText($colNo['x'], $y - ($rowH / 2) - 3, $colNo['w'], (string)$no, 9);

        $pdf->rect($colMapel['x'], $y, $colMapel['w'], -$rowH, 'S');
        $mapelLines = array_slice($pdf->wrapText((string)$subject['name'], $colMapel['w'] - 11.0, 9), 0, 2);
        $mapelStartY = $y - 10.0;
        if (count($mapelLines) === 2) {
            $mapelStartY = $y - ($rowH / 2) + 2;
        }
        foreach ($mapelLines as $mi => $ml) {
            $pdf->text($colMapel['x'] + 5.5, $mapelStartY - ($mi * $lineH), $ml, 9);
        }

        $pdf->rect($colNilai['x'], $y, $colNilai['w'], -$rowH, 'S');
        $score = $subject['score'] !== null ? (string)$subject['score'] : '...........';
        $pdf->centerText($colNilai['x'], $y - ($rowH / 2) - 3, $colNilai['w'], $score, 9);

        $pdf->rect($colCapaian['x'], $y, $colCapaian['w'], -$rowH, 'S');
        $pdf->justifyText($colCapaian['x'] + 5.5, $y - 10.0, $capaianTextW, $description, 9);

        $y -= $rowH;
        $no++;
    }

    // === KOKURIKULER ===
    $y -= 14.0;
    if ($y - 60.0 < $pageBottom) { $pageNo++; $pdf->addPage(); draw_laporan_footer($pdf, $student, $pageNo); $y = $pageTop; }
    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($lm, $y, 'Kokurikuler', 10, true);
    $y -= 14.0;

    $kokMapelW = 200.0;
    $kokKetW = $tableW - $noW - $kokMapelW;
    $kokColNo = ['x' => $lm, 'w' => $noW];
    $kokColMapel = ['x' => $lm + $noW, 'w' => $kokMapelW];
    $kokColKet = ['x' => $lm + $noW + $kokMapelW, 'w' => $kokKetW];

    $pdf->setFont('Helvetica', 9, true);
    $pdf->rect($kokColNo['x'], $y, $kokColNo['w'], -$headerH, 'S');
    $pdf->centerText($kokColNo['x'], $y - 12.0, $kokColNo['w'], 'No', 9, true);
    $pdf->rect($kokColMapel['x'], $y, $kokColMapel['w'], -$headerH, 'S');
    $pdf->centerText($kokColMapel['x'], $y - 12.0, $kokColMapel['w'], 'Kokurikuler', 9, true);
    $pdf->rect($kokColKet['x'], $y, $kokColKet['w'], -$headerH, 'S');
    $pdf->centerText($kokColKet['x'], $y - 12.0, $kokColKet['w'], 'Keterangan', 9, true);
    $y -= $headerH;

    $pdf->setFont('Helvetica', 9);
    $kokRows = [];
    $kokurikuler = report_cocurricular_for_student($student);
    if ($kokurikuler) {
        $kokRows[] = [$kokurikuler['group_name'] ?? $kokurikuler['theme_name'] ?? '...........', $kokurikuler['activity_title'] ?? $kokurikuler['description'] ?? '...........'];
    }
    if (!$kokRows) {
        $kokRows[] = ['...........', '...........'];
    }
    foreach ($kokRows as $ki => $kr) {
        $rh = 16.0;
        $pdf->rect($kokColNo['x'], $y, $kokColNo['w'], -$rh, 'S');
        $pdf->centerText($kokColNo['x'], $y - 11.0, $kokColNo['w'], (string)($ki + 1), 9);
        $pdf->rect($kokColMapel['x'], $y, $kokColMapel['w'], -$rh, 'S');
        $pdf->text($kokColMapel['x'] + 5.5, $y - 11.0, (string)$kr[0], 9);
        $pdf->rect($kokColKet['x'], $y, $kokColKet['w'], -$rh, 'S');
        $pdf->text($kokColKet['x'] + 5.5, $y - 11.0, (string)$kr[1], 9);
        $y -= $rh;
    }

    // === KETIDAKHADIRAN & CATATAN WALI KELAS (fixed 2-column table) ===
    $y -= 14.0;
    $absenBlockH = 16.0 * 3 + 20.0;
    if ($y - $absenBlockH < $pageBottom) { $pageNo++; $pdf->addPage(); draw_laporan_footer($pdf, $student, $pageNo); $y = $pageTop; }

    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($colLeftX, $y, 'Ketidakhadiran', 10, true);
    $pdf->text($colRightX, $y, 'Catatan Wali Kelas', 10, true);
    $y -= 16.0;

    $absenLabels = [
        ['Sakit', $attendance['sakit'] ?? 0],
        ['Izin', $attendance['izin'] ?? 0],
        ['Tanpa Keterangan', $attendance['alpa'] ?? 0],
    ];
    $absenStartY = $y;
    $pdf->setFont('Helvetica', 10);
    foreach ($absenLabels as $al) {
        $pdf->text($colLeftX + 10, $y, $al[0] . ' : ' . $al[1] . ' hari', 10);
        $y -= 14.0;
    }

    $catatan = trim((string)($reportDate['note'] ?? '')) ?: 'Menunjukkan sikap baik dan perlu terus dibiasakan belajar mandiri di rumah.';
    $catatanW = $contentW / 2 - 10.0;
    $catatanLines = $pdf->wrapText($catatan, $catatanW, 9);
    $catY = $absenStartY;
    foreach ($catatanLines as $cl) {
        $pdf->text($colRightX, $catY, $cl, 9);
        $catY -= $lineH;
    }
    $y = min($y, $catY);

    // garis pemisah antar kolom
    $pdf->line($lm + $contentW / 2, $absenStartY + 14.0, $lm + $contentW / 2, $y);

    // === TANGGAPAN ORANG TUA ===
    $y -= 14.0;
    if ($y - 80.0 < $pageBottom) { $pageNo++; $pdf->addPage(); draw_laporan_footer($pdf, $student, $pageNo); $y = $pageTop; }
    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($lm, $y, 'Tanggapan Orang Tua/Wali Murid', 10, true);
    $y -= 16.0;
    $pdf->setFont('Helvetica', 10);
    for ($line = 0; $line < 4; $line++) {
        $pdf->line($lm, $y, $rm, $y);
        $y -= 16.0;
    }

    // === KETERANGAN KELULUSAN ===
    if ($isFinalGrade && $graduation) {
        $y -= 6.0;
        if ($y - 20.0 < $pageBottom) { $pageNo++; $pdf->addPage(); draw_laporan_footer($pdf, $student, $pageNo); $y = $pageTop; }
        $pdf->setFont('Helvetica', 10, true);
        $statusLabel = match ((string)($graduation['status'] ?? '')) {
            'lulus' => 'Lulus',
            'tidak_lulus' => 'Tidak Lulus',
            'naik' => 'Naik Kelas',
            'tinggal' => 'Tinggal Kelas',
            default => '...........',
        };
        $pdf->text($lm, $y, 'Keterangan Kelulusan : ' . $statusLabel, 10, true);
        $y -= 16.0;
    }

    // === FOOTER TANDA TANGAN ===
    $y -= 10.0;
    if ($y - 120.0 < $pageBottom) { $pageNo++; $pdf->addPage(); draw_laporan_footer($pdf, $student, $pageNo); $y = $pageTop; }
    $pdf->setFont('Helvetica', 10);
    $pdf->text($rm - 170, $y, $dateText, 10);
    $y -= 22.0;

    $sigGap = 52.0;
    $col1X = $lm + 20.0;
    $col2X = $lm + $contentW / 2 - 40.0;
    $col3X = $rm - 110.0;

    $pdf->setFont('Helvetica', 10);
    $pdf->text($col1X, $y, 'Orang Tua Murid,', 10);
    $pdf->text($col2X, $y, 'Kepala Sekolah,', 10);
    $pdf->text($col3X, $y, 'Wali Kelas,', 10);
    $y -= $sigGap;

    $pdf->text($col1X, $y, '............................', 10);
    $pdf->setFont('Helvetica', 10, true);
    $pdf->text($col2X, $y, $principalName ?: '............................', 10, true);
    $pdf->text($col3X, $y, $homeroomName ?: '............................', 10, true);
    $y -= 12.0;

    $pdf->setFont('Helvetica', 10);
    $pdf->text($col2X - 6, $y, $principalNip ? 'NIP. ' . $principalNip : '............................', 10);
    $pdf->text($col3X - 6, $y, $homeroomNip ? 'NIP. ' . $homeroomNip : '............................', 10);

    draw_laporan_footer($pdf, $student, $pageNo);
    return $pdf->output();
}

function draw_laporan_footer(SimplePdf $pdf, array $student, int $pageNo): void
{
    $pdf->line(56.69, 36.85, 538.58, 36.85);
    $pdf->setFont('Helvetica', 7.5);
    $footer = (string)($student['class_name'] ?? '') . '  |  ' . (string)$student['name'] . '  |  ' . (string)($student['nis'] ?? '');
    $pdf->italicText(59.53, 20.43, $footer, 7.5);
    $pdf->italicText(489.08, 20.43, 'Halaman : ' . $pageNo, 7.5);
}