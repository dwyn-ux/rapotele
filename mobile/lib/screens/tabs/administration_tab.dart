import 'package:flutter/material.dart';
import '../../models/teaching_assignment.dart';
import '../../models/journal_model.dart';
import '../../models/grade_models.dart';
import '../../services/api_service.dart';

class AdministrationTab extends StatefulWidget {
  final int initialSubTab;

  const AdministrationTab({
    super.key,
    this.initialSubTab = 0,
  });

  @override
  State<AdministrationTab> createState() => _AdministrationTabState();
}

class _AdministrationTabState extends State<AdministrationTab> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _api = ApiService();

  bool _isLoadingAssignments = false;
  List<TeachingAssignmentModel> _assignments = [];

  // ================= JURNAL STATE =================
  TeachingAssignmentModel? _journalAssignment;
  DateTime _journalDate = DateTime.now();
  int _journalMeetingNo = 1;
  final TextEditingController _topicController = TextEditingController();
  final TextEditingController _activitiesController = TextEditingController();
  final TextEditingController _materialsController = TextEditingController();
  final TextEditingController _obstaclesController = TextEditingController();
  final TextEditingController _followUpController = TextEditingController();
  bool _isSavingJournal = false;
  bool _isLoadingJournalsList = false;
  List<JournalModel> _journalsList = [];
  bool _showJournalHistory = false;

  // ================= NILAI STATE =================
  TeachingAssignmentModel? _gradeAssignment;
  String _assessmentType = 'UH';
  final List<String> _assessmentTypes = ['UH', 'Formatif', 'Sumatif', 'PTS', 'PAS'];
  LearningObjectiveModel? _selectedObjective;
  List<LearningObjectiveModel> _objectives = [];
  bool _isLoadingGrades = false;
  bool _isSavingGrades = false;
  List<GradeStudentItem> _gradeStudents = [];
  final Map<int, TextEditingController> _scoreControllers = {};

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this, initialIndex: widget.initialSubTab);
    _loadAssignments();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _topicController.dispose();
    _activitiesController.dispose();
    _materialsController.dispose();
    _obstaclesController.dispose();
    _followUpController.dispose();
    for (var c in _scoreControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  String _formatTimeDigits(int n) => n.toString().padLeft(2, '0');
  String _formatDateYmd(DateTime dt) =>
      '${dt.year}-${_formatTimeDigits(dt.month)}-${_formatTimeDigits(dt.day)}';

  Future<void> _loadAssignments() async {
    setState(() => _isLoadingAssignments = true);
    final list = await _api.getTeachingAssignments();
    if (mounted) {
      setState(() {
        _isLoadingAssignments = false;
        _assignments = list;
        if (list.isNotEmpty) {
          _journalAssignment ??= list.first;
          _gradeAssignment ??= list.first;
          _loadJournalsList();
          _loadGradesData();
        }
      });
    }
  }

  // ================= JURNAL LOGIC =================
  Future<void> _loadJournalsList() async {
    setState(() => _isLoadingJournalsList = true);
    final list = await _api.getJournals(assignmentId: _journalAssignment?.id);
    if (mounted) {
      setState(() {
        _isLoadingJournalsList = false;
        _journalsList = list;
      });
    }
  }

  Future<void> _saveJournal() async {
    if (_journalAssignment == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pilih kelas pembelajaran')));
      return;
    }
    if (_topicController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Topik/materi pembelajaran wajib diisi')));
      return;
    }
    if (_activitiesController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Kegiatan pembelajaran wajib diisi')));
      return;
    }

    setState(() => _isSavingJournal = true);
    final res = await _api.saveJournal(
      assignmentId: _journalAssignment!.id,
      date: _formatDateYmd(_journalDate),
      meetingNo: _journalMeetingNo,
      topic: _topicController.text.trim(),
      activities: _activitiesController.text.trim(),
      materials: _materialsController.text.trim(),
      obstacles: _obstaclesController.text.trim(),
      followUp: _followUpController.text.trim(),
    );

    if (mounted) {
      setState(() => _isSavingJournal = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? (res['success'] ? 'Jurnal tersimpan' : 'Gagal menyimpan')),
          backgroundColor: res['success'] ? Colors.green.shade700 : Colors.red.shade700,
        ),
      );
      if (res['success'] == true) {
        _topicController.clear();
        _activitiesController.clear();
        _materialsController.clear();
        _obstaclesController.clear();
        _followUpController.clear();
        _journalMeetingNo++;
        _loadJournalsList();
      }
    }
  }

  // ================= NILAI LOGIC =================
  Future<void> _loadGradesData() async {
    if (_gradeAssignment == null) return;
    setState(() => _isLoadingGrades = true);

    final res = await _api.getGradesData(
      assignmentId: _gradeAssignment!.id,
      assessmentType: _assessmentType,
      learningObjectiveId: _selectedObjective?.id,
    );

    if (mounted) {
      setState(() {
        _isLoadingGrades = false;
        if (res['success'] == true) {
          _gradeStudents = (res['students'] as List<GradeStudentItem>?) ?? [];
          _objectives = (res['objectives'] as List<LearningObjectiveModel>?) ?? [];

          // Initialize text controllers
          for (var c in _scoreControllers.values) {
            c.dispose();
          }
          _scoreControllers.clear();
          for (var s in _gradeStudents) {
            _scoreControllers[s.id] = TextEditingController(
              text: s.score != null ? s.score!.toStringAsFixed(s.score! % 1 == 0 ? 0 : 1) : '',
            );
          }
        } else {
          _gradeStudents = [];
          _objectives = [];
        }
      });
    }
  }

  Future<void> _saveGrades() async {
    if (_gradeAssignment == null) return;
    if (_gradeStudents.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tidak ada siswa.')));
      return;
    }

    setState(() => _isSavingGrades = true);

    // Apply values from controllers to student items
    for (var s in _gradeStudents) {
      final text = _scoreControllers[s.id]?.text.trim() ?? '';
      s.score = text.isNotEmpty ? double.tryParse(text) : null;
    }

    final res = await _api.saveGrades(
      assignmentId: _gradeAssignment!.id,
      assessmentType: _assessmentType,
      learningObjectiveId: _selectedObjective?.id,
      students: _gradeStudents,
    );

    if (mounted) {
      setState(() => _isSavingGrades = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? (res['success'] ? 'Nilai tersimpan' : 'Gagal')),
          backgroundColor: res['success'] ? Colors.green.shade700 : Colors.red.shade700,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Administrasi Kelas',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          tabs: const [
            Tab(icon: Icon(Icons.menu_book), text: 'Jurnal Mengajar'),
            Tab(icon: Icon(Icons.grading), text: 'Input Nilai'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildJournalTab(),
          _buildGradesTab(),
        ],
      ),
    );
  }

  // ================= JURNAL VIEW =================
  Widget _buildJournalTab() {
    if (_isLoadingAssignments) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_assignments.isEmpty) {
      return const Center(child: Text('Belum ada kelas pembelajaran aktif.'));
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Mode Switch: Form Input vs Riwayat
        Row(
          children: [
            Expanded(
              child: SegmentedButton<bool>(
                segments: const [
                  ButtonSegment(value: false, label: Text('Tulis Jurnal Baru'), icon: Icon(Icons.edit_note)),
                  ButtonSegment(value: true, label: Text('Riwayat Jurnal'), icon: Icon(Icons.history)),
                ],
                selected: {_showJournalHistory},
                onSelectionChanged: (set) {
                  setState(() => _showJournalHistory = set.first);
                },
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),

        if (_showJournalHistory) ...[
          _buildJournalHistoryList(),
        ] else ...[
          _buildJournalForm(),
        ],
      ],
    );
  }

  Widget _buildJournalForm() {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Form Jurnal Harian KBM',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 14),

            // Class Dropdown
            DropdownButtonFormField<TeachingAssignmentModel>(
              value: _journalAssignment,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: 'Kelas & Mata Pelajaran',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
              items: _assignments.map((a) {
                return DropdownMenuItem(value: a, child: Text(a.displayName, overflow: TextOverflow.ellipsis));
              }).toList(),
              onChanged: (val) {
                setState(() => _journalAssignment = val);
                _loadJournalsList();
              },
            ),
            const SizedBox(height: 12),

            // Date and Meeting No
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: _journalDate,
                        firstDate: DateTime(2025),
                        lastDate: DateTime(2028),
                      );
                      if (picked != null) setState(() => _journalDate = picked);
                    },
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.calendar_today, size: 16),
                    label: Text(_formatDateYmd(_journalDate), style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 2,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade400),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Pert: ', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        Text('$_journalMeetingNo', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                        Row(
                          children: [
                            InkWell(
                              onTap: _journalMeetingNo > 1 ? () => setState(() => _journalMeetingNo--) : null,
                              child: const Icon(Icons.remove_circle_outline, size: 20),
                            ),
                            const SizedBox(width: 4),
                            InkWell(
                              onTap: () => setState(() => _journalMeetingNo++),
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
            const SizedBox(height: 12),

            // Topic / Material
            TextField(
              controller: _topicController,
              decoration: InputDecoration(
                labelText: 'Topik / Materi Pokok *',
                hintText: 'Contoh: Bab 2 - Teks Deskripsi Struktur Paragraf',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 12),

            // Activities
            TextField(
              controller: _activitiesController,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: 'Kegiatan Pembelajaran *',
                hintText: 'Aktivitas KBM, diskusi kelompok, presentasi materi...',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 12),

            // Materials / Media
            TextField(
              controller: _materialsController,
              decoration: InputDecoration(
                labelText: 'Media & Sumber Belajar (Opsional)',
                hintText: 'Buku Paket hal. 45, Video YouTube, LCD Proyektor',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 12),

            // Obstacles
            TextField(
              controller: _obstaclesController,
              decoration: InputDecoration(
                labelText: 'Hambatan / Catatan KBM (Opsional)',
                hintText: 'Sebagian siswa kesulitan pada kosakata teknis...',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 12),

            // Follow-up
            TextField(
              controller: _followUpController,
              decoration: InputDecoration(
                labelText: 'Rencana Tindak Lanjut (Opsional)',
                hintText: 'Penugasan latihan soal mandiri di rumah...',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 20),

            // Submit Button
            SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton.icon(
                onPressed: _isSavingJournal ? null : _saveJournal,
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.blue.shade700,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: _isSavingJournal
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.save),
                label: Text(
                  _isSavingJournal ? 'Menyimpan...' : 'Simpan Jurnal KBM',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildJournalHistoryList() {
    if (_isLoadingJournalsList) {
      return const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()));
    }

    if (_journalsList.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            children: [
              Icon(Icons.history, size: 54, color: Colors.grey.shade400),
              const SizedBox(height: 12),
              const Text('Belum ada riwayat jurnal tersimpan untuk kelas ini.'),
            ],
          ),
        ),
      );
    }

    return Column(
      children: _journalsList.map((j) {
        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.blue.shade50,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        'Pertemuan ${j.meetingNo} • ${j.date}',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.blue.shade800),
                      ),
                    ),
                    Text(
                      '${j.className} - ${j.subjectName}',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  j.topic,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
                const SizedBox(height: 4),
                Text(
                  j.activities,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 13, color: Colors.grey.shade800),
                ),
                if (j.obstacles.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    '⚠️ Kendala: ${j.obstacles}',
                    style: TextStyle(fontSize: 12, color: Colors.orange.shade800, fontStyle: FontStyle.italic),
                  ),
                ],
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  // ================= NILAI VIEW =================
  Widget _buildGradesTab() {
    if (_isLoadingAssignments) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        // Filter Controls
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          color: Theme.of(context).colorScheme.surface,
          child: Column(
            children: [
              // Class & Subject Selector
              DropdownButtonFormField<TeachingAssignmentModel>(
                value: _gradeAssignment,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: 'Kelas & Mata Pelajaran',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                ),
                items: _assignments.map((a) {
                  return DropdownMenuItem(value: a, child: Text(a.displayName, overflow: TextOverflow.ellipsis));
                }).toList(),
                onChanged: (val) {
                  setState(() => _gradeAssignment = val);
                  _loadGradesData();
                },
              ),
              const SizedBox(height: 8),

              // Assessment Types
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: _assessmentTypes.map((type) {
                    final isSel = _assessmentType == type;
                    return Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: ChoiceChip(
                        label: Text(type),
                        selected: isSel,
                        onSelected: (val) {
                          if (val) {
                            setState(() => _assessmentType = type);
                            _loadGradesData();
                          }
                        },
                      ),
                    );
                  }).toList(),
                ),
              ),

              // Learning Objective (TP) selector if available
              if (_objectives.isNotEmpty) ...[
                const SizedBox(height: 8),
                DropdownButtonFormField<LearningObjectiveModel?>(
                  value: _selectedObjective,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: 'Tujuan Pembelajaran (TP)',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                  items: [
                    const DropdownMenuItem<LearningObjectiveModel?>(
                      value: null,
                      child: Text('Semua TP / Nilai Rata-rata'),
                    ),
                    ..._objectives.map((o) => DropdownMenuItem(value: o, child: Text(o.displayName, overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (val) {
                    setState(() => _selectedObjective = val);
                    _loadGradesData();
                  },
                ),
              ],
            ],
          ),
        ),

        const Divider(height: 1),

        // Students Table / List
        Expanded(
          child: _isLoadingGrades
              ? const Center(child: CircularProgressIndicator())
              : _gradeStudents.isEmpty
                  ? Center(
                      child: Text('Tidak ada data siswa.', style: TextStyle(color: Colors.grey.shade600)),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 10, 16, 90),
                      itemCount: _gradeStudents.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (ctx, idx) {
                        final s = _gradeStudents[idx];
                        final ctrl = _scoreControllers[s.id];
                        return Card(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          elevation: 1,
                          margin: EdgeInsets.zero,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            child: Row(
                              children: [
                                CircleAvatar(
                                  radius: 14,
                                  backgroundColor: Colors.blue.shade50,
                                  child: Text('${idx + 1}', style: TextStyle(fontSize: 11, color: Colors.blue.shade900, fontWeight: FontWeight.bold)),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(s.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                      Text('NIS: ${s.nis.isNotEmpty ? s.nis : '-'}', style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                                    ],
                                  ),
                                ),
                                SizedBox(
                                  width: 80,
                                  child: TextField(
                                    controller: ctrl,
                                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                    textAlign: TextAlign.center,
                                    decoration: InputDecoration(
                                      hintText: '0-100',
                                      contentPadding: const EdgeInsets.symmetric(vertical: 8),
                                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
        ),

        // Bottom Sticky Save Button
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          color: Colors.white,
          child: SafeArea(
            top: false,
            child: SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton.icon(
                onPressed: _isSavingGrades ? null : _saveGrades,
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.blue.shade700,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: _isSavingGrades
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.save),
                label: Text(
                  _isSavingGrades ? 'Menyimpan...' : 'Simpan Nilai $_assessmentType',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

