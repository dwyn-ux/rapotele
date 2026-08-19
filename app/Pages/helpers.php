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
    return $active
        ? '<span class="badge ok"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Aktif</span>'
        : '<span class="badge off"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Nonaktif</span>';
}

function row_actions(string $page, int $id, string $deleteAction): string
{
    if (!is_admin()) {
        return '<span class="hint">-</span>';
    }
    return '<div class="row-actions">'
        . '<a class="button small" href="' . e(route_url($page, ['edit' => $id])) . '">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit</a>'
        . '<form method="post" onsubmit="return confirm(\'Hapus data ini? Aksi ini tidak dapat dibatalkan.\')">' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($deleteAction) . '"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<button class="button small danger"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Hapus</button></form></div>';
}

function table_panel(string $title, array $headers, array $rows, callable $renderer, string $actions = '', bool $search = false): void
{
    ?>
    <section class="panel table-panel">
        <?php panel_title($title, '', $actions); ?>
        <?php if ($search): ?>
        <div class="table-search">
            <div style="position:relative;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="table-search-input" placeholder="Cari data..." style="padding-left:40px;">
            </div>
        </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table>
                <thead><tr><?php foreach ($headers as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= e(count($headers)) ?>" class="empty"><div class="empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><p>Belum ada data.</p></div></td></tr>
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

/**
 * Build cascading data JSON from assignments.
 * Returns structure: { classId: { name: "Kelas 7A", subjects: { subjectId: { name: "Matematika", teachers: [{id, name}] } } } }
 */
function assignment_cascading_data(array $assignments): array
{
    $data = [];
    foreach ($assignments as $a) {
        $cid = (string)$a['class_id'];
        $sid = (string)$a['subject_id'];
        if (!isset($data[$cid])) {
            $data[$cid] = ['name' => $a['class_name'], 'subjects' => []];
        }
        if (!isset($data[$cid]['subjects'][$sid])) {
            $data[$cid]['subjects'][$sid] = ['name' => $a['subject_name'], 'teachers' => []];
        }
        $data[$cid]['subjects'][$sid]['teachers'][] = [
            'id' => (int)$a['id'],
            'name' => $a['teacher_name'],
        ];
    }
    return $data;
}

/**
 * Determine pre-selected class/subject from a selected assignment_id.
 */
function assignment_preselect(array $assignments, int $selected): array
{
    if ($selected <= 0) {
        return [0, 0, 0];
    }
    foreach ($assignments as $a) {
        if ((int)$a['id'] === $selected) {
            return [(int)$a['class_id'], (int)$a['subject_id'], (int)$a['id']];
        }
    }
    return [0, 0, 0];
}

/**
 * Cascading assignment picker: Kelas → Mapel → auto-submit
 * Used by page_grades and other pages with a standalone picker section.
 */
function assignment_picker(string $page, array $assignments, int $selected): void
{
    $data = assignment_cascading_data($assignments);
    [$preClass, $preSubject, $preTeacher] = assignment_preselect($assignments, $selected);
    $pickerId = 'picker-' . $page;
    ?>
    <section class="panel">
        <form method="get" id="<?= e($pickerId) ?>" class="grid four">
            <input type="hidden" name="page" value="<?= e($page) ?>">
            <input type="hidden" name="assignment_id" id="<?= e($pickerId) ?>-assignment" value="<?= e($selected ?: '') ?>">
            <label>Kelas
                <select id="<?= e($pickerId) ?>-class" data-cascade-class="<?= e($pickerId) ?>">
                    <option value="">Pilih Kelas</option>
                </select>
            </label>
            <label>Mapel
                <select id="<?= e($pickerId) ?>-subject" data-cascade-subject="<?= e($pickerId) ?>" disabled>
                    <option value="">Pilih Mapel</option>
                </select>
            </label>
            <div class="actions" style="align-self:end">
                <button type="submit" class="button primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Tampilkan
                </button>
            </div>
        </form>
    </section>
    <script>
    (function(){
        var data = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
        var pickerId = <?= json_encode($pickerId) ?>;
        var preClass = <?= json_encode($preClass) ?>;
        var preSubject = <?= json_encode($preSubject) ?>;
        var preAssignment = <?= json_encode($preSubject ? $selected : 0) ?>;
        var classEl = document.getElementById(pickerId + '-class');
        var subjectEl = document.getElementById(pickerId + '-subject');
        var assignmentEl = document.getElementById(pickerId + '-assignment');

        /* Populate class dropdown */
        Object.keys(data).sort(function(a,b){ return data[a].name.localeCompare(data[b].name); }).forEach(function(cid){
            var opt = document.createElement('option');
            opt.value = cid;
            opt.textContent = data[cid].name;
            classEl.appendChild(opt);
        });

        /* If pre-selected, set class */
        if (preClass && data[preClass]) {
            classEl.value = preClass;
        }

        function updateSubjects() {
            var cid = classEl.value;
            subjectEl.innerHTML = '<option value="">Pilih Mapel</option>';
            assignmentEl.value = '';
            if (!cid || !data[cid]) {
                subjectEl.disabled = true;
                return;
            }
            subjectEl.disabled = false;
            var subjects = data[cid].subjects;
            Object.keys(subjects).sort(function(a,b){ return subjects[a].name.localeCompare(subjects[b].name); }).forEach(function(sid){
                var opt = document.createElement('option');
                opt.value = sid;
                opt.textContent = subjects[sid].name;
                subjectEl.appendChild(opt);
            });
            /* If pre-selected, set subject */
            if (preSubject && subjects[preSubject]) {
                subjectEl.value = preSubject;
            } else {
                /* Auto-select if only one subject */
                var keys = Object.keys(subjects);
                if (keys.length === 1) {
                    subjectEl.value = keys[0];
                }
            }
            selectAssignment();
        }

        function selectAssignment() {
            var cid = classEl.value;
            var sid = subjectEl.value;
            if (!cid || !sid || !data[cid] || !data[cid].subjects[sid]) {
                assignmentEl.value = '';
                return;
            }
            var teachers = data[cid].subjects[sid].teachers;
            if (teachers.length === 1) {
                assignmentEl.value = teachers[0].id;
            } else if (preAssignment && teachers.some(function(t){ return t.id === preAssignment; })) {
                assignmentEl.value = preAssignment;
            } else {
                assignmentEl.value = teachers[0].id;
            }
        }

        classEl.addEventListener('change', function(){
            preSubject = 0;
            preAssignment = 0;
            updateSubjects();
        });
        subjectEl.addEventListener('change', function(){
            preAssignment = 0;
            selectAssignment();
        });

        updateSubjects();
    })();
    </script>
    <?php
}

function render_cascading_assignment_selects(array $assignments, int $selected, string $pickerId): void
{
    $data = assignment_cascading_data($assignments);
    [$preClass, $preSubject] = assignment_preselect($assignments, $selected);
    ?>
    <div class="cascade-assignment" id="<?= e($pickerId) ?>">
        <input type="hidden" name="assignment_id" id="<?= e($pickerId) ?>-assignment" value="<?= e($selected ?: '') ?>">
        <label>Kelas
            <select id="<?= e($pickerId) ?>-class" data-cascade-inline="<?= e($pickerId) ?>">
                <option value="">Pilih Kelas</option>
            </select>
        </label>
        <label>Mapel
            <select id="<?= e($pickerId) ?>-subject" data-cascade-inline-subject="<?= e($pickerId) ?>" disabled>
                <option value="">Pilih Mapel</option>
            </select>
        </label>
    </div>
    <script>
    (function(){
        var data = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
        var pickerId = <?= json_encode($pickerId) ?>;
        var preClass = <?= json_encode($preClass) ?>;
        var preSubject = <?= json_encode($preSubject) ?>;
        var classEl = document.getElementById(pickerId + '-class');
        var subjectEl = document.getElementById(pickerId + '-subject');
        var assignmentEl = document.getElementById(pickerId + '-assignment');
        Object.keys(data).sort(function(a,b){ return data[a].name.localeCompare(data[b].name); }).forEach(function(cid){
            var opt = document.createElement('option');
            opt.value = cid; opt.textContent = data[cid].name;
            classEl.appendChild(opt);
        });
        if (preClass && data[preClass]) classEl.value = preClass;
        function updateSubjects() {
            var cid = classEl.value;
            subjectEl.innerHTML = '<option value="">Pilih Mapel</option>';
            assignmentEl.value = '';
            if (!cid || !data[cid]) { subjectEl.disabled = true; return; }
            subjectEl.disabled = false;
            var subjects = data[cid].subjects;
            Object.keys(subjects).sort(function(a,b){ return subjects[a].name.localeCompare(subjects[b].name); }).forEach(function(sid){
                var opt = document.createElement('option');
                opt.value = sid; opt.textContent = subjects[sid].name;
                subjectEl.appendChild(opt);
            });
            if (preSubject && subjects[preSubject]) {
                subjectEl.value = preSubject;
            } else {
                var keys = Object.keys(subjects);
                if (keys.length === 1) subjectEl.value = keys[0];
            }
            selectAssignment();
        }
        function selectAssignment() {
            var cid = classEl.value, sid = subjectEl.value;
            if (!cid || !sid || !data[cid] || !data[cid].subjects[sid]) { assignmentEl.value = ''; return; }
            var teachers = data[cid].subjects[sid].teachers;
            assignmentEl.value = teachers[0].id;
        }
        classEl.addEventListener('change', function(){ preSubject = 0; updateSubjects(); });
        subjectEl.addEventListener('change', function(){ selectAssignment(); });
        updateSubjects();
    })();
    </script>
    <?php
}

function render_file_upload(string $name, string $accept, string $label = 'Pilih file', bool $required = false, string $hint = ''): void
{
    $req = $required ? ' <span style="color:var(--danger,#ef4444)">*</span>' : '';
    $acceptLabel = str_replace(['.', 'image/', 'application/', 'text/'], ['', '', '', ''], $accept);
    $acceptUpper = strtoupper(str_replace(',', ' / ', $acceptLabel));
    ?>
    <div class="file-upload-wrap">
        <label class="file-upload-label"><?= $label . $req ?></label>
        <div class="file-upload-box" id="fub-<?= e($name) ?>">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--primary,#6366f1)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div class="file-upload-text">Seret & lepas file ke sini atau <span class="file-upload-browse">cari file</span></div>
            <div class="file-upload-accept">Format: <?= e($acceptUpper) ?></div>
            <input type="file" name="<?= e($name) ?>" accept="<?= e($accept) ?>" <?= $required ? 'required' : '' ?> class="file-upload-input" data-target="fub-<?= e($name) ?>">
            <div class="file-upload-filename"></div>
        </div>
        <?php if ($hint !== ''): ?>
            <p class="file-upload-hint"><?= $hint ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function render_file_upload_inline(string $name, string $accept, bool $required = false, string $filename = ''): void
{
    ?>
    <div class="file-upload-inline">
        <label class="file-upload-inline-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Pilih File
            <input type="file" name="<?= e($name) ?>" accept="<?= e($accept) ?>" <?= $required ? 'required' : '' ?> class="file-upload-input">
        </label>
        <span class="file-upload-inline-filename"><?= e($filename ?: 'Belum ada file dipilih') ?></span>
    </div>
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
