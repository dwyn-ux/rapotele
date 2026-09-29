<?php

declare(strict_types=1);

function test_fixture_teacher(string $name = 'Guru Fixture'): int
{
    $row = fetch_one('SELECT id FROM teachers WHERE name = ? LIMIT 1', [$name]);
    if ($row) {
        return (int)$row['id'];
    }
    execute_sql('INSERT INTO teachers (name, active, created_at, updated_at) VALUES (?, 1, ?, ?)', [$name, now_string(), now_string()]);

    return (int)db()->lastInsertId();
}

function test_fixture_class(int $teacherId, string $name = '1A Fixture', string $grade = '7', string $level = '7'): int
{
    $row = fetch_one('SELECT id FROM classes WHERE name = ? LIMIT 1', [$name]);
    if ($row) {
        return (int)$row['id'];
    }
    execute_sql(
        'INSERT INTO classes (name, grade, level, homeroom_teacher_id, academic_year, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
        [$name, $grade, $level, $teacherId, (string)config('school.academic_year', '2025/2026'), now_string(), now_string()]
    );

    return (int)db()->lastInsertId();
}

function test_fixture_subject(string $name = 'Matematika Fixture', string $shortName = 'MTKF', string $level = 'SMP'): int
{
    $row = fetch_one('SELECT id FROM subjects WHERE short_name = ? LIMIT 1', [$shortName]);
    if ($row) {
        return (int)$row['id'];
    }
    execute_sql(
        'INSERT INTO subjects (name, short_name, group_name, level, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
        [$name, $shortName, 'Wajib', $level, now_string(), now_string()]
    );

    return (int)db()->lastInsertId();
}

function test_fixture_admin(int $teacherId): int
{
    $row = fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', ['admin_fixture']);
    if ($row) {
        return (int)$row['id'];
    }
    execute_sql(
        'INSERT INTO users (username, password_hash, name, email, role, teacher_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)',
        ['admin_fixture', password_hash('fixture', PASSWORD_BCRYPT), 'Admin Fixture', 'admin@fixture.local', 'admin', $teacherId, now_string(), now_string()]
    );

    return (int)db()->lastInsertId();
}
