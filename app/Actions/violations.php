<?php declare(strict_types=1);

function is_bk(): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (is_admin()) {
        return true;
    }
    if ((string)($user['role'] ?? '') !== 'guru') {
        return false;
    }
    $teacherId = (int)($user['teacher_id'] ?? 0);
    if ($teacherId <= 0) {
        return false;
    }
    $teacher = fetch_one('SELECT is_bk FROM teachers WHERE id = ?', [$teacherId]);
    return (bool)($teacher['is_bk'] ?? false);
}

function require_bk(): void
{
    require_login();
    if (!is_bk()) {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini hanya untuk Admin dan Guru BK.');
    }
}

function violation_net_points(int $studentId): array
{
    $violations = fetch_all('SELECT SUM(points) AS total FROM student_violations WHERE student_id = ?', [$studentId]);
    $totalPoints = (int)($violations[0]['total'] ?? 0);

    $rewards = fetch_all('SELECT discount_percent FROM student_rewards WHERE student_id = ?', [$studentId]);
    $totalDiscount = 0;
    foreach ($rewards as $r) {
        $totalDiscount += (int)$r['discount_percent'];
    }
    $totalDiscount = min($totalDiscount, 100);

    $deduction = (int)round($totalPoints * $totalDiscount / 100);
    $netPoints = max(0, $totalPoints - $deduction);

    return [
        'gross_points'    => $totalPoints,
        'discount_pct'    => $totalDiscount,
        'deduction'       => $deduction,
        'net_points'      => $netPoints,
    ];
}

function violation_sp_level(int $netPoints): ?array
{
    if (!table_exists('sp_thresholds')) {
        return null;
    }
    $thresholds = fetch_all('SELECT * FROM sp_thresholds ORDER BY min_points DESC');
    foreach ($thresholds as $t) {
        if ($netPoints >= (int)$t['min_points']) {
            return $t;
        }
    }
    return null;
}

function action_save_violation_rule(): void
{
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $code = trim((string)$_POST['code']);
    $category = trim((string)($_POST['category'] ?? 'Umum'));
    $description = trim((string)$_POST['description']);
    $points = (int)($_POST['points'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if ($code === '' || $description === '') {
        flash('danger', 'Kode dan deskripsi wajib diisi.');
        redirect_to('violation-rules');
    }

    if ($id > 0) {
        execute_sql('UPDATE violation_rules SET code = ?, category = ?, description = ?, points = ?, active = ?, updated_at = ? WHERE id = ?', [$code, $category, $description, $points, $active, now_string(), $id]);
    } else {
        execute_sql('INSERT INTO violation_rules (code, category, description, points, active, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$code, $category, $description, $points, $active, now_string()]);
    }
    flash('success', 'Pasal pelanggaran tersimpan.');
    redirect_to('violation-rules');
}

function action_delete_violation_rule(): void
{
    require_role(['admin']);
    execute_sql('DELETE FROM violation_rules WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('success', 'Pasal dihapus.');
    redirect_to('violation-rules');
}

function action_save_sp_threshold(): void
{
    require_role(['admin']);
    $levels = $_POST['levels'] ?? [];
    $labels = $_POST['labels'] ?? [];
    $minPoints = $_POST['min_points'] ?? [];

    foreach ($levels as $i => $level) {
        $level = (int)$level;
        $label = trim((string)($labels[$i] ?? ''));
        $points = (int)($minPoints[$i] ?? 0);
        if ($label === '' || $level < 1) {
            continue;
        }
        $existing = fetch_one('SELECT id FROM sp_thresholds WHERE level = ?', [$level]);
        if ($existing) {
            execute_sql('UPDATE sp_thresholds SET label = ?, min_points = ?, updated_at = ? WHERE level = ?', [$label, $points, now_string(), $level]);
        } else {
            execute_sql('INSERT INTO sp_thresholds (level, label, min_points, updated_at) VALUES (?, ?, ?, ?)', [$level, $label, $points, now_string()]);
        }
    }
    flash('success', 'Threshold SP disimpan.');
    redirect_to('violation-rules');
}

function action_save_reward(): void
{
    require_bk();
    $id = (int)($_POST['id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $date = trim((string)($_POST['date'] ?? date('Y-m-d')));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $discountPct = min(100, max(0, (int)($_POST['discount_percent'] ?? 0)));

    if (!$studentId || $title === '') {
        flash('danger', 'Siswa dan judul reward wajib diisi.');
        redirect_to('violations');
    }

    $data = [$studentId, $date, $title, $description, $discountPct, (int)current_user()['id'], now_string()];
    if ($id > 0) {
        execute_sql('UPDATE student_rewards SET student_id = ?, date = ?, title = ?, description = ?, discount_percent = ?, created_by = ?, updated_at = ? WHERE id = ?', array_merge($data, [$id]));
    } else {
        execute_sql('INSERT INTO student_rewards (student_id, date, title, description, discount_percent, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', $data);
    }
    flash('success', 'Reward siswa tersimpan.');
    redirect_to('violations');
}

function action_delete_reward(): void
{
    require_bk();
    execute_sql('DELETE FROM student_rewards WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('success', 'Reward dihapus.');
    redirect_to('violations');
}
