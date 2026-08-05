<?php

declare(strict_types=1);

function export_csv(array $headers, array $rows, callable $renderer, string $filename): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $renderer($row));
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

    throw new RuntimeException('Tipe export tidak dikenal.');
}
