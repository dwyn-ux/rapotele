<?php

declare(strict_types=1);

function export_csv(array $headers, array $rows, callable $renderer, string $filename): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, $headers, ',', '"', '\\');

    foreach ($rows as $row) {
        fputcsv($out, $renderer($row), ',', '"', '\\');
    }

    fclose($out);
    exit;
}

function page_export_csv(): void
{
    require_login();

    $type = (string)($_GET['type'] ?? '');

    if ($type === 'nilai' || $type === 'grades') {
        $assignmentId = (int)($_GET['assignment_id'] ?? 0);
        require_role(['admin', 'guru']);

        $assignment = fetch_one(
            'SELECT ta.*, t.name AS teacher_name, c.name AS class_name, c.grade, s.name AS subject_name
             FROM teaching_assignments ta
             JOIN teachers t ON t.id = ta.teacher_id
             JOIN classes c ON c.id = ta.class_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.id = ? AND ta.active = 1',
            [$assignmentId]
        );
        if (!$assignment) {
            throw new RuntimeException('Pembelajaran tidak ditemukan.');
        }
        if (!is_admin()) {
            $currentTeacherId = (int)(current_user()['teacher_id'] ?? 0);
            if ((int)$assignment['teacher_id'] !== $currentTeacherId) {
                throw new RuntimeException('Akses ditolak.');
            }
        }

        $students = fetch_all(
            'SELECT s.id, s.name, s.nis, s.nisn FROM students s WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name',
            [(int)$assignment['class_id']]
        );
        $headers = ['No', 'NIS', 'NISN', 'Nama', 'Nilai', 'Deskripsi'];
        $rows = $students;
        $renderer = function (array $row) use ($assignment): array {
            static $no = 0;
            $no++;
            $grade = fetch_one(
                'SELECT score, description FROM grades WHERE assignment_id = ? AND student_id = ?',
                [(int)$assignment['id'], (int)$row['id']]
            );
            return [
                $no,
                $row['nis'] ?? '-',
                $row['nisn'] ?? '-',
                $row['name'],
                $grade['score'] ?? '',
                $grade['description'] ?? '',
            ];
        };

        $filename = 'Nilai_' . $assignment['class_name'] . '_' . $assignment['subject_name'] . '.csv';
        export_csv($headers, $rows, $renderer, $filename);
    }

    if ($type === 'siswa' || $type === 'students') {
        require_role(['admin']);
        $classId = (int)($_GET['class_id'] ?? 0);

        $students = fetch_all(
            'SELECT s.nis, s.nisn, s.name, s.gender, s.phone, c.name AS class_name
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.active = 1' . ($classId > 0 ? ' AND s.class_id = ' . $classId : '') . '
             ORDER BY c.grade, c.name, s.name'
        );
        $headers = ['No', 'NIS', 'NISN', 'Nama', 'JK', 'No HP', 'Kelas'];
        $renderer = function (array $row) {
            static $no = 0;
            $no++;
            return [$no, $row['nis'] ?? '-', $row['nisn'] ?? '-', $row['name'], $row['gender'] ?? '-', $row['phone'] ?? '-', $row['class_name'] ?? '-'];
        };
        export_csv($headers, $students, $renderer, 'Data_Siswa.csv');
    }

    if ($type === 'absensi' || $type === 'attendance') {
        $assignmentId = (int)($_GET['assignment_id'] ?? 0);
        require_role(['admin', 'guru']);

        $assignment = fetch_one(
            'SELECT ta.*, c.name AS class_name, s.name AS subject_name
             FROM teaching_assignments ta
             JOIN classes c ON c.id = ta.class_id
             JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.id = ? AND ta.active = 1',
            [$assignmentId]
        );
        if (!$assignment) {
            throw new RuntimeException('Pembelajaran tidak ditemukan.');
        }
        if (!is_admin()) {
            $currentTeacherId = (int)(current_user()['teacher_id'] ?? 0);
            if ((int)$assignment['teacher_id'] !== $currentTeacherId) {
                throw new RuntimeException('Akses ditolak.');
            }
        }

        $sessions = fetch_all(
            'SELECT ses.id, ses.date, ses.meeting_no, ses.topic
             FROM student_attendance_sessions ses
             WHERE ses.assignment_id = ?
             ORDER BY ses.date, ses.meeting_no',
            [$assignmentId]
        );
        $students = fetch_all(
            'SELECT id, name, nis FROM students WHERE class_id = ? AND active = 1 ORDER BY name',
            [(int)$assignment['class_id']]
        );

        $headerCells = ['No', 'NIS', 'Nama'];
        foreach ($sessions as $s) {
            $headerCells[] = $s['date'] . ' P' . $s['meeting_no'];
        }
        $renderer = function (array $student) use ($sessions): array {
            static $no = 0;
            $no++;
            $row = [$no, $student['nis'] ?? '-', $student['name']];
            foreach ($sessions as $s) {
                $entry = fetch_one(
                    'SELECT status FROM student_attendance_entries WHERE session_id = ? AND student_id = ?',
                    [(int)$s['id'], (int)$student['id']]
                );
                $row[] = $entry['status'] ?? '-';
            }
            return $row;
        };
        export_csv($headerCells, $students, $renderer, 'Absensi_' . $assignment['class_name'] . '_' . $assignment['subject_name'] . '.csv');
    }

    if ($type === 'rekap_guru_harian') {
        require_role(['admin', 'guru', 'kepsek', 'pengawas']);
        $startDate = date_ymd((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = date_ymd((string)($_GET['end_date'] ?? date('Y-m-d')));
        $teacherId = (int)($_GET['teacher_id'] ?? 0);

        $where = 't.active = 1';
        $params = [$startDate, $endDate];
        if ($teacherId > 0) {
            $where .= ' AND t.id = ?';
            $params[] = $teacherId;
        }

        $summaryRows = fetch_all(
            "SELECT t.id, t.name, t.nip,
                    COUNT(ta.id) AS total_recorded,
                    SUM(CASE WHEN ta.status = 'hadir' THEN 1 ELSE 0 END) AS count_hadir,
                    SUM(CASE WHEN ta.status = 'terlambat' THEN 1 ELSE 0 END) AS count_terlambat,
                    SUM(CASE WHEN ta.status = 'izin' THEN 1 ELSE 0 END) AS count_izin,
                    SUM(CASE WHEN ta.status = 'sakit' THEN 1 ELSE 0 END) AS count_sakit,
                    SUM(CASE WHEN ta.status = 'dinas' THEN 1 ELSE 0 END) AS count_dinas,
                    SUM(CASE WHEN ta.status = 'alpa' THEN 1 ELSE 0 END) AS count_alpa
             FROM teachers t
             LEFT JOIN teacher_attendance ta ON ta.teacher_id = t.id AND ta.date >= ? AND ta.date <= ?
             WHERE $where
             GROUP BY t.id, t.name, t.nip
             ORDER BY t.name",
            $params
        );

        $headers = ['No', 'NIP', 'Nama Guru', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Dinas', 'Alpa', 'Total Hadir', 'Total Hari', 'Persentase Hadir (%)'];
        $renderer = function (array $row): array {
            static $no = 0;
            $no++;
            $masuk = (int)$row['count_hadir'] + (int)$row['count_terlambat'];
            $total = (int)$row['total_recorded'];
            $pct = $total > 0 ? round(($masuk / $total) * 100, 1) : 0;
            return [
                $no,
                $row['nip'] ?: '-',
                $row['name'],
                (int)$row['count_hadir'],
                (int)$row['count_terlambat'],
                (int)$row['count_izin'],
                (int)$row['count_sakit'],
                (int)$row['count_dinas'],
                (int)$row['count_alpa'],
                $masuk,
                $total,
                $pct . '%',
            ];
        };

        $filename = 'Rekap_Kehadiran_Guru_Harian_' . $startDate . '_sd_' . $endDate . '.csv';
        export_csv($headers, $summaryRows, $renderer, $filename);
    }

    if ($type === 'rekap_guru_jadwal') {
        require_role(['admin', 'guru', 'kepsek', 'pengawas']);
        $startDate = date_ymd((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = date_ymd((string)($_GET['end_date'] ?? date('Y-m-d')));
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $classId = (int)($_GET['class_id'] ?? 0);

        $where = 'tta.date >= ? AND tta.date <= ?';
        $params = [$startDate, $endDate];
        if ($teacherId > 0) {
            $where .= ' AND tta.teacher_id = ?';
            $params[] = $teacherId;
        }
        if ($classId > 0) {
            $where .= ' AND tta.class_id = ?';
            $params[] = $classId;
        }

        $rows = fetch_all(
            "SELECT tta.*, t.name AS teacher_name, t.nip,
                    c.name AS class_name,
                    s.name AS subject_name,
                    ls.period_no, ls.start_time, ls.end_time
             FROM teacher_teaching_attendance tta
             JOIN teachers t ON t.id = tta.teacher_id
             JOIN classes c ON c.id = tta.class_id
             JOIN subjects s ON s.id = tta.subject_id
             LEFT JOIN lesson_schedules ls ON ls.id = tta.schedule_id
             WHERE $where
             ORDER BY tta.date DESC, ls.period_no ASC, t.name ASC",
            $params
        );

        $headers = ['No', 'Tanggal', 'Jam Ke', 'Waktu', 'NIP', 'Nama Guru', 'Kelas', 'Mata Pelajaran', 'Status', 'Jam Masuk', 'Jam Pulang', 'Catatan'];
        $renderer = function (array $r): array {
            static $no = 0;
            $no++;
            $waktu = trim(substr((string)($r['start_time'] ?? ''), 0, 5) . ' - ' . substr((string)($r['end_time'] ?? ''), 0, 5));
            return [
                $no,
                $r['date'],
                'Jam ke-' . (int)($r['period_no'] ?? 1),
                $waktu ?: '-',
                $r['nip'] ?: '-',
                $r['teacher_name'],
                $r['class_name'],
                $r['subject_name'],
                ucfirst((string)($r['status'] ?? 'hadir')),
                substr((string)($r['time_in'] ?? '-'), 0, 5),
                substr((string)($r['time_out'] ?? '-'), 0, 5),
                $r['notes'] ?: '-',
            ];
        };

        $filename = 'Rekap_Kehadiran_Guru_Per_Jadwal_' . $startDate . '_sd_' . $endDate . '.csv';
        export_csv($headers, $rows, $renderer, $filename);
    }

    if ($type === 'rekap_siswa_harian') {
        require_role(['admin', 'guru', 'kepsek', 'pengawas']);
        $startDate = date_ymd((string)($_GET['start_date'] ?? date('Y-m-01')));
        $endDate = date_ymd((string)($_GET['end_date'] ?? date('Y-m-d')));
        $classId = (int)($_GET['class_id'] ?? 0);

        $where = 's.active = 1';
        $params = [$startDate, $endDate];
        if ($classId > 0) {
            $where .= ' AND s.class_id = ?';
            $params[] = $classId;
        }

        $students = fetch_all(
            "SELECT s.id, s.name, s.nis, s.nisn, c.name AS class_name, c.grade,
                    COUNT(e.id) AS total_recorded,
                    SUM(CASE WHEN e.status = 'hadir' THEN 1 ELSE 0 END) AS count_hadir,
                    SUM(CASE WHEN e.status = 'sakit' THEN 1 ELSE 0 END) AS count_sakit,
                    SUM(CASE WHEN e.status = 'izin' THEN 1 ELSE 0 END) AS count_izin,
                    SUM(CASE WHEN e.status = 'alpa' THEN 1 ELSE 0 END) AS count_alpa,
                    SUM(CASE WHEN e.status = 'terlambat' THEN 1 ELSE 0 END) AS count_terlambat
             FROM students s
             JOIN classes c ON c.id = s.class_id
             LEFT JOIN (
                 SELECT sub_e.id, sub_e.student_id, sub_e.status
                 FROM student_attendance_entries sub_e
                 JOIN student_attendance_sessions sub_s ON sub_s.id = sub_e.session_id
                 WHERE sub_s.date >= ? AND sub_s.date <= ?
             ) e ON e.student_id = s.id
             WHERE $where
             GROUP BY s.id, s.name, s.nis, s.nisn, c.name, c.grade
             ORDER BY c.grade, c.name, s.name",
            $params
        );

        $headers = ['No', 'NIS', 'NISN', 'Nama Siswa', 'Kelas', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Terlambat', 'Total Sesi', 'Persentase Hadir (%)'];
        $renderer = function (array $st): array {
            static $no = 0;
            $no++;
            $tot = (int)$st['total_recorded'];
            $hadir = (int)$st['count_hadir'];
            $pct = $tot > 0 ? round(($hadir / $tot) * 100, 1) : 0;
            return [
                $no,
                $st['nis'] ?: '-',
                $st['nisn'] ?: '-',
                $st['name'],
                $st['class_name'],
                (int)$st['count_hadir'],
                (int)$st['count_sakit'],
                (int)$st['count_izin'],
                (int)$st['count_alpa'],
                (int)$st['count_terlambat'],
                $tot,
                $pct . '%',
            ];
        };

        $filename = 'Rekap_Kehadiran_Siswa_Harian_' . $startDate . '_sd_' . $endDate . '.csv';
        export_csv($headers, $students, $renderer, $filename);
    }

    http_response_code(400);
    exit('Tipe export tidak dikenal.');
}
