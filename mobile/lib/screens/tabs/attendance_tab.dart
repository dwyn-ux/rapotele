import 'dart:async';
import 'package:flutter/material.dart';
import '../../models/teaching_assignment.dart';
import '../../models/attendance_models.dart';
import '../../services/api_service.dart';

class AttendanceTab extends StatefulWidget {
  final int initialSubTab;
  final Function(int tabIndex)? onNavigateTab;

  const AttendanceTab({
    super.key,
    this.initialSubTab = 0,
    this.onNavigateTab,
  });

  @override
  State<AttendanceTab> createState() => _AttendanceTabState();
}

class _AttendanceTabState extends State<AttendanceTab> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _api = ApiService();

  // 1. Teacher Daily Attendance (Harian Sekolah)
  bool _isLoadingTeacher = false;
  TeacherAttendanceModel? _teacherAttendance;
  String _teacherStatus = 'hadir';
  final TextEditingController _teacherNotesController = TextEditingController();
  DateTime _currentTime = DateTime.now();
  Timer? _timer;

  // 2. Teacher Class Attendance & Student Attendance (Masuk Kelas & Siswa)
  bool _isLoadingAssignments = false;
  bool _isLoadingStudents = false;
  bool _isSavingStudents = false;
  bool _isTeacherInClass = false;
  String? _teacherClassTimeIn;
  bool _isCheckingInClass = false;
  bool _hasSchedule = true;
  String? _scheduleInfo;
  String _scheduleDayName = '';
  List<String> _otherSchedules = [];

  List<TeachingAssignmentModel> _assignments = [];
  TeachingAssignmentModel? _selectedAssignment;
  DateTime _selectedDate = DateTime.now();
  int _meetingNo = 1;
  final TextEditingController _topicController = TextEditingController(text: 'Presensi KBM');
  final bool _queueWhatsapp = false;
  List<StudentAttendanceItem> _students = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this, initialIndex: widget.initialSubTab);
    _loadTeacherAttendance();
    _loadAssignments();

    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() {
          _currentTime = DateTime.now();
        });
      }
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    _timer?.cancel();
    _teacherNotesController.dispose();
    _topicController.dispose();
    super.dispose();
  }

  String _formatTimeDigits(int n) => n.toString().padLeft(2, '0');

  String _formatDateYmd(DateTime dt) =>
      '${dt.year}-${_formatTimeDigits(dt.month)}-${_formatTimeDigits(dt.day)}';

  String _formatDateIndo(DateTime dt) {
    const months = [
      '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const days = [
      '', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'
    ];
    return '${days[dt.weekday]}, ${dt.day} ${months[dt.month]} ${dt.year}';
  }

  // ================= 1. TEACHER DAILY ATTENDANCE (HARIAN) =================
  Future<void> _loadTeacherAttendance() async {
    setState(() => _isLoadingTeacher = true);
    final res = await _api.getTeacherAttendance(date: _formatDateYmd(_selectedDate));
    if (mounted) {
      setState(() {
        _isLoadingTeacher = false;
        if (res['success'] == true) {
          _teacherAttendance = res['attendance'] as TeacherAttendanceModel?;
          if (_teacherAttendance != null && _teacherAttendance!.notes.isNotEmpty) {
            _teacherNotesController.text = _teacherAttendance!.notes;
            _teacherStatus = _teacherAttendance!.status;
          }
        }
      });
    }
  }

  Future<void> _submitTeacherCheckIn(String type) async {
    setState(() => _isLoadingTeacher = true);
    final res = await _api.teacherCheckIn(
      type: type,
      status: _teacherStatus,
      notes: _teacherNotesController.text.trim().isEmpty
          ? (type == 'checkin' ? 'Presensi Datang Mobile' : 'Presensi Pulang Mobile')
          : _teacherNotesController.text.trim(),
    );

    if (mounted) {
      setState(() => _isLoadingTeacher = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? (res['success'] ? 'Berhasil' : 'Gagal')),
          backgroundColor: res['success'] ? Colors.green.shade700 : Colors.red.shade700,
        ),
      );
      if (res['success'] == true) {
        _loadTeacherAttendance();
      }
    }
  }

  // ================= 2. TEACHER CLASS & STUDENT ATTENDANCE =================
  Future<void> _loadAssignments() async {
    setState(() => _isLoadingAssignments = true);
    final list = await _api.getTeachingAssignments();
    if (mounted) {
      setState(() {
        _isLoadingAssignments = false;
        _assignments = list;
        if (list.isNotEmpty && _selectedAssignment == null) {
          _selectedAssignment = list.first;
          _loadStudentsForSession();
        }
      });
    }
  }

  Future<void> _loadStudentsForSession() async {
    if (_selectedAssignment == null) return;
    setState(() => _isLoadingStudents = true);
    final res = await _api.getStudentsForAttendance(
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
    );

    if (mounted) {
      setState(() {
        _isLoadingStudents = false;
        if (res['success'] == true) {
          _students = (res['students'] as List<StudentAttendanceItem>?) ?? [];
          _isTeacherInClass = res['teacher_checked_in'] == true;
          _teacherClassTimeIn = res['teacher_time_in']?.toString();
          _hasSchedule = res['has_schedule'] == true;
          _scheduleInfo = res['schedule_info']?.toString();
          _scheduleDayName = res['day_name']?.toString() ?? '';
          _otherSchedules = (res['other_schedules'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [];

          final sess = res['session'] as Map<String, dynamic>?;
          if (sess != null && sess['topic'] != null && sess['topic'].toString().isNotEmpty) {
            _topicController.text = sess['topic'].toString();
          }
        } else {
          _students = [];
          _isTeacherInClass = false;
          _teacherClassTimeIn = null;
          _hasSchedule = true;
          _scheduleInfo = null;
          _otherSchedules = [];
        }
      });
    }
  }

  Future<void> _submitTeacherClassCheckIn() async {
    if (_selectedAssignment == null) return;
    if (!_hasSchedule) {
      _showMustCheckInDialog();
      return;
    }
    setState(() => _isCheckingInClass = true);

    final res = await _api.teacherClassCheckIn(
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
      status: 'hadir',
    );

    if (mounted) {
      setState(() => _isCheckingInClass = false);
      if (res['success'] == true) {
        setState(() {
          _isTeacherInClass = true;
          _teacherClassTimeIn = res['teacher_time_in'] ??
              '${_formatTimeDigits(_currentTime.hour)}:${_formatTimeDigits(_currentTime.minute)}:${_formatTimeDigits(_currentTime.second)}';
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    res['message'] ?? 'Absen masuk kelas berhasil. Presensi siswa sekarang aktif.',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            backgroundColor: const Color(0xFF16A34A),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? 'Gagal absen masuk kelas'),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    }
  }

  Future<void> _saveStudentAttendance() async {
    if (!_isTeacherInClass) {
      _showMustCheckInDialog();
      return;
    }

    if (_selectedAssignment == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih kelas pembelajaran terlebih dahulu')),
      );
      return;
    }

    if (_students.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tidak ada data siswa untuk disimpan.')),
      );
      return;
    }

    setState(() => _isSavingStudents = true);
    final res = await _api.saveStudentAttendance(
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
      topic: _topicController.text.trim().isEmpty ? 'Presensi KBM' : _topicController.text.trim(),
      queueWhatsapp: _queueWhatsapp,
      students: _students,
    );

    if (mounted) {
      setState(() => _isSavingStudents = false);
      if (res['require_teacher_checkin'] == true) {
        setState(() => _isTeacherInClass = false);
        _showMustCheckInDialog();
        return;
      }

      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: [
              Icon(
                res['success'] ? Icons.check_circle : Icons.error,
                color: res['success'] ? Colors.green : Colors.red,
              ),
              const SizedBox(width: 8),
              Text(res['success'] ? 'Presensi Tersimpan' : 'Gagal Menyimpan'),
            ],
          ),
          content: Text(res['message']?.toString() ?? ''),
          actions: [
            FilledButton(
              onPressed: () {
                Navigator.pop(ctx);
                _loadStudentsForSession();
              },
              child: const Text('OK'),
            ),
          ],
        ),
      );
    }
  }

  void _showMustCheckInDialog() {
    if (!_hasSchedule) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.event_busy_rounded, color: Color(0xFFDC2626)),
              SizedBox(width: 8),
              Text('Tidak Ada Jadwal Mengajar'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Tidak ada jadwal KBM terdaftar untuk ${_selectedAssignment?.displayName ?? ''} pada hari $_scheduleDayName. Guru hanya dapat melakukan absen masuk kelas pada jadwal mengajar yang telah ditentukan.',
              ),
              if (_otherSchedules.isNotEmpty) ...[
                const SizedBox(height: 10),
                const Text('Jadwal kelas ini di hari lain:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                const SizedBox(height: 4),
                ..._otherSchedules.map((s) => Text('• $s', style: const TextStyle(fontSize: 12, color: Color(0xFF475569)))),
              ],
            ],
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Tutup'),
            ),
          ],
        ),
      );
      return;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.lock_clock_rounded, color: Color(0xFFD97706)),
            SizedBox(width: 8),
            Text('Absen Masuk Kelas Wajib'),
          ],
        ),
        content: Text(
          'Jadwal KBM: ${_scheduleInfo ?? "Hari $_scheduleDayName"}.\n\nBapak/Ibu Guru belum melakukan absensi masuk kelas untuk sesi ini. Silakan tekan tombol "Absen Masuk Kelas Sekarang" terlebih dahulu sebelum mendata kehadiran siswa.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          FilledButton.icon(
            onPressed: () {
              Navigator.pop(ctx);
              _submitTeacherClassCheckIn();
            },
            icon: const Icon(Icons.login_rounded),
            label: const Text('Absen Masuk Kelas Sekarang'),
          ),
        ],
      ),
    );
  }

  void _setAllStudentsStatus(String status) {
    if (!_isTeacherInClass) {
      _showMustCheckInDialog();
      return;
    }
    setState(() {
      for (var s in _students) {
        s.status = status;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.asset('assets/logo.png', width: 28, height: 28, fit: BoxFit.contain),
            ),
            const SizedBox(width: 8),
            const Flexible(
              child: Text(
                'Presensi & Kehadiran',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
          tabs: const [
            Tab(icon: Icon(Icons.person_pin, size: 20), text: 'Presensi Guru (Harian)'),
            Tab(icon: Icon(Icons.groups, size: 20), text: 'Presensi Siswa & Kelas'),
          ],
        ),
      ),
      body: LayoutBuilder(
        builder: (context, constraints) {
          return TabBarView(
            controller: _tabController,
            children: [
              _buildTeacherTab(theme, constraints),
              _buildStudentTab(theme, constraints),
            ],
          );
        },
      ),
    );
  }

  // ================= VIEW: 1. TEACHER TAB (HARIAN SEKOLAH) =================
  Widget _buildTeacherTab(ThemeData theme, BoxConstraints constraints) {
    final user = _api.currentUser;
    final timeStr =
        '${_formatTimeDigits(_currentTime.hour)}:${_formatTimeDigits(_currentTime.minute)}:${_formatTimeDigits(_currentTime.second)}';

    final hasIn = _teacherAttendance?.hasCheckedIn ?? false;
    final hasOut = _teacherAttendance?.hasCheckedOut ?? false;

    return RefreshIndicator(
      onRefresh: _loadTeacherAttendance,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        children: [
          // Teacher Profile Card
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            elevation: 2,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: theme.colorScheme.primaryContainer,
                    child: Icon(Icons.person, size: 34, color: theme.colorScheme.primary),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user?.name ?? 'Guru Pengajar',
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'User: @${user?.username ?? '-'}  •  Role: ${user?.role.toUpperCase() ?? 'GURU'}',
                          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _formatDateIndo(_currentTime),
                          style: TextStyle(fontSize: 12, color: Colors.blue.shade700, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),

          // Digital Clock & Status Banner
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            color: theme.colorScheme.primary.withValues(alpha: 0.06),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
              child: Column(
                children: [
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      timeStr,
                      style: TextStyle(
                        fontSize: 38,
                        fontWeight: FontWeight.w800,
                        color: theme.colorScheme.primary,
                        letterSpacing: 2,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Waktu Indonesia Barat (WIB) • Presensi Harian Sekolah',
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                  ),
                  const SizedBox(height: 14),

                  // Status Indicator Badges
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _statusBadge(
                        label: 'Datang: ${hasIn ? _teacherAttendance!.timeIn!.substring(0, 5) : '--:--'}',
                        isActive: hasIn,
                        color: Colors.green,
                        icon: Icons.login,
                      ),
                      _statusBadge(
                        label: 'Pulang: ${hasOut ? _teacherAttendance!.timeOut!.substring(0, 5) : '--:--'}',
                        isActive: hasOut,
                        color: Colors.orange,
                        icon: Icons.logout,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),

          // Attendance Form & Buttons
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            elevation: 2,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Pilih Status Kehadiran Hari Ini',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      ChoiceChip(
                        label: const Text('Hadir'),
                        selected: _teacherStatus == 'hadir',
                        onSelected: (val) {
                          if (val) setState(() => _teacherStatus = 'hadir');
                        },
                      ),
                      ChoiceChip(
                        label: const Text('Dinas Luar'),
                        selected: _teacherStatus == 'dinas_luar',
                        onSelected: (val) {
                          if (val) setState(() => _teacherStatus = 'dinas_luar');
                        },
                      ),
                      ChoiceChip(
                        label: const Text('Izin / Sakit'),
                        selected: _teacherStatus == 'izin',
                        onSelected: (val) {
                          if (val) setState(() => _teacherStatus = 'izin');
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: _teacherNotesController,
                    decoration: InputDecoration(
                      labelText: 'Catatan / Lokasi / Keterangan',
                      hintText: 'Contoh: Hadir di sekolah / Mengajar KBM',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                  ),
                  const SizedBox(height: 18),

                  // Action Buttons
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _isLoadingTeacher ? null : () => _submitTeacherCheckIn('checkin'),
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.green.shade600,
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          icon: const Icon(Icons.login, size: 18),
                          label: Text(
                            hasIn ? 'Update Datang' : 'Check-In Masuk',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _isLoadingTeacher ? null : () => _submitTeacherCheckIn('checkout'),
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.blue.shade700,
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          icon: const Icon(Icons.logout, size: 18),
                          label: Text(
                            hasOut ? 'Update Pulang' : 'Check-Out Pulang',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _statusBadge({
    required String label,
    required bool isActive,
    required Color color,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: isActive ? color.withValues(alpha: 0.12) : Colors.grey.shade100,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isActive ? color : Colors.grey.shade300,
          width: 1.5,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: isActive ? color : Colors.grey),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: isActive ? color : Colors.grey.shade700,
            ),
          ),
        ],
      ),
    );
  }

  // ================= VIEW: 2. STUDENT TAB (PRESENSI KELAS & SISWA) =================
  Widget _buildStudentTab(ThemeData theme, BoxConstraints constraints) {
    if (_isLoadingAssignments) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_assignments.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.assignment_late, size: 64, color: Colors.grey.shade400),
              const SizedBox(height: 16),
              const Text(
                'Belum Ada Penugasan Pembelajaran',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Akun Anda belum terdaftar mengajar di kelas/mapel tertentu.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey.shade600),
              ),
            ],
          ),
        ),
      );
    }

    // Attendance summary counts
    int hadirCount = 0;
    int sakitCount = 0;
    int izinCount = 0;
    int alfaCount = 0;
    for (var s in _students) {
      if (s.status == 'hadir') hadirCount++;
      if (s.status == 'sakit') sakitCount++;
      if (s.status == 'izin') izinCount++;
      if (s.status == 'alfa') alfaCount++;
    }

    return Column(
      children: [
        // Filter Header & Controls
        Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
          color: theme.colorScheme.surface,
          child: Column(
            children: [
              // Class & Subject Selector
              DropdownButtonFormField<TeachingAssignmentModel>(
                initialValue: _selectedAssignment,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: 'Kelas & Mata Pelajaran',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                ),
                items: _assignments.map((a) {
                  return DropdownMenuItem(
                    value: a,
                    child: Text(
                      a.displayName,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                  );
                }).toList(),
                onChanged: (val) {
                  setState(() => _selectedAssignment = val);
                  _loadStudentsForSession();
                },
              ),
              const SizedBox(height: 8),

              // Date & Meeting No
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _selectedDate,
                          firstDate: DateTime(2025),
                          lastDate: DateTime(2028),
                        );
                        if (picked != null) {
                          setState(() => _selectedDate = picked);
                          _loadStudentsForSession();
                        }
                      },
                      style: OutlinedButton.styleFrom(
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                      icon: const Icon(Icons.calendar_today, size: 15),
                      label: Text(
                        _formatDateYmd(_selectedDate),
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade400),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Pert: ', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          Text('$_meetingNo', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                          Row(
                            children: [
                              InkWell(
                                onTap: _meetingNo > 1
                                    ? () {
                                        setState(() => _meetingNo--);
                                        _loadStudentsForSession();
                                      }
                                    : null,
                                child: const Icon(Icons.remove_circle_outline, size: 20),
                              ),
                              const SizedBox(width: 4),
                              InkWell(
                                onTap: () {
                                  setState(() => _meetingNo++);
                                  _loadStudentsForSession();
                                },
                                child: const Icon(Icons.add_circle_outline, size: 20),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              // MANDATORY TEACHER CLASS CHECK-IN BANNER
              _buildTeacherClassStatusBanner(theme),
              const SizedBox(height: 6),

              // Quick Bulk Actions (Set All Hadir)
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Total Siswa: ${_students.length}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                  TextButton.icon(
                    onPressed: (!_isTeacherInClass || _students.isEmpty) ? null : () => _setAllStudentsStatus('hadir'),
                    style: TextButton.styleFrom(padding: EdgeInsets.zero, visualDensity: VisualDensity.compact),
                    icon: Icon(
                      Icons.done_all,
                      size: 16,
                      color: _isTeacherInClass ? Colors.green : Colors.grey,
                    ),
                    label: Text(
                      'Set Semua Hadir',
                      style: TextStyle(
                        color: _isTeacherInClass ? Colors.green : Colors.grey,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        const Divider(height: 1),

        // Students List
        Expanded(
          child: _isLoadingStudents
              ? const Center(child: CircularProgressIndicator())
              : _students.isEmpty
                  ? Center(
                      child: Text(
                        'Tidak ada siswa terdaftar di kelas ini.',
                        style: TextStyle(color: Colors.grey.shade600),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
                      itemCount: _students.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 8),
                      itemBuilder: (ctx, idx) {
                        final student = _students[idx];
                        return _buildStudentCard(student, idx + 1);
                      },
                    ),
        ),

        // Sticky Bottom Save Panel
        _buildBottomSavePanel(hadirCount, sakitCount, izinCount, alfaCount),
      ],
    );
  }

  /// Banner requirement: Guru belum bisa absen siswa sebelum absen masuk kelas, dan harus ada jadwal
  Widget _buildTeacherClassStatusBanner(ThemeData theme) {
    if (_isTeacherInClass) {
      final timeDisplay = _teacherClassTimeIn != null && _teacherClassTimeIn!.length >= 5
          ? _teacherClassTimeIn!.substring(0, 5)
          : 'Tercatat';
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: const Color(0xFFF0FDF4),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFBBF7D0)),
        ),
        child: Row(
          children: [
            const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Guru Sudah Absen Masuk Kelas',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF166534)),
                  ),
                  Text(
                    'Pukul $timeDisplay WIB • ${_scheduleInfo ?? "Jadwal Aktif"} • Presensi aktif',
                    style: const TextStyle(fontSize: 11, color: Color(0xFF15803D)),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: const Color(0xFFDCFCE7),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                'AKTIF',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF166534)),
              ),
            ),
          ],
        ),
      );
    }

    if (!_hasSchedule) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF2F2),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFFECACA)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.event_busy_rounded, color: Color(0xFFDC2626), size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Tidak Ada Jadwal KBM Hari $_scheduleDayName',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF991B1B)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Absen masuk kelas terkunci karena tidak ada jadwal KBM terdaftar untuk ${_selectedAssignment?.displayName ?? "mapel ini"} pada hari $_scheduleDayName.',
              style: const TextStyle(fontSize: 11, color: Color(0xFF7F1D1D)),
            ),
            if (_otherSchedules.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                'Jadwal di hari lain:\n• ${_otherSchedules.join("\n• ")}',
                style: const TextStyle(fontSize: 10, color: Color(0xFFB91C1C), fontWeight: FontWeight.w600),
              ),
            ],
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () => _showMustCheckInDialog(),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.grey.shade400,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                icon: const Icon(Icons.block, size: 16, color: Colors.white),
                label: const Text(
                  'Terkunci (Harus Sesuai Jadwal KBM)',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.white),
                ),
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.lock_clock_rounded, color: Color(0xFFD97706), size: 18),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Jadwal KBM Ditemukan (${_scheduleInfo ?? "Hari $_scheduleDayName"})',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF92400E)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Guru wajib melakukan presensi masuk kelas sesuai jadwal terlebih dahulu sebelum mendata kehadiran siswa.',
            style: TextStyle(fontSize: 11, color: Color(0xFF78350F)),
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _isCheckingInClass ? null : _submitTeacherClassCheckIn,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFD97706),
                padding: const EdgeInsets.symmetric(vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              icon: _isCheckingInClass
                  ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Icon(Icons.login_rounded, size: 16),
              label: Text(
                _isCheckingInClass ? 'Mencatat Kehadiran...' : 'Absen Masuk Kelas Sekarang',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStudentCard(StudentAttendanceItem student, int index) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 1,
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 13,
                  backgroundColor: Colors.blue.shade50,
                  child: Text(
                    '$index',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.blue.shade900),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        student.name,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      Text(
                        'NIS: ${student.nis.isNotEmpty ? student.nis : '-'} • ${student.gender == 'L' ? 'L' : 'P'}',
                        style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                      ),
                    ],
                  ),
                ),
                if (!_isTeacherInClass)
                  const Icon(Icons.lock_rounded, size: 16, color: Color(0xFFD97706)),
              ],
            ),
            const SizedBox(height: 8),

            // Status selection chips
            Row(
              children: [
                _attendanceChip(student, 'hadir', 'H', Colors.green),
                const SizedBox(width: 6),
                _attendanceChip(student, 'sakit', 'S', Colors.blue),
                const SizedBox(width: 6),
                _attendanceChip(student, 'izin', 'I', Colors.orange),
                const SizedBox(width: 6),
                _attendanceChip(student, 'alfa', 'A', Colors.red),
              ],
            ),

            if (_isTeacherInClass && student.status != 'hadir') ...[
              const SizedBox(height: 8),
              TextField(
                onChanged: (val) => student.notes = val,
                controller: TextEditingController(text: student.notes)..selection = TextSelection.collapsed(offset: student.notes.length),
                decoration: InputDecoration(
                  hintText: 'Keterangan tidak hadir (opsional)',
                  isDense: true,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                ),
                style: const TextStyle(fontSize: 12),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _attendanceChip(StudentAttendanceItem student, String code, String label, Color color) {
    final isSelected = student.status == code;
    return Expanded(
      child: InkWell(
        onTap: !_isTeacherInClass
            ? () => _showMustCheckInDialog()
            : () {
                setState(() {
                  student.status = code;
                });
              },
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 7),
          decoration: BoxDecoration(
            color: !_isTeacherInClass
                ? Colors.grey.shade100
                : isSelected
                    ? color
                    : color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: !_isTeacherInClass
                  ? Colors.grey.shade300
                  : isSelected
                      ? color
                      : color.withValues(alpha: 0.3),
              width: isSelected ? 1.5 : 1.0,
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            label, // 'H', 'S', 'I', 'A'
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: !_isTeacherInClass
                  ? Colors.grey.shade400
                  : isSelected
                      ? Colors.white
                      : color,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBottomSavePanel(int hadir, int sakit, int izin, int alfa) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            offset: const Offset(0, -3),
            blurRadius: 8,
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Status counts
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _countBadge('Hadir', hadir, Colors.green),
                _countBadge('Sakit', sakit, Colors.blue),
                _countBadge('Izin', izin, Colors.orange),
                _countBadge('Alfa', alfa, Colors.red),
              ],
            ),
            const SizedBox(height: 6),

            // WA Notification Checkbox (Pending / dinonaktifkan sementara)
            // Row(
            //   children: [
            //     Checkbox(value: _queueWhatsapp, onChanged: (val) => setState(() => _queueWhatsapp = val ?? false)),
            //     const Text('Kirim WA otomatis ke wali santri tidak hadir'),
            //   ],
            // ),
            const SizedBox(height: 8),

            // Save Button
            SizedBox(
              width: double.infinity,
              height: 46,
              child: FilledButton.icon(
                onPressed: (!_isTeacherInClass || _isSavingStudents)
                    ? (!_isTeacherInClass ? _showMustCheckInDialog : null)
                    : _saveStudentAttendance,
                style: FilledButton.styleFrom(
                  backgroundColor: _isTeacherInClass ? Colors.blue.shade700 : Colors.grey.shade400,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: _isSavingStudents
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : Icon(_isTeacherInClass ? Icons.save : Icons.lock_rounded, size: 18),
                label: Text(
                  _isSavingStudents
                      ? 'Menyimpan...'
                      : _isTeacherInClass
                          ? 'Simpan Presensi Kelas'
                          : 'Terkunci (Absen Masuk Kelas Dahulu)',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _countBadge(String label, int count, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(shape: BoxShape.circle, color: color),
        ),
        const SizedBox(width: 4),
        Text(
          '$label: $count',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: color.shade800 ?? color),
        ),
      ],
    );
  }
}

extension ColorShade on Color {
  Color? get shade800 => this is MaterialColor ? (this as MaterialColor)[800] : this;
}
