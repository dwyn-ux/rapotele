import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../models/schedule_item.dart';

class ScheduleTab extends StatefulWidget {
  const ScheduleTab({super.key});

  @override
  State<ScheduleTab> createState() => _ScheduleTabState();
}

class _ScheduleTabState extends State<ScheduleTab> {
  bool _isLoading = true;
  bool _isTriggering = false;
  String _todayDayName = '';
  String _todayDate = '';
  List<ScheduleItem> _todayList = [];
  List<DaySchedule> _weekList = [];
  int _selectedDayIndex = 0;
  bool _showWeekly = false;

  @override
  void initState() {
    super.initState();
    _loadSchedule();
  }

  Future<void> _loadSchedule() async {
    setState(() => _isLoading = true);
    final data = await ApiService().getMySchedule();
    if (!mounted) return;

    if (data['success'] == true) {
      setState(() {
        _todayDayName = data['today_day_name'] ?? '';
        _todayDate = data['today_date'] ?? '';
        _todayList = data['today'] as List<ScheduleItem>;
        _weekList = data['week'] as List<DaySchedule>;
        _isLoading = false;
      });
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(data['message'] ?? 'Gagal memuat jadwal')),
      );
    }
  }

  Future<void> _triggerReminders() async {
    setState(() => _isTriggering = true);
    final res = await ApiService().triggerScheduleReminders();
    if (!mounted) return;

    setState(() => _isTriggering = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(res['message'] ?? 'Reminder jadwal dipicu.'),
        backgroundColor: res['success'] == true ? const Color(0xFF16A34A) : Colors.red,
      ),
    );
    _loadSchedule();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Text(
          _todayDate.isNotEmpty ? 'Jadwal KBM ($_todayDate)' : 'Jadwal Pelajaran & KBM',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
        actions: [
          IconButton(
            icon: _isTriggering
                ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.alarm_on_rounded),
            tooltip: 'Picu Reminder KBM',
            onPressed: _isTriggering ? null : _triggerReminders,
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Segarkan',
            onPressed: _loadSchedule,
          ),
        ],
      ),
      body: Column(
        children: [
          // View Switcher (Hari Ini vs Mingguan)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: Colors.white,
            child: Row(
              children: [
                Expanded(
                  child: SegmentedButton<bool>(
                    segments: const [
                      ButtonSegment(value: false, label: Text('Hari Ini'), icon: Icon(Icons.today_rounded, size: 16)),
                      ButtonSegment(value: true, label: Text('Semua Hari'), icon: Icon(Icons.date_range_rounded, size: 16)),
                    ],
                    selected: {_showWeekly},
                    onSelectionChanged: (val) => setState(() => _showWeekly = val.first),
                    style: const ButtonStyle(
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      visualDensity: VisualDensity.compact,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),

          // Content
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _loadSchedule,
                    child: _showWeekly ? _buildWeeklyView() : _buildTodayView(),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildTodayView() {
    if (_todayList.isEmpty) {
      return ListView(
        children: [
          const SizedBox(height: 80),
          Center(
            child: Column(
              children: [
                const Icon(Icons.event_available_rounded, size: 54, color: Color(0xFF94A3B8)),
                const SizedBox(height: 12),
                Text('Tidak Ada Jadwal KBM Hari Ini ($_todayDayName)', style: const TextStyle(color: Color(0xFF475569), fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                const Text('Silakan periksa tab "Semua Hari" untuk melihat jadwal hari lain.', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
              ],
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _todayList.length,
      separatorBuilder: (ctx, idx) => const SizedBox(height: 12),
      itemBuilder: (ctx, index) {
        final item = _todayList[index];
        return _buildScheduleCard(item);
      },
    );
  }

  Widget _buildWeeklyView() {
    if (_weekList.isEmpty) {
      return const Center(child: Text('Data jadwal mingguan kosong.'));
    }

    final currentDay = _weekList[_selectedDayIndex.clamp(0, _weekList.length - 1)];

    return Column(
      children: [
        // Day selector tabs
        Container(
          height: 46,
          color: Colors.white,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            itemCount: _weekList.length,
            itemBuilder: (ctx, index) {
              final d = _weekList[index];
              final isSelected = index == _selectedDayIndex;
              return Padding(
                padding: const EdgeInsets.only(right: 6),
                child: ChoiceChip(
                  label: Text(d.dayName, style: TextStyle(fontSize: 12, fontWeight: isSelected ? FontWeight.bold : FontWeight.normal)),
                  selected: isSelected,
                  selectedColor: const Color(0xFF2563EB),
                  labelStyle: TextStyle(color: isSelected ? Colors.white : const Color(0xFF475569)),
                  onSelected: (_) => setState(() => _selectedDayIndex = index),
                ),
              );
            },
          ),
        ),
        const Divider(height: 1, color: Color(0xFFE2E8F0)),

        Expanded(
          child: currentDay.items.isEmpty
              ? Center(
                  child: Text('Tidak ada KBM pada hari ${currentDay.dayName}', style: const TextStyle(color: Color(0xFF64748B))),
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: currentDay.items.length,
                  separatorBuilder: (ctx, idx) => const SizedBox(height: 10),
                  itemBuilder: (ctx, index) {
                    final item = currentDay.items[index];
                    return _buildScheduleCard(item, showStatus: false);
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildScheduleCard(ScheduleItem item, {bool showStatus = true}) {
    final isActive = item.status == 'active';
    final isUpcoming = item.status == 'upcoming';

    Color cardBg = Colors.white;
    Color borderColor = const Color(0xFFE2E8F0);
    if (showStatus && isActive) {
      cardBg = const Color(0xFFF0FDF4);
      borderColor = const Color(0xFF86EFAC);
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor, width: (showStatus && isActive) ? 1.5 : 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
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
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  'JAM KE-${item.period}',
                  style: const TextStyle(color: Color(0xFF2563EB), fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
              if (showStatus) ...[
                if (isActive)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCFCE7),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.play_circle_fill_rounded, size: 12, color: Color(0xFF15803D)),
                        SizedBox(width: 4),
                        Text('Sedang Berlangsung', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF15803D))),
                      ],
                    ),
                  )
                else if (isUpcoming)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      item.minutesUntilStart > 0 ? '${item.minutesUntilStart} mnt lagi' : 'Segera',
                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                    ),
                  )
                else
                  const Text('Selesai', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
              ],
            ],
          ),
          const SizedBox(height: 10),
          Text(
            item.subjectName,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.people_outline_rounded, size: 15, color: Color(0xFF64748B)),
              const SizedBox(width: 4),
              Text('Kelas ${item.className}', style: const TextStyle(fontSize: 13, color: Color(0xFF475569), fontWeight: FontWeight.w500)),
              const SizedBox(width: 14),
              const Icon(Icons.access_time_rounded, size: 15, color: Color(0xFF64748B)),
              const SizedBox(width: 4),
              Text('${item.startTime} - ${item.endTime}', style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.person_outline_rounded, size: 15, color: Color(0xFF94A3B8)),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  'Guru: ${item.teacherName}',
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
