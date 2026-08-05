<?php declare(strict_types=1);

function options(array $options, mixed $selected): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $isSelected = (string)$value === (string)$selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e($label) . '</option>';
    }
    return $html;
}

function checked(mixed $value): string
{
    return (int)$value === 1 ? 'checked' : '';
}

function status_badge(int $active): string
{
    return $active ? '<span class="badge ok">Aktif</span>' : '<span class="badge off">Nonaktif</span>';
}

function row_actions(string $page, int $id, string $deleteAction): string
{
    if (!is_admin()) {
        return '<span class="hint">-</span>';
    }
    return '<div class="row-actions"><a class="button small" href="' . e(route_url($page, ['edit' => $id])) . '">Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus data ini?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($deleteAction) . '"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger">Hapus</button></form></div>';
}

function table_panel(string $title, array $headers, array $rows, callable $renderer, string $actions = ''): void
{
    ?>
    <section class="panel table-panel">
        <?php panel_title($title, '', $actions); ?>
        <div class="table-wrap">
            <table>
                <thead><tr><?php foreach ($headers as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= e(count($headers)) ?>" class="empty">Belum ada data.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr><?php $renderer($row); ?></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

function map_options(string $table, string $labelColumn): array
{
    $rows = fetch_all("SELECT id, $labelColumn AS label FROM $table WHERE active = 1 ORDER BY $labelColumn");
    return array_column_map($rows, 'id', 'label');
}

function array_column_map(array $rows, string $key, string $value): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[(string)$row[$key]] = (string)$row[$value];
    }
    return $out;
}

function assignment_rows(): array
{
    return fetch_all(
        'SELECT ta.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         ORDER BY c.grade, c.name, s.name'
    );
}

function assignments_for_current_user(): array
{
    $params = [];
    $where = 'ta.active = 1';
    if (!is_admin()) {
        $where .= ' AND ta.teacher_id = ?';
        $params[] = (int)(current_user()['teacher_id'] ?? 0);
    }
    return fetch_all(
        "SELECT ta.*, t.name AS teacher_name, c.name AS class_name, s.name AS subject_name
         FROM teaching_assignments ta
         JOIN teachers t ON t.id = ta.teacher_id
         JOIN classes c ON c.id = ta.class_id
         JOIN subjects s ON s.id = ta.subject_id
         WHERE $where
         ORDER BY c.grade, c.name, s.name",
        $params
    );
}

function assignment_options(array $assignments, mixed $selected): string
{
    $options = [];
    foreach ($assignments as $assignment) {
        $options[(string)$assignment['id']] = $assignment['class_name'] . ' - ' . $assignment['subject_name'] . ' - ' . $assignment['teacher_name'];
    }
    return options($options, $selected);
}

function assignment_picker(string $page, array $assignments, int $selected): void
{
    ?>
    <section class="panel">
        <form method="get" class="grid four">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <label>Pembelajaran <select name="assignment_id"><?= assignment_options($assignments, $selected) ?></select></label>
            <div class="actions"><button class="button">Tampilkan</button></div>
        </form>
    </section>
    <?php
}

function attendance_summary_for_student(int $studentId): array
{
    $rows = fetch_all('SELECT status, COUNT(*) AS total FROM student_attendance_entries WHERE student_id = ? GROUP BY status', [$studentId]);
    $summary = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'terlambat' => 0];
    foreach ($rows as $row) {
        $summary[$row['status']] = (int)$row['total'];
    }
    return $summary;
}
