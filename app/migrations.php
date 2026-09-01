<?php

declare(strict_types=1);

function migration_pk(): string
{
    return db_driver() === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
}

function migration_bool(): string
{
    return db_driver() === 'sqlite' ? 'INTEGER' : 'TINYINT(1)';
}

function migration_column_exists(string $table, string $column): bool
{
    return table_column_exists($table, $column);
}

function migration_add_column(string $table, string $column, string $definition): void
{
    if (!table_exists($table)) {
        return;
    }

    if (!migration_column_exists($table, $column)) {
        try {
            db()->exec('ALTER TABLE ' . db_identifier($table) . ' ADD COLUMN ' . db_identifier($column) . ' ' . $definition);
        } catch (PDOException $exception) {
            if (!(db_driver() === 'mysql' && str_contains($exception->getMessage(), 'Duplicate column name'))) {
                throw $exception;
            }
        }
    }

    if (!migration_column_exists($table, $column)) {
        throw new RuntimeException('Kolom ' . $table . '.' . $column . ' belum berhasil dibuat. Jalankan install.php sekali atau beri user database izin ALTER TABLE.');
    }
}

function run_migrations(): void
{
    $pk = migration_pk();
    $bool = migration_bool();
    $engine = db_driver() === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $statements = [
        "CREATE TABLE IF NOT EXISTS school_profile (
            id $pk,
            name VARCHAR(160) NOT NULL,
            npsn VARCHAR(32) NULL,
            address TEXT NULL,
            principal_name VARCHAR(160) NULL,
            principal_nip VARCHAR(64) NULL,
            academic_year VARCHAR(32) NOT NULL,
            semester VARCHAR(32) NOT NULL,
            location_lat DECIMAL(10,8) NULL,
            location_lng DECIMAL(11,8) NULL,
            attendance_radius_meters INT NULL DEFAULT 500,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS teachers (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            name VARCHAR(160) NOT NULL,
            nip VARCHAR(64) NULL,
            nuptk VARCHAR(64) NULL,
            gender VARCHAR(16) NULL,
            phone VARCHAR(32) NULL,
            email VARCHAR(160) NULL,
            position VARCHAR(120) NULL,
            telegram_chat_id VARCHAR(64) NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS classes (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            name VARCHAR(80) NOT NULL,
            grade VARCHAR(16) NOT NULL,
            level VARCHAR(16) NULL,
            major VARCHAR(80) NULL,
            school_id INT UNSIGNED NULL,
            homeroom_teacher_id INT NULL,
            academic_year VARCHAR(32) NOT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS students (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            nis VARCHAR(64) NULL,
            nisn VARCHAR(64) NULL,
            name VARCHAR(160) NOT NULL,
            gender VARCHAR(16) NULL,
            birth_place VARCHAR(80) NULL,
            birth_date DATE NULL,
            religion VARCHAR(64) NULL,
            class_id INT NULL,
            location_lat DECIMAL(10,8) NULL,
            location_lng DECIMAL(11,8) NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS subjects (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            name VARCHAR(160) NOT NULL,
            short_name VARCHAR(40) NULL,
            group_name VARCHAR(80) NULL,
            curriculum VARCHAR(32) NULL,
            kkm INT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS teaching_assignments (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            academic_year VARCHAR(32) NOT NULL,
            semester VARCHAR(32) NOT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS teacher_schedule_requests (
            id $pk,
            teacher_id INT NOT NULL,
            request_type VARCHAR(24) NOT NULL DEFAULT 'prefer',
            day_of_week INT NOT NULL,
            start_period INT NOT NULL DEFAULT 1,
            end_period INT NOT NULL DEFAULT 1,
            note TEXT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS lesson_schedules (
            id $pk,
            assignment_id INT NOT NULL,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            day_of_week INT NOT NULL,
            period_no INT NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            locked $bool NOT NULL DEFAULT 0,
            note TEXT NULL,
            generated_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (class_id, day_of_week, period_no),
            UNIQUE (teacher_id, day_of_week, period_no)
        )$engine",

        "CREATE TABLE IF NOT EXISTS lesson_schedule_reminder_logs (
            id $pk,
            schedule_id INT NOT NULL,
            teacher_id INT NOT NULL,
            telegram_chat_id VARCHAR(64) NOT NULL,
            reminder_date DATE NOT NULL,
            schedule_start_time TIME NOT NULL,
            reminder_minutes INT NOT NULL DEFAULT 10,
            message TEXT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (schedule_id, reminder_date, schedule_start_time, reminder_minutes)
        )$engine",

        "CREATE TABLE IF NOT EXISTS daily_summary_reminder_logs (
            id $pk,
            teacher_id INT NOT NULL,
            reminder_type VARCHAR(20) NOT NULL,
            reminder_date DATE NOT NULL,
            message TEXT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_id, reminder_type, reminder_date)
        )$engine",

        "CREATE TABLE IF NOT EXISTS users (
            id $pk,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(160) NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'guru',
            teacher_id INT NULL,
            student_id INT NULL,
            telegram_chat_id VARCHAR(64) NULL,
            telegram_login_active $bool NOT NULL DEFAULT 1,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS learning_objectives (
            id $pk,
            subject_id INT NOT NULL,
            grade VARCHAR(16) NOT NULL,
            description TEXT NOT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS grades (
            id $pk,
            assignment_id INT NOT NULL,
            student_id INT NOT NULL,
            score DECIMAL(5,2) NULL,
            description TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (assignment_id, student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_attendance_sessions (
            id $pk,
            assignment_id INT NOT NULL,
            date DATE NOT NULL,
            meeting_no INT NOT NULL DEFAULT 1,
            topic VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (assignment_id, date, meeting_no)
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_attendance_entries (
            id $pk,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'hadir',
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (session_id, student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS teacher_attendance (
            id $pk,
            teacher_id INT NOT NULL,
            date DATE NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'hadir',
            time_in TIME NULL,
            time_out TIME NULL,
            location_lat DECIMAL(10,8) NULL,
            location_lng DECIMAL(11,8) NULL,
            notes TEXT NULL,
            recorded_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_id, date)
        )$engine",

        "CREATE TABLE IF NOT EXISTS teacher_teaching_attendance (
            id $pk,
            schedule_id INT NOT NULL,
            assignment_id INT NULL,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            date DATE NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'hadir',
            time_in TIME NULL,
            time_out TIME NULL,
            notes TEXT NULL,
            recorded_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (schedule_id, date)
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_violations (
            id $pk,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            type VARCHAR(120) NOT NULL,
            description TEXT NULL,
            points INT NOT NULL DEFAULT 0,
            action_taken TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS whatsapp_guardians (
            id $pk,
            student_id INT NOT NULL,
            name VARCHAR(160) NOT NULL,
            relationship VARCHAR(80) NULL,
            phone VARCHAR(32) NOT NULL,
            whatsapp_enabled $bool NOT NULL DEFAULT 1,
            active $bool NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS whatsapp_templates (
            id $pk,
            code VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'utility',
            body TEXT NOT NULL,
            cloud_template_name VARCHAR(160) NULL,
            language_code VARCHAR(16) NOT NULL DEFAULT 'id',
            parameter_keys TEXT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS whatsapp_queue (
            id $pk,
            student_id INT NULL,
            guardian_id INT NULL,
            template_id INT NULL,
            message_type VARCHAR(40) NOT NULL,
            context_key VARCHAR(160) NULL,
            report_start DATE NULL,
            report_end DATE NULL,
            phone VARCHAR(32) NOT NULL,
            message TEXT NOT NULL,
            template_variables TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id $pk,
            queue_id INT NULL,
            student_id INT NULL,
            guardian_id INT NULL,
            phone VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL,
            provider_message_id VARCHAR(160) NULL,
            response_code INT NULL,
            response_body TEXT NULL,
            error_message TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS daily_journals (
            id $pk,
            assignment_id INT NOT NULL,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            date DATE NOT NULL,
            meeting_no INT NOT NULL DEFAULT 1,
            topic VARCHAR(255) NOT NULL,
            activities TEXT NOT NULL,
            materials TEXT NULL,
            obstacles TEXT NULL,
            follow_up TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS telegram_logs (
            id $pk,
            chat_id VARCHAR(64) NOT NULL,
            username VARCHAR(160) NULL,
            message TEXT NULL,
            response TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS telegram_registration_tokens (
            id $pk,
            token VARCHAR(96) NOT NULL UNIQUE,
            chat_id VARCHAR(64) NOT NULL,
            from_username VARCHAR(160) NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS telegram_web_login_tokens (
            id $pk,
            token VARCHAR(96) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            next_page VARCHAR(80) NOT NULL DEFAULT 'dashboard',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS audit_logs (
            id $pk,
            user_id INT NULL,
            action VARCHAR(120) NOT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS extracurriculars (
            id $pk,
            dapodik_id VARCHAR(64) NULL,
            class_name VARCHAR(120) NOT NULL,
            type VARCHAR(80) NULL,
            name VARCHAR(160) NOT NULL,
            teacher_id INT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS extracurricular_members (
            id $pk,
            extracurricular_id INT NOT NULL,
            student_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (extracurricular_id, student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS dapodik_rombel_cache (
            id $pk,
            dapodik_id VARCHAR(64) NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            kind VARCHAR(80) NULL,
            grade VARCHAR(16) NULL,
            major VARCHAR(80) NULL,
            academic_year VARCHAR(32) NULL,
            teacher_id INT NULL,
            is_regular $bool NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS subject_groups (
            id $pk,
            code VARCHAR(32) NOT NULL,
            name VARCHAR(120) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'aktif',
            display_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS merged_subjects (
            id $pk,
            grade VARCHAR(16) NOT NULL,
            source_subject_id INT NOT NULL,
            target_subject_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS report_mappings (
            id $pk,
            curriculum VARCHAR(80) NOT NULL,
            grade VARCHAR(16) NOT NULL,
            subject_id INT NOT NULL,
            group_id INT NULL,
            display_order INT NOT NULL DEFAULT 0,
            include_in_report $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS signatures (
            id $pk,
            type VARCHAR(40) NOT NULL,
            user_id INT NULL,
            title VARCHAR(120) NULL,
            person_name VARCHAR(160) NULL,
            nip VARCHAR(64) NULL,
            file_path VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS report_dates (
            id $pk,
            grade VARCHAR(16) NOT NULL,
            report_date DATE NOT NULL,
            homeroom_place VARCHAR(80) NULL,
            principal_place VARCHAR(80) NULL,
            note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_photos (
            id $pk,
            student_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS cocurricular_themes (
            id $pk,
            name VARCHAR(180) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'aktif',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS cocurricular_activities (
            id $pk,
            theme_id INT NOT NULL,
            phase VARCHAR(32) NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            objective TEXT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS cocurricular_groups (
            id $pk,
            name VARCHAR(160) NOT NULL,
            grade VARCHAR(16) NOT NULL,
            phase VARCHAR(32) NOT NULL,
            theme_id INT NULL,
            coordinator_teacher_id INT NULL,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS cocurricular_members (
            id $pk,
            group_id INT NOT NULL,
            student_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (group_id, student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS graduations (
            id $pk,
            student_id INT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'lulus',
            certificate_no VARCHAR(120) NULL,
            transcript_no VARCHAR(120) NULL,
            graduation_date DATE NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS final_scores (
            id $pk,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            score DECIMAL(5,2) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id, subject_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS exam_scores (
            id $pk,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            score DECIMAL(5,2) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id, subject_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS app_settings (
            id $pk,
            setting_key VARCHAR(120) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS dapodik_sync_logs (
            id $pk,
            mode VARCHAR(40) NOT NULL,
            data_type VARCHAR(80) NOT NULL,
            endpoint VARCHAR(255) NULL,
            status VARCHAR(40) NOT NULL,
            message TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS backups (
            id $pk,
            file_name VARCHAR(180) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'ready',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS extracurricular_scores (
            id $pk,
            student_id INT NOT NULL,
            extracurricular_id INT NOT NULL,
            score VARCHAR(20) NULL,
            notes TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id, extracurricular_id)
        )$engine",

        "CREATE TABLE IF NOT EXISTS violation_rules (
            id $pk,
            code VARCHAR(40) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Umum',
            description TEXT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            active $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_rewards (
            id $pk,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT NULL,
            discount_percent INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS sp_thresholds (
            id $pk,
            level INT NOT NULL UNIQUE,
            label VARCHAR(40) NOT NULL,
            min_points INT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$engine",

        "CREATE TABLE IF NOT EXISTS student_descriptions (
            id $pk,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            grade_val VARCHAR(16) NOT NULL,
            description TEXT NOT NULL,
            auto_generated $bool NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (student_id, subject_id, grade_val)
        )$engine",
    ];

    foreach ($statements as $sql) {
        db()->exec($sql);
    }

    migration_add_column('users', 'student_id', 'INT NULL');
    migration_add_column('users', 'telegram_login_active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('teachers', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('teachers', 'nip', 'VARCHAR(64) NULL');
    migration_add_column('teachers', 'nuptk', 'VARCHAR(64) NULL');
    migration_add_column('teachers', 'gender', 'VARCHAR(16) NULL');
    migration_add_column('teachers', 'position', 'VARCHAR(120) NULL');
    migration_add_column('teachers', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('teachers', 'updated_at', 'DATETIME NULL');
    migration_add_column('teachers', 'is_bk', migration_bool() . ' NOT NULL DEFAULT 0');
    migration_add_column('grades', 'assessment_type', "VARCHAR(20) NOT NULL DEFAULT 'UH'");
    migration_add_column('grades', 'learning_objective_id', 'INT NULL');
    migration_add_column('classes', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('classes', 'grade', 'VARCHAR(16) NULL');
    migration_add_column('classes', 'major', 'VARCHAR(80) NULL');
    migration_add_column('classes', 'homeroom_teacher_id', 'INT NULL');
    migration_add_column('classes', 'academic_year', 'VARCHAR(32) NULL');
    migration_add_column('classes', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('classes', 'updated_at', 'DATETIME NULL');
    migration_add_column('classes', 'level', 'VARCHAR(16) NULL');
    migration_add_column('classes', 'school_id', 'INT UNSIGNED NULL');
    if (table_exists('classes') && migration_column_exists('classes', 'school_id')) {
        $firstSchool = fetch_one('SELECT id FROM school_profile ORDER BY id LIMIT 1');
        if ($firstSchool) {
            execute_sql('UPDATE classes SET school_id = ? WHERE school_id IS NULL', [(int)$firstSchool['id']]);
        }
    }
    migration_add_column('students', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('students', 'nis', 'VARCHAR(64) NULL');
    migration_add_column('students', 'nisn', 'VARCHAR(64) NULL');
    migration_add_column('students', 'gender', 'VARCHAR(16) NULL');
    migration_add_column('students', 'birth_place', 'VARCHAR(80) NULL');
    migration_add_column('students', 'birth_date', 'DATE NULL');
    migration_add_column('students', 'religion', 'VARCHAR(64) NULL');
    migration_add_column('students', 'class_id', 'INT NULL');
    migration_add_column('students', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('students', 'updated_at', 'DATETIME NULL');
    migration_add_column('students', 'address', 'TEXT NULL');
    migration_add_column('students', 'phone', 'VARCHAR(32) NULL');
    migration_add_column('students', 'father_name', 'VARCHAR(160) NULL');
    migration_add_column('students', 'father_occupation', 'VARCHAR(120) NULL');
    migration_add_column('students', 'mother_name', 'VARCHAR(160) NULL');
    migration_add_column('students', 'mother_occupation', 'VARCHAR(120) NULL');
    migration_add_column('students', 'guardian_name', 'VARCHAR(160) NULL');
    migration_add_column('subjects', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('subjects', 'short_name', 'VARCHAR(40) NULL');
    migration_add_column('subjects', 'group_name', 'VARCHAR(80) NULL');
    migration_add_column('subjects', 'curriculum', 'VARCHAR(32) NULL');
    migration_add_column('subjects', 'kkm', 'INT NULL');
    migration_add_column('subjects', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('subjects', 'updated_at', 'DATETIME NULL');
    migration_add_column('teaching_assignments', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('teaching_assignments', 'academic_year', 'VARCHAR(32) NULL');
    migration_add_column('teaching_assignments', 'semester', 'VARCHAR(32) NULL');
    migration_add_column('teaching_assignments', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('teaching_assignments', 'updated_at', 'DATETIME NULL');
    migration_add_column('extracurriculars', 'dapodik_id', 'VARCHAR(64) NULL');
    migration_add_column('extracurriculars', 'class_name', 'VARCHAR(80) NULL');
    migration_add_column('extracurriculars', 'type', 'VARCHAR(80) NULL');
    migration_add_column('extracurriculars', 'teacher_id', 'INT NULL');
    migration_add_column('extracurriculars', 'active', migration_bool() . ' NOT NULL DEFAULT 1');
    migration_add_column('extracurriculars', 'updated_at', 'DATETIME NULL');
    migration_add_column('extracurricular_members', 'updated_at', 'DATETIME NULL');

    migration_add_column('school_profile', 'short_days', 'VARCHAR(64) NULL');
    migration_add_column('school_profile', 'regular_period_minutes', 'INT NULL DEFAULT 35');
    migration_add_column('school_profile', 'short_period_minutes', 'INT NULL DEFAULT 25');
    migration_add_column('school_profile', 'max_periods', 'INT NULL DEFAULT 10');
    migration_add_column('school_profile', 'start_time', 'VARCHAR(10) NULL DEFAULT \'07:00\'');
    migration_add_column('school_profile', 'break1_after', 'INT NULL DEFAULT 3');
    migration_add_column('school_profile', 'break1_minutes', 'INT NULL DEFAULT 15');
    migration_add_column('school_profile', 'break2_after', 'INT NULL DEFAULT 6');
    migration_add_column('school_profile', 'break2_minutes', 'INT NULL DEFAULT 15');
    migration_add_column('school_profile', 'village', 'VARCHAR(120) NULL');
    migration_add_column('school_profile', 'district', 'VARCHAR(120) NULL');
    migration_add_column('school_profile', 'regency', 'VARCHAR(120) NULL');
    migration_add_column('school_profile', 'province', 'VARCHAR(120) NULL');

    migrate_foreign_keys();
}

function seed_defaults(): void
{
    $schoolCount = (int)db()->query('SELECT COUNT(*) FROM school_profile')->fetchColumn();
    if ($schoolCount === 0) {
        $stmt = db()->prepare('INSERT INTO school_profile (name, academic_year, semester) VALUES (?, ?, ?)');
        $stmt->execute([
            (string)config('school.name', 'Nama Sekolah'),
            (string)config('school.academic_year', '2025/2026'),
            (string)config('school.semester', 'Genap'),
        ]);
    }

    $userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount > 0) {
        return;
    }

    $teachers = [
        ['Siti Aminah, S.Pd', '198601012010012001', '1234567890123456', 'P', 'Guru Kelas'],
        ['Budi Santoso, S.Pd', '198410102009011002', '2234567890123456', 'L', 'Guru Mapel'],
        ['Rina Lestari, S.Pd', '199003032015022003', '3234567890123456', 'P', 'Guru Mapel'],
    ];
    $stmt = db()->prepare('INSERT INTO teachers (name, nip, nuptk, gender, position, email, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ($teachers as $index => $teacher) {
        $stmt->execute([$teacher[0], $teacher[1], $teacher[2], $teacher[3], $teacher[4], 'guru' . ($index + 1) . '@sekolah.local']);
    }

    $stmt = db()->prepare('INSERT INTO classes (name, grade, homeroom_teacher_id, academic_year, active) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute(['1A', '1', 1, (string)config('school.academic_year', '2025/2026')]);
    $stmt->execute(['2A', '2', 2, (string)config('school.academic_year', '2025/2026')]);

    $subjects = [
        ['Bahasa Indonesia', 'B.Indo', 'Wajib'],
        ['Matematika', 'MTK', 'Wajib'],
        ['Pendidikan Pancasila', 'PP', 'Wajib'],
        ['PJOK', 'PJOK', 'Wajib'],
        ['Seni Budaya', 'SBdP', 'Wajib'],
    ];
    $stmt = db()->prepare('INSERT INTO subjects (name, short_name, group_name, active) VALUES (?, ?, ?, 1)');
    foreach ($subjects as $subject) {
        $stmt->execute($subject);
    }

    $students = [
        ['1001', '0091001001', 'Ahmad Fauzan', 'L', '1'],
        ['1002', '0091001002', 'Bilqis Aulia', 'P', '1'],
        ['1003', '0091001003', 'Cahya Pratama', 'L', '1'],
        ['1004', '0091001004', 'Dinda Safitri', 'P', '1'],
        ['2001', '0092001001', 'Eka Saputra', 'L', '2'],
        ['2002', '0092001002', 'Farah Nabila', 'P', '2'],
        ['2003', '0092001003', 'Gilang Ramadhan', 'L', '2'],
        ['2004', '0092001004', 'Hana Zahra', 'P', '2'],
    ];
    $stmt = db()->prepare('INSERT INTO students (nis, nisn, name, gender, class_id, active) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($students as $student) {
        $stmt->execute($student);
    }

    $assignments = [
        [1, 1, 1],
        [1, 1, 2],
        [2, 2, 2],
        [3, 1, 4],
        [3, 2, 5],
    ];
    $stmt = db()->prepare('INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($assignments as $assignment) {
        $stmt->execute([
            $assignment[0],
            $assignment[1],
            $assignment[2],
            (string)config('school.academic_year', '2025/2026'),
            (string)config('school.semester', 'Genap'),
        ]);
    }

    $users = [
        ['administrator', 'administrator', 'Administrator', 'adminrapor@sekolah.local', 'admin', null],
        ['guru1', 'guru123', 'Siti Aminah', 'guru1@sekolah.local', 'guru', 1],
        ['guru2', 'guru123', 'Budi Santoso', 'guru2@sekolah.local', 'guru', 2],
        ['guru3', 'guru123', 'Rina Lestari', 'guru3@sekolah.local', 'guru', 3],
    ];
    $stmt = db()->prepare('INSERT INTO users (username, password_hash, name, email, role, teacher_id, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ($users as $user) {
        $stmt->execute([
            $user[0],
            password_hash($user[1], PASSWORD_DEFAULT),
            $user[2],
            $user[3],
            $user[4],
            $user[5],
        ]);
    }
}

function install_database(): void
{
    run_migrations();
    seed_defaults();
    if (config('demo_seed', false)) {
        seed_extended_defaults();
    } else {
        seed_clean_defaults();
    }
}

function seed_clean_defaults(): void
{
    foreach ([
        'dapodik_url'                  => '',
        'dapodik_token'                => '',
        'dapodik_npsn'                 => '',
        'whatsapp_mode'                => 'simulate',
        'whatsapp_access_token'        => '',
        'whatsapp_phone_number_id'     => '',
        'whatsapp_waba_id'             => '',
        'whatsapp_graph_version'       => 'v23.0',
        'whatsapp_cloud_delivery'      => 'text',
        'whatsapp_fonnte_token'        => '',
        'whatsapp_fonnte_country_code' => '62',
    ] as $key => $value) {
        if (get_app_setting($key, null) === null) {
            set_app_setting($key, $value);
        }
    }
    if (get_app_setting('dapodik_bridge_token', '') === '') {
        set_app_setting('dapodik_bridge_token', bin2hex(random_bytes(12)));
    }
    if (get_app_setting('whatsapp_cron_secret', '') === '') {
        set_app_setting('whatsapp_cron_secret', bin2hex(random_bytes(12)));
    }
    seed_default_sp_thresholds();
}

function seed_default_sp_thresholds(): void
{
    if (!table_exists('sp_thresholds')) {
        return;
    }
    $defaults = [
        [1, 'SP-1', 50],
        [2, 'SP-2', 75],
        [3, 'SP-3', 100],
    ];
    foreach ($defaults as [$level, $label, $minPoints]) {
        if (!fetch_one('SELECT id FROM sp_thresholds WHERE level = ?', [$level])) {
            execute_sql('INSERT INTO sp_thresholds (level, label, min_points, updated_at) VALUES (?, ?, ?, ?)', [$level, $label, $minPoints, now_string()]);
        }
    }
}

function seed_extended_defaults(): void
{
    seed_demo_reference_data();
    seed_demo_report_data();
    seed_demo_learning_activity_data();
    seed_demo_student_portal_data();
    seed_demo_whatsapp_data();
    seed_default_sp_thresholds();

    foreach (['dapodik_url' => '', 'dapodik_token' => '', 'dapodik_npsn' => ''] as $key => $value) {
        if (get_app_setting($key, null) === null) {
            set_app_setting($key, $value);
        }
    }
    if (get_app_setting('dapodik_bridge_token', '') === '') {
        set_app_setting('dapodik_bridge_token', bin2hex(random_bytes(12)));
    }
    foreach ([
        'whatsapp_mode' => 'simulate',
        'whatsapp_access_token' => '',
        'whatsapp_phone_number_id' => '',
        'whatsapp_waba_id' => '',
        'whatsapp_graph_version' => 'v23.0',
        'whatsapp_cloud_delivery' => 'text',
        'whatsapp_fonnte_token' => '',
        'whatsapp_fonnte_country_code' => '62',
    ] as $key => $value) {
        if (get_app_setting($key, null) === null) {
            set_app_setting($key, $value);
        }
    }
    if (get_app_setting('whatsapp_cron_secret', '') === '') {
        set_app_setting('whatsapp_cron_secret', bin2hex(random_bytes(12)));
    }
}

function seed_demo_reference_data(): void
{
    $school = fetch_one('SELECT * FROM school_profile ORDER BY id LIMIT 1');
    if ($school) {
        execute_sql(
            'UPDATE school_profile SET npsn = COALESCE(NULLIF(npsn, \'\'), ?), address = COALESCE(NULLIF(address, \'\'), ?), principal_name = COALESCE(NULLIF(principal_name, \'\'), ?), principal_nip = COALESCE(NULLIF(principal_nip, \'\'), ?), updated_at = ? WHERE id = ?',
            ['12345678', 'Jl. Sepi Gg.01 Makam Pahlawan', 'Drs. Abdul Rahman', '196804121990031006', now_string(), (int)$school['id']]
        );
    }

    $teachers = [
        'wali1' => ['Siti Aminah, S.Pd', '198601012010012001', '1234567890123456', 'P', 'Guru Kelas', 'guru1@sekolah.local', '70010001'],
        'mapel1' => ['Budi Santoso, S.Pd', '198410102009011002', '2234567890123456', 'L', 'Guru Mapel', 'guru2@sekolah.local', '70010002'],
        'mapel2' => ['Rina Lestari, S.Pd', '199003032015022003', '3234567890123456', 'P', 'Guru Mapel', 'guru3@sekolah.local', '70010003'],
        'wali2' => ['Agus Prasetyo, S.Pd', '198905052014011004', '4234567890123456', 'L', 'Guru Kelas', 'guru4@sekolah.local', '70010004'],
    ];
    $teacherIds = [];
    foreach ($teachers as $key => $teacher) {
        $teacherIds[$key] = seed_demo_teacher(...$teacher);
    }

    seed_demo_user('guru1', 'guru123', 'Siti Aminah', 'guru1@sekolah.local', 'guru', $teacherIds['wali1'], '70010001');
    seed_demo_user('guru2', 'guru123', 'Budi Santoso', 'guru2@sekolah.local', 'guru', $teacherIds['mapel1'], '70010002');
    seed_demo_user('guru3', 'guru123', 'Rina Lestari', 'guru3@sekolah.local', 'guru', $teacherIds['mapel2'], '70010003');
    seed_demo_user('guru4', 'guru123', 'Agus Prasetyo', 'guru4@sekolah.local', 'guru', $teacherIds['wali2'], '70010004');

    $class1 = seed_demo_class('1A', '1', $teacherIds['wali1']);
    $class2 = seed_demo_class('2A', '2', $teacherIds['wali2']);
    $class9 = seed_demo_class('9A', '9', $teacherIds['wali2']);

    $subjectIds = [];
    foreach ([
        'Pendidikan Pancasila' => ['PP', 'Wajib'],
        'Bahasa Indonesia' => ['B.Indo', 'Wajib'],
        'Matematika' => ['MTK', 'Wajib'],
        'PJOK' => ['PJOK', 'Wajib'],
        'Seni Budaya' => ['SBdP', 'Wajib'],
    ] as $name => $meta) {
        $subjectIds[$name] = seed_demo_subject($name, $meta[0], $meta[1]);
    }

    $students = [
        ['1001', '0091001001', 'Ahmad Fauzan', 'L', $class1],
        ['1002', '0091001002', 'Bilqis Aulia', 'P', $class1],
        ['1003', '0091001003', 'Cahya Pratama', 'L', $class1],
        ['1004', '0091001004', 'Dinda Safitri', 'P', $class1],
        ['2001', '0092001001', 'Eka Saputra', 'L', $class2],
        ['2002', '0092001002', 'Farah Nabila', 'P', $class2],
        ['2003', '0092001003', 'Gilang Ramadhan', 'L', $class2],
        ['2004', '0092001004', 'Hana Zahra', 'P', $class2],
        ['9001', '0099001001', 'Nadia Azzahra', 'P', $class9],
        ['9002', '0099001002', 'Raka Maulana', 'L', $class9],
    ];
    foreach ($students as $student) {
        seed_demo_student(...$student);
    }

    foreach ([['A', 'Kelompok A', 1], ['B', 'Kelompok B', 2], ['C', 'Muatan Lokal', 3], ['P5', 'Kokurikuler/P5', 4]] as $group) {
        seed_demo_subject_group($group[0], $group[1], $group[2]);
    }
    foreach ([['WAJIB_A', 'Mata Pelajaran Wajib A', 10], ['WAJIB_B', 'Mata Pelajaran Wajib B', 11], ['PILIHAN', 'Mata Pelajaran Pilihan', 12], ['MULOK', 'Muatan Lokal', 13]] as $group) {
        seed_demo_subject_group($group[0], $group[1], $group[2]);
    }

    $teacherCycle = [$teacherIds['wali1'], $teacherIds['mapel1'], $teacherIds['mapel1'], $teacherIds['mapel2'], $teacherIds['mapel2']];
    foreach ([$class1, $class2, $class9] as $classId) {
        $index = 0;
        foreach ($subjectIds as $subjectId) {
            seed_demo_assignment($teacherCycle[$index] ?? $teacherIds['wali1'], $classId, $subjectId);
            $index++;
        }
    }

    $pancasilaId = $subjectIds['Pendidikan Pancasila'] ?? 0;
    if ($pancasilaId) {
        $assignment = fetch_one('SELECT id, teacher_id FROM teaching_assignments WHERE class_id = ? AND subject_id = ? ORDER BY id LIMIT 1', [$class2, $pancasilaId]);
        if ($assignment && (int)$assignment['teacher_id'] === $teacherIds['wali1']) {
            execute_sql('UPDATE teaching_assignments SET teacher_id = ?, updated_at = ? WHERE id = ?', [$teacherIds['wali2'], now_string(), (int)$assignment['id']]);
        }
    }
}

function seed_demo_report_data(): void
{
    $groups = array_column(fetch_all('SELECT id, code FROM subject_groups'), 'id', 'code');
    $order = ['Pendidikan Pancasila', 'Bahasa Indonesia', 'Matematika', 'PJOK', 'Seni Budaya'];
    foreach (['1', '2', '9'] as $grade) {
        $display = 1;
        foreach ($order as $subjectName) {
            $subject = fetch_one('SELECT id FROM subjects WHERE name = ?', [$subjectName]);
            if ($subject) {
                seed_demo_report_mapping($grade, (int)$subject['id'], (int)($groups['A'] ?? 0), $display++);
            }
        }
    }

    foreach (['1', '2', '9'] as $grade) {
        if (!fetch_one('SELECT id FROM report_dates WHERE grade = ?', [$grade])) {
            execute_sql(
                'INSERT INTO report_dates (grade, report_date, homeroom_place, principal_place, note, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$grade, '2026-06-20', 'Jakarta', 'Jakarta', 'Menunjukkan sikap baik dan perlu terus dibiasakan belajar mandiri di rumah.', now_string()]
            );
        }
    }

    seed_demo_extracurricular('1A', 'Wajib', 'Pramuka', 1);
    seed_demo_extracurricular('1A', 'Pilihan', 'Tahfidz', 2);
    seed_demo_extracurricular('2A', 'Wajib', 'Pramuka', 1);
    seed_demo_extracurricular('2A', 'Pilihan', 'Seni Tari', 3);
    seed_demo_extracurricular('9A', 'Wajib', 'Pramuka', 1);
    seed_demo_extracurricular('9A', 'Pilihan', 'Karya Ilmiah Remaja', 2);

    $themeId = seed_demo_cocurricular_theme('Bhinneka Tunggal Ika');
    seed_demo_cocurricular_activity($themeId, 'A', 'Aku Cinta Lingkungan Sekolah', 'Peserta didik mengenal kebiasaan gotong royong, berbagi peran, dan menjaga kebersihan kelas.', 'Melatih tanggung jawab, komunikasi, dan kreativitas melalui kegiatan bersama.');
    seed_demo_cocurricular_group('Projek Kokurikuler Kelas 1A', '1', 'A', $themeId, '1A');
    seed_demo_cocurricular_group('Projek Kokurikuler Kelas 2A', '2', 'A', $themeId, '2A');
    seed_demo_cocurricular_group('Projek Kokurikuler Kelas 9A', '9', 'D', $themeId, '9A');

    seed_demo_asset_files();
    seed_demo_signature('logo', null, 'Logo Sekolah', 'Logo Sekolah', '', 'storage/uploads/signatures/dummy-logo.svg');
    seed_demo_signature('logo_dinas', null, 'Logo Dinas', 'Logo Dinas Pendidikan', '', 'storage/uploads/signatures/dummy-logo-dinas.svg');
    seed_demo_signature('ttd_kepsek', null, 'Kepala Sekolah', 'Drs. Abdul Rahman', '196804121990031006', 'storage/uploads/signatures/dummy-ttd-kepsek.svg');
    $waliUser = fetch_one("SELECT id FROM users WHERE username = 'guru1'");
    seed_demo_signature('ttd_wali', (int)($waliUser['id'] ?? 0) ?: null, 'Wali Kelas', 'Siti Aminah, S.Pd', '198601012010012001', 'storage/uploads/signatures/dummy-ttd-wali.svg');

    $students = fetch_all('SELECT id FROM students WHERE active = 1 ORDER BY id');
    foreach ($students as $student) {
        if (!fetch_one('SELECT id FROM student_photos WHERE student_id = ?', [(int)$student['id']])) {
            execute_sql('INSERT INTO student_photos (student_id, file_path, updated_at) VALUES (?, ?, ?)', [(int)$student['id'], 'storage/uploads/student-photos/dummy-student.svg', now_string()]);
        }
    }
}

function seed_demo_learning_activity_data(): void
{
    $descriptions = [
        'Pendidikan Pancasila' => 'Menunjukkan pemahaman baik tentang aturan, hak, kewajiban, dan kebiasaan bermusyawarah dalam kehidupan sehari-hari.',
        'Bahasa Indonesia' => 'Mampu menyimak, membaca, dan menyampaikan kembali informasi sederhana dengan bahasa yang runtut.',
        'Matematika' => 'Mampu memahami bilangan, membandingkan jumlah, dan menyelesaikan soal kontekstual sederhana dengan teliti.',
        'PJOK' => 'Menunjukkan kemampuan gerak dasar, kerja sama, dan kebiasaan menjaga kebugaran tubuh.',
        'Seni Budaya' => 'Mampu mengekspresikan gagasan melalui gambar, irama, dan karya sederhana dengan percaya diri.',
    ];

    $assignments = fetch_all('SELECT ta.*, s.name AS subject_name FROM teaching_assignments ta JOIN subjects s ON s.id = ta.subject_id WHERE ta.active = 1');
    foreach ($assignments as $assignment) {
        $students = fetch_all('SELECT id FROM students WHERE class_id = ? AND active = 1 ORDER BY id', [(int)$assignment['class_id']]);
        foreach ($students as $student) {
            $score = 76 + (((int)$student['id'] + (int)$assignment['subject_id']) % 18);
            $description = $descriptions[(string)$assignment['subject_name']] ?? ('Menunjukkan perkembangan belajar yang baik pada mata pelajaran ' . $assignment['subject_name'] . '.');
            $existing = fetch_one('SELECT id, score, description FROM grades WHERE assignment_id = ? AND student_id = ?', [(int)$assignment['id'], (int)$student['id']]);
            if ($existing) {
                if ($existing['score'] === null || trim((string)$existing['description']) === '') {
                    execute_sql('UPDATE grades SET score = COALESCE(score, ?), description = COALESCE(NULLIF(description, \'\'), ?), updated_at = ? WHERE id = ?', [$score, $description, now_string(), (int)$existing['id']]);
                }
            } else {
                execute_sql('INSERT INTO grades (assignment_id, student_id, score, description, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [(int)$assignment['id'], (int)$student['id'], $score, $description, 1, now_string()]);
            }
            if (!fetch_one('SELECT id FROM final_scores WHERE student_id = ? AND subject_id = ?', [(int)$student['id'], (int)$assignment['subject_id']])) {
                execute_sql('INSERT INTO final_scores (student_id, subject_id, score, updated_at) VALUES (?, ?, ?, ?)', [(int)$student['id'], (int)$assignment['subject_id'], $score, now_string()]);
            }
        }

        seed_demo_student_attendance_session((int)$assignment['id'], (int)$assignment['class_id'], '2026-06-03', 1, 'Latihan dan refleksi pembelajaran');
        seed_demo_journal((int)$assignment['id'], (int)$assignment['teacher_id'], (int)$assignment['class_id'], (int)$assignment['subject_id'], (string)$assignment['subject_name']);
    }

    foreach (fetch_all('SELECT id FROM teachers WHERE active = 1') as $teacher) {
        seed_demo_teacher_attendance((int)$teacher['id'], '2026-06-03');
        seed_demo_teacher_attendance((int)$teacher['id'], '2026-06-04');
    }
}

function seed_demo_student_portal_data(): void
{
    foreach (fetch_all('SELECT s.*, c.grade, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.active = 1 ORDER BY s.id') as $student) {
        seed_demo_student_user($student);
        seed_demo_student_violation((int)$student['id']);
        if ((string)($student['grade'] ?? '') === '9') {
            seed_demo_graduation((int)$student['id']);
        }
    }
}

function seed_demo_whatsapp_data(): void
{
    if (!table_exists('whatsapp_guardians') || !table_exists('whatsapp_templates')) {
        return;
    }

    seed_demo_whatsapp_template(
        'weekly_report',
        'Weekly Report Wali Santri',
        "Assalamu'alaikum Bapak/Ibu {guardian_name}.\n\nLaporan pekan {start_date} s.d. {end_date} untuk {student_name} ({class_name}):\nKehadiran: Hadir {hadir}, Sakit {sakit}, Izin {izin}, Alpa {alpa}, Terlambat {terlambat}.\nPelanggaran: {violation_count} catatan, total {violation_points} poin.\n{violation_summary}\n\nTerima kasih.\n{school}",
        'guardian_name,student_name,class_name,start_date,end_date,hadir,sakit,izin,alpa,terlambat,violation_count,violation_points,violation_summary,school'
    );
    seed_demo_whatsapp_template(
        'violation_notice',
        'Pemberitahuan Pelanggaran',
        "Assalamu'alaikum Bapak/Ibu {guardian_name}.\n\nKami menginformasikan catatan pelanggaran atas nama {student_name} ({class_name}) pada {date}.\nJenis: {type}\nPoin: {points}\nKeterangan: {description}\nTindak lanjut: {action_taken}\n\nMohon menjadi perhatian bersama.\n{school}",
        'guardian_name,student_name,class_name,date,type,points,description,action_taken,school'
    );
    seed_demo_whatsapp_template(
        'attendance_notice',
        'Pemberitahuan Kehadiran',
        "Assalamu'alaikum Bapak/Ibu {guardian_name}.\n\nKehadiran {student_name} ({class_name}) pada {date} tercatat: {status}.\nMapel: {subject_name}\nCatatan: {notes}\n\nTerima kasih.\n{school}",
        'guardian_name,student_name,class_name,date,status,subject_name,notes,school'
    );
    seed_demo_whatsapp_template(
        'manual_notice',
        'Pesan Manual Wali Santri',
        "Assalamu'alaikum Bapak/Ibu {guardian_name}.\n\n{message}\n\nTerkait siswa: {student_name} ({class_name}).\n{school}",
        'guardian_name,message,student_name,class_name,school'
    );

    $students = fetch_all('SELECT id, name FROM students WHERE active = 1 ORDER BY id');
    foreach ($students as $student) {
        $phone = '6281200' . str_pad((string)$student['id'], 6, '0', STR_PAD_LEFT);
        if (!fetch_one('SELECT id FROM whatsapp_guardians WHERE student_id = ? AND phone = ?', [(int)$student['id'], $phone])) {
            execute_sql(
                'INSERT INTO whatsapp_guardians (student_id, name, relationship, phone, whatsapp_enabled, active, notes, updated_at) VALUES (?, ?, ?, ?, 1, 1, ?, ?)',
                [(int)$student['id'], 'Wali ' . (string)$student['name'], 'Orang Tua', $phone, 'Data dummy wali santri.', now_string()]
            );
        }
    }
}

function seed_demo_whatsapp_template(string $code, string $name, string $body, string $parameterKeys): void
{
    $existing = fetch_one('SELECT id FROM whatsapp_templates WHERE code = ?', [$code]);
    if ($existing) {
        execute_sql(
            'UPDATE whatsapp_templates SET name = COALESCE(NULLIF(name, \'\'), ?), body = COALESCE(NULLIF(body, \'\'), ?), parameter_keys = COALESCE(NULLIF(parameter_keys, \'\'), ?), updated_at = ? WHERE id = ?',
            [$name, $body, $parameterKeys, now_string(), (int)$existing['id']]
        );
        return;
    }

    execute_sql(
        'INSERT INTO whatsapp_templates (code, name, category, body, language_code, parameter_keys, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$code, $name, 'utility', $body, 'id', $parameterKeys, now_string()]
    );
}

function seed_demo_teacher(string $name, string $nip, string $nuptk, string $gender, string $position, string $email, string $telegramChatId): int
{
    $row = fetch_one('SELECT id FROM teachers WHERE nip = ? OR name = ? ORDER BY id LIMIT 1', [$nip, $name]);
    if ($row) {
        execute_sql('UPDATE teachers SET name = ?, nuptk = COALESCE(NULLIF(nuptk, \'\'), ?), gender = COALESCE(NULLIF(gender, \'\'), ?), position = COALESCE(NULLIF(position, \'\'), ?), email = COALESCE(NULLIF(email, \'\'), ?), telegram_chat_id = COALESCE(NULLIF(telegram_chat_id, \'\'), ?), active = 1, updated_at = ? WHERE id = ?', [$name, $nuptk, $gender, $position, $email, $telegramChatId, now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO teachers (name, nip, nuptk, gender, position, email, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)', [$name, $nip, $nuptk, $gender, $position, $email, $telegramChatId, now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_user(string $username, string $password, string $name, string $email, string $role, ?int $teacherId, string $telegramChatId, ?int $studentId = null): void
{
    $row = fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
    if ($row) {
        execute_sql('UPDATE users SET name = ?, email = COALESCE(NULLIF(email, \'\'), ?), role = ?, teacher_id = COALESCE(teacher_id, ?), student_id = COALESCE(student_id, ?), telegram_chat_id = CASE WHEN ? = ? THEN ? ELSE COALESCE(NULLIF(telegram_chat_id, \'\'), ?) END, active = 1, updated_at = ? WHERE id = ?', [$name, $email, $role, $teacherId, $studentId, $role, 'siswa', '', $telegramChatId, now_string(), (int)$row['id']]);
        return;
    }
    execute_sql('INSERT INTO users (username, password_hash, name, email, role, teacher_id, student_id, telegram_chat_id, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)', [$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, $role, $teacherId, $studentId, $role === 'siswa' ? '' : $telegramChatId, now_string()]);
}

function seed_demo_student_user(array $student): void
{
    $nis = trim((string)($student['nis'] ?? ''));
    $username = $nis !== '' ? 'siswa' . $nis : 'siswa' . (int)$student['id'];
    seed_demo_user($username, 'siswa123', (string)$student['name'], $username . '@siswa.local', 'siswa', null, '', (int)$student['id']);
}

function seed_demo_student_violation(int $studentId): void
{
    if (!table_exists('student_violations')) {
        return;
    }
    if (!fetch_one('SELECT id FROM student_violations WHERE student_id = ? AND date = ?', [$studentId, '2026-06-05'])) {
        execute_sql(
            'INSERT INTO student_violations (student_id, date, type, description, points, action_taken, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$studentId, '2026-06-05', 'Kedisiplinan', 'Terlambat masuk kelas pada jam pertama.', 5, 'Pembinaan oleh wali kelas dan komitmen hadir tepat waktu.', 1, now_string()]
        );
    }
}

function seed_demo_graduation(int $studentId): void
{
    if (fetch_one('SELECT id FROM graduations WHERE student_id = ?', [$studentId])) {
        return;
    }
    $student = fetch_one('SELECT nisn, nis FROM students WHERE id = ?', [$studentId]) ?: [];
    execute_sql(
        'INSERT INTO graduations (student_id, status, certificate_no, transcript_no, graduation_date, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$studentId, 'lulus', 'SKL-' . ($student['nis'] ?? $studentId) . '/2026', 'TR-' . ($student['nisn'] ?? $studentId) . '/2026', '2026-06-15', 'Memenuhi seluruh kriteria kelulusan satuan pendidikan.', now_string()]
    );
}

function seed_demo_class(string $name, string $grade, int $homeroomTeacherId): int
{
    $row = fetch_one('SELECT id FROM classes WHERE name = ? ORDER BY id LIMIT 1', [$name]);
    if ($row) {
        execute_sql('UPDATE classes SET grade = ?, homeroom_teacher_id = COALESCE(homeroom_teacher_id, ?), academic_year = ?, active = 1, updated_at = ? WHERE id = ?', [$grade, $homeroomTeacherId, (string)config('school.academic_year', '2025/2026'), now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO classes (name, grade, homeroom_teacher_id, academic_year, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)', [$name, $grade, $homeroomTeacherId, (string)config('school.academic_year', '2025/2026'), now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_subject(string $name, string $shortName, string $groupName): int
{
    $row = fetch_one('SELECT id FROM subjects WHERE name = ? ORDER BY id LIMIT 1', [$name]);
    if ($row) {
        execute_sql('UPDATE subjects SET short_name = COALESCE(NULLIF(short_name, \'\'), ?), group_name = COALESCE(NULLIF(group_name, \'\'), ?), active = 1, updated_at = ? WHERE id = ?', [$shortName, $groupName, now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO subjects (name, short_name, group_name, active, updated_at) VALUES (?, ?, ?, 1, ?)', [$name, $shortName, $groupName, now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_student(string $nis, string $nisn, string $name, string $gender, int $classId): int
{
    $row = fetch_one('SELECT id FROM students WHERE nisn = ? OR nis = ? ORDER BY id LIMIT 1', [$nisn, $nis]);
    if ($row) {
        execute_sql('UPDATE students SET name = ?, gender = COALESCE(NULLIF(gender, \'\'), ?), class_id = COALESCE(class_id, ?), active = 1, updated_at = ? WHERE id = ?', [$name, $gender, $classId, now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO students (nis, nisn, name, gender, class_id, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)', [$nis, $nisn, $name, $gender, $classId, now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_subject_group(string $code, string $name, int $displayOrder): int
{
    $row = fetch_one('SELECT id FROM subject_groups WHERE code = ?', [$code]);
    if ($row) {
        execute_sql('UPDATE subject_groups SET name = ?, status = ?, display_order = ?, updated_at = ? WHERE id = ?', [$name, 'aktif', $displayOrder, now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO subject_groups (code, name, status, display_order, updated_at) VALUES (?, ?, ?, ?, ?)', [$code, $name, 'aktif', $displayOrder, now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_assignment(int $teacherId, int $classId, int $subjectId): int
{
    $row = fetch_one('SELECT id FROM teaching_assignments WHERE class_id = ? AND subject_id = ? ORDER BY id LIMIT 1', [$classId, $subjectId]);
    if ($row) {
        execute_sql('UPDATE teaching_assignments SET teacher_id = COALESCE(NULLIF(teacher_id, 0), ?), academic_year = ?, semester = ?, active = 1, updated_at = ? WHERE id = ?', [$teacherId, (string)config('school.academic_year', '2025/2026'), (string)config('school.semester', 'Genap'), now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO teaching_assignments (teacher_id, class_id, subject_id, academic_year, semester, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)', [$teacherId, $classId, $subjectId, (string)config('school.academic_year', '2025/2026'), (string)config('school.semester', 'Genap'), now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_report_mapping(string $grade, int $subjectId, int $groupId, int $displayOrder): void
{
    $row = fetch_one('SELECT id FROM report_mappings WHERE grade = ? AND subject_id = ? ORDER BY id LIMIT 1', [$grade, $subjectId]);
    if ($row) {
        execute_sql('UPDATE report_mappings SET curriculum = ?, group_id = ?, display_order = ?, include_in_report = 1, updated_at = ? WHERE id = ?', ['Kurikulum Merdeka', $groupId ?: null, $displayOrder, now_string(), (int)$row['id']]);
        return;
    }
    execute_sql('INSERT INTO report_mappings (curriculum, grade, subject_id, group_id, display_order, include_in_report, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)', ['Kurikulum Merdeka', $grade, $subjectId, $groupId ?: null, $displayOrder, now_string()]);
}

function seed_demo_extracurricular(string $className, string $type, string $name, int $teacherId): void
{
    if (!fetch_one('SELECT id FROM extracurriculars WHERE class_name = ? AND name = ?', [$className, $name])) {
        execute_sql('INSERT INTO extracurriculars (class_name, type, name, teacher_id, active, updated_at) VALUES (?, ?, ?, ?, 1, ?)', [$className, $type, $name, $teacherId, now_string()]);
    }
}

function seed_demo_cocurricular_theme(string $name): int
{
    $row = fetch_one('SELECT id FROM cocurricular_themes WHERE name = ?', [$name]);
    if ($row) {
        execute_sql('UPDATE cocurricular_themes SET status = ?, updated_at = ? WHERE id = ?', ['aktif', now_string(), (int)$row['id']]);
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO cocurricular_themes (name, status, updated_at) VALUES (?, ?, ?)', [$name, 'aktif', now_string()]);
    return (int)db()->lastInsertId();
}

function seed_demo_cocurricular_activity(int $themeId, string $phase, string $title, string $description, string $objective): void
{
    if (!fetch_one('SELECT id FROM cocurricular_activities WHERE theme_id = ? AND phase = ? AND title = ?', [$themeId, $phase, $title])) {
        execute_sql('INSERT INTO cocurricular_activities (theme_id, phase, title, description, objective, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)', [$themeId, $phase, $title, $description, $objective, now_string()]);
    }
}

function seed_demo_cocurricular_group(string $name, string $grade, string $phase, int $themeId, string $className): void
{
    $teacher = fetch_one('SELECT homeroom_teacher_id FROM classes WHERE name = ?', [$className]);
    $row = fetch_one('SELECT id FROM cocurricular_groups WHERE name = ?', [$name]);
    if ($row) {
        $groupId = (int)$row['id'];
        execute_sql('UPDATE cocurricular_groups SET grade = ?, phase = ?, theme_id = ?, coordinator_teacher_id = COALESCE(coordinator_teacher_id, ?), active = 1, updated_at = ? WHERE id = ?', [$grade, $phase, $themeId, (int)($teacher['homeroom_teacher_id'] ?? 0) ?: null, now_string(), $groupId]);
    } else {
        execute_sql('INSERT INTO cocurricular_groups (name, grade, phase, theme_id, coordinator_teacher_id, active, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?)', [$name, $grade, $phase, $themeId, (int)($teacher['homeroom_teacher_id'] ?? 0) ?: null, now_string()]);
        $groupId = (int)db()->lastInsertId();
    }
    $students = fetch_all('SELECT id FROM students WHERE class_id = (SELECT id FROM classes WHERE name = ? LIMIT 1) AND active = 1', [$className]);
    foreach ($students as $student) {
        if (!fetch_one('SELECT id FROM cocurricular_members WHERE group_id = ? AND student_id = ?', [$groupId, (int)$student['id']])) {
            execute_sql('INSERT INTO cocurricular_members (group_id, student_id) VALUES (?, ?)', [$groupId, (int)$student['id']]);
        }
    }
}

function seed_demo_asset_files(): void
{
    seed_demo_asset_file('storage/uploads/signatures/dummy-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#0b63f6"/><circle cx="80" cy="62" r="32" fill="#ffffff"/><path d="M32 132h96L80 86z" fill="#ffffff"/></svg>');
    seed_demo_asset_file('storage/uploads/signatures/dummy-logo-dinas.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#16a34a"/><path d="M80 24l48 24v34c0 34-20 56-48 70-28-14-48-36-48-70V48z" fill="#ffffff"/><path d="M54 82h52v12H54zm10-22h32v12H64zm0 44h32v12H64z" fill="#16a34a"/></svg>');
    seed_demo_asset_file('storage/uploads/signatures/dummy-ttd-kepsek.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="260" height="90"><path d="M20 58c40-45 67 31 107-5 26-23 44-22 72 0 18 14 31 13 43-4" fill="none" stroke="#111827" stroke-width="4"/><text x="28" y="82" font-family="Arial" font-size="14">TTD Kepsek</text></svg>');
    seed_demo_asset_file('storage/uploads/signatures/dummy-ttd-wali.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="260" height="90"><path d="M20 58c35-38 54 28 88-2 22-20 40-18 64 1 20 16 41 9 63-14" fill="none" stroke="#111827" stroke-width="4"/><text x="28" y="82" font-family="Arial" font-size="14">TTD Wali</text></svg>');
    seed_demo_asset_file('storage/uploads/student-photos/dummy-student.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="200"><rect width="160" height="200" fill="#e0f2fe"/><circle cx="80" cy="68" r="34" fill="#38bdf8"/><path d="M28 182c8-45 38-68 52-68s44 23 52 68z" fill="#0284c7"/></svg>');
}

function seed_demo_asset_file(string $relativePath, string $content): void
{
    $path = dirname(__DIR__) . '/' . $relativePath;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_file($path)) {
        file_put_contents($path, $content);
    }
}

function seed_demo_signature(string $type, ?int $userId, string $title, string $personName, string $nip, string $filePath): void
{
    $row = fetch_one('SELECT id FROM signatures WHERE type = ? AND person_name = ? ORDER BY id LIMIT 1', [$type, $personName]);
    if ($row) {
        execute_sql('UPDATE signatures SET user_id = COALESCE(user_id, ?), title = ?, nip = COALESCE(NULLIF(nip, \'\'), ?), file_path = COALESCE(NULLIF(file_path, \'\'), ?), updated_at = ? WHERE id = ?', [$userId, $title, $nip, $filePath, now_string(), (int)$row['id']]);
        return;
    }
    execute_sql('INSERT INTO signatures (type, user_id, title, person_name, nip, file_path, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$type, $userId, $title, $personName, $nip, $filePath, now_string()]);
}

function seed_demo_student_attendance_session(int $assignmentId, int $classId, string $date, int $meetingNo, string $topic): void
{
    $session = fetch_one('SELECT id FROM student_attendance_sessions WHERE assignment_id = ? AND date = ? AND meeting_no = ?', [$assignmentId, $date, $meetingNo]);
    if ($session) {
        $sessionId = (int)$session['id'];
    } else {
        execute_sql('INSERT INTO student_attendance_sessions (assignment_id, date, meeting_no, topic, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$assignmentId, $date, $meetingNo, $topic, 1, now_string()]);
        $sessionId = (int)db()->lastInsertId();
    }
    $statuses = ['hadir', 'hadir', 'sakit', 'izin', 'alpa'];
    foreach (fetch_all('SELECT id FROM students WHERE class_id = ? AND active = 1 ORDER BY id', [$classId]) as $index => $student) {
        $status = $statuses[$index % count($statuses)];
        if (!fetch_one('SELECT id FROM student_attendance_entries WHERE session_id = ? AND student_id = ?', [$sessionId, (int)$student['id']])) {
            execute_sql('INSERT INTO student_attendance_entries (session_id, student_id, status, notes, updated_at) VALUES (?, ?, ?, ?, ?)', [$sessionId, (int)$student['id'], $status, $status === 'hadir' ? '' : 'Data dummy absensi', now_string()]);
        }
    }
}

function seed_demo_teacher_attendance(int $teacherId, string $date): void
{
    if (!fetch_one('SELECT id FROM teacher_attendance WHERE teacher_id = ? AND date = ?', [$teacherId, $date])) {
        execute_sql('INSERT INTO teacher_attendance (teacher_id, date, status, time_in, time_out, notes, recorded_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$teacherId, $date, 'hadir', '07:00', '14:00', 'Data dummy report admin', 1, now_string()]);
    }
}

function seed_demo_journal(int $assignmentId, int $teacherId, int $classId, int $subjectId, string $subjectName): void
{
    if (!fetch_one('SELECT id FROM daily_journals WHERE assignment_id = ? AND date = ? AND meeting_no = ?', [$assignmentId, '2026-06-03', 1])) {
        execute_sql(
            'INSERT INTO daily_journals (assignment_id, teacher_id, class_id, subject_id, date, meeting_no, topic, activities, materials, obstacles, follow_up, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$assignmentId, $teacherId, $classId, $subjectId, '2026-06-03', 1, 'Pembelajaran ' . $subjectName, 'Apersepsi, diskusi, latihan terarah, dan refleksi.', 'Buku siswa dan lembar kerja.', 'Sebagian siswa perlu pendampingan membaca instruksi.', 'Guru memberi penguatan dan latihan tambahan.', 1, now_string()]
        );
    }
}

function migrate_align_fk_column_types(): void
{
    if (db_driver() !== 'mysql') {
        return;
    }
    $fixes = [
        ['students', 'class_id', 'INT UNSIGNED NULL'],
        ['teaching_assignments', 'teacher_id', 'INT UNSIGNED NOT NULL'],
        ['teaching_assignments', 'class_id', 'INT UNSIGNED NOT NULL'],
        ['teaching_assignments', 'subject_id', 'INT UNSIGNED NOT NULL'],
        ['grades', 'assignment_id', 'INT UNSIGNED NOT NULL'],
        ['grades', 'student_id', 'INT UNSIGNED NOT NULL'],
        ['student_attendance_sessions', 'assignment_id', 'INT UNSIGNED NOT NULL'],
        ['student_attendance_entries', 'session_id', 'INT UNSIGNED NOT NULL'],
        ['student_attendance_entries', 'student_id', 'INT UNSIGNED NOT NULL'],
        ['daily_journals', 'assignment_id', 'INT UNSIGNED NOT NULL'],
        ['lesson_schedules', 'assignment_id', 'INT UNSIGNED NOT NULL'],
        ['lesson_schedules', 'teacher_id', 'INT UNSIGNED NOT NULL'],
        ['lesson_schedules', 'class_id', 'INT UNSIGNED NOT NULL'],
        ['lesson_schedules', 'subject_id', 'INT UNSIGNED NOT NULL'],
        ['teacher_teaching_attendance', 'assignment_id', 'INT UNSIGNED NULL'],
        ['teacher_teaching_attendance', 'teacher_id', 'INT UNSIGNED NOT NULL'],
        ['student_violations', 'student_id', 'INT UNSIGNED NOT NULL'],
        ['final_scores', 'student_id', 'INT UNSIGNED NOT NULL'],
        ['final_scores', 'subject_id', 'INT UNSIGNED NOT NULL'],
        ['whatsapp_guardians', 'student_id', 'INT UNSIGNED NOT NULL'],
    ];
    foreach ($fixes as [$table, $column, $definition]) {
        if (!table_exists($table) || !table_column_exists($table, $column)) {
            continue;
        }
        try {
            $fkName = 'fk_' . $table . '_' . $column;
            try {
                db()->exec(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', db_identifier($table), db_identifier($fkName)));
            } catch (PDOException $e2) {
                // FK may not exist yet
            }
            db()->exec(sprintf('ALTER TABLE %s MODIFY %s %s', db_identifier($table), db_identifier($column), $definition));
        } catch (PDOException $e) {
            // ignore
        }
    }
}

function migrate_foreign_keys(): int
{
    if (db_driver() !== 'mysql') {
        return 0;
    }

    migrate_align_fk_column_types();

    $fks = [
        'students' => [
            ['columns' => ['class_id'], 'ref' => 'classes(id)', 'delete' => 'SET NULL'],
        ],
        'teaching_assignments' => [
            ['columns' => ['teacher_id'], 'ref' => 'teachers(id)', 'delete' => 'CASCADE'],
            ['columns' => ['class_id'], 'ref' => 'classes(id)', 'delete' => 'CASCADE'],
            ['columns' => ['subject_id'], 'ref' => 'subjects(id)', 'delete' => 'CASCADE'],
        ],
        'grades' => [
            ['columns' => ['assignment_id'], 'ref' => 'teaching_assignments(id)', 'delete' => 'CASCADE'],
            ['columns' => ['student_id'], 'ref' => 'students(id)', 'delete' => 'CASCADE'],
        ],
        'student_attendance_sessions' => [
            ['columns' => ['assignment_id'], 'ref' => 'teaching_assignments(id)', 'delete' => 'CASCADE'],
        ],
        'student_attendance_entries' => [
            ['columns' => ['session_id'], 'ref' => 'student_attendance_sessions(id)', 'delete' => 'CASCADE'],
            ['columns' => ['student_id'], 'ref' => 'students(id)', 'delete' => 'CASCADE'],
        ],
        'daily_journals' => [
            ['columns' => ['assignment_id'], 'ref' => 'teaching_assignments(id)', 'delete' => 'CASCADE'],
        ],
        'lesson_schedules' => [
            ['columns' => ['assignment_id'], 'ref' => 'teaching_assignments(id)', 'delete' => 'CASCADE'],
            ['columns' => ['teacher_id'], 'ref' => 'teachers(id)', 'delete' => 'CASCADE'],
            ['columns' => ['class_id'], 'ref' => 'classes(id)', 'delete' => 'CASCADE'],
            ['columns' => ['subject_id'], 'ref' => 'subjects(id)', 'delete' => 'CASCADE'],
        ],
        'teacher_teaching_attendance' => [
            ['columns' => ['assignment_id'], 'ref' => 'teaching_assignments(id)', 'delete' => 'CASCADE'],
            ['columns' => ['teacher_id'], 'ref' => 'teachers(id)', 'delete' => 'CASCADE'],
        ],
        'student_violations' => [
            ['columns' => ['student_id'], 'ref' => 'students(id)', 'delete' => 'CASCADE'],
        ],
        'final_scores' => [
            ['columns' => ['student_id'], 'ref' => 'students(id)', 'delete' => 'CASCADE'],
            ['columns' => ['subject_id'], 'ref' => 'subjects(id)', 'delete' => 'CASCADE'],
        ],
        'whatsapp_guardians' => [
            ['columns' => ['student_id'], 'ref' => 'students(id)', 'delete' => 'CASCADE'],
        ],
    ];

    $added = 0;
    foreach ($fks as $table => $constraints) {
        if (!table_exists($table)) {
            continue;
        }
        foreach ($constraints as $i => $fk) {
            $colSql = implode('_', $fk['columns']);
            $fkName = 'fk_' . $table . '_' . $colSql;
            try {
                db()->exec(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s ON DELETE %s ON UPDATE CASCADE',
                    db_identifier($table),
                    db_identifier($fkName),
                    implode(', ', array_map('db_identifier', $fk['columns'])),
                    $fk['ref'],
                    $fk['delete']
                ));
                $added++;
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        }
    }
    return $added;
}
