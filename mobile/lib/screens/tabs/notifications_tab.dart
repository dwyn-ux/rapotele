import 'package:flutter/material.dart';
import '../../services/notification_service.dart';
import '../../models/notification_item.dart';

class NotificationsTab extends StatefulWidget {
  const NotificationsTab({super.key});

  @override
  State<NotificationsTab> createState() => _NotificationsTabState();
}

class _NotificationsTabState extends State<NotificationsTab> {
  bool _isLoading = true;
  List<NotificationItem> _notifications = [];
  String _activeFilter = 'all';

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() => _isLoading = true);
    final items = await NotificationService().checkAndTriggerNotifications();
    if (!mounted) return;

    setState(() {
      _notifications = items;
      _isLoading = false;
    });
  }

  Future<void> _sendTestNotification() async {
    await NotificationService().showNotification(
      id: 9999,
      title: '🔔 Test Trigger Notifikasi Android',
      body: 'Pengingat: 15 menit lagi KBM Matematika di Kelas 7A. Notifikasi, suara, dan getaran berfungsi dengan baik!',
      channelId: NotificationService.channelScheduleId,
      channelName: 'Pengingat Jadwal KBM',
    );

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Notifikasi uji coba berhasil dikirim ke bilah status Android!'),
          backgroundColor: Color(0xFF16A34A),
        ),
      );
    }
  }

  List<NotificationItem> get _filteredNotifications {
    if (_activeFilter == 'schedule') {
      return _notifications.where((n) => n.type == 'schedule').toList();
    } else if (_activeFilter == 'whatsapp') {
      return _notifications.where((n) => n.type == 'whatsapp').toList();
    } else if (_activeFilter == 'student') {
      return _notifications.where((n) => n.type == 'attendance' || n.type == 'violation').toList();
    }
    return _notifications;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Pusat Notifikasi & Trigger', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.notification_add_rounded),
            tooltip: 'Kirim Test Notifikasi Android',
            onPressed: _sendTestNotification,
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Segarkan',
            onPressed: _fetchNotifications,
          ),
        ],
      ),
      body: Column(
        children: [
          // Filter Chips & Test Notification Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            color: Colors.white,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _buildFilterChip('Semua', 'all'),
                  const SizedBox(width: 6),
                  _buildFilterChip('Jadwal KBM', 'schedule'),
                  const SizedBox(width: 6),
                  _buildFilterChip('WhatsApp', 'whatsapp'),
                  const SizedBox(width: 6),
                  _buildFilterChip('Presensi / Pelanggaran', 'student'),
                ],
              ),
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),

          // List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _fetchNotifications,
                    child: _filteredNotifications.isEmpty
                        ? ListView(
                            children: const [
                              SizedBox(height: 90),
                              Center(
                                child: Column(
                                  children: [
                                    Icon(Icons.notifications_paused_outlined, size: 54, color: Color(0xFF94A3B8)),
                                    SizedBox(height: 12),
                                    Text('Tidak Ada Trigger Notifikasi', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF475569))),
                                    SizedBox(height: 4),
                                    Text('Semua jadwal dan antrian berjalan normal.', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
                                  ],
                                ),
                              ),
                            ],
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.all(16),
                            itemCount: _filteredNotifications.length,
                            separatorBuilder: (ctx, idx) => const SizedBox(height: 10),
                            itemBuilder: (ctx, index) {
                              final item = _filteredNotifications[index];
                              return _buildNotificationCard(item);
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label, String value) {
    final isSelected = _activeFilter == value;
    return ChoiceChip(
      label: Text(label, style: TextStyle(fontSize: 12, fontWeight: isSelected ? FontWeight.bold : FontWeight.normal)),
      selected: isSelected,
      selectedColor: const Color(0xFF2563EB),
      labelStyle: TextStyle(color: isSelected ? Colors.white : const Color(0xFF334155)),
      onSelected: (_) => setState(() => _activeFilter = value),
    );
  }

  Widget _buildNotificationCard(NotificationItem item) {
    Color bg = Colors.white;
    Color border = const Color(0xFFE2E8F0);
    IconData icon = Icons.notifications_none_rounded;
    Color iconColor = const Color(0xFF64748B);

    if (item.level == 'danger') {
      bg = const Color(0xFFFEF2F2);
      border = const Color(0xFFFECACA);
      icon = Icons.error_outline_rounded;
      iconColor = const Color(0xFFDC2626);
    } else if (item.level == 'warning') {
      bg = const Color(0xFFFFFBEB);
      border = const Color(0xFFFDE68A);
      icon = Icons.alarm_rounded;
      iconColor = const Color(0xFFD97706);
    } else if (item.level == 'success') {
      bg = const Color(0xFFF0FDF4);
      border = const Color(0xFFBBF7D0);
      icon = Icons.check_circle_outline_rounded;
      iconColor = const Color(0xFF16A34A);
    } else if (item.type == 'whatsapp') {
      icon = Icons.chat_bubble_outline_rounded;
      iconColor = const Color(0xFF0284C7);
    }

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: iconColor, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                      ),
                    ),
                    Text(
                      item.time,
                      style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  item.body,
                  style: const TextStyle(fontSize: 13, height: 1.4, color: Color(0xFF334155)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
