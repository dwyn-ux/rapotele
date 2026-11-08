import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/notification_service.dart';
import '../../models/notification_item.dart';
import '../../models/schedule_item.dart';
import '../../models/attendance_models.dart';
import '../teaching_wizard_screen.dart';
import 'notifications_tab.dart';

class DashboardTab extends StatefulWidget {
  final Function(int tabIndex, {int? subTab}) onTabChange;

  const DashboardTab({super.key, required this.onTabChange});

  @override
  State<DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<DashboardTab> {
  bool _isLoading = true;
  ScheduleItem? _nextSchedule;
  TeacherAttendanceModel? _teacherAttendance;
  List<NotificationItem> _recentNotifications = [];

  @override
  void initState() {
    super.initState();
    _loadDashboardData();
  }

  Future<void> _loadDashboardData() async {
    setState(() => _isLoading = true);

    try {
      final notifsFuture = NotificationService().checkAndTriggerNotifications();
      final schedFuture = ApiService().getMySchedule();
      final attFuture = ApiService().getTeacherAttendance();

      final notifs = await notifsFuture;
      final schedData = await schedFuture;
      final attData = await attFuture;

      ScheduleItem? next;
      if (schedData['success'] == true) {
        final todayList = (schedData['today'] as List<dynamic>?) ?? [];
        final items = todayList.whereType<ScheduleItem>().toList();
        final nextUpcoming = schedData['next_upcoming_schedule'] as ScheduleItem?;

        if (items.isNotEmpty) {
          next = items.firstWhere(
            (item) => item.status == 'active' || item.status == 'upcoming',
            orElse: () => items.first,
          );
        } else if (nextUpcoming != null) {
          next = nextUpcoming;
        } else {
          next = ScheduleItem(
            id: 0,
            dayOfWeek: 1,
            dayName: '',
            period: 0,
            startTime: '-',
            endTime: '-',
            subjectName: 'Tidak ada KBM hari ini',
            className: '-',
            teacherName: '-',
            status: 'finished',
          );
        }
      }

      TeacherAttendanceModel? att;
      if (attData['success'] == true) {
        att = attData['attendance'] as TeacherAttendanceModel?;
      }

      if (mounted) {
        setState(() {
          _recentNotifications = notifs;
          _nextSchedule = next;
          _teacherAttendance = att;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final school = ApiService().currentSchool;

    return RefreshIndicator(
      onRefresh: _loadDashboardData,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // User Welcome Header Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF1E3A8A), Color(0xFF2563EB)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(18),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF2563EB).withValues(alpha: 0.25),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Assalamu\'alaikum,',
                              style: TextStyle(
                                fontSize: 13,
                                color: Colors.blue.shade100,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              user?.name ?? 'Bapak / Ibu Guru',
                              style: const TextStyle(
                                fontSize: 18,
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          (user?.role ?? 'guru').toUpperCase(),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.6,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        ClipRRect(borderRadius: BorderRadius.circular(6), child: Image.asset('assets/logo.png', width: 22, height: 22, fit: BoxFit.contain)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            school?.name ?? 'SMP Muhammadiyah Unggulan Ashidiq',
                            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w500),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        Text(
                          '${school?.semester ?? 'Gasal'} ${school?.academicYear ?? '2026/2027'}',
                          style: TextStyle(color: Colors.blue.shade100, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Teacher Attendance Status Card (Presensi Hari Ini)
            _buildTeacherAttendanceStatusCard(),
            const SizedBox(height: 16),

            // Core Administration Grid (Presensi Guru, Presensi Siswa, Jurnal, Nilai)
            const Text(
              'Administrasi Kelas & Pembelajaran',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 10),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.35,
              children: [
                _buildAdminActionCard(
                  title: 'Presensi Guru',
                  subtitle: 'Absen datang & pulang',
                  icon: Icons.person_pin_circle_rounded,
                  color: const Color(0xFF16A34A),
                  onTap: () => widget.onTabChange(1, subTab: 0),
                ),
                _buildAdminActionCard(
                  title: 'Alur KBM Kelas',
                  subtitle: 'Absen masuk, siswa & jurnal',
                  icon: Icons.play_circle_fill_rounded,
                  color: const Color(0xFF2563EB),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => TeachingWizardScreen(
                          assignmentId: _nextSchedule?.assignmentId,
                          scheduleId: _nextSchedule?.id,
                        ),
                      ),
                    );
                  },
                ),
                _buildAdminActionCard(
                  title: 'Jurnal Mengajar',
                  subtitle: 'Materi & kegiatan KBM',
                  icon: Icons.menu_book_rounded,
                  color: const Color(0xFF8B5CF6),
                  onTap: () => widget.onTabChange(2, subTab: 0),
                ),
                _buildAdminActionCard(
                  title: 'Input Nilai Siswa',
                  subtitle: 'UH, TP, PTS & PAS',
                  icon: Icons.grading_rounded,
                  color: const Color(0xFFEA580C),
                  onTap: () => widget.onTabChange(2, subTab: 1),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Live KBM Schedule Banner Card
            _buildLiveScheduleCard(),
            const SizedBox(height: 16),

            // Recent Notifications Preview
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Catatan & Notifikasi Hari Ini',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const NotificationsTab()),
                    );
                  },
                  child: const Text('Lihat Semua', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (_isLoading)
              const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()))
            else if (_recentNotifications.isEmpty)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline, color: Color(0xFF64748B), size: 20),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Tidak ada pemberitahuan atau peringatan jadwal saat ini.',
                        style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                      ),
                    ),
                  ],
                ),
              )
            else
              ..._recentNotifications.take(3).map(_buildNotificationSnippet),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildTeacherAttendanceStatusCard() {
    final hasIn = _teacherAttendance?.hasCheckedIn ?? false;
    final hasOut = _teacherAttendance?.hasCheckedOut ?? false;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: hasIn ? const Color(0xFFBBF7D0) : const Color(0xFFFED7AA),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: hasIn ? const Color(0xFFDCFCE7) : const Color(0xFFFFEDD5),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  hasIn ? Icons.check_circle_rounded : Icons.pending_actions_rounded,
                  color: hasIn ? const Color(0xFF16A34A) : const Color(0xFFEA580C),
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      hasIn ? 'Presensi Hari Ini: Sudah Masuk' : 'Presensi Hari Ini: Belum Masuk',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      hasIn
                          ? 'Jam Masuk: ${_teacherAttendance?.timeIn?.substring(0, 5) ?? '-'}${hasOut ? ' • Jam Pulang: ${_teacherAttendance?.timeOut?.substring(0, 5)}' : ''}'
                          : 'Silakan lakukan check-in kehadiran sekarang',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
              OutlinedButton(
                onPressed: () => widget.onTabChange(1, subTab: 0),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: Text(hasIn ? 'Detail' : 'Check-In'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAdminActionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLiveScheduleCard() {
    final next = _nextSchedule;
    final isActive = next?.status == 'active';
    final isUpcomingToday = next?.status == 'upcoming';
    final isNextDay = next != null && next.id > 0 && !isActive && !isUpcomingToday;

    String headerLabel = 'JADWAL KBM HARI INI';
    IconData headerIcon = Icons.schedule_rounded;
    Color headerColor = const Color(0xFF64748B);

    if (isActive) {
      headerLabel = 'KBM SEDANG BERLANGSUNG';
      headerIcon = Icons.play_circle_fill_rounded;
      headerColor = const Color(0xFF2563EB);
    } else if (isNextDay && next.dayName.isNotEmpty) {
      headerLabel = 'JADWAL BERIKUTNYA (${next.dayName.toUpperCase()})';
      headerIcon = Icons.event_available_rounded;
      headerColor = const Color(0xFF1D4ED8);
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isActive ? const Color(0xFF60A5FA) : const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Icon(
                    headerIcon,
                    color: headerColor,
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    headerLabel,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.6,
                      color: headerColor,
                    ),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => widget.onTabChange(3),
                style: TextButton.styleFrom(visualDensity: VisualDensity.compact, padding: EdgeInsets.zero),
                child: const Text('Semua Jadwal', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
          const Divider(height: 16),
          if (next == null || next.subjectName == 'Tidak ada KBM hari ini')
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Tidak ada jadwal KBM tersisa hari ini.',
                style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
              ),
            )
          else ...[
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        next.subjectName,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${isNextDay && next.dayName.isNotEmpty ? "Hari ${next.dayName} • " : ""}Kelas ${next.className} • Jam ke-${next.period} (${next.timeRange})',
                        style: const TextStyle(fontSize: 12, color: Color(0xFF475569)),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: isActive ? const Color(0xFFEFF6FF) : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isActive ? const Color(0xFFBFDBFE) : const Color(0xFFE2E8F0),
                    ),
                  ),
                  child: Text(
                    isNextDay && next.dayName.isNotEmpty ? 'Hari ${next.dayName}' : next.remainingLabel,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: isActive ? const Color(0xFF1D4ED8) : const Color(0xFF475569),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => TeachingWizardScreen(
                        assignmentId: next.assignmentId > 0 ? next.assignmentId : null,
                        scheduleId: next.id > 0 ? next.id : null,
                      ),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: isActive ? const Color(0xFF16A34A) : const Color(0xFF2563EB),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                ),
                icon: const Icon(Icons.play_circle_fill_rounded, size: 18),
                label: Text(
                  isActive
                      ? 'Mulai KBM (Absen Masuk, Siswa & Jurnal) ➜'
                      : (isNextDay && next.dayName.isNotEmpty
                          ? 'Buka Alur KBM (Hari ${next.dayName}) ➜'
                          : 'Buka Alur Mengajar (3 Langkah) ➜'),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildNotificationSnippet(NotificationItem item) {
    Color bg = const Color(0xFFF8FAFC);
    Color border = const Color(0xFFE2E8F0);
    IconData icon = Icons.notifications_none_rounded;
    Color iconColor = const Color(0xFF64748B);

    if (item.level == 'danger') {
      bg = const Color(0xFFFEF2F2);
      border = const Color(0xFFFECACA);
      icon = Icons.warning_amber_rounded;
      iconColor = const Color(0xFFDC2626);
    } else if (item.level == 'warning') {
      bg = const Color(0xFFFFFBEB);
      border = const Color(0xFFFDE68A);
      icon = Icons.schedule_rounded;
      iconColor = const Color(0xFFD97706);
    } else if (item.level == 'success') {
      bg = const Color(0xFFF0FDF4);
      border = const Color(0xFFBBF7D0);
      icon = Icons.check_circle_outline_rounded;
      iconColor = const Color(0xFF16A34A);
    }

    final isSchedule = item.type == 'schedule' && item.assignmentId > 0;

    return InkWell(
      onTap: isSchedule
          ? () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => TeachingWizardScreen(
                    assignmentId: item.assignmentId,
                    scheduleId: item.scheduleId,
                  ),
                ),
              );
            }
          : null,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: iconColor, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                      ),
                      if (isSchedule)
                        const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Color(0xFF2563EB)),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.body,
                    style: const TextStyle(fontSize: 12, color: Color(0xFF475569)),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Text(
              item.time,
              style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
            ),
          ],
        ),
      ),
    );
  }
}
