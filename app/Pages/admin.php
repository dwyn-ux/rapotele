<?php declare(strict_types=1);

function page_bulk_delete(): void
{
    require_role(['admin']);
    render_header('Hapus Data Massal');

    $counts = [
        'students'           => (int)(fetch_one('SELECT COUNT(*) AS c FROM students')['c'] ?? 0),
        'teachers'           => (int)(fetch_one('SELECT COUNT(*) AS c FROM teachers')['c'] ?? 0),
        'classes'            => (int)(fetch_one('SELECT COUNT(*) AS c FROM classes')['c'] ?? 0),
        'subjects'           => (int)(fetch_one('SELECT COUNT(*) AS c FROM subjects')['c'] ?? 0),
        'assignments'        => (int)(fetch_one('SELECT COUNT(*) AS c FROM teaching_assignments')['c'] ?? 0),
        'schedules'          => (int)(fetch_one('SELECT COUNT(*) AS c FROM lesson_schedules')['c'] ?? 0),
        'grades'             => (int)(fetch_one('SELECT COUNT(*) AS c FROM grades')['c'] ?? 0),
        'final_scores'       => (int)(fetch_one('SELECT COUNT(*) AS c FROM final_scores')['c'] ?? 0),
        'attendance_student' => (int)(fetch_one('SELECT COUNT(*) AS c FROM student_attendance_entries')['c'] ?? 0),
        'attendance_teacher' => (int)((int)(fetch_one('SELECT COUNT(*) AS c FROM teacher_attendance')['c'] ?? 0) + (int)(fetch_one('SELECT COUNT(*) AS c FROM teacher_teaching_attendance')['c'] ?? 0)),
        'violations'         => (int)(fetch_one('SELECT COUNT(*) AS c FROM student_violations')['c'] ?? 0),
        'journals'           => (int)(fetch_one('SELECT COUNT(*) AS c FROM daily_journals')['c'] ?? 0),
        'extracurriculars'   => (int)(fetch_one('SELECT COUNT(*) AS c FROM extracurriculars')['c'] ?? 0),
        'users'              => (int)(fetch_one('SELECT COUNT(*) AS c FROM users WHERE role != ?', ['admin'])['c'] ?? 0),
    ];

    $scheduleByDay = [];
    for ($d = 1; $d <= 6; $d++) {
        $scheduleByDay[$d] = (int)(fetch_one('SELECT COUNT(*) AS c FROM lesson_schedules WHERE day_of_week = ?', [$d])['c'] ?? 0);
    }

    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
    ?>
    <div class="alert warning" style="margin-bottom:1.25rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span><strong>Peringatan:</strong> Semua operasi hapus bersifat permanen dan tidak dapat dibatalkan. Pastikan sudah membuat <a href="<?= e(route_url('backup-restore')) ?>">backup</a> sebelum melakukan hapus massal.</span>
    </div>

    <div class="grid three">

        <section class="panel">
            <?php panel_title('Data Siswa', ''); ?>
            <p>Total: <strong><?= $counts['students'] ?> siswa</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua siswa beserta nilai, absensi, pelanggaran, foto, kelulusan, dan akun login siswa.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data siswa (<?= $counts['students'] ?> siswa)?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="students">
                <button type="submit" class="button danger" <?= $counts['students'] === 0 ? 'disabled' : '' ?>>Hapus Semua Siswa</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Data Guru', ''); ?>
            <p>Total: <strong><?= $counts['teachers'] ?> guru</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua guru beserta data absensi dan akun login guru (non-admin).</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data guru (<?= $counts['teachers'] ?> guru)?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="teachers">
                <button type="submit" class="button danger" <?= $counts['teachers'] === 0 ? 'disabled' : '' ?>>Hapus Semua Guru</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Data Kelas', ''); ?>
            <p>Total: <strong><?= $counts['classes'] ?> kelas</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua data kelas.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data kelas?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="classes">
                <button type="submit" class="button danger" <?= $counts['classes'] === 0 ? 'disabled' : '' ?>>Hapus Semua Kelas</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Data Mapel', ''); ?>
            <p>Total: <strong><?= $counts['subjects'] ?> mata pelajaran</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua mapel beserta mapping rapor dan gabungan mapel.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data mapel (<?= $counts['subjects'] ?> mapel)?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="subjects">
                <button type="submit" class="button danger" <?= $counts['subjects'] === 0 ? 'disabled' : '' ?>>Hapus Semua Mapel</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Data Pembelajaran', ''); ?>
            <p>Total: <strong><?= $counts['assignments'] ?> pembelajaran</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua pembelajaran beserta jadwal, jurnal, absensi, dan nilai terkait.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data pembelajaran (<?= $counts['assignments'] ?>)?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="assignments">
                <button type="submit" class="button danger" <?= $counts['assignments'] === 0 ? 'disabled' : '' ?>>Hapus Semua Pembelajaran</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Jadwal Pelajaran', ''); ?>
            <p>Total: <strong><?= $counts['schedules'] ?> jadwal</strong></p>
            <div style="margin:.5rem 0 .75rem;display:flex;flex-wrap:wrap;gap:.25rem;">
                <?php for ($d = 1; $d <= 6; $d++): ?>
                    <span style="padding:.2rem .45rem;border-radius:4px;background:var(--surface-secondary,#f1f5f9);font-size:.8rem;"><?= e($dayNames[$d]) ?>: <strong><?= $scheduleByDay[$d] ?></strong></span>
                <?php endfor; ?>
            </div>
            <form method="post" id="form-del-schedule" onsubmit="return confirmSchedDelete(this)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="schedules">
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                    <select name="day_of_week" id="sched-day" onchange="updateSchedBtn(this)" style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-size:.875rem;">
                        <option value="all">Semua Hari</option>
                        <?php for ($d = 1; $d <= 6; $d++): ?>
                            <option value="<?= $d ?>"><?= e($dayNames[$d]) ?> (<?= $scheduleByDay[$d] ?>)</option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" id="btn-del-sched" class="button danger" <?= $counts['schedules'] === 0 ? 'disabled' : '' ?>>Hapus Semua Jadwal</button>
                </div>
            </form>
            <script>
            function updateSchedBtn(sel) {
                var b = document.getElementById('btn-del-sched');
                b.textContent = sel.value === 'all' ? 'Hapus Semua Jadwal' : 'Hapus Jadwal ' + sel.options[sel.selectedIndex].text.split(' (')[0];
            }
            function confirmSchedDelete() {
                var sel = document.getElementById('sched-day');
                var label = sel.value === 'all' ? 'SEMUA jadwal' : 'jadwal hari ' + sel.options[sel.selectedIndex].text.split(' (')[0];
                return confirm('Yakin hapus ' + label + '?\nTindakan ini tidak dapat dibatalkan!');
            }
            </script>
        </section>

        <section class="panel">
            <?php panel_title('Nilai', ''); ?>
            <p>Nilai mapel: <strong><?= $counts['grades'] ?></strong></p>
            <p>Nilai akhir (SKL): <strong><?= $counts['final_scores'] ?></strong></p>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA nilai mapel?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="grades">
                    <button type="submit" class="button danger" <?= $counts['grades'] === 0 ? 'disabled' : '' ?>>Hapus Nilai Mapel</button>
                </form>
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA nilai akhir (SKL)?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="final_scores">
                    <button type="submit" class="button danger" <?= $counts['final_scores'] === 0 ? 'disabled' : '' ?>>Hapus Nilai Akhir</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <?php panel_title('Absensi', ''); ?>
            <p>Absensi siswa: <strong><?= $counts['attendance_student'] ?></strong></p>
            <p>Absensi guru: <strong><?= $counts['attendance_teacher'] ?></strong></p>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA absensi siswa?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="attendance_student">
                    <button type="submit" class="button danger" <?= $counts['attendance_student'] === 0 ? 'disabled' : '' ?>>Hapus Absensi Siswa</button>
                </form>
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA absensi guru?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="attendance_teacher">
                    <button type="submit" class="button danger" <?= $counts['attendance_teacher'] === 0 ? 'disabled' : '' ?>>Hapus Absensi Guru</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <?php panel_title('Pelanggaran Siswa', ''); ?>
            <p>Total: <strong><?= $counts['violations'] ?> pelanggaran</strong></p>
            <div style="margin-top:1rem;">
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA pelanggaran siswa?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="violations">
                    <button type="submit" class="button danger" <?= $counts['violations'] === 0 ? 'disabled' : '' ?>>Hapus Semua Pelanggaran</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <?php panel_title('Jurnal Harian', ''); ?>
            <p>Total: <strong><?= $counts['journals'] ?> jurnal</strong></p>
            <div style="margin-top:1rem;">
                <form method="post" onsubmit="return confirm('Yakin hapus SEMUA jurnal harian?\nTindakan ini tidak dapat dibatalkan!')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="target" value="journals">
                    <button type="submit" class="button danger" <?= $counts['journals'] === 0 ? 'disabled' : '' ?>>Hapus Semua Jurnal</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <?php panel_title('Ekstrakurikuler', ''); ?>
            <p>Total: <strong><?= $counts['extracurriculars'] ?> ekskul</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua ekskul beserta anggota dan nilai ekskul.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA data ekstrakurikuler?\nTindakan ini tidak dapat dibatalkan!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="extracurriculars">
                <button type="submit" class="button danger" <?= $counts['extracurriculars'] === 0 ? 'disabled' : '' ?>>Hapus Semua Ekskul</button>
            </form>
        </section>

        <section class="panel">
            <?php panel_title('Akun Pengguna (non-Admin)', ''); ?>
            <p>Total: <strong><?= $counts['users'] ?> pengguna</strong></p>
            <p style="color:var(--muted,#64748b);font-size:.875rem;margin:.5rem 0 1rem;">Menghapus semua akun kecuali admin. Guru/siswa tidak akan bisa login.</p>
            <form method="post" onsubmit="return confirm('Yakin hapus SEMUA akun pengguna (kecuali admin)?\nGuru dan siswa tidak akan bisa login setelah ini!')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="target" value="users">
                <button type="submit" class="button danger" <?= $counts['users'] === 0 ? 'disabled' : '' ?>>Hapus Semua Akun</button>
            </form>
        </section>

    </div>
    <?php
    render_footer();
}
