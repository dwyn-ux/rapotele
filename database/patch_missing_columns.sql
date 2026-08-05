-- Patch: tambah kolom missing untuk database lama
-- Jalankan di phpMyAdmin sekali saja

-- school_profile
ALTER TABLE school_profile ADD COLUMN location_lat DECIMAL(10,8) NULL;
ALTER TABLE school_profile ADD COLUMN location_lng DECIMAL(11,8) NULL;
ALTER TABLE school_profile ADD COLUMN attendance_radius_meters INT NULL DEFAULT 500;

-- teacher_attendance
ALTER TABLE teacher_attendance ADD COLUMN location_lat DECIMAL(10,8) NULL;
ALTER TABLE teacher_attendance ADD COLUMN location_lng DECIMAL(11,8) NULL;

-- users
ALTER TABLE users ADD COLUMN student_id INT NULL;
ALTER TABLE users ADD COLUMN telegram_login_active TINYINT(1) NOT NULL DEFAULT 1;

-- teachers
ALTER TABLE teachers ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE teachers ADD COLUMN nip VARCHAR(64) NULL;
ALTER TABLE teachers ADD COLUMN nuptk VARCHAR(64) NULL;
ALTER TABLE teachers ADD COLUMN gender VARCHAR(16) NULL;
ALTER TABLE teachers ADD COLUMN position VARCHAR(120) NULL;
ALTER TABLE teachers ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE teachers ADD COLUMN updated_at DATETIME NULL;

-- classes
ALTER TABLE classes ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE classes ADD COLUMN grade VARCHAR(16) NULL;
ALTER TABLE classes ADD COLUMN major VARCHAR(80) NULL;
ALTER TABLE classes ADD COLUMN homeroom_teacher_id INT NULL;
ALTER TABLE classes ADD COLUMN academic_year VARCHAR(32) NULL;
ALTER TABLE classes ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE classes ADD COLUMN updated_at DATETIME NULL;

-- students
ALTER TABLE students ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE students ADD COLUMN nis VARCHAR(64) NULL;
ALTER TABLE students ADD COLUMN nisn VARCHAR(64) NULL;
ALTER TABLE students ADD COLUMN gender VARCHAR(16) NULL;
ALTER TABLE students ADD COLUMN birth_place VARCHAR(80) NULL;
ALTER TABLE students ADD COLUMN birth_date DATE NULL;
ALTER TABLE students ADD COLUMN religion VARCHAR(64) NULL;
ALTER TABLE students ADD COLUMN class_id INT NULL;
ALTER TABLE students ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE students ADD COLUMN updated_at DATETIME NULL;

-- subjects
ALTER TABLE subjects ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE subjects ADD COLUMN short_name VARCHAR(40) NULL;
ALTER TABLE subjects ADD COLUMN group_name VARCHAR(80) NULL;
ALTER TABLE subjects ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE subjects ADD COLUMN updated_at DATETIME NULL;

-- teaching_assignments
ALTER TABLE teaching_assignments ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE teaching_assignments ADD COLUMN academic_year VARCHAR(32) NULL;
ALTER TABLE teaching_assignments ADD COLUMN semester VARCHAR(32) NULL;
ALTER TABLE teaching_assignments ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE teaching_assignments ADD COLUMN updated_at DATETIME NULL;

-- extracurriculars
ALTER TABLE extracurriculars ADD COLUMN dapodik_id VARCHAR(64) NULL;
ALTER TABLE extracurriculars ADD COLUMN class_name VARCHAR(80) NULL;
ALTER TABLE extracurriculars ADD COLUMN type VARCHAR(80) NULL;
ALTER TABLE extracurriculars ADD COLUMN teacher_id INT NULL;
ALTER TABLE extracurriculars ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE extracurriculars ADD COLUMN updated_at DATETIME NULL;

-- extracurricular_members
ALTER TABLE extracurricular_members ADD COLUMN updated_at DATETIME NULL;
