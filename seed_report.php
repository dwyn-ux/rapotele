<?php
// Use the existing bootstrap
require __DIR__ . '/app/bootstrap.php';
$configFile = __DIR__ . '/config/config.php';
$GLOBALS['config'] = require $configFile;
require_once __DIR__ . '/app/Core/config.php';
require_once __DIR__ . '/app/Core/helpers.php';
require_once __DIR__ . '/app/Core/database.php';
require_once __DIR__ . '/app/Core/security.php';
require_once __DIR__ . '/app/Core/http.php';
require_once __DIR__ . '/app/migrations.php';
require_once __DIR__ . '/app/Services/pdf.php';
require_once __DIR__ . '/app/Services/schedule.php';
require_once __DIR__ . '/app/Actions/master.php';
require_once __DIR__ . '/app/Actions/assessment.php';
require_once __DIR__ . '/app/Actions/settings.php';
require_once __DIR__ . '/app/Pages/render.php';
require_once __DIR__ . '/app/Pages/helpers.php';
require_once __DIR__ . '/app/Pages/assessment.php';
require_once __DIR__ . '/app/Pages/reports.php';
require_once __DIR__ . '/app/Pages/rapor.php';

run_migrations();
$db = db();

$class = fetch_one("SELECT * FROM classes WHERE name = '1A'");
if (!$class) {
    die("Class 1A not found\n");
}
echo "Using class: {$class['name']} (grade {$class['grade']})\n";

$teacher = fetch_one("SELECT * FROM teachers WHERE id = ?", [(int)$class['homeroom_teacher_id']]);
echo "Teacher: {$teacher['name']}\n";

$generalSubjects = [
    ['Pendidikan Agama Islam', 'PAI', 'Wajib'],
    ['Pendidikan Pancasila', 'PP', 'Wajib'],
    ['Bahasa Indonesia', 'B.Indo', 'Wajib'],
    ['Matematika', 'MTK', 'Wajib'],
    ['IPA', 'IPA', 'Wajib'],
    ['IPS', 'IPS', 'Wajib'],
    ['Bahasa Inggris', 'B.Ing', 'Wajib'],
    ['PJOK', 'PJOK', 'Wajib'],
    ['Seni Budaya', 'SBdP', 'Wajib'],
    ['Informatika', 'Info', 'Wajib'],
    ['Bahasa Daerah', 'B.Daerah', 'Muatan Lokal'],
    ['Prakarya', 'Prakarya', 'Muatan Lokal'],
    ['Keterampilan Komputer', 'Komputer', 'Muatan Lokal'],
];

$specialSubjects = [
    ['Fiqih', 'Fiqih', 'Khusus'],
    ['Tahfidz', 'Tahfidz', 'Khusus'],
    ['Ke-NU-an', 'KeNUan', 'Khusus'],
];

$allSubjectDefs = array_merge($generalSubjects, $specialSubjects);

foreach ($allSubjectDefs as $def) {
    $name = $def[0];
    $short = $def[1];
    $group = $def[2];
    
    $existing = fetch_one("SELECT id FROM subjects WHERE name = ?", [$name]);
    if ($existing) {
        echo "Subject exists: $name (id={$existing['id']})\n";
        continue;
    }
    
    execute_sql(
        "INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, 1, ?)",
        [$name, $short, $group, now_string()]
    );
    $sid = (int)db()->lastInsertId();
    echo "Created subject: $name (id=$sid)\n";
}

$students = fetch_all("SELECT * FROM students WHERE class_id = ? AND active = 1 ORDER BY id", [(int)$class['id']]);
echo "Students: " . count($students) . "\n";

$descriptions = [
    'Pendidikan Agama Islam' => 'Mampu membaca Al-Quran dengan tajwid dasar, memahami rukun iman dan praktik ibadah sehari-hari.',
    'Pendidikan Pancasila' => 'Menunjukkan pemahaman baik tentang aturan, hak, kewajiban, dan kebiasaan bermusyawarah.',
    'Bahasa Indonesia' => 'Mampu menyimak, membaca, dan menyampaikan kembali informasi sederhana dengan bahasa yang runtut.',
    'Matematika' => 'Mampu memahami bilangan, membandingkan jumlah, dan menyelesaikan soal kontekstual sederhana.',
    'IPA' => 'Memahami konsep dasar makhluk hidup, benda, dan energi di lingkungan sekitar.',
    'IPS' => 'Mengenal lingkungan sosial, ekonomi, dan budaya sekitar dengan pemahaman yang cukup baik.',
    'Bahasa Inggris' => 'Mulai mengenal kosakata dasar dan ungkapan sederhana dalam komunikasi sehari-hari.',
    'PJOK' => 'Menunjukkan kemampuan gerak dasar, kerja sama, dan kebiasaan menjaga kebugaran tubuh.',
    'Seni Budaya' => 'Mampu mengekspresikan gagasan melalui gambar, irama, dan karya sederhana dengan percaya diri.',
    'Informatika' => 'Mengenal perangkat komputer dan mampu menggunakan aplikasi sederhana dengan bimbingan.',
    'Bahasa Daerah' => 'Mampu menggunakan bahasa daerah dalam percakapan dan memahami nilai-nilai budaya lokal.',
    'Prakarya' => 'Kreatif dalam membuat kerajinan tangan sederhana dan mengelola bahan bekas.',
    'Keterampilan Komputer' => 'Menguasai dasar mengetik, menggambar, dan membuat dokumen sederhana.',
    'Fiqih' => 'Memahami tata cara wudhu, shalat, puasa, dan doa sehari-hari dengan baik.',
    'Tahfidz' => 'Mampu menghafal surat-surat pendek Juz Amma dengan makhraj dan tajwid yang baik.',
    'Ke-NU-an' => 'Mengenal dasar-dasar Ahlussunnah wal Jamaah, amaliyah, dan tradisi NU.',
];

$allSubjects = fetch_all("SELECT * FROM subjects WHERE active = 1 ORDER BY id");

foreach ($allSubjects as $subj) {
    $subjId = (int)$subj['id'];
    $subjName = $subj['name'];

    $existingAssign = fetch_one(
        "SELECT id FROM teaching_assignments WHERE class_id = ? AND subject_id = ? AND active = 1",
        [(int)$class['id'], $subjId]
    );
    if (!$existingAssign) {
        execute_sql(
            "INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)",
            [(int)$class['homeroom_teacher_id'], (int)$class['id'], $subjId, $class['academic_year'], $class['semester'] ?? 'Genap', now_string()]
        );
        echo "  Created assignment for: $subjName\n";
    }

    $existingMap = fetch_one(
        "SELECT id FROM report_mappings WHERE grade = ? AND subject_id = ?",
        [(string)$class['grade'], $subjId]
    );
    if (!$existingMap) {
        $group = fetch_one("SELECT id FROM subject_groups ORDER BY id LIMIT 1");
        $groupId = $group ? (int)$group['id'] : null;
        execute_sql(
            "INSERT INTO report_mappings (curriculum, grade, subject_id, group_id, display_order, include_in_report, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)",
            ['Kurikulum Merdeka', (string)$class['grade'], $subjId, $groupId, count($allSubjects) * 10, now_string()]
        );
    }

    $assignRow = fetch_one(
        "SELECT id FROM teaching_assignments WHERE class_id = ? AND subject_id = ? AND active = 1",
        [(int)$class['id'], $subjId]
    );
    if (!$assignRow) continue;
    $assignId = (int)$assignRow['id'];

    $desc = $descriptions[$subjName] ?? "Menunjukkan perkembangan belajar yang baik pada $subjName.";
    foreach ($students as $s) {
        $score = rand(73, 96);
        $existingGrade = fetch_one(
            "SELECT id FROM grades WHERE assignment_id = ? AND student_id = ?",
            [$assignId, (int)$s['id']]
        );
        if (!$existingGrade) {
            execute_sql(
                "INSERT INTO grades (assignment_id, student_id, score, description, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
                [$assignId, (int)$s['id'], $score, $desc, 1, now_string()]
            );
        }
        
        $existingFS = fetch_one(
            "SELECT id FROM final_scores WHERE student_id = ? AND subject_id = ?",
            [(int)$s['id'], $subjId]
        );
        if (!$existingFS) {
            execute_sql(
                "INSERT INTO final_scores (student_id, subject_id, score, updated_at) VALUES (?, ?, ?, ?)",
                [(int)$s['id'], $subjId, $score, now_string()]
            );
        }
    }
}

// Make sure school has location and radius set for the attendance feature
$school = get_school_profile();
if (empty($school['location_lat'])) {
    execute_sql("UPDATE school_profile SET location_lat = -6.2088, location_lng = 106.8456, attendance_radius_meters = 500 WHERE id = 1");
    echo "Set school location to default.\n";
}

// Set promotion.enabled
set_app_setting('promotion.enabled', '1');

// Add sample extracurricular scores
if (table_exists('extracurricular_scores')) {
    foreach ($students as $s) {
        $ekskuls = fetch_all('SELECT id FROM extracurriculars WHERE active = 1 LIMIT 2');
        $scores = ['Baik', 'Sangat Baik'];
        foreach ($ekskuls as $idx => $ekskul) {
            $existing = fetch_one('SELECT id FROM extracurricular_scores WHERE student_id = ? AND extracurricular_id = ?', [(int)$s['id'], (int)$ekskul['id']]);
            if (!$existing) {
                execute_sql('INSERT INTO extracurricular_scores (student_id, extracurricular_id, score, updated_at) VALUES (?, ?, ?, ?)', [(int)$s['id'], (int)$ekskul['id'], $scores[$idx % count($scores)], now_string()]);
            }
        }
    }
    echo "Added sample extracurricular scores.\n";
}

echo "\n=== Generating Report PDF ===\n";

$firstStudent = $students[0];
echo "Generating for: {$firstStudent['name']} (id={$firstStudent['id']})\n";

$payload = report_student_payload((int)$firstStudent['id']);
echo "Subjects in payload: " . count($payload['subjects']) . "\n";
foreach ($payload['subjects'] as $subj) {
    echo "  - {$subj['name']} ({$subj['group_name']}): {$subj['score']}\n";
}

$pdfFile = generate_student_report_pdf((int)$firstStudent['id']);
echo "\nPDF generated: $pdfFile\n";
echo "File size: " . filesize($pdfFile) . " bytes\n";
echo "Done.\n";
