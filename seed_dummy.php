<?php
declare(strict_types=1);

$db = new PDO('sqlite:' . __DIR__ . '/storage/eraport.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$now = date('Y-m-d H:i:s');

// School profile
$existing = $db->query('SELECT COUNT(*) FROM school_profile')->fetchColumn();
if ($existing == 0) {
    $db->exec("INSERT INTO school_profile (name, npsn, address, principal_name, principal_nip, academic_year, semester, updated_at) VALUES ('MTSS AL-HIKMAH', '20234567', 'Jl. Pendidikan No. 123, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40123', 'H. Ahmad Sudirman, M.Pd', '197501012000031101', '2025/2026', 'Genap', '$now')");
    echo "School profile OK\n";
}

// Classes
$classCount = $db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
if ($classCount == 0) {
    $classes = [
        ['name' => 'VII-A', 'grade' => '7'],
        ['name' => 'VII-B', 'grade' => '7'],
        ['name' => 'VII-C', 'grade' => '7'],
        ['name' => 'VIII-A', 'grade' => '8'],
        ['name' => 'VIII-B', 'grade' => '8'],
        ['name' => 'IX-A', 'grade' => '9'],
        ['name' => 'IX-B', 'grade' => '9'],
    ];
    $stmt = $db->prepare('INSERT INTO classes (name, grade, major, academic_year, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)');
    foreach ($classes as $c) {
        $stmt->execute([$c['name'], $c['grade'], '', '2025/2026', $now]);
    }
    echo "Classes OK\n";
}

// Teachers
$teacherCount = $db->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
if ($teacherCount == 0) {
    $teachers = [
        ['name' => 'H. Ahmad Sudirman, M.Pd', 'nip' => '197501012000031101', 'gender' => 'L', 'position' => 'Kepala Sekolah'],
        ['name' => 'Siti Nurhaliza, S.Pd', 'nip' => '198205152005022003', 'gender' => 'P', 'position' => 'Guru'],
        ['name' => 'Budi Santoso, S.Pd', 'nip' => '198003102003121004', 'gender' => 'L', 'position' => 'Guru'],
        ['name' => 'Rina Mulyani, S.Pd', 'nip' => '197908202001122002', 'gender' => 'P', 'position' => 'Guru'],
        ['name' => 'Dedi Kurniawan, S.Pd', 'nip' => '198507102008011005', 'gender' => 'L', 'position' => 'Guru'],
    ];
    $stmt = $db->prepare('INSERT INTO teachers (name, nip, gender, position, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)');
    foreach ($teachers as $t) {
        $stmt->execute([$t['name'], $t['nip'], $t['gender'], $t['position'], $now]);
    }
    echo "Teachers OK\n";
}

// Homeroom teachers
$teacherIds = [];
$rows = $db->query('SELECT id, nip FROM teachers')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { $teacherIds[$r['nip']] = $r['id']; }
$db->prepare('UPDATE classes SET homeroom_teacher_id = ? WHERE name = ?')->execute([$teacherIds['198205152005022003'] ?? null, 'VII-A']);
$db->prepare('UPDATE classes SET homeroom_teacher_id = ? WHERE name = ?')->execute([$teacherIds['198003102003121004'] ?? null, 'VII-B']);
$db->prepare('UPDATE classes SET homeroom_teacher_id = ? WHERE name = ?')->execute([$teacherIds['197908202001122002'] ?? null, 'VIII-A']);
$db->prepare('UPDATE classes SET homeroom_teacher_id = ? WHERE name = ?')->execute([$teacherIds['198507102008011005'] ?? null, 'IX-A']);

// Students
$studentCount = $db->query('SELECT COUNT(*) FROM students')->fetchColumn();
if ($studentCount == 0) {
    $students = [
        ['name' => 'Ahmad Rizky Pratama', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-03-15', 'religion' => 'Islam', 'father_name' => 'H. Pratama Wijaya', 'father_occupation' => 'Wiraswasta', 'mother_name' => 'Siti Aminah', 'mother_occupation' => 'Guru', 'address' => 'Jl. Merdeka No. 10, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40123', 'phone' => '081234567890'],
        ['name' => 'Siti Nur Aisyah', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-07-22', 'religion' => 'Islam', 'father_name' => 'Nursalam', 'father_occupation' => 'Petani', 'mother_name' => 'Rohmah', 'mother_occupation' => 'Ibu Rumah Tangga', 'address' => 'Jl. Cendana No. 5, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40124', 'phone' => '081234567891'],
        ['name' => 'Muhammad Fadil', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-01-10', 'religion' => 'Islam', 'father_name' => 'H. Fadli Rahman', 'father_occupation' => 'PNS', 'mother_name' => 'Dewi Kartika', 'mother_occupation' => 'Bidang Kesehatan', 'address' => 'Jl. Pendidikan No. 25, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40125', 'phone' => '081234567892'],
        ['name' => 'Ayu Lestari', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-11-05', 'religion' => 'Islam', 'father_name' => 'Joko Lestari', 'father_occupation' => 'Karyawan Swasta', 'mother_name' => 'Endang Lestari', 'mother_occupation' => 'Pedagang', 'address' => 'Jl. Kenanga No. 8, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40126', 'phone' => '081234567893'],
        ['name' => 'Rizky Aditya Nugroho', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-06-18', 'religion' => 'Islam', 'father_name' => 'Budi Nugroho', 'father_occupation' => 'TNI', 'mother_name' => 'Putri Rahayu', 'mother_occupation' => 'PNS', 'address' => 'Jl. Anggrek No. 12, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40127', 'phone' => '081234567894'],
        ['name' => 'Naura Calysta Putri', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-09-30', 'religion' => 'Islam', 'father_name' => 'Hendra Putra', 'father_occupation' => 'Dokter', 'mother_name' => 'Ratna Dewi', 'mother_occupation' => 'Dokter Gigi', 'address' => 'Jl. Melati No. 3, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40128', 'phone' => '081234567895'],
        ['name' => 'Farhan Maulana', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-04-12', 'religion' => 'Islam', 'father_name' => 'Agus Maulana', 'father_occupation' => 'Mekanik', 'mother_name' => 'Wati Sumarni', 'mother_occupation' => 'Ibu Rumah Tangga', 'address' => 'Jl. Sawah Indah No. 7, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40129', 'phone' => '081234567896'],
        ['name' => 'Khaira Aulia Rahma', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-08-25', 'religion' => 'Islam', 'father_name' => 'Rahmat Syah', 'father_occupation' => 'Buruh Pabrik', 'mother_name' => 'Sri Wahyuni', 'mother_occupation' => 'Penjahit', 'address' => 'Jl. Cempaka No. 14, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40130', 'phone' => '081234567897'],
        ['name' => 'Gilang Ramadhan', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-02-14', 'religion' => 'Islam', 'father_name' => 'Wahyu Ramadhan', 'father_occupation' => 'Pedagang', 'mother_name' => 'Murni Rahayu', 'mother_occupation' => 'Guru', 'address' => 'Jl. Mawar No. 9, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40131', 'phone' => '081234567898'],
        ['name' => 'Ratu Syifa Azzahra', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-12-01', 'religion' => 'Islam', 'father_name' => 'H. Zainal Arifin', 'father_occupation' => 'Wiraswasta', 'mother_name' => 'Nurul Hidayah', 'mother_occupation' => 'Pedagang', 'address' => 'Jl. Dahlia No. 20, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40132', 'phone' => '081234567899'],
        ['name' => 'Dimas Prayoga', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2012-05-20', 'religion' => 'Islam', 'father_name' => 'Yoga Pratama', 'father_occupation' => 'Karyawan Swasta', 'mother_name' => 'Ani Pratama', 'mother_occupation' => 'Ibu Rumah Tangga', 'address' => 'Jl. Jambu No. 6, Kel. Sukamaju, Kec. Sukamaju, Kota Bandung 40133', 'phone' => '081234567800'],
        ['name' => 'Zahra Putri Ramadhani', 'gender' => 'P', 'birth_place' => 'Bandung', 'birth_date' => '2012-10-08', 'religion' => 'Islam', 'father_name' => 'Ramadhani', 'father_occupation' => 'Supir', 'mother_name' => 'Rina Susanti', 'mother_occupation' => 'Pedagang', 'address' => 'Jl. Durian No. 11, Kel. Mekar Jaya, Kec. Sukamaju, Kota Bandung 40134', 'phone' => '081234567801'],
    ];

    $classRows = $db->query('SELECT id, name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $classMap = [];
    foreach ($classRows as $c) { $classMap[$c['name']] = $c['id']; }

    $rounds = ['VII-A', 'VII-B', 'VII-C', 'VIII-A', 'VIII-B', 'IX-A', 'IX-B'];
    $stmt = $db->prepare('INSERT INTO students (nis, nisn, name, gender, birth_place, birth_date, religion, address, phone, father_name, father_occupation, mother_name, mother_occupation, guardian_name, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');

    foreach ($students as $i => $s) {
        $classKey = $rounds[$i % count($rounds)];
        $classId = $classMap[$classKey] ?? null;
        $nis = (string)(2025001 + $i);
        $nisn = (string)(81234001 + $i);
        $stmt->execute([
            $nis, $nisn, $s['name'], $s['gender'], $s['birth_place'], $s['birth_date'], $s['religion'],
            $s['address'], $s['phone'], $s['father_name'], $s['father_occupation'],
            $s['mother_name'], $s['mother_occupation'], '', $classId, $now,
        ]);
    }
    echo "Students OK (" . count($students) . " siswa)\n";
}

// Subjects
$subjectCount = $db->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
if ($subjectCount == 0) {
    $subjects = [
        ['name' => 'Bahasa Indonesia', 'short_name' => 'Bindo', 'group_name' => 'Kelompok A'],
        ['name' => 'Matematika', 'short_name' => 'MTK', 'group_name' => 'Kelompok A'],
        ['name' => 'Bahasa Inggris', 'short_name' => 'Bing', 'group_name' => 'Kelompok A'],
        ['name' => 'Ilmu Pengetahuan Alam', 'short_name' => 'IPA', 'group_name' => 'Kelompok A'],
        ['name' => 'Ilmu Pengetahuan Sosial', 'short_name' => 'IPS', 'group_name' => 'Kelompok A'],
        ['name' => 'Pendidikan Agama Islam', 'short_name' => 'PAI', 'group_name' => 'Kelompok B'],
        ['name' => 'Pendidikan Pancasila', 'short_name' => 'PP', 'group_name' => 'Kelompok B'],
        ['name' => 'Bahasa Arab', 'short_name' => 'Barab', 'group_name' => 'Kelompok B'],
        ['name' => 'Prakarya', 'short_name' => 'Prak', 'group_name' => 'Kelompok B'],
        ['name' => 'PJOK', 'short_name' => 'PJOK', 'group_name' => 'Kelompok B'],
    ];
    $stmt = $db->prepare('INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, 1, ?)');
    foreach ($subjects as $s) {
        $stmt->execute([$s['name'], $s['short_name'], $s['group_name'], $now]);
    }
    echo "Subjects OK\n";
}

echo "\nAll dummy data ready!\n";
echo "Login: administrator / administrator\n";
echo "Buka: http://localhost:8000\n";
