<?php
require 'app/bootstrap.php';

$teachers = [
    "Ust. Rahmat Yulianto, S. H", "Ust. Muhammad Abdullah", "Ust. Asyik Nur Rahman", 
    "Usth. Ina Rusiana, S.Pd", "Usth. Mesya Sukmayati, S. Pd", "Usth. Maya Yulaicha, S. Pd", 
    "Usth. Risti Hardiyanti Rukmana, S.Pd", "Usth. Ekasari Kurniawati, A.Md. Kom", 
    "Usth. Mekha Eka Sari, S.Sos", "Usth. Ella Nur Fatimah", "Usth. Maryam Laila Ummul Khasanah", 
    "Usth. Alfiyaturradhiyah", "Usth. Maria Hindun Oktavia Rahmadani", "Guru Tahfidz", 
    "Ust. Rohmad Sigid Affandi, S.H", "Ust. Fahmi Dwi Payana, S. H", "Ust. Kodir, S. T.", 
    "Ust. Imam Aditya Ramadhan, S. Pd", "Ust. Dirjo, M. Pd.", "Ust. Setiyoko, S. Pd", 
    "Ust. Nur Wahyudi", "Usth. Rosita Nova", "Usth. Evi Nurhayati, S. Pd", 
    "Ust. Kasfaril Ramadani", "Ust. Fadholi", "Usth. Vivi Nurwulan, S. Pd.", 
    "Usth. Amelia Septiana Rahmadhani", "Ust. Dhiyael Haq Ivan Putranto", 
    "Ust. Anis Musthova", "Ust. Gesang Nur Wahid", "Ust. Adin Rahmatullah", 
    "Ust. Salafi Hafizh Azzikri", "Usth. Nafidzah Ilma Fajrin"
];

$subjects = [
    "Bahasa Indonesia" => "BINDO", "Bimbingan Konseling" => "BK", "Matematika" => "MATE", 
    "IPA" => "IPA", "Prakarya" => "PRAK", "PJOK" => "PJOK", "Seni Budaya" => "SEBU", 
    "Bahasa Inggris" => "BING", "Bahasa Jawa" => "BJAW", "TIK" => "TIK", 
    "Pendidikan Kewarganegaraan" => "PKN", "Fiqih" => "FIQIH", "Qur'an Hadits" => "QURHA", 
    "Akidah Akhlak" => "AKLK", "IPS" => "IPS", "Tariq" => "TARIQ", 
    "Kemuhammadiyahan" => "KEMUH", "Bahasa Arab" => "BARAB", "Matematika Nalaria" => "MANL", 
    "Tahfidz" => "TAHFIDZ", "Fisika" => "FISIKA", "Biologi" => "BIOLOGI", 
    "Kimia" => "KIMIA", "Shorof" => "SHOROF", "Hadits" => "HADIST", "Imla'" => "IMLA", 
    "Tauhid" => "TAUHID", "Nahwu" => "NAHWU", "Sosiologi" => "SOS", "Sejarah" => "SEJA", 
    "Ekonomi" => "EKO", "Matematika Peminatan" => "MAPE", "Qiraatul Qutub" => "Q.QUTB", 
    "Geografi" => "GEO", "Latihan Soal" => "Latsol"
];

$classes = [
    "VII A" => "7", "VII B" => "7", "VIII A" => "8", "VIII B" => "8", 
    "IX A" => "9", "IX B" => "9", "X" => "10", "XI" => "11", "XII A" => "12", "XII B" => "12"
];

foreach ($teachers as $t) {
    if (!fetch_one('SELECT id FROM teachers WHERE name = ?', [$t])) {
        $gender = str_starts_with($t, 'Usth') ? 'P' : 'L';
        execute_sql('INSERT INTO teachers (name, gender) VALUES (?, ?)', [$t, $gender]);
    }
}

foreach ($subjects as $name => $short) {
    if (!fetch_one('SELECT id FROM subjects WHERE name = ?', [$name])) {
        execute_sql('INSERT INTO subjects (name, short_name, group_name) VALUES (?, ?, ?)', [$name, $short, 'Wajib']);
    }
}

foreach ($classes as $name => $grade) {
    if (!fetch_one('SELECT id FROM classes WHERE name = ?', [$name])) {
        execute_sql('INSERT INTO classes (name, grade, academic_year) VALUES (?, ?, ?)', [$name, $grade, '2025/2026']);
    }
}

echo "Master data imported.\n";
