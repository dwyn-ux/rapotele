import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/notification_service.dart';
import '../../models/notification_item.dart';
import '../../models/schedule_item.dart';
import '../../models/whatsapp_item.dart';

class DashboardTab extends StatefulWidget {
  final Function(int) onTabChange;

  const DashboardTab({super.key, required this.onTabChange});

  @override
  State<DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<DashboardTab> {
  bool _isLoading = true;
  ScheduleItem? _nextSchedule;
  WhatsAppStats? _waStats;
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
      final waFuture = ApiService().getWhatsAppFeed(limit: 5);

      final notifs = await notifsFuture;
      final schedData = await schedFuture;
      final waData = await waFuture;

      ScheduleItem? next;
      if (schedData['success'] == true) {
        final todayList = schedData['today'] as List<ScheduleItem>;
        next = todayList.firstWhere(
          (item) => item.status == 'active' || item.status == 'upcoming',
          orElse: () => todayList.isNotEmpty ? todayList.first : ScheduleItem(
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
          ),
        );
      }

      WhatsAppStats? stats;
      if (waData['success'] == true) {
        stats = waData['stats'] as WhatsAppStats;
      }

      if (mounted) {
        setState(() {
          _recentNotifications = notifs;
          _nextSchedule = next;
          _waStats = stats;
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
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // User Welcome Header Card
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF1E3A8A), Color(0xFF2563EB)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
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
                              user?.name ?? 'Pengguna',
                              style: const TextStyle(
                                fontSize: 19,
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
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.school_outlined, color: Colors.white70, size: 16),
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
                          '${school?.semester ?? 'Gasal'} ${school?.academicYear ?? ''}',
                          style: TextStyle(color: Colors.blue.shade100, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),

            // Live KBM Schedule Banner Card
            _buildLiveScheduleCard(),
            const SizedBox(height: 18),

            // WhatsApp Activity Highlights Card
            _buildWhatsAppOverviewCard(),
            const SizedBox(height: 18),

            // Quick Actions Grid
            const Text(
              'Akses Cepat',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _buildActionTile(
                    icon: Icons.calendar_today_rounded,
                    color: const Color(0xFF0284C7),
                    label: 'Jadwal KBM',
                    onTap: () => widget.onTabChange(2),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _buildActionTile(
                    icon: Icons.chat_bubble_outline_rounded,
                    color: const Color(0xFF16A34A),
                    label: 'Update WA',
                    onTap: () => widget.onTabChange(1),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _buildActionTile(
                    icon: Icons.notifications_active_outlined,
                    color: const Color(0xFFF59E0B),
                    label: 'Notifikasi',
                    onTap: () => widget.onTabChange(3),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),

            // Recent Triggers Feed Preview
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Trigger Notifikasi Terbaru',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                ),
                TextButton(
                  onPressed: () => widget.onTabChange(3),
                  child: const Text('Lihat Semua', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (_isLoading)
              const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()))
            else if (_recentNotifications.isEmpty)
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: const Center(
                  child: Text(
                    'Belum ada trigger notifikasi baru saat ini.',
                    style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                  ),
                ),
              )
            else
              ..._recentNotifications.take(3).map((item) => _buildNotificationSnippet(item)),
          ],
        ),
      ),
    );
  }

  Widget _buildLiveScheduleCard() {
    if (_nextSchedule == null || _nextSchedule!.period == 0) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: const Row(
          children: [
            Icon(Icons.event_busy_rounded, color: Colors.grey),
            SizedBox(width: 12),
            Text('Tidak ada agenda KBM saat ini.', style: TextStyle(color: Color(0xFF64748B))),
          ],
        ),
      );
    }

    final s = _nextSchedule!;
    final isActive = s.status == 'active';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isActive ? const Color(0xFFEFF6FF) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isActive ? const Color(0xFF93C5FD) : const Color(0xFFE2E8F0),
          width: isActive ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
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
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: isActive ? const Color(0xFF16A34A) : const Color(0xFFF59E0B),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    isActive ? 'KBM SEDANG BERLANGSUNG' : 'KBM SELANJUTNYA HARI INI',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                      color: isActive ? const Color(0xFF15803D) : const Color(0xFFB45309),
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: isActive ? const Color(0xFFDCFCE7) : const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  isActive ? 'Aktif' : (s.minutesUntilStart > 0 ? '${s.minutesUntilStart} menit lagi' : 'Segera'),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: isActive ? const Color(0xFF166534) : const Color(0xFF92400E),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            s.subjectName,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.meeting_room_outlined, size: 15, color: Color(0xFF64748B)),
              const SizedBox(width: 4),
              Text('Kelas ${s.className}', style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
              const SizedBox(width: 12),
              const Icon(Icons.access_time_rounded, size: 15, color: Color(0xFF64748B)),
              const SizedBox(width: 4),
              Text('${s.startTime} - ${s.endTime} (Jam ke-${s.period})', style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildWhatsAppOverviewCard() {
    final pending = _waStats?.totalPending ?? 0;
    final sent = _waStats?.totalSent ?? 0;
    final failed = _waStats?.totalFailed ?? 0;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.mark_chat_unread_rounded, color: Color(0xFF16A34A), size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Status Antrian WhatsApp',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                  ),
                ],
              ),
              InkWell(
                onTap: () => widget.onTabChange(1),
                child: const Text('Monitor WA >', style: TextStyle(fontSize: 12, color: Color(0xFF2563EB), fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(child: _buildMiniStat('Pending', pending.toString(), const Color(0xFFF59E0B))),
              Expanded(child: _buildMiniStat('Terkirim', sent.toString(), const Color(0xFF16A34A))),
              Expanded(child: _buildMiniStat('Gagal', failed.toString(), const Color(0xFFDC2626))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMiniStat(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold, color: color)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
      ],
    );
  }

  Widget _buildActionTile({
    required IconData icon,
    required Color color,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
              textAlign: TextAlign.center,
            ),
          ],
        ),
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

    return Container(
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
                Text(
                  item.title,
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
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
    );
  }
}
