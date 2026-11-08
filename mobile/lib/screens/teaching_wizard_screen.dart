import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/teaching_assignment.dart';
import '../models/attendance_models.dart';
import '../services/api_service.dart';

class TeachingWizardScreen extends StatefulWidget {
  final int? assignmentId;
  final int? scheduleId;
  final String? date;
  final int? meetingNo;
  final int initialStep;

  const TeachingWizardScreen({
    super.key,
    this.assignmentId,
    this.scheduleId,
    this.date,
    this.meetingNo,
    this.initialStep = 1,
  });

  @override
  State<TeachingWizardScreen> createState() => _TeachingWizardScreenState();
}

class _TeachingWizardScreenState extends State<TeachingWizardScreen> {
  final ApiService _api = ApiService();

  bool _isLoading = true;
  int _currentStep = 1; // 1: Masuk Kelas, 2: Presensi Siswa, 3: Jurnal Mengajar

  // Assignment & Session info
  List<TeachingAssignmentModel> _assignments = [];
  TeachingAssignmentModel? _selectedAssignment;
  late DateTime _selectedDate;
  int _meetingNo = 1;

  // Step 1: Teacher Classroom Check-in
  bool _isTeacherCheckedIn = false;
  String? _teacherTimeIn;
  String _teacherStatus = 'hadir';
  final TextEditingController _teacherNotesController = TextEditingController();
  bool _isSubmittingCheckin = false;
  String? _scheduleInfo;
  bool _hasSchedule = true;
  String _scheduleDayName = '';
  List<String> _otherSchedules = [];

  // Step 2: Student Attendance
  List<StudentAttendanceItem> _students = [];
  bool _isLoadingStudents = false;
  bool _isSubmittingAttendance = false;
  bool _hasSavedAttendance = false;

  // Step 3: Teaching Journal
  final TextEditingController _topicController = TextEditingController();
  final TextEditingController _activitiesController = TextEditingController();
  final TextEditingController _materialsController = TextEditingController();
  final TextEditingController _obstaclesController = TextEditingController();
  final TextEditingController _followUpController = TextEditingController();
  int? _existingJournalId;
  bool _isJournalSaved = false;
  bool _isSubmittingJournal = false;

  @override
  void initState() {
    super.initState();
    _currentStep = widget.initialStep;
    if (widget.date != null && widget.date!.isNotEmpty) {
      try {
        _selectedDate = DateTime.parse(widget.date!);
      } catch (_) {
        _selectedDate = DateTime.now();
      }
    } else {
      _selectedDate = DateTime.now();
    }
    if (widget.meetingNo != null && widget.meetingNo! > 0) {
      _meetingNo = widget.meetingNo!;
    }
    _initData();
  }

  @override
  void dispose() {
    _teacherNotesController.dispose();
    _topicController.dispose();
    _activitiesController.dispose();
    _materialsController.dispose();
    _obstaclesController.dispose();
    _followUpController.dispose();
    super.dispose();
  }

  static const _days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  static const _months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
  ];

  String _formatDateYmd(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _formatDateDisplay(DateTime d) {
    final dayName = (d.weekday >= 1 && d.weekday <= 7) ? _days[d.weekday - 1] : '';
    final monthName = (d.month >= 1 && d.month <= 12) ? _months[d.month - 1] : '';
    return '$dayName, ${d.day} $monthName ${d.year}';
  }

  Future<void> _initData() async {
    setState(() => _isLoading = true);

    try {
      // 1. Fetch teacher's teaching assignments
      final assignments = await _api.getTeachingAssignments();
      _assignments = assignments;

      if (_assignments.isNotEmpty) {
        if (widget.assignmentId != null && widget.assignmentId! > 0) {
          _selectedAssignment = _assignments.firstWhere(
            (a) => a.id == widget.assignmentId,
            orElse: () => _assignments.first,
          );
        } else {
          _selectedAssignment = _assignments.first;
        }
      }

      if (_selectedAssignment != null) {
        await _loadSessionAndStudents();
        await _loadJournal();
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadSessionAndStudents() async {
    if (_selectedAssignment == null) return;
    setState(() => _isLoadingStudents = true);

    try {
      final res = await _api.getStudentsForAttendance(
        assignmentId: _selectedAssignment!.id,
        date: _formatDateYmd(_selectedDate),
        meetingNo: _meetingNo,
      );

      if (res['success'] == true) {
        _students = (res['students'] as List<StudentAttendanceItem>?) ?? [];
        _meetingNo = res['meeting_no'] as int? ?? _meetingNo;
        _isTeacherCheckedIn = res['teacher_checked_in'] == true;
        _teacherTimeIn = res['teacher_time_in']?.toString();
        _teacherStatus = res['teacher_status']?.toString() ?? 'hadir';
        _hasSchedule = res['has_schedule'] == true;
        _scheduleInfo = res['schedule_info']?.toString();
        _scheduleDayName = res['day_name']?.toString() ?? '';
        final otherRaw = res['other_schedules'] as List<dynamic>?;
        _otherSchedules = otherRaw?.map((e) => e.toString()).toList() ?? [];

        // Check if any student attendance has been recorded
        _hasSavedAttendance = _students.any((s) => s.notes.isNotEmpty || s.status != 'hadir') || _isTeacherCheckedIn;

        // Auto determine recommended step if starting from default
        if (widget.initialStep == 1) {
          if (!_isTeacherCheckedIn) {
            _currentStep = 1;
          } else if (!_hasSavedAttendance) {
            _currentStep = 2;
          }
        }
      }
    } catch (_) {}

    if (mounted) setState(() => _isLoadingStudents = false);
  }

  Future<void> _loadJournal() async {
    if (_selectedAssignment == null) return;
    try {
      final journals = await _api.getJournals(assignmentId: _selectedAssignment!.id);
      final todayYmd = _formatDateYmd(_selectedDate);
      final j = journals.where((j) => j.date == todayYmd && j.meetingNo == _meetingNo).firstOrNull;

      if (j != null) {
        _existingJournalId = j.id;
        _topicController.text = j.topic;
        _activitiesController.text = j.activities;
        _materialsController.text = j.materials;
        _obstaclesController.text = j.obstacles;
        _followUpController.text = j.followUp;
        _isJournalSaved = true;
      }
    } catch (_) {}
  }

  // --- SUBMISSIONS ---

  Future<void> _submitTeacherCheckin() async {
    if (_selectedAssignment == null) return;
    setState(() => _isSubmittingCheckin = true);

    final res = await _api.teacherClassCheckIn(
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
      status: _teacherStatus,
    );

    setState(() => _isSubmittingCheckin = false);

    if (!mounted) return;

    if (res['success'] == true) {
      setState(() {
        _isTeacherCheckedIn = true;
        _teacherTimeIn = res['teacher_time_in'] ?? DateFormat('HH:mm:ss').format(DateTime.now());
        _currentStep = 2; // Auto advance to Step 2
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('✓ ${res['message']} Lanjut ke absensi siswa.'),
          backgroundColor: const Color(0xFF16A34A),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Gagal absen masuk kelas.'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
    }
  }

  Future<void> _submitStudentAttendance() async {
    if (_selectedAssignment == null) return;
    if (!_isTeacherCheckedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Guru harus melakukan absen masuk kelas terlebih dahulu!'),
          backgroundColor: Color(0xFFDC2626),
        ),
      );
      setState(() => _currentStep = 1);
      return;
    }

    setState(() => _isSubmittingAttendance = true);

    final res = await _api.saveStudentAttendance(
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
      topic: _topicController.text.trim().isEmpty ? 'Presensi KBM Pertemuan $_meetingNo' : _topicController.text.trim(),
      queueWhatsapp: false,
      students: _students,
    );

    setState(() => _isSubmittingAttendance = false);

    if (!mounted) return;

    if (res['success'] == true) {
      setState(() {
        _hasSavedAttendance = true;
        _currentStep = 3; // Auto advance to Step 3
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('✓ ${res['message']} Lanjut mengisi jurnal mengajar.'),
          backgroundColor: const Color(0xFF16A34A),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Gagal menyimpan absensi.'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
    }
  }

  Future<void> _submitJournal() async {
    if (_selectedAssignment == null) return;
    if (_topicController.text.trim().isEmpty || _activitiesController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Topik/materi dan kegiatan pembelajaran wajib diisi!'),
          backgroundColor: Color(0xFFDC2626),
        ),
      );
      return;
    }

    setState(() => _isSubmittingJournal = true);

    final res = await _api.saveJournal(
      id: _existingJournalId,
      assignmentId: _selectedAssignment!.id,
      date: _formatDateYmd(_selectedDate),
      meetingNo: _meetingNo,
      topic: _topicController.text.trim(),
      activities: _activitiesController.text.trim(),
      materials: _materialsController.text.trim(),
      obstacles: _obstaclesController.text.trim(),
      followUp: _followUpController.text.trim(),
    );

    setState(() => _isSubmittingJournal = false);

    if (!mounted) return;

    if (res['success'] == true) {
      setState(() => _isJournalSaved = true);
      _showCompletionDialog();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Gagal menyimpan jurnal.'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
    }
  }

  void _showCompletionDialog() {
    int hadir = _students.where((s) => s.status == 'hadir').length;
    int berhalangan = _students.length - hadir;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 28),
              SizedBox(width: 10),
              Text('KBM Selesai Lengkap! 🎉', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Alur mengajar di kelas ${_selectedAssignment?.displayName ?? ""} berhasil diselesaikan:',
                style: const TextStyle(fontSize: 13, color: Color(0xFF334155)),
              ),
              const SizedBox(height: 12),
              _buildSummaryCheckRow('1. Absen Masuk Guru', 'Tercatat pukul ${_teacherTimeIn?.substring(0, 5) ?? "Hadir"} WIB'),
              _buildSummaryCheckRow('2. Presensi Siswa', '$hadir Hadir • $berhalangan Berhalangan'),
              _buildSummaryCheckRow('3. Jurnal KBM', 'Materi: ${_topicController.text.trim()}'),
              const SizedBox(height: 14),
              const Text(
                'Seluruh data langsung sinkron dengan rekap kehadiran dan pemantauan live web.',
                style: TextStyle(fontSize: 11, color: Color(0xFF64748B), fontStyle: FontStyle.italic),
              ),
            ],
          ),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.of(ctx).pop();
                Navigator.of(context).pop();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2563EB),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Kembali ke Beranda'),
            ),
          ],
        );
      },
    );
  }

  Widget _buildSummaryCheckRow(String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_rounded, color: Color(0xFF16A34A), size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // --- BUILD METHODS ---

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Alur Mengajar Kelas Terpandu', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF0F172A),
          elevation: 0.5,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_assignments.isEmpty) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Alur Mengajar Kelas Terpandu', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF0F172A),
          elevation: 0.5,
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.assignment_late_rounded, size: 64, color: Color(0xFF94A3B8)),
                const SizedBox(height: 16),
                const Text('Tidak Ada Pembelajaran', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF1E293B))),
                const SizedBox(height: 8),
                const Text(
                  'Akun Anda belum terdaftar mengajar di kelas atau mata pelajaran semester ini.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: () => Navigator.pop(context),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF2563EB),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: const Text('Kembali ke Beranda'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Alur Mengajar Kelas Terpandu', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            Text('Absen Masuk • Presensi Siswa • Jurnal', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
          ],
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
      ),
      body: Column(
        children: [
          // 1. Class & Meeting Selector Header
          _buildClassHeader(),

          // 2. Stepper Nav (3 Steps)
          _buildStepperNav(),

          const Divider(height: 1, color: Color(0xFFE2E8F0)),

          // 3. Step Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: _buildCurrentStepContent(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildClassHeader() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Column(
        children: [
          // Dropdown Penugasan
          DropdownButtonFormField<TeachingAssignmentModel>(
            initialValue: _selectedAssignment,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Kelas & Mata Pelajaran',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
            items: _assignments.map((a) {
              return DropdownMenuItem(
                value: a,
                child: Text(a.displayName, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
              );
            }).toList(),
            onChanged: (val) {
              if (val != null) {
                setState(() => _selectedAssignment = val);
                _loadSessionAndStudents();
                _loadJournal();
              }
            },
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              // Tanggal
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
                      _loadSessionAndStudents();
                      _loadJournal();
                    }
                  },
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.calendar_today_rounded, size: 14),
                  label: Text(
                    DateFormat('d MMM yyyy').format(_selectedDate),
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              // Pertemuan ke-
              Expanded(
                flex: 2,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFCBD5E1)),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Pert ke-', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                      Text('$_meetingNo', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                      Row(
                        children: [
                          InkWell(
                            onTap: _meetingNo > 1
                                ? () {
                                    setState(() => _meetingNo--);
                                    _loadSessionAndStudents();
                                    _loadJournal();
                                  }
                                : null,
                            child: const Icon(Icons.remove_circle_outline, size: 18),
                          ),
                          const SizedBox(width: 4),
                          InkWell(
                            onTap: () {
                              setState(() => _meetingNo++);
                              _loadSessionAndStudents();
                              _loadJournal();
                            },
                            child: const Icon(Icons.add_circle_outline, size: 18),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStepperNav() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(14, 4, 14, 12),
      child: Row(
        children: [
          _buildStepCard(
            stepNum: 1,
            label: '1. Masuk Kelas',
            isCompleted: _isTeacherCheckedIn,
            isActive: _currentStep == 1,
            onTap: () => setState(() => _currentStep = 1),
          ),
          const SizedBox(width: 6),
          _buildStepCard(
            stepNum: 2,
            label: '2. Absen Siswa',
            isCompleted: _hasSavedAttendance,
            isActive: _currentStep == 2,
            onTap: () => setState(() => _currentStep = 2),
          ),
          const SizedBox(width: 6),
          _buildStepCard(
            stepNum: 3,
            label: '3. Jurnal KBM',
            isCompleted: _isJournalSaved,
            isActive: _currentStep == 3,
            onTap: () => setState(() => _currentStep = 3),
          ),
        ],
      ),
    );
  }

  Widget _buildStepCard({
    required int stepNum,
    required String label,
    required bool isCompleted,
    required bool isActive,
    required VoidCallback onTap,
  }) {
    Color border = const Color(0xFFE2E8F0);
    Color bg = Colors.white;
    Color textCol = const Color(0xFF64748B);

    if (isActive) {
      border = const Color(0xFF2563EB);
      bg = const Color(0xFFEFF6FF);
      textCol = const Color(0xFF2563EB);
    } else if (isCompleted) {
      border = const Color(0xFF86EFAC);
      bg = const Color(0xFFF0FDF4);
      textCol = const Color(0xFF16A34A);
    }

    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: border, width: isActive ? 2 : 1),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (isCompleted)
                const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 16)
              else
                CircleAvatar(
                  radius: 8,
                  backgroundColor: isActive ? const Color(0xFF2563EB) : const Color(0xFFCBD5E1),
                  child: Text(
                    '$stepNum',
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: isActive || isCompleted ? FontWeight.bold : FontWeight.w500,
                    color: textCol,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCurrentStepContent() {
    switch (_currentStep) {
      case 1:
        return _buildStep1Content();
      case 2:
        return _buildStep2Content();
      case 3:
        return _buildStep3Content();
      default:
        return _buildStep1Content();
    }
  }

  // ================= STEP 1: ABSEN MASUK KELAS =================
  Widget _buildStep1Content() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_isTeacherCheckedIn) ...[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFBBF7D0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: const BoxDecoration(
                        color: Color(0xFF16A34A),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.check_rounded, color: Colors.white, size: 20),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Guru Terkonfirmasi Hadir di Kelas',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF166534)),
                          ),
                          Text(
                            'Pukul Masuk: ${_teacherTimeIn != null && _teacherTimeIn!.length >= 5 ? _teacherTimeIn!.substring(0, 5) : "Tercatat"} WIB • Status: ${ucfirst(_teacherStatus)}',
                            style: const TextStyle(fontSize: 12, color: Color(0xFF15803D)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => setState(() => _currentStep = 2),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF16A34A),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    icon: const Icon(Icons.groups_rounded, size: 18),
                    label: const Text('Lanjut ke Langkah 2: Absensi Siswa ➜', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Petunjuk & Form Absen Masuk Kelas
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.person_pin_circle_rounded, color: Color(0xFF2563EB), size: 22),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Presensi Masuk Mengajar',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          'Konfirmasi bahwa Anda sudah berada di ruang kelas.',
                          style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 24),
              if (_hasSchedule && _scheduleInfo != null) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFBBF7D0)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle_outline_rounded, size: 16, color: Color(0xFF16A34A)),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Sesuai Jadwal: $_scheduleInfo',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF15803D)),
                        ),
                      ),
                    ],
                  ),
                ),
              ] else if (!_hasSchedule && _scheduleDayName.isNotEmpty) ...[
                Container(
                  padding: const EdgeInsets.all(10),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFBFDBFE)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.info_outline_rounded, size: 16, color: Color(0xFF2563EB)),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _otherSchedules.isNotEmpty
                                  ? 'Jadwal Reguler: ${_otherSchedules.join(' & ')}'
                                  : 'Hari ini ($_scheduleDayName) di luar jam reguler.',
                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF1D4ED8)),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Anda tetap dapat melakukan presensi & pengisian jurnal jika mengajar jam tambahan atau jam pengganti (inval).',
                        style: TextStyle(fontSize: 10.5, color: Colors.blue.shade900),
                      ),
                    ],
                  ),
                ),
              ],
              // Info Kelas & Jadwal
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  children: [
                    _buildInfoRow('Mata Pelajaran:', _selectedAssignment?.subjectName ?? '-'),
                    const SizedBox(height: 6),
                    _buildInfoRow('Kelas & Rombel:', _selectedAssignment?.className ?? '-'),
                    const SizedBox(height: 6),
                    _buildInfoRow('Pertemuan:', 'Pertemuan ke-$_meetingNo'),
                    const SizedBox(height: 6),
                    _buildInfoRow('Waktu Hari Ini:', _formatDateDisplay(_selectedDate)),
                    if (_scheduleInfo != null && _scheduleInfo!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      _buildInfoRow('Jadwal KBM:', _scheduleInfo!),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Status Kehadiran Guru Selector
              const Text('Status Kehadiran Mengajar:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: ChoiceChip(
                      label: const Center(child: Text('Hadir Mengajar')),
                      selected: _teacherStatus == 'hadir',
                      selectedColor: const Color(0xFF16A34A),
                      labelStyle: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: _teacherStatus == 'hadir' ? Colors.white : const Color(0xFF334155),
                      ),
                      onSelected: (_) => setState(() => _teacherStatus = 'hadir'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ChoiceChip(
                      label: const Center(child: Text('Inval (Pengganti)')),
                      selected: _teacherStatus == 'inval',
                      selectedColor: const Color(0xFFD97706),
                      labelStyle: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: _teacherStatus == 'inval' ? Colors.white : const Color(0xFF334155),
                      ),
                      onSelected: (_) => setState(() => _teacherStatus = 'inval'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Button Check-in
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: _isSubmittingCheckin ? null : _submitTeacherCheckin,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF2563EB),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: _isSubmittingCheckin
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Icon(Icons.touch_app_rounded, size: 20),
                  label: Text(
                    _isTeacherCheckedIn ? 'Perbarui Absen Masuk Kelas' : 'Konfirmasi Masuk Kelas Sekarang',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ================= STEP 2: ABSENSI SISWA =================
  Widget _buildStep2Content() {
    int hadir = _students.where((s) => s.status == 'hadir').length;
    int sakit = _students.where((s) => s.status == 'sakit').length;
    int izin = _students.where((s) => s.status == 'izin').length;
    int alfa = _students.where((s) => s.status == 'alfa').length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Summary & Quick actions
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Daftar Hadir Siswa', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                      Text('Total: ${_students.length} Siswa', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                    ],
                  ),
                  OutlinedButton.icon(
                    onPressed: () {
                      setState(() {
                        for (var s in _students) {
                          s.status = 'hadir';
                        }
                      });
                    },
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    icon: const Icon(Icons.done_all_rounded, size: 16, color: Color(0xFF16A34A)),
                    label: const Text('Set Semua Hadir', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF16A34A))),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              // Counters Row
              Row(
                children: [
                  _buildCountBadge('Hadir', hadir, const Color(0xFF16A34A), const Color(0xFFDCFCE7)),
                  const SizedBox(width: 6),
                  _buildCountBadge('Sakit', sakit, const Color(0xFFD97706), const Color(0xFFFEF3C7)),
                  const SizedBox(width: 6),
                  _buildCountBadge('Izin', izin, const Color(0xFF2563EB), const Color(0xFFDBEAFE)),
                  const SizedBox(width: 6),
                  _buildCountBadge('Alfa', alfa, const Color(0xFFDC2626), const Color(0xFFFEE2E2)),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        // Students List
        if (_isLoadingStudents)
          const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()))
        else if (_students.isEmpty)
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Center(
              child: Text('Tidak ada siswa terdaftar pada kelas ini.', style: TextStyle(color: Color(0xFF64748B))),
            ),
          )
        else
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _students.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (ctx, idx) {
              final student = _students[idx];
              return _buildStudentItemCard(student, idx + 1);
            },
          ),

        const SizedBox(height: 16),

        // Action Buttons: Save & Advance
        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton.icon(
            onPressed: _isSubmittingAttendance ? null : _submitStudentAttendance,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF2563EB),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            icon: _isSubmittingAttendance
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.arrow_forward_rounded, size: 20),
            label: const Text(
              'Simpan Presensi & Lanjut ke Jurnal (Langkah 3) ➜',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildCountBadge(String label, int count, Color col, Color bg) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          children: [
            Text('$count', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: col)),
            Text(label, style: TextStyle(fontSize: 10, color: col)),
          ],
        ),
      ),
    );
  }

  Widget _buildStudentItemCard(StudentAttendanceItem student, int no) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 12,
                backgroundColor: const Color(0xFFF1F5F9),
                child: Text('$no', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569))),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.name,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                    ),
                    Text(
                      'NIS: ${student.nis.isNotEmpty ? student.nis : "-"} • ${student.gender == "L" ? "Laki-laki" : "Perempuan"}',
                      style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          // H / S / I / A Toggle Buttons
          Row(
            children: [
              _buildAttendanceButton(student, 'hadir', 'Hadir', const Color(0xFF16A34A)),
              const SizedBox(width: 6),
              _buildAttendanceButton(student, 'sakit', 'Sakit', const Color(0xFFD97706)),
              const SizedBox(width: 6),
              _buildAttendanceButton(student, 'izin', 'Izin', const Color(0xFF2563EB)),
              const SizedBox(width: 6),
              _buildAttendanceButton(student, 'alfa', 'Alfa', const Color(0xFFDC2626)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAttendanceButton(StudentAttendanceItem student, String val, String label, Color color) {
    final isSelected = student.status == val;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => student.status = val),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 6),
          decoration: BoxDecoration(
            color: isSelected ? color : const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: isSelected ? color : const Color(0xFFE2E8F0)),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              color: isSelected ? Colors.white : const Color(0xFF475569),
            ),
          ),
        ),
      ),
    );
  }

  // ================= STEP 3: JURNAL MENGAJAR =================
  Widget _buildStep3Content() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF8B5CF6).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.menu_book_rounded, color: Color(0xFF8B5CF6), size: 22),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Jurnal Mengajar Harian KBM',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          'Tuliskan materi dan kegiatan belajar yang telah terlaksana.',
                          style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 24),

              // Materi / Topik
              const Text('Topik / Materi Pembelajaran: *', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 6),
              TextField(
                controller: _topicController,
                decoration: InputDecoration(
                  hintText: 'Contoh: Operasi Hitung Aljabar / Thaharah & Najis',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              const SizedBox(height: 14),

              // Kegiatan Pembelajaran
              const Text('Kegiatan Pembelajaran: *', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 6),
              TextField(
                controller: _activitiesController,
                maxLines: 4,
                decoration: InputDecoration(
                  hintText: 'Contoh:\n1. Apersepsi dan penjelasan materi\n2. Diskusi kelompok dan latihan soal\n3. Tanya jawab dan refleksi bersama',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.all(12),
                ),
              ),
              const SizedBox(height: 14),

              // Media / Bahan Ajar
              const Text('Media / Sumber Belajar (Opsional):', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 6),
              TextField(
                controller: _materialsController,
                decoration: InputDecoration(
                  hintText: 'Contoh: Buku Paket hal 45, LKPD, Video Animasi',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              const SizedBox(height: 14),

              // Hambatan / Kendala
              const Text('Hambatan / Kendala Pembelajaran (Opsional):', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 6),
              TextField(
                controller: _obstaclesController,
                decoration: InputDecoration(
                  hintText: 'Contoh: Sebagian siswa belum memahami konsep variabel',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              const SizedBox(height: 14),

              // Rencana Tindak Lanjut
              const Text('Rencana Tindak Lanjut (Opsional):', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
              const SizedBox(height: 6),
              TextField(
                controller: _followUpController,
                decoration: InputDecoration(
                  hintText: 'Contoh: Remedial singkat di awal pertemuan selanjutnya',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
              const SizedBox(height: 20),

              // Save Button
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: _isSubmittingJournal ? null : _submitJournal,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF16A34A),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: _isSubmittingJournal
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Icon(Icons.check_circle_rounded, size: 20),
                  label: const Text(
                    'Simpan & Tuntaskan KBM 🎉',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
          ),
        ),
      ],
    );
  }

  String ucfirst(String s) => s.isNotEmpty ? s[0].toUpperCase() + s.substring(1) : s;
}
