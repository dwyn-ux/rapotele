import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../models/schedule_item.dart';
import '../teaching_wizard_screen.dart';

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
      final todayList = (data['today'] as List<ScheduleItem>?) ?? [];
      final weekList = (data['week'] as List<DaySchedule>?) ?? [];

      // Cari hari pertama yang memiliki jadwal jika hari ini kosong
      int initialDayIndex = 0;
      if (todayList.isEmpty && weekList.isNotEmpty) {
        final firstActive = weekList.indexWhere((d) => d.items.isNotEmpty);
        if (firstActive != -1) {
          initialDayIndex = firstActive;
        }
      }

      setState(() {
        _todayDayName = data['today_day_name'] ?? '';
        _todayDate = data['today_date'] ?? '';
        _todayList = todayList;
        _weekList = weekList;
        if (_selectedDayIndex == 0) {
          _selectedDayIndex = initialDayIndex;
        }
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
      final activeDays = _weekList.where((d) => d.items.isNotEmpty).toList();

      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
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
              children: [
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.event_available_rounded, size: 40, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 14),
                Text(
                  'Hari Ini (${_todayDayName.isNotEmpty ? _todayDayName : "Libur"}) Tidak Ada KBM',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Tidak ada jam pelajaran yang dijadwalkan untuk hari ini.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (activeDays.isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
              child: Row(
                children: [
                  const Icon(Icons.calendar_month_rounded, size: 18, color: Color(0xFF2563EB)),
                  const SizedBox(width: 8),
                  const Text(
                    'Jadwal Mengajar Anda Minggu Ini',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1E293B)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            ...activeDays.map((d) {
              final subjectsSummary = d.items.map((i) => '${i.subjectName} (${i.className})').toSet().join(', ');
              return Container(
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFDBEAFE)),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        children: [
                          Text(d.dayName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1D4ED8))),
                          Text('${d.items.length} Jam', style: const TextStyle(fontSize: 10, color: Color(0xFF3B82F6))),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            subjectsSummary.isNotEmpty ? subjectsSummary : 'Jam Mengajar',
                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Color(0xFF0F172A)),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Jam ke-${d.items.first.period} (${d.items.first.startTime}) s/d Jam ke-${d.items.last.period} (${d.items.last.endTime})',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: () {
                        setState(() {
                          _selectedDayIndex = _weekList.indexOf(d);
                          _showWeekly = true;
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF2563EB),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        visualDensity: VisualDensity.compact,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: const Text('Buka', style: TextStyle(fontSize: 12)),
                    ),
                  ],
                ),
              );
            }),
          ] else ...[
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline_rounded, color: Color(0xFFD97706), size: 20),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Belum ada jadwal mengajar yang terhubung ke akun ini. Pastikan akun sudah terhubung ke data Guru di web.',
                      style: TextStyle(fontSize: 12, color: Color(0xFF92400E)),
                    ),
                  ),
                ],
              ),
            ),
          ],
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
    final activeDays = _weekList.where((d) => d.items.isNotEmpty).toList();

    return Column(
      children: [
        // Day selector tabs
        Container(
          height: 48,
          color: Colors.white,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            itemCount: _weekList.length,
            itemBuilder: (ctx, index) {
              final d = _weekList[index];
              final isSelected = index == _selectedDayIndex;
              final hasItems = d.items.isNotEmpty;
              return Padding(
                padding: const EdgeInsets.only(right: 6),
                child: ChoiceChip(
                  label: Text(
                    '${d.dayName} (${d.items.length})',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: (isSelected || hasItems) ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                  selected: isSelected,
                  selectedColor: const Color(0xFF2563EB),
                  backgroundColor: hasItems ? const Color(0xFFEFF6FF) : const Color(0xFFF8FAFC),
                  labelStyle: TextStyle(
                    color: isSelected
                        ? Colors.white
                        : (hasItems ? const Color(0xFF1D4ED8) : const Color(0xFF64748B)),
                  ),
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
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.event_busy_rounded, size: 48, color: Color(0xFF94A3B8)),
                        const SizedBox(height: 12),
                        Text(
                          'Tidak ada KBM pada hari ${currentDay.dayName}',
                          style: const TextStyle(color: Color(0xFF475569), fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        if (activeDays.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          const Text(
                            'Jadwal KBM Anda tersedia pada hari:',
                            style: TextStyle(color: Color(0xFF64748B), fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            alignment: WrapAlignment.center,
                            children: activeDays.map((d) {
                              return ActionChip(
                                avatar: const Icon(Icons.calendar_today_rounded, size: 14, color: Color(0xFF2563EB)),
                                label: Text('${d.dayName} (${d.items.length} jam)'),
                                onPressed: () {
                                  setState(() => _selectedDayIndex = _weekList.indexOf(d));
                                },
                              );
                            }).toList(),
                          ),
                        ],
                      ],
                    ),
                  ),
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
          if (showStatus) ...[
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => TeachingWizardScreen(
                        assignmentId: item.assignmentId,
                        scheduleId: item.id,
                      ),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  side: BorderSide(color: isActive ? const Color(0xFF16A34A) : const Color(0xFF2563EB)),
                ),
                icon: Icon(
                  isActive ? Icons.play_circle_fill_rounded : Icons.assignment_turned_in_rounded,
                  size: 16,
                  color: isActive ? const Color(0xFF16A34A) : const Color(0xFF2563EB),
                ),
                label: Text(
                  isActive ? 'Masuk Kelas & Isi Jurnal (KBM Aktif)' : 'Buka Alur Mengajar (3 Langkah)',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: isActive ? const Color(0xFF16A34A) : const Color(0xFF2563EB),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
