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

        $next = 5;
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
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >>%s >> /Contents %d 0 R >>',
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
        if ($size !== null || ($bold && $this->font !== 'Helvetica-Bold')) {
            $this->setFont('Helvetica', $size ?? $this->fontSize, $bold);
        }
        $this->content .= sprintf("q 0 g BT %.2F %.2F Td (%s) Tj ET Q\n", $x, $y, $this->escape($text));
    }

    public function centerText(float $x, float $y, float $w, string $text, ?float $size = null, bool $bold = false): void
    {
        $fontSize = $size ?? $this->fontSize;
        $textWidth = $this->stringWidth($text, $fontSize, $bold);
        $this->text($x + max(0, ($w - $textWidth) / 2), $y, $text, $fontSize, $bold);
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
    return str_contains(strtolower((string)config('school.semester', 'Genap')), 'genap') ? '2' : '1';
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
    ];
}

function report_subjects_for_student(array $student, int $studentId): array
{
    $classId = (int)($student['class_id'] ?? 0);
    $grade = (string)($student['grade'] ?? '');
    $mapped = $grade !== ''
        ? (int)(fetch_one('SELECT COUNT(*) AS total FROM report_mappings WHERE grade = ? AND include_in_report = 1', [$grade])['total'] ?? 0)
        : 0;

    if ($mapped > 0) {
        return fetch_all(
            'SELECT sub.id, sub.name, COALESCE(sg.name, sub.group_name, ?) AS group_name,
                    COALESCE(AVG(g.score), fs.score) AS score,
                    COALESCE(MAX(NULLIF(g.description, ?)), ?) AS description,
                    MIN(rm.display_order) AS display_order
             FROM report_mappings rm
             JOIN subjects sub ON sub.id = rm.subject_id
             LEFT JOIN subject_groups sg ON sg.id = rm.group_id
             LEFT JOIN teaching_assignments ta ON ta.subject_id = sub.id AND ta.class_id = ?
             LEFT JOIN grades g ON g.assignment_id = ta.id AND g.student_id = ?
             LEFT JOIN final_scores fs ON fs.subject_id = sub.id AND fs.student_id = ?
             WHERE rm.grade = ? AND rm.include_in_report = 1 AND sub.active = 1
             GROUP BY sub.id, sub.name, sg.name, sub.group_name, fs.score
             ORDER BY MIN(rm.display_order), COALESCE(MIN(sg.display_order), 999), sub.name',
            ['Kelompok A', '', '', $classId, $studentId, $studentId, $grade]
        );
    }

    return fetch_all(
        'SELECT sub.id, sub.name, COALESCE(sub.group_name, ?) AS group_name,
                COALESCE(AVG(g.score), fs.score) AS score,
                COALESCE(MAX(NULLIF(g.description, ?)), ?) AS description
         FROM subjects sub
         LEFT JOIN teaching_assignments ta ON ta.subject_id = sub.id AND ta.class_id = ?
         LEFT JOIN grades g ON g.assignment_id = ta.id AND g.student_id = ?
         LEFT JOIN final_scores fs ON fs.subject_id = sub.id AND fs.student_id = ?
         WHERE sub.active = 1
         GROUP BY sub.id, sub.name, sub.group_name, fs.score
         ORDER BY sub.group_name, sub.name',
        ['Kelompok A', '', '', $classId, $studentId, $studentId]
    );
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
            'SELECT e.*, t.name AS teacher_name
             FROM extracurricular_members em
             JOIN extracurriculars e ON e.id = em.extracurricular_id
             LEFT JOIN teachers t ON t.id = e.teacher_id
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
        'SELECT e.*, t.name AS teacher_name
         FROM extracurriculars e
         LEFT JOIN teachers t ON t.id = e.teacher_id
         WHERE e.active = 1 AND (e.class_name = ? OR e.class_name = ? OR e.class_name = ?)
         ORDER BY e.type, e.name
         LIMIT 2',
        [$className, 'Semua Kelas', 'Pramuka Reguler']
    );
    if ($rows) {
        return $rows;
    }
    return fetch_all(
        'SELECT e.*, t.name AS teacher_name
         FROM extracurriculars e
         LEFT JOIN teachers t ON t.id = e.teacher_id
         WHERE e.active = 1
         ORDER BY e.type, e.name
         LIMIT 2'
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
            'academic_year' => config('school.academic_year'),
            'semester' => config('school.semester'),
            'address' => '',
            'principal_name' => '',
            'principal_nip' => '',
        ];
    }

    $school = fetch_one('SELECT * FROM school_profile ORDER BY id LIMIT 1');
    return $school ?: [
        'name' => config('school.name'),
        'academic_year' => config('school.academic_year'),
        'semester' => config('school.semester'),
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
    $grade = (string)($student['grade'] ?: $class);
    $address = $school['address'] ?: 'Jl. Sepi  Gg.01 Makam Pahlawan';

    draw_report_asset_badge($pdf, 24.00, 789.00, 28.00, 28.00, 'LOGO', $payload['logo']['file_path'] ?? '');
    draw_report_asset_badge($pdf, 542.00, 789.00, 30.00, 38.00, report_student_initials((string)$student['name']), $payload['photo']['file_path'] ?? '');

    $pdf->setFont('Helvetica', 10);
    $pdf->text(59.53, 775.11, 'Nama Murid');
    $pdf->text(161.58, 775.11, ':');
    $pdf->text(170.08, 775.11, (string)$student['name']);
    $pdf->text(391.18, 775.11, 'Kelas');
    $pdf->text(476.22, 775.11, ':');
    $pdf->text(484.72, 775.11, $class);

    $pdf->text(59.53, 760.94, 'NIS/NISN ');
    $pdf->text(161.58, 760.94, ':');
    $pdf->text(170.08, 760.94, trim(($student['nis'] ?: '-') . ' / ' . ($student['nisn'] ?: '-')));
    $pdf->text(391.18, 760.94, 'Fase');
    $pdf->text(476.22, 760.94, ':');
    $pdf->text(484.72, 760.94, class_phase($grade));

    $pdf->text(59.53, 746.76, 'Sekolah');
    $pdf->text(161.58, 746.76, ':');
    $pdf->text(170.08, 746.76, (string)$school['name']);
    $pdf->text(391.18, 746.76, 'Semester');
    $pdf->text(476.22, 746.76, ':');
    $pdf->text(484.72, 746.76, semester_number());

    $pdf->text(59.53, 732.59, 'Alamat');
    $pdf->text(161.58, 732.59, ':');
    $pdf->text(170.08, 732.59, $address);
    $pdf->text(391.18, 732.59, 'Tahun Ajaran');
    $pdf->text(476.22, 732.59, ':');
    $pdf->text(484.72, 732.59, (string)$school['academic_year']);
    $pdf->line(56.69, 722.83, 538.58, 722.83);

    $pdf->setFont('Helvetica', 7.5);
    $pdf->line(56.69, 36.85, 538.58, 36.85);
    $pdf->text(59.53, 20.43, $class . '  | ' . $student['name'] . ' | ' . $student['nis']);
    $pdf->text(489.08, 20.43, 'Halaman : ' . $pageNo);
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

function draw_report_learning_table_header(SimplePdf $pdf): float
{
    $pdf->setFont('Helvetica', 12, true);
    $pdf->centerText(56.69, 696.56, 481.89, 'LAPORAN HASIL BELAJAR', 12, true);
    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, 680.31, 22.68, -22.68, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(56.69, 665.98, 22.68, 'No', 10, true);
    $pdf->rect(79.37, 680.31, 113.39, -22.68, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(79.37, 665.98, 113.39, 'Mata Pelajaran', 10, true);
    $pdf->rect(192.76, 680.31, 56.69, -22.68, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(192.76, 665.98, 56.69, 'Nilai Akhir', 10, true);
    $pdf->rect(249.45, 680.31, 289.13, -22.68, 'B', [0.973, 0.973, 1.000]);
    $pdf->centerText(249.45, 665.98, 289.13, 'Capaian Kompetensi', 10, true);

    return 657.64;
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
        $lines = array_slice($pdf->wrapText($description, 270, 9), 0, 4);
        $height = max(28.35, 17 + (count($lines) * 11.34));
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
        $pdf->rect(56.69, $y, 22.68, -$height, 'S');
        $pdf->centerText(56.69, $y - ($height / 2) - 3, 22.68, (string)$no, 9);
        $pdf->rect(79.37, $y, 113.39, -$height, 'S');
        $pdf->text(85.04, $y - ($height / 2) - 3, (string)$subject['name'], 9);
        $pdf->rect(192.76, $y, 56.69, -$height, 'S');
        $score = $subject['score'] !== null && $subject['score'] !== '' ? number_format((float)$subject['score'], 0) : '';
        $pdf->centerText(192.76, $y - ($height / 2) - 3, 56.69, $score, 9);
        $pdf->rect(249.45, $y, 289.13, -$height, 'S');
        $lineY = $y - 11.55;
        foreach ($lines as $line) {
            $pdf->text(255.12, $lineY, $line, 9);
            $lineY -= 11.34;
        }
        $y -= $height;
        $no++;
    }

    $sectionGap = 14.17;
    $sectionStackHeight = 85.04 + $sectionGap + 56.69 + $sectionGap + 181.42;
    $sectionTop = min(547.09, $y - $sectionGap);
    if ($sectionTop - $sectionStackHeight < $pageBottomLimit) {
        $pageNo++;
        $pdf->addPage();
        draw_report_identity($pdf, $payload, $pageNo);
        $sectionTop = 547.09;
    }
    $sectionBottom = draw_kokurikuler_section($pdf, $payload, $sectionTop);
    $sectionBottom = draw_extrakurikuler_section($pdf, $payload, $sectionBottom - $sectionGap);
    draw_attendance_note_section($pdf, $payload, $sectionBottom - $sectionGap);

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
    $lines = array_slice($pdf->wrapText($text, 460, 9), 0, 5);
    $lineY = $topY - 33.88;
    foreach ($lines as $line) {
        $pdf->text(62.36, $lineY, $line, 9);
        $lineY -= 11.34;
    }
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
            $note = trim((string)($row['type'] ?? ''));
            $teacher = trim((string)($row['teacher_name'] ?? ''));
            $text = trim(($note !== '' ? $note : 'Aktif') . ($teacher !== '' ? ' - Pembina: ' . $teacher : ''));
            $pdf->text(226.77, $rowTop - 11.5, $text, 9);
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
    $noteLines = array_slice($pdf->wrapText((string)($payload['homeroom_note'] ?? ''), 300, 9), 0, 4);
    $noteY = $noteTop - 12.62;
    foreach ($noteLines as $line) {
        $pdf->text(226.77, $noteY, $line, 9);
        $noteY -= 11.34;
    }
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

    $pdf->setFont('Helvetica', 10, true);
    $pdf->rect(56.69, 711.50, 481.89, -28.35, 'S');
    $pdf->centerText(56.69, 694.32, 481.89, 'Keterangan Kenaikan Kelas  :  Naik/Tidak Naik ke kelas ....... ', 10, true);
    $pdf->setFont('Helvetica', 10);
    $pdf->text(390.11, 660.31, $dateText, 10);
    $pdf->text(81.30, 648.97, 'Orang Tua Murid,', 10);
    $pdf->text(237.51, 648.97, 'Kepala Sekolah,', 10);
    $pdf->text(427.49, 648.97, ' Wali Kelas', 10);
    draw_report_signature_marker($pdf, 230.00, 628.00, 'TTD Digital', (string)($principalSignature['file_path'] ?? ''));
    draw_report_signature_marker($pdf, 418.00, 628.00, 'TTD Digital', (string)($homeroomSignature['file_path'] ?? ''));
    $pdf->text(80.43, 586.61, '............................', 10);
    $pdf->text(234.28, 586.61, $principalName, 10, true);
    $pdf->text(419.02, 586.61, $homeroomName, 10, true);
    $pdf->setFont('Helvetica', 10);
    $pdf->text(211.80, 575.27, 'NIP. ' . $principalNip, 10);
    $pdf->text(441.87, 575.27, 'NIP. ' . $homeroomNip, 10);
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
