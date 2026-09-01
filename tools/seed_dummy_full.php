<?php

declare(strict_types=1);

$base = __DIR__ . '/..';
require $base . '/app/bootstrap.php';
require $base . '/app/web.php';

$pdo = db();
set_exception_handler(function (Throwable $e) {
    echo PHP_EOL . "FATAL: " . $e->getMessage() . PHP_EOL;
    echo "AT " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
});
$now = now_string();

function ensure_column(string $table, string $column, string $definition): void
{
    if (!table_exists($table)) return;
    $key = db_driver() === 'sqlite' ? 'name' : 'Field';
    $cols = array_column(fetch_all(
        db_driver() === 'sqlite' ? 'PRAGMA table_info(' . $table . ')' : 'SHOW COLUMNS FROM ' . $table
    ), $key);
    if (!in_array($column, $cols, true)) {
        echo "  -> adding column $table.$column\n";
        db()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

ensure_column('subjects', 'curriculum', 'VARCHAR(32) NULL');
ensure_column('subjects', 'kkm', 'INT NULL');
ensure_column('classes', 'level', 'VARCHAR(16) NULL');
ensure_column('classes', 'school_id', 'INT UNSIGNED NULL');
ensure_column('students', 'birth_place', 'VARCHAR(80) NULL');
ensure_column('students', 'birth_date', 'DATE NULL');
ensure_column('students', 'religion', 'VARCHAR(64) NULL');
ensure_column('students', 'address', 'TEXT NULL');
ensure_column('students', 'phone', 'VARCHAR(32) NULL');
ensure_column('students', 'father_name', 'VARCHAR(160) NULL');
ensure_column('students', 'father_occupation', 'VARCHAR(120) NULL');
ensure_column('students', 'mother_name', 'VARCHAR(160) NULL');
ensure_column('students', 'mother_occupation', 'VARCHAR(120) NULL');
echo "Schema check OK\n\n";
$currentYear = '2024/2025';
$currentSemester = '2';
$sqlIgnore = db_insert_ignore();
$sqlReplace = db_insert_replace();

echo "=== RESET DATA ===\n";
$pdo->exec('DELETE FROM final_scores');
$pdo->exec('DELETE FROM exam_scores');
$pdo->exec('DELETE FROM grades');
$pdo->exec('DELETE FROM learning_objectives');
$pdo->exec('DELETE FROM student_descriptions');
$pdo->exec('DELETE FROM student_attendance_entries');
$pdo->exec('DELETE FROM student_attendance_sessions');
$pdo->exec('DELETE FROM report_mappings');
$pdo->exec('DELETE FROM report_dates');
$pdo->exec('DELETE FROM graduations');
$pdo->exec('DELETE FROM student_violations');
$pdo->exec('DELETE FROM student_rewards');
$pdo->exec('DELETE FROM teaching_assignments');
$pdo->exec('DELETE FROM students');
$pdo->exec('DELETE FROM classes');
$pdo->exec('DELETE FROM subjects');
$pdo->exec('DELETE FROM subject_groups');
$pdo->exec('DELETE FROM school_profile');
$pdo->exec('DELETE FROM users WHERE username != "administrator"');
$pdo->exec('DELETE FROM extracurricular_members');
$pdo->exec('DELETE FROM extracurricular_scores');
$pdo->exec('DELETE FROM extracurriculars');
$pdo->exec('DELETE FROM cocurricular_members');
$pdo->exec('DELETE FROM cocurricular_activities');
$pdo->exec('DELETE FROM cocurricular_groups');
$pdo->exec('DELETE FROM cocurricular_themes');
$pdo->exec('DELETE FROM signatures');
$pdo->exec('DELETE FROM violation_rules');
echo "Tables cleared\n\n";

echo "=== SEKOLAH 1: SMP Muhammadiyah Unggulan Ashidiq ===\n";
$stmt = $pdo->prepare('INSERT INTO school_profile (name, npsn, address, village, district, regency, province, principal_name, principal_nip, academic_year, semester, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    'SMP Muhammadiyah Unggulan Ashidiq', '60724810',
    'Jl. Kauman No. 12, Ngawen', 'Kauman', 'Ngawen', 'Kab. Blora', 'Jawa Tengah',
    'Drs. H. Ahmad Subagyo, M.Pd', '196512151990031005',
    $currentYear, $currentSemester, $now, $now
]);
$smpId = (int)$pdo->lastInsertId();
echo "School 1 (SMP Ashidiq) id=$smpId\n";

echo "=== SEKOLAH 2: SMA Muhammadiyah Ngawen ===\n";
$stmt->execute([
    'SMA Muhammadiyah Ngawen', '60724821',
    'Kompleks Masjid Kota Kecamatan Ngawen', 'Ngawen', 'Ngawen', 'Kab. Blora', 'Jawa Tengah',
    'Dr. H. Sutrisno, M.Pd', '196803201991031002',
    $currentYear, $currentSemester, $now, $now
]);
$smaId = (int)$pdo->lastInsertId();
echo "School 2 (SMA Ngawen) id=$smaId\n\n";

echo "=== SUBJECT GROUPS ===\n";
$groupData = [
    ['A', 'Kelompok A', 1],
    ['B', 'Kelompok B', 2],
    ['C', 'Muatan Lokal', 3],
    ['P5', 'Kokurikuler/P5', 4],
    ['WAJIB_A', 'Mata Pelajaran Wajib A', 10],
    ['WAJIB_B', 'Mata Pelajaran Wajib B', 11],
    ['PILIHAN', 'Mata Pelajaran Pilihan', 12],
    ['MULOK', 'Muatan Lokal', 13],
];
$groups = [];
foreach ($groupData as $g) {
    $stmt = $pdo->prepare('INSERT INTO subject_groups (code, name, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$g[0], $g[1], 'aktif', $g[2], $now, $now]);
    $groups[$g[0]] = (int)$pdo->lastInsertId();
}
echo "Groups: " . count($groups) . "\n\n";

echo "=== MAPEL SMP (Kurikulum Merdeka) ===\n";
$smpMapel = [
    // ['nama', 'kkm', 'curriculum', 'group_code']
    ['Pendidikan Agama Islam dan Budi Pekerti', 75, null, 'WAJIB_A'],
    ['Pendidikan Pancasila', 75, null, 'WAJIB_A'],
    ['Bahasa Indonesia', 75, null, 'WAJIB_A'],
    ['Matematika', 70, null, 'WAJIB_A'],
    ['Ilmu Pengetahuan Alam', 73, null, 'WAJIB_B'],
    ['Ilmu Pengetahuan Sosial', 73, null, 'WAJIB_B'],
    ['Bahasa Inggris', 75, null, 'WAJIB_B'],
    ['Pendidikan Jasmani, Olahraga, dan Kesehatan', 75, null, 'MULOK'],
    ['Seni dan Budaya', 75, null, 'MULOK'],
    ['Informatika', 75, null, 'MULOK'],
    ['Bahasa Jawa', 75, null, 'MULOK'],
    ['Prakarya dan Kewirausahaan', 75, null, 'MULOK'],
];
$smpMapelIds = [];
$stmt = $pdo->prepare('INSERT INTO subjects (name, short_name, group_name, curriculum, kkm, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
foreach ($smpMapel as $m) {
    $shortName = strtoupper(substr($m[0], 0, 30));
    $stmt->execute([$m[0], $shortName, $m[3], $m[2], $m[1], $now, $now]);
    $smpMapelIds[$m[0]] = (int)$pdo->lastInsertId();
}
echo "SMP mapel: " . count($smpMapelIds) . "\n";

echo "=== MAPEL SMA (Kurikulum Merdeka Fase E) ===\n";
$smaMapel = [
    // Wajib A (umum)
    ['Pendidikan Agama Islam dan Budi Pekerti', 75, null, 'WAJIB_A'],
    ['Pendidikan Pancasila dan Kewarganegaraan', 75, null, 'WAJIB_A'],
    ['Bahasa Indonesia', 75, null, 'WAJIB_A'],
    ['Matematika', 75, null, 'WAJIB_A'],
    // Wajib B
    ['Bahasa Inggris', 75, null, 'WAJIB_B'],
    ['Informatika', 75, null, 'WAJIB_B'],
    ['Pendidikan Jasmani, Olahraga, dan Kesehatan', 75, null, 'WAJIB_B'],
    ['Seni Rupa', 75, null, 'WAJIB_B'],
    // Pilihan IPA
    ['Biologi', 75, 'IPA', 'PILIHAN'],
    ['Fisika', 75, 'IPA', 'PILIHAN'],
    ['Kimia', 75, 'IPA', 'PILIHAN'],
    // Pilihan IPS
    ['Ekonomi', 75, 'IPS', 'PILIHAN'],
    ['Sosiologi', 75, 'IPS', 'PILIHAN'],
    ['Sejarah', 75, 'IPS', 'PILIHAN'],
    ['Geografi', 75, 'IPS', 'PILIHAN'],
    // Mulok
    ['Bahasa Jawa', 75, null, 'MULOK'],
    ['Prakarya dan Kewirausahaan', 75, null, 'MULOK'],
    ['Kemuhammadiyahan', 75, null, 'MULOK'],
];
$smaMapelIds = [];
foreach ($smaMapel as $m) {
    $shortName = strtoupper(substr($m[0], 0, 30));
    $stmt->execute([$m[0], $shortName, $m[3], $m[2], $m[1], $now, $now]);
    $smaMapelIds[$m[0]] = (int)$pdo->lastInsertId();
}
echo "SMA mapel: " . count($smaMapelIds) . "\n\n";

echo "=== REPORT MAPPINGS ===\n";
$order = 1;
$stmt = $pdo->prepare($sqlIgnore . ' INTO report_mappings (curriculum, grade, subject_id, group_id, display_order, include_in_report, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
foreach (['7', '8', '9'] as $grade) {
    foreach ($smpMapel as $m) {
        $stmt->execute(['merdeka_smp', $grade, $smpMapelIds[$m[0]], $groups[$m[3]], $order++, $now, $now]);
    }
    $order = 1;
}
foreach (['10', '11', '12'] as $grade) {
    foreach ($smaMapel as $m) {
        $stmt->execute(['merdeka_sma', $grade, $smaMapelIds[$m[0]], $groups[$m[3]], $order++, $now, $now]);
    }
    $order = 1;
}
echo "Mappings created\n\n";

echo "=== GURU ===\n";
$teacherData = [
    ['Drs. H. Ahmad Subagyo, M.Pd', '196512151990031005', 'P', 'Kepala Sekolah'],
    ['Siti Aminah, S.Pd', '198601012010012001', 'P', 'Guru Kelas / Wali Kelas 7A'],
    ['Budi Santoso, S.Pd', '198410102009011002', 'L', 'Guru Mapel Matematika'],
    ['Rina Lestari, S.Pd', '198505052011012003', 'P', 'Guru Mapel IPA'],
    ['Agus Prasetyo, S.Pd', '198207152008011004', 'L', 'Guru Mapel IPS'],
    ['Dewi Anggraini, S.Pd', '199003202014012005', 'P', 'Guru Mapel Bahasa Indonesia'],
    ['Hendro Wibowo, S.Pd', '198811122012011006', 'L', 'Guru Mapel Bahasa Inggris'],
    ['Sri Wahyuni, S.Pd', '198709092011012007', 'P', 'Guru Mapel PAI'],
    ['Muhammad Arif, S.Pd.I', '198606062010011008', 'L', 'Guru Mapel PAI / Kemuhammadiyahan'],
    ['Fitri Handayani, S.Pd', '199205252015012009', 'P', 'Guru Mapel Seni Budaya'],
    ['Bambang Suryadi, S.Pd', '198004042005011010', 'L', 'Guru PJOK'],
    ['Tuti Alawiyah, S.Pd', '198903102013012011', 'P', 'Wali Kelas 8A'],
    ['Joko Susilo, S.Pd', '198702282011011012', 'L', 'Wali Kelas 9A'],
    ['Dra. Hj. Khadijah, M.Pd', '196805151994032013', 'P', 'Wali Kelas 10A'],
    ['Ir. Hendra Gunawan, M.T', '197512102000031014', 'L', 'Guru Mapel Fisika'],
    ['Dr. Endang Sulistyowati', '197408082000032015', 'P', 'Guru Mapel Biologi'],
    ['Wahyu Pratama, S.Pd', '198909092014011016', 'L', 'Guru Mapel Kimia'],
    ['Hesti Wulandari, S.E', '199112122015012017', 'P', 'Guru Mapel Ekonomi'],
    ['Galih Pratama, S.Sn', '199010102015011018', 'L', 'Guru Seni Rupa'],
    ['Nur Hidayah, S.Pd', '199211112016012019', 'P', 'Wali Kelas 11A'],
    ['Yusuf Effendi, S.Pd', '198801082013011020', 'L', 'Wali Kelas 12A'],
];
$teacherIds = [];
$stmt = $pdo->prepare('INSERT INTO teachers (name, nip, gender, position, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
foreach ($teacherData as $t) {
    $stmt->execute([$t[0], $t[1], $t[2], $t[3], $now, $now]);
    $teacherIds[] = (int)$pdo->lastInsertId();
}
echo "Teachers: " . count($teacherIds) . "\n\n";

echo "=== USERS (admin + guru) ===\n";
$users = [
    ['administrator', 'administrator', 'Administrator', 'admin@eraport.local', 'admin', null, ''],
    ['guru1', 'guru1', 'Siti Aminah, S.Pd', 'guru1@eraport.local', 'guru', $teacherIds[1], ''],
    ['guru2', 'guru2', 'Budi Santoso, S.Pd', 'guru2@eraport.local', 'guru', $teacherIds[2], ''],
    ['guru3', 'guru3', 'Rina Lestari, S.Pd', 'guru3@eraport.local', 'guru', $teacherIds[3], ''],
    ['guru4', 'guru4', 'Agus Prasetyo, S.Pd', 'guru4@eraport.local', 'guru', $teacherIds[4], ''],
];
$stmt = $pdo->prepare($sqlIgnore . ' INTO users (username, password_hash, name, email, role, teacher_id, telegram_chat_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
foreach ($users as $u) {
    $hash = password_hash($u[1], PASSWORD_BCRYPT);
    $stmt->execute([$u[0], $hash, $u[2], $u[3], $u[4], $u[5], $u[6], $now, $now]);
}
echo "Users: " . count($users) . "\n\n";

echo "=== KELAS ===\n";
echo "DEBUG: smpId=$smpId smaId=$smaId teacherIds count=" . count($teacherIds) . PHP_EOL;
$classData = [
    // SMP Ashidiq
    ['VII A', '7', 'SMP', null, $smpId, $teacherIds[1]],
    ['VII B', '7', 'SMP', null, $smpId, $teacherIds[1]],
    ['VIII A', '8', 'SMP', null, $smpId, $teacherIds[11]],
    ['VIII B', '8', 'SMP', null, $smpId, $teacherIds[11]],
    ['IX A', '9', 'SMP', null, $smpId, $teacherIds[12]],
    ['IX B', '9', 'SMP', null, $smpId, $teacherIds[12]],
    // SMA Ngawen
    ['X MIPA 1', '10', 'SMA', 'IPA', $smaId, $teacherIds[13]],
    ['X MIPA 2', '10', 'SMA', 'IPA', $smaId, $teacherIds[13]],
    ['XI MIPA 1', '11', 'SMA', 'IPA', $smaId, $teacherIds[19]],
    ['XII MIPA 1', '12', 'SMA', 'IPA', $smaId, $teacherIds[20]],
    ['XII IPS 1', '12', 'SMA', 'IPS', $smaId, $teacherIds[20]],
];
$classIds = [];
$stmt = $pdo->prepare('INSERT INTO classes (name, grade, level, major, school_id, homeroom_teacher_id, academic_year, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
foreach ($classData as $c) {
    try {
        $stmt->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $currentYear, $now, $now]);
        $classIds[$c[0]] = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        echo "GAGAL insert kelas '{$c[0]}': " . $e->getMessage() . PHP_EOL;
        throw $e;
    }
}
echo "Classes: " . count($classIds) . "\n\n";

echo "=== TEACHING ASSIGNMENTS ===\n";
$stmt = $pdo->prepare($sqlIgnore . ' INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
foreach ($classIds as $className => $classId) {
    $level = strpos($className, 'MIPA') !== false || strpos($className, 'IPS') !== false ? 'SMA' : 'SMP';
    $mapelIds = $level === 'SMA' ? $smaMapelIds : $smpMapelIds;
    $i = 0;
    foreach ($mapelIds as $mapelName => $mapelId) {
        $teacherId = $teacherIds[($i % (count($teacherIds) - 4)) + 1];
        $stmt->execute([$teacherId, $classId, $mapelId, $currentYear, $currentSemester, $now, $now]);
        $i++;
    }
}
echo "Teaching assignments created\n\n";

echo "=== SISWA ===\n";
$siswaNames = [
    // SMP kelas 7
    ['Ahmad Fauzan', 'L', '1001', '0091001001'],
    ['Bilqis Aulia', 'P', '1002', '0091001002'],
    ['Cahya Pratama', 'L', '1003', '0091001003'],
    ['Dinda Safitri', 'P', '1004', '0091001004'],
    ['Eka Saputra', 'L', '1005', '0091001005'],
    ['Fitriani Rahma', 'P', '1006', '0091001006'],
    ['Galih Pratama', 'L', '1007', '0091001007'],
    ['Hanifah Putri', 'P', '1008', '0091001008'],
    // SMP kelas 8
    ['Irfan Hakim', 'L', '2001', '0092001001'],
    ['Jihan Aulia', 'P', '2002', '0092001002'],
    ['Krisna Mukti', 'L', '2003', '0092001003'],
    ['Lestari Wulandari', 'P', '2004', '0092001004'],
    ['Muhammad Rafi', 'L', '2005', '0092001005'],
    ['Naufal Pradana', 'L', '2006', '0092001006'],
    // SMP kelas 9
    ['Oktaviani Sari', 'P', '3001', '0093001001'],
    ['Putra Mahardika', 'L', '3002', '0093001002'],
    ['Qori Hidayatullah', 'L', '3003', '0093001003'],
    ['Rina Salsabila', 'P', '3004', '0093001004'],
    ['Surya Pranata', 'L', '3005', '0093001005'],
    // SMA kelas 10
    ['Abdurrahman Izzatuddhuha', 'L', '4001', '3098271354'],
    ['Bintang Wisesa', 'L', '4002', '3098271355'],
    ['Citra Dewi Lestari', 'P', '4003', '3098271356'],
    ['Daffa Pratama', 'L', '4004', '3098271357'],
    ['Elvira Rahmadhani', 'P', '4005', '3098271358'],
    ['Fadhil Ramadan', 'L', '4006', '3098271359'],
    // SMA kelas 11
    ['Galang Pradana', 'L', '5001', '3098271360'],
    ['Hanifa Khairani', 'P', '5002', '3098271361'],
    ['Iqbal Maulana', 'L', '5003', '3098271362'],
    ['Jasmine Aulia', 'P', '5004', '3098271363'],
    // SMA kelas 12
    ['Khaesi Fara Fida', 'P', '6001', '3098271364'],
    ['Lutfi Alamsyah', 'L', '6002', '3098271365'],
    ['Mellyza Anggraini', 'P', '6003', '3098271366'],
    ['Naufal Akbar', 'L', '6004', '3098271367'],
];

$siswaPerKelas = [
    'VII A' => 4, 'VII B' => 4, 'VIII A' => 3, 'VIII B' => 3, 'IX A' => 2, 'IX B' => 3,
    'X MIPA 1' => 3, 'X MIPA 2' => 3, 'XI MIPA 1' => 4, 'XII MIPA 1' => 2, 'XII IPS 1' => 2,
];
$stmt = $pdo->prepare('INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, religion, address, phone, father_name, father_occupation, mother_name, mother_occupation, class_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
$idx = 0;
$siswaByClass = [];
$birthPlaces = ['Ngawen', 'Blora', 'Cepu', 'Rembang', 'Purwodadi', 'Kudus', 'Semarang', 'Solo', 'Sragen'];
$religions = ['Islam', 'Islam', 'Islam', 'Islam', 'Kristen', 'Katolik', 'Hindu'];
$fatherOccs = ['Petani', 'Wiraswasta', 'PNS', 'Guru', 'Pedagang', 'Karyawan Swasta', 'Buruh', 'Sopir', 'Nelayan'];
$motherOccs = ['Ibu Rumah Tangga', 'Ibu Rumah Tangga', 'Ibu Rumah Tangga', 'Guru', 'Pedagang', 'Karyawan', 'PNS', 'Penjahit'];
mt_srand(2024);
foreach ($siswaPerKelas as $kelasName => $jumlah) {
    $classId = $classIds[$kelasName];
    $siswaByClass[$kelasName] = [];
    for ($i = 0; $i < $jumlah; $i++) {
        if ($idx >= count($siswaNames)) break;
        $s = $siswaNames[$idx++];
        $bp = $birthPlaces[array_rand($birthPlaces)];
        $yob = mt_rand(2008, 2011);
        $mob = mt_rand(1, 12);
        $dob = mt_rand(1, 28);
        $bd = sprintf('%04d-%02d-%02d', $yob, $mob, $dob);
        $rel = $religions[array_rand($religions)];
        $addr = 'Dsn. ' . ['Krajan', 'Ngemplak', 'Sambirejo', 'Pojok'][array_rand(['Krajan', 'Ngemplak', 'Sambirejo', 'Pojok'])] . ', Rt ' . mt_rand(1, 8) . '/Rw ' . mt_rand(1, 5) . ', ' . $bp;
        $phone = '08' . mt_rand(1111111111, 9999999999);
        $fName = 'Bpk. ' . ['Sutrisno', 'Wahyudi', 'Supriyadi', 'Hartono', 'Sudirman', 'Paiman'][array_rand(['Sutrisno', 'Wahyudi', 'Supriyadi', 'Hartono', 'Sudirman', 'Paiman'])];
        $mName = 'Ibu ' . ['Sumiati', 'Puji Astuti', 'Suryani', 'Wahyuni', 'Suprapti', 'Sri Rejeki'][array_rand(['Sumiati', 'Puji Astuti', 'Suryani', 'Wahyuni', 'Suprapti', 'Sri Rejeki'])];
        $fOcc = $fatherOccs[array_rand($fatherOccs)];
        $mOcc = $motherOccs[array_rand($motherOccs)];
        $stmt->execute([$s[2], $s[3], $s[0], $s[1], $bp, $bd, $rel, $addr, $phone, $fName, $fOcc, $mName, $mOcc, $classId, $now, $now]);
        $siswaByClass[$kelasName][] = (int)$pdo->lastInsertId();
    }
}
$totalSiswa = 0;
foreach ($siswaByClass as $arr) $totalSiswa += count($arr);
echo "Siswa: $totalSiswa\n\n";

echo "=== REPORT DATES ===\n";
$stmt = $pdo->prepare($sqlReplace . ' INTO report_dates (grade, report_date, principal_place, homeroom_place, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$dates = [
    ['7', '2025-06-20', 'Ngawen', 'Ngawen'],
    ['8', '2025-06-20', 'Ngawen', 'Ngawen'],
    ['9', '2025-06-20', 'Ngawen', 'Ngawen'],
    ['10', '2025-05-25', 'Ngawen', 'Ngawen'],
    ['11', '2025-05-25', 'Ngawen', 'Ngawen'],
    ['12', '2025-05-15', 'Ngawen', 'Ngawen'],
];
foreach ($dates as $d) {
    $stmt->execute([$d[0], $d[1], $d[2], $d[3], $now, $now]);
}
echo "Report dates created\n\n";

echo "=== NILAI (final_scores) ===\n";
mt_srand(42);
$stmt = $pdo->prepare($sqlReplace . ' INTO final_scores (student_id, subject_id, score, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
$examStmt = $pdo->prepare($sqlReplace . ' INTO exam_scores (student_id, subject_id, score, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
$descStmt = $pdo->prepare($sqlIgnore . ' INTO learning_objectives (subject_id, grade, description, active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
$loCount = 0;
foreach ($smaMapelIds as $nama => $id) {
    $descStmt->execute([$id, '10', 'Memahami konsep dasar ' . $nama, $now, $now]);
    $descStmt->execute([$id, '10', 'Menerapkan ' . $nama . ' dalam kehidupan', $now, $now]);
    $descStmt->execute([$id, '11', 'Menganalisis ' . $nama, $now, $now]);
    $descStmt->execute([$id, '11', 'Mengevaluasi penerapan ' . $nama, $now, $now]);
    $descStmt->execute([$id, '12', 'Mencipta solusi berbasis ' . $nama, $now, $now]);
    $descStmt->execute([$id, '12', 'Mengkomunikasikan hasil analisis ' . $nama, $now, $now]);
    $loCount += 6;
}
foreach ($smpMapelIds as $nama => $id) {
    $descStmt->execute([$id, '7', 'Mengenal konsep dasar ' . $nama, $now, $now]);
    $descStmt->execute([$id, '7', 'Menerapkan ' . $nama . ' dalam kehidupan', $now, $now]);
    $descStmt->execute([$id, '8', 'Memahami ' . $nama . ' lebih lanjut', $now, $now]);
    $descStmt->execute([$id, '8', 'Menerapkan ' . $nama . ' dalam konteks nyata', $now, $now]);
    $descStmt->execute([$id, '9', 'Menganalisis konsep ' . $nama, $now, $now]);
    $descStmt->execute([$id, '9', 'Mengevaluasi penerapan ' . $nama, $now, $now]);
    $loCount += 6;
}
echo "Learning objectives: $loCount\n";

$scoreCount = 0;
foreach ($siswaByClass as $kelasName => $studentIds) {
    $isSma = strpos($kelasName, 'MIPA') !== false || strpos($kelasName, 'IPS') !== false;
    $mapelIds = $isSma ? $smaMapelIds : $smpMapelIds;
    foreach ($studentIds as $studentId) {
        foreach ($mapelIds as $mapelName => $subjectId) {
            $score = mt_rand(70, 95);
            $stmt->execute([$studentId, $subjectId, $score, $now, $now]);
            $examStmt->execute([$studentId, $subjectId, $score, $now, $now]);
            $scoreCount++;
        }
    }
}
echo "Scores: $scoreCount\n\n";

echo "=== GRADUATIONS ===\n";
$stmt = $pdo->prepare($sqlReplace . ' INTO graduations (student_id, status, certificate_no, transcript_no, graduation_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach ($siswaByClass['XII MIPA 1'] as $sid) {
    $stmt->execute([$sid, 'lulus', 'SKL/' . $currentYear . '/A' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), 'TR/' . $currentYear . '/A' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), '2025-05-15', $now, $now]);
}
foreach ($siswaByClass['XII IPS 1'] as $sid) {
    $stmt->execute([$sid, 'lulus', 'SKL/' . $currentYear . '/B' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), 'TR/' . $currentYear . '/B' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), '2025-05-15', $now, $now]);
}
foreach ($siswaByClass['IX A'] as $sid) {
    $stmt->execute([$sid, 'lulus', 'SKL-MP/' . $currentYear . '/' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), 'TR-MP/' . $currentYear . '/' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), '2025-06-20', $now, $now]);
}
foreach ($siswaByClass['IX B'] as $sid) {
    $stmt->execute([$sid, 'lulus', 'SKL-MP/' . $currentYear . '/' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), 'TR-MP/' . $currentYear . '/' . str_pad((string)$sid, 4, '0', STR_PAD_LEFT), '2025-06-20', $now, $now]);
}
echo "Graduations created\n\n";

echo "=== ATTENDANCE (via teaching_assignments) ===\n";
mt_srand(123);
$attSessionStmt = $pdo->prepare($sqlIgnore . ' INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
$attEntryStmt = $pdo->prepare($sqlIgnore . ' INTO student_attendance_entries (session_id, student_id, status, created_at) VALUES (?, ?, ?, ?)');
$attCount = 0;
$attSessionCount = 0;
foreach ($classIds as $className => $classId) {
    $isSma = strpos($className, 'MIPA') !== false || strpos($className, 'IPS') !== false;
    $mapelIds = $isSma ? $smaMapelIds : $smpMapelIds;
    $taStmt = $pdo->prepare('SELECT id FROM teaching_assignments WHERE class_id = ? ORDER BY id LIMIT 1');
    $taStmt->execute([$classId]);
    $taRow = $taStmt->fetch(PDO::FETCH_ASSOC);
    $taId = $taRow ? (int)$taRow['id'] : 0;
    if ($taId === 0) continue;
    foreach (range(1, 5) as $meet) {
        $date = sprintf('2025-04-%02d', $meet * 5);
        $attSessionStmt->execute([$taId, $date, $meet, 'Pertemuan ' . $meet, null, $now, $now]);
        $sessionRow = $pdo->query("SELECT id FROM student_attendance_sessions WHERE assignment_id=$taId AND date='$date'")->fetch(PDO::FETCH_ASSOC);
        $sessionId = (int)$sessionRow['id'];
        if ($sessionId > 0) $attSessionCount++;
        foreach ($siswaByClass[$className] ?? [] as $studentId) {
            $r = mt_rand(0, 20);
            $status = $r < 17 ? 'hadir' : ($r < 19 ? 'sakit' : ($r < 20 ? 'izin' : 'alpa'));
            $attEntryStmt->execute([$sessionId, $studentId, $status, $now]);
            $attCount++;
        }
    }
}
echo "Attendance sessions: $attSessionCount, entries: $attCount\n\n";

echo "=== SIGNATURES ===\n";
$stmt = $pdo->prepare($sqlReplace . ' INTO signatures (type, user_id, title, person_name, nip, file_path, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)');
$stmt->execute(['principal', 'Kepala Sekolah', 'Drs. H. Ahmad Subagyo, M.Pd', '196512151990031005', '', $now, $now]);
$stmt->execute(['homeroom', 'Wali Kelas', 'Siti Aminah, S.Pd', '198601012010012001', '', $now, $now]);
echo "Signatures created\n\n";

echo "=== VIOLATION RULES ===\n";
$violationRules = [
    ['R001', 'Ringan', 'Terlambat masuk sekolah', 5],
    ['R002', 'Ringan', 'Tidak mengerjakan PR', 5],
    ['R003', 'Ringan', 'Tidak memakai atribut sekolah', 3],
    ['R004', 'Ringan', 'Makan di kelas', 3],
    ['R005', 'Sedang', 'Bolos satu mata pelajaran', 10],
    ['R006', 'Sedang', 'Tidak mengikuti upacara bendera', 10],
    ['R007', 'Sedang', 'Berkelahi ringan', 15],
    ['R008', 'Sedang', 'Membolos setengah hari', 20],
    ['R009', 'Berat', 'Berkelahi dengan senjata tajam', 50],
    ['R010', 'Berat', 'Merokok di lingkungan sekolah', 50],
    ['R011', 'Berat', 'Membolos satu hari penuh', 30],
    ['R012', 'Berat', 'Merusak fasilitas sekolah', 40],
];
$ruleStmt = $pdo->prepare($sqlIgnore . ' INTO violation_rules (code, category, description, points, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
foreach ($violationRules as $r) {
    $ruleStmt->execute([$r[0], $r[1], $r[2], $r[3], $now, $now]);
}
echo "Rules: " . count($violationRules) . "\n\n";

echo "=== STUDENT VIOLATIONS ===\n";
$ruleIds = [];
foreach ($pdo->query('SELECT id, code FROM violation_rules ORDER BY id') as $r) $ruleIds[$r['code']] = (int)$r['id'];
$violStmt = $pdo->prepare($sqlIgnore . ' INTO student_violations (student_id, date, type, description, points, action_taken, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)');
$violCount = 0;
$vSample = [
    ['R001', 'Terlambat 15 menit', 'Teguran lisan'],
    ['R002', 'Tidak mengerjakan PR Matematika', 'Teguran + disuruh kerjakan di sekolah'],
    ['R003', 'Tidak pakai dasi', 'Teguran'],
    ['R005', 'Bolos jam terakhir', 'Panggilan orang tua'],
    ['R006', 'Tidak ikut upacara', 'Teguran tertulis'],
    ['R011', 'Bolos seharian', 'Surat peringatan 1'],
];
mt_srand(99);
foreach ($siswaByClass as $kelasName => $studentIds) {
    foreach ($studentIds as $studentId) {
        $numViolations = mt_rand(0, 3);
        for ($i = 0; $i < $numViolations; $i++) {
            $v = $vSample[mt_rand(0, count($vSample) - 1)];
            $ruleId = $ruleIds[$v[0]] ?? null;
            if (!$ruleId) continue;
            $stmtRule = $pdo->prepare('SELECT description, points FROM violation_rules WHERE id = ?');
            $stmtRule->execute([$ruleId]);
            $rr = $stmtRule->fetch(PDO::FETCH_ASSOC);
            $date = sprintf('2025-0%d-%02d', mt_rand(2, 5), mt_rand(1, 28));
            $violStmt->execute([$studentId, $date, $rr['description'], $rr['description'], (int)$rr['points'], $v[2], $now, $now]);
            $violCount++;
        }
    }
}
echo "Violations: $violCount\n\n";

echo "=== STUDENT REWARDS ===\n";
$rewStmt = $pdo->prepare($sqlIgnore . ' INTO student_rewards (student_id, date, title, description, discount_percent, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, ?, ?)');
$rSample = [
    ['Juara 1 Lomba Cerdas Cermat', 'Mewakili sekolah di lomba kecamatan', 5],
    ['Juara 2 Olimpiade Matematika', 'Olimpiade tingkat kabupaten', 10],
    ['Juara 3 Lomba Pidato', 'Lomba peringatan HUT RI', 5],
    ['Siswa Teladan', 'Siswa teladan semester ini', 15],
    ['Juara 1 Futsal', 'Turnamen antar sekolah', 5],
    ['Juara 2 Seni Tari', 'Festival seni budaya', 5],
];
$rewCount = 0;
foreach ($siswaByClass as $kelasName => $studentIds) {
    foreach ($studentIds as $studentId) {
        $numRewards = mt_rand(0, 2);
        for ($i = 0; $i < $numRewards; $i++) {
            $r = $rSample[mt_rand(0, count($rSample) - 1)];
            $date = sprintf('2025-0%d-%02d', mt_rand(2, 5), mt_rand(1, 28));
            $rewStmt->execute([$studentId, $date, $r[0], $r[1], $r[2], $now, $now]);
            $rewCount++;
        }
    }
}
echo "Rewards: $rewCount\n\n";

echo "=== EKSTRAKURIKULER ===\n";
$ekskulData = [
    ['Pramuka', 'Wajib', 'Budi Santoso, S.Pd', $teacherIds[2]],
    ['Paskibraka', 'Pilihan', 'Bambang Suryadi, S.Pd', $teacherIds[10]],
    ['Futsal', 'Pilihan', 'Hendro Wibowo, S.Pd', $teacherIds[6]],
    ['Seni Tari', 'Pilihan', 'Fitri Handayani, S.Pd', $teacherIds[9]],
    ['Tapak Suci', 'Wajib', 'Muhammad Arif, S.Pd.I', $teacherIds[8]],
    ['English Club', 'Pilihan', 'Hendro Wibowo, S.Pd', $teacherIds[6]],
    ['Jurnalistik', 'Pilihan', 'Dewi Anggraini, S.Pd', $teacherIds[5]],
];
$eksStmt = $pdo->prepare($sqlIgnore . ' INTO extracurriculars (class_name, name, type, teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)');
foreach ($ekskulData as $e) {
    $eksStmt->execute(['Umum', $e[0], $e[1], $e[3], $now, $now]);
}
$ekskulIds = [];
foreach ($pdo->query('SELECT id, name FROM extracurriculars ORDER BY id') as $r) $ekskulIds[$r['name']] = (int)$r['id'];
echo "Ekskul: " . count($ekskulIds) . "\n";

$memberStmt = $pdo->prepare($sqlIgnore . ' INTO extracurricular_members (extracurricular_id, student_id, created_at) VALUES (?, ?, ?)');
$scoreEksStmt = $pdo->prepare($sqlReplace . ' INTO extracurricular_scores (student_id, extracurricular_id, score, notes, updated_at) VALUES (?, ?, ?, ?, ?)');
$memberCount = 0;
$scoreEksCount = 0;
$predikatEkskul = ['Sangat Baik' => 'Sangat Baik', 'Baik' => 'Baik', 'Cukup' => 'Cukup', 'Kurang' => 'Kurang'];
foreach ($siswaByClass as $kelasName => $studentIds) {
    foreach ($studentIds as $studentId) {
        $numEkskul = mt_rand(1, 2);
        for ($i = 0; $i < $numEkskul; $i++) {
            $eksName = array_rand($ekskulIds);
            $eksId = $ekskulIds[$eksName];
            $memberStmt->execute([$eksId, $studentId, $now]);
            $memberCount++;
            $scoreKey = array_rand($predikatEkskul);
            $scoreEksStmt->execute([$studentId, $eksId, $predikatEkskul[$scoreKey], '', $now]);
            $scoreEksCount++;
        }
    }
}
echo "Ekskul members: $memberCount, scores: $scoreEksCount\n\n";

echo "=== KOKURIKULER ===\n";
$temaStmt = $pdo->prepare($sqlIgnore . ' INTO cocurricular_themes (name, status, created_at, updated_at) VALUES (?, ?, ?, ?)');
$temaList = [
    'Bhinneka Tunggal Ika',
    'Sustainable Development Goals (SDGs)',
    'Literasi dan Numerasi',
];
foreach ($temaList as $tema) {
    $temaStmt->execute([$tema, 'aktif', $now, $now]);
}
$temaIds = [];
foreach ($pdo->query('SELECT id, name FROM cocurricular_themes ORDER BY id') as $r) $temaIds[$r['name']] = (int)$r['id'];
echo "Tema: " . count($temaIds) . "\n";

$kelompokStmt = $pdo->prepare($sqlIgnore . ' INTO cocurricular_groups (name, grade, phase, theme_id, coordinator_teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
$kegiatanStmt = $pdo->prepare($sqlIgnore . ' INTO cocurricular_activities (theme_id, phase, title, description, objective, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
$kokurCount = 0;
foreach ($temaIds as $temaId) {
    $kelompokStmt->execute(['Kelompok ' . $temaId, '7', 'D', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kelompokStmt->execute(['Kelompok ' . $temaId, '8', 'D', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kelompokStmt->execute(['Kelompok ' . $temaId, '9', 'D', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kelompokStmt->execute(['Kelompok ' . $temaId, '10', 'E', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kelompokStmt->execute(['Kelompok ' . $temaId, '11', 'E', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kelompokStmt->execute(['Kelompok ' . $temaId, '12', 'E', $temaId, $teacherIds[($temaId % count($teacherIds))], $now, $now]);
    $kegiatanStmt->execute([$temaId, 'D', 'Proyek ' . array_search($temaId, $temaIds), 'Proyek kelas', 'Menerapkan nilai tema dalam proyek kelas', $now, $now]);
    $kegiatanStmt->execute([$temaId, 'E', 'Proyek ' . array_search($temaId, $temaIds), 'Proyek kelas', 'Menerapkan nilai tema dalam proyek kelas', $now, $now]);
    $kokurCount += 8;
}
echo "Kelompok + kegiatan kokurikuler: $kokurCount\n\n";

echo "=== UPDATE USERS: tambah teacher_id untuk user guru ===\n";
$updUserStmt = $pdo->prepare('UPDATE users SET teacher_id = ? WHERE username = ?');
$updUserStmt->execute([$teacherIds[1], 'guru1']);
$updUserStmt->execute([$teacherIds[2], 'guru2']);
$updUserStmt->execute([$teacherIds[3], 'guru3']);
$updUserStmt->execute([$teacherIds[4], 'guru4']);
echo "User-teacher mapping updated\n\n";

echo "=== SUMMARY ===\n";
echo "Schools: 2\n";
echo "Classes: " . count($classIds) . "\n";
echo "Subjects: " . (count($smpMapelIds) + count($smaMapelIds)) . "\n";
echo "Teachers: " . count($teacherIds) . "\n";
echo "Students: $totalSiswa\n";
echo "Scores: $scoreCount\n";
echo "Done!\n";
