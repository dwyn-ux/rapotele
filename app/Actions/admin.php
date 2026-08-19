<?php declare(strict_types=1);

function action_bulk_delete(): void
{
    require_role(['admin']);
    $target = (string)($_POST['target'] ?? '');

    switch ($target) {
        case 'students':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM students')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data siswa untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM student_attendance_entries WHERE session_id IN (SELECT id FROM student_attendance_sessions WHERE assignment_id IN (SELECT id FROM teaching_assignments))');
            execute_sql('DELETE FROM student_attendance_sessions');
            execute_sql('DELETE FROM whatsapp_queue WHERE student_id IS NOT NULL');
            execute_sql('DELETE FROM whatsapp_guardians');
            execute_sql('DELETE FROM extracurricular_members');
            execute_sql('DELETE FROM cocurricular_members');
            execute_sql('DELETE FROM student_photos');
            execute_sql('DELETE FROM graduations');
            execute_sql('DELETE FROM final_scores');
            execute_sql('DELETE FROM grades');
            execute_sql('DELETE FROM student_violations');
            execute_sql('DELETE FROM users WHERE student_id IS NOT NULL');
            execute_sql('DELETE FROM students');
            flash('success', "Berhasil menghapus $count data siswa dan data terkait.");
            break;

        case 'teachers':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM teachers')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data guru untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM teacher_attendance');
            execute_sql('DELETE FROM teacher_teaching_attendance');
            execute_sql('DELETE FROM teacher_schedule_requests');
            execute_sql('DELETE FROM users WHERE teacher_id IS NOT NULL AND role != ?', ['admin']);
            execute_sql('DELETE FROM teachers');
            flash('success', "Berhasil menghapus $count data guru dan data terkait.");
            break;

        case 'classes':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM classes')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data kelas untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM classes');
            flash('success', "Berhasil menghapus $count data kelas.");
            break;

        case 'subjects':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM subjects')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data mapel untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM merged_subjects');
            execute_sql('DELETE FROM report_mappings');
            execute_sql('DELETE FROM subjects');
            flash('success', "Berhasil menghapus $count data mapel dan data terkait.");
            break;

        case 'assignments':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM teaching_assignments')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data pembelajaran untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM lesson_schedule_reminder_logs');
            execute_sql('DELETE FROM lesson_schedules WHERE assignment_id IN (SELECT id FROM teaching_assignments)');
            execute_sql('DELETE FROM daily_journals');
            execute_sql('DELETE FROM student_attendance_entries WHERE session_id IN (SELECT id FROM student_attendance_sessions)');
            execute_sql('DELETE FROM student_attendance_sessions');
            execute_sql('DELETE FROM grades');
            execute_sql('DELETE FROM teaching_assignments');
            flash('success', "Berhasil menghapus $count data pembelajaran dan semua data terkait.");
            break;

        case 'schedules':
            $dayFilter = (string)($_POST['day_of_week'] ?? '');
            if ($dayFilter !== '' && $dayFilter !== 'all') {
                $day = (int)$dayFilter;
                if ($day < 1 || $day > 6) {
                    flash('danger', 'Filter hari tidak valid.');
                    redirect_to('bulk-delete');
                }
                $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM lesson_schedules WHERE day_of_week = ?', [$day])['c'] ?? 0);
                if ($count === 0) {
                    flash('warning', "Tidak ada jadwal hari {$dayNames[$day]} untuk dihapus.");
                    redirect_to('bulk-delete');
                }
                execute_sql('DELETE FROM lesson_schedule_reminder_logs WHERE schedule_id IN (SELECT id FROM lesson_schedules WHERE day_of_week = ?)', [$day]);
                execute_sql('DELETE FROM lesson_schedules WHERE day_of_week = ?', [$day]);
                flash('success', "Berhasil menghapus $count jadwal hari {$dayNames[$day]}.");
            } else {
                $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM lesson_schedules')['c'] ?? 0);
                if ($count === 0) {
                    flash('warning', 'Tidak ada data jadwal untuk dihapus.');
                    redirect_to('bulk-delete');
                }
                execute_sql('DELETE FROM lesson_schedule_reminder_logs');
                execute_sql('DELETE FROM lesson_schedules');
                flash('success', "Berhasil menghapus $count data jadwal pelajaran.");
            }
            break;

        case 'grades':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM grades')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data nilai untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM grades');
            flash('success', "Berhasil menghapus $count data nilai.");
            break;

        case 'final_scores':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM final_scores')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data nilai akhir untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM final_scores');
            flash('success', "Berhasil menghapus $count data nilai akhir.");
            break;

        case 'attendance_student':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM student_attendance_entries')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data absensi siswa untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM student_attendance_entries');
            execute_sql('DELETE FROM student_attendance_sessions');
            flash('success', "Berhasil menghapus $count data absensi siswa.");
            break;

        case 'attendance_teacher':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM teacher_attendance')['c'] ?? 0);
            $count2 = (int)(fetch_one('SELECT COUNT(*) AS c FROM teacher_teaching_attendance')['c'] ?? 0);
            if ($count === 0 && $count2 === 0) {
                flash('warning', 'Tidak ada data absensi guru untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM teacher_teaching_attendance');
            execute_sql('DELETE FROM teacher_attendance');
            flash('success', "Berhasil menghapus " . ($count + $count2) . " data absensi guru.");
            break;

        case 'violations':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM student_violations')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data pelanggaran untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM student_violations');
            flash('success', "Berhasil menghapus $count data pelanggaran.");
            break;

        case 'journals':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM daily_journals')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data jurnal untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM daily_journals');
            flash('success', "Berhasil menghapus $count data jurnal harian.");
            break;

        case 'extracurriculars':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM extracurriculars')['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data ekstrakurikuler untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM extracurricular_scores');
            execute_sql('DELETE FROM extracurricular_members');
            execute_sql('DELETE FROM extracurriculars');
            flash('success', "Berhasil menghapus $count data ekstrakurikuler.");
            break;

        case 'users':
            $count = (int)(fetch_one('SELECT COUNT(*) AS c FROM users WHERE role != ?', ['admin'])['c'] ?? 0);
            if ($count === 0) {
                flash('warning', 'Tidak ada data pengguna (non-admin) untuk dihapus.');
                redirect_to('bulk-delete');
            }
            execute_sql('DELETE FROM users WHERE role != ?', ['admin']);
            flash('success', "Berhasil menghapus $count data pengguna (kecuali admin).");
            break;

        default:
            flash('danger', 'Target hapus tidak valid.');
            break;
    }

    redirect_to('bulk-delete');
}
