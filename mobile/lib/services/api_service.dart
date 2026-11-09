import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../models/whatsapp_item.dart';
import '../models/notification_item.dart';
import '../models/schedule_item.dart';
import '../models/teaching_assignment.dart';
import '../models/attendance_models.dart';
import '../models/journal_model.dart';
import '../models/grade_models.dart';

class ApiService {
  static const String _defaultBaseUrl = 'http://10.49.62.121:8000';
  static const String _keyBaseUrl = 'server_base_url';
  static const String _keyToken = 'auth_token';
  static const String _keyUser = 'cached_user';
  static const String _keySchool = 'cached_school';

  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String _baseUrl = _defaultBaseUrl;
  String? _token;
  UserModel? _currentUser;
  SchoolModel? _currentSchool;
  String? _workingEndpointPrefix;

  String get baseUrl => _baseUrl;
  String? get token => _token;
  UserModel? get currentUser => _currentUser;
  SchoolModel? get currentSchool => _currentSchool;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final savedBase = prefs.getString(_keyBaseUrl);
    if (savedBase != null && savedBase.isNotEmpty) {
      if (savedBase.contains('smpmuhashidiq')) {
        _baseUrl = savedBase.replaceAll('smpmuhashidiq', 'smpmuashidiq');
        await prefs.setString(_keyBaseUrl, _baseUrl);
      } else {
        _baseUrl = savedBase;
      }
    } else {
      _baseUrl = _defaultBaseUrl;
    }

    _token = prefs.getString(_keyToken);

    final userJsonStr = prefs.getString(_keyUser);
    if (userJsonStr != null) {
      try {
        _currentUser = UserModel.fromJson(jsonDecode(userJsonStr));
      } catch (_) {}
    }

    final schoolJsonStr = prefs.getString(_keySchool);
    if (schoolJsonStr != null) {
      try {
        _currentSchool = SchoolModel.fromJson(jsonDecode(schoolJsonStr));
      } catch (_) {}
    }
  }

  Future<void> setBaseUrl(String url) async {
    var cleanUrl = url.trim();
    if (cleanUrl.endsWith('/')) {
      cleanUrl = cleanUrl.substring(0, cleanUrl.length - 1);
    }
    _baseUrl = cleanUrl;
    _workingEndpointPrefix = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyBaseUrl, _baseUrl);
  }

  Future<void> saveAuth({
    required String token,
    required UserModel user,
    required SchoolModel school,
  }) async {
    _token = token;
    _currentUser = user;
    _currentSchool = school;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    await prefs.setString(_keyUser, jsonEncode(user.toJson()));
    await prefs.setString(_keySchool, jsonEncode({
      'name': school.name,
      'npsn': school.npsn,
      'address': school.address,
      'academic_year': school.academicYear,
      'semester': school.semester,
      'logo_url': school.logoUrl,
    }));
  }

  Future<void> logout() async {
    _token = null;
    _currentUser = null;
    _currentSchool = null;

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUser);
  }

  List<Uri> _candidateUris(String action, [Map<String, String>? queryParams]) {
    final params = {
      'action': action,
      if (_token != null && _token!.isNotEmpty) 'token': _token!,
      ...?queryParams,
    };

    final candidates = <String>[];
    if (_workingEndpointPrefix != null) {
      candidates.add('$_baseUrl$_workingEndpointPrefix');
    }

    if (_baseUrl.endsWith('/public')) {
      candidates.add('$_baseUrl/api.php');
      candidates.add('$_baseUrl/index.php?page=api');
    } else {
      candidates.add('$_baseUrl/public/api.php');
      candidates.add('$_baseUrl/api.php');
      candidates.add('$_baseUrl/public/index.php?page=api');
      candidates.add('$_baseUrl/index.php?page=api');
    }

    final uris = <Uri>[];
    final seen = <String>{};

    for (var cand in candidates) {
      if (seen.add(cand)) {
        if (cand.contains('?')) {
          final parts = cand.split('?');
          final basePart = parts[0];
          final queryPart = Uri.splitQueryString(parts[1]);
          final mergedParams = {...queryPart, ...params};
          uris.add(Uri.parse(basePart).replace(queryParameters: mergedParams));
        } else {
          uris.add(Uri.parse(cand).replace(queryParameters: params));
        }
      }
    }

    return uris;
  }

  Map<String, String> _headers() {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_token != null && _token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
      headers['X-Auth-Token'] = _token!;
    }
    return headers;
  }

  Future<http.Response> _executeWithFallback(
    String action, {
    Map<String, String>? queryParams,
    String? body,
    bool isPost = false,
  }) async {
    final uris = _candidateUris(action, queryParams);
    http.Response? lastResponse;
    Object? lastError;

    for (var uri in uris) {
      try {
        final client = http.Client();
        final request = http.Request(isPost ? 'POST' : 'GET', uri);
        request.headers.addAll(_headers());
        request.followRedirects = false;

        if (isPost && body != null) {
          request.body = body;
        }

        final streamed = await client.send(request).timeout(const Duration(seconds: 10));
        final response = await http.Response.fromStream(streamed);

        if (response.statusCode != 301 &&
            response.statusCode != 302 &&
            !response.body.trim().startsWith('<') &&
            response.headers['content-type']?.contains('json') == true) {
          final pathAndQuery = uri.path + (uri.queryParameters.containsKey('page') ? '?page=api' : '');
          final prefix = pathAndQuery.replaceFirst(RegExp(r'\?action=.*$'), '');
          _workingEndpointPrefix = prefix.isNotEmpty ? prefix : null;
          return response;
        }

        lastResponse = response;
      } catch (e) {
        lastError = e;
      }
    }

    if (lastResponse != null) {
      return lastResponse;
    }
    throw lastError ?? Exception('Gagal menghubungi server.');
  }

  Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final response = await _executeWithFallback(
        'login',
        isPost: true,
        body: jsonEncode({
          'username': username,
          'password': password,
        }),
      );

      if (response.statusCode == 301 || response.statusCode == 302 || response.body.trim().startsWith('<')) {
        return {
          'success': false,
          'message': 'Server mengalihkan ke login web. Silakan lakukan "Update from Remote / Git Pull" di cPanel hosting Anda.',
        };
      }

      final dynamic decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        return {
          'success': false,
          'message': 'Format respons server tidak valid.',
        };
      }

      final data = decoded;

      // Check if server redirected to web login
      if (data['redirect'] != null && data['token'] == null) {
        return {
          'success': false,
          'message': 'Server live belum di-pull di cPanel (merespon redirect web login). Silakan klik "Update from Remote" di cPanel Git Version Control atau gunakan USB Localhost.',
        };
      }

      if (response.statusCode == 200 && data['ok'] == true && data['token'] != null) {
        final token = data['token'].toString();
        final user = UserModel.fromJson((data['user'] as Map<String, dynamic>?) ?? {});
        final school = SchoolModel.fromJson((data['school'] as Map<String, dynamic>?) ?? {});
        await saveAuth(token: token, user: user, school: school);
        return {'success': true, 'message': data['message']?.toString() ?? 'Login berhasil'};
      } else {
        return {'success': false, 'message': data['message']?.toString() ?? 'Username atau password salah'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal terhubung ke server ($baseUrl): $e'};
    }
  }

  Future<Map<String, dynamic>> getWhatsAppFeed({int limit = 40}) async {
    try {
      final response = await _executeWithFallback('whatsapp_feed', queryParams: {'limit': limit.toString()});
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final stats = WhatsAppStats.fromJson((decoded['stats'] as Map<String, dynamic>?) ?? {});
          final queueRaw = (decoded['queue'] as List<dynamic>?) ?? [];
          final logsRaw = (decoded['logs'] as List<dynamic>?) ?? [];

          return {
            'success': true,
            'stats': stats,
            'queue': queueRaw.map((e) => WhatsAppQueueItem.fromJson(e as Map<String, dynamic>)).toList(),
            'logs': logsRaw.map((e) => WhatsAppLogItem.fromJson(e as Map<String, dynamic>)).toList(),
          };
        }
      }
      return {'success': false, 'message': 'Gagal memuat data WhatsApp feed'};
    } catch (e) {
      return {'success': false, 'message': 'Error feed WA: $e'};
    }
  }

  Future<Map<String, dynamic>> sendWhatsAppQueue({int limit = 30}) async {
    try {
      final response = await _executeWithFallback(
        'send_whatsapp_queue',
        isPost: true,
        body: jsonEncode({'limit': limit}),
      );

      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Selesai memproses antrian.',
          'sent': decoded['sent'] ?? 0,
          'failed': decoded['failed'] ?? 0,
        };
      }
      return {'success': false, 'message': 'Format respons tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Koneksi gagal: $e'};
    }
  }

  Future<List<NotificationItem>> getNotifications() async {
    try {
      final response = await _executeWithFallback('notifications');
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final list = (decoded['notifications'] as List<dynamic>?) ?? [];
          return list.map((e) => NotificationItem.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  Future<Map<String, dynamic>> getMySchedule() async {
    try {
      final response = await _executeWithFallback('my_schedule');
      dynamic decoded;
      try {
        decoded = jsonDecode(response.body);
      } catch (_) {
        decoded = null;
      }

      if (response.statusCode == 200 && decoded is Map<String, dynamic> && decoded['ok'] == true) {
        final todayRaw = (decoded['today'] as List<dynamic>?) ?? [];
        final weekRaw = (decoded['week'] as List<dynamic>?) ?? [];
        final nextRaw = decoded['next_upcoming_schedule'];
        ScheduleItem? nextUpcoming;
        if (nextRaw is Map<String, dynamic>) {
          try {
            nextUpcoming = ScheduleItem.fromJson(nextRaw);
          } catch (_) {}
        }

        final todayItems = <ScheduleItem>[];
        for (var e in todayRaw) {
          if (e is Map<String, dynamic>) {
            try {
              todayItems.add(ScheduleItem.fromJson(e));
            } catch (_) {}
          }
        }

        final weekItems = <DaySchedule>[];
        for (var e in weekRaw) {
          if (e is Map<String, dynamic>) {
            try {
              weekItems.add(DaySchedule.fromJson(e));
            } catch (_) {}
          }
        }

        return {
          'success': true,
          'today_day_name': decoded['today_day_name'] ?? '',
          'today_date': decoded['today_date'] ?? '',
          'today': todayItems,
          'week': weekItems,
          'next_upcoming_schedule': nextUpcoming,
          'summary': decoded['summary'] is Map<String, dynamic> ? decoded['summary'] as Map<String, dynamic> : {},
        };
      }

      if (response.statusCode == 401) {
        return {
          'success': false,
          'is_unauthorized': true,
          'message': 'Sesi login telah berakhir. Silakan login kembali.',
        };
      }

      String serverMsg = 'Gagal mengambil jadwal (${response.statusCode})';
      if (decoded is Map) {
        if (decoded['error'] != null && decoded['error'].toString().isNotEmpty) {
          serverMsg = '${decoded['message'] ?? 'Error'}: ${decoded['error']}';
        } else if (decoded['message'] != null) {
          serverMsg = decoded['message'].toString();
        }
      }

      return {'success': false, 'message': serverMsg};
    } catch (e) {
      return {'success': false, 'message': 'Koneksi error: $e'};
    }
  }

  Future<Map<String, dynamic>> triggerScheduleReminders() async {
    try {
      final response = await _executeWithFallback('trigger_schedule_reminders', isPost: true);
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Reminder jadwal berhasil dipicu.',
        };
      }
      return {'success': false, 'message': 'Format respons tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Error: $e'};
    }
  }

  // ================= ADMIN & TEACHER ACTIONS =================

  /// Get teacher attendance for today or specific date
  Future<Map<String, dynamic>> getTeacherAttendance({String? date}) async {
    try {
      final query = <String, String>{};
      if (date != null && date.isNotEmpty) query['date'] = date;
      final response = await _executeWithFallback('teacher_attendance', queryParams: query);
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final attRaw = decoded['attendance'];
          TeacherAttendanceModel? attendance;
          final sLat = decoded['school_lat'] != null ? double.tryParse(decoded['school_lat'].toString()) : null;
          final sLng = decoded['school_lng'] != null ? double.tryParse(decoded['school_lng'].toString()) : null;
          final radius = decoded['radius_meters'] is int ? decoded['radius_meters'] as int : int.tryParse(decoded['radius_meters']?.toString() ?? '500') ?? 500;

          if (attRaw is Map<String, dynamic>) {
            attendance = TeacherAttendanceModel.fromJson(attRaw, schoolLat: sLat, schoolLng: sLng, radiusMeters: radius);
          }

          return {
            'success': true,
            'attendance': attendance,
            'date': decoded['date']?.toString() ?? '',
            'school_lat': sLat,
            'school_lng': sLng,
            'radius_meters': radius,
          };
        }
      }
      dynamic decoded;
      try {
        decoded = jsonDecode(response.body);
      } catch (_) {}

      if (response.statusCode == 401) {
        return {'success': false, 'is_unauthorized': true, 'message': 'Sesi login telah berakhir.'};
      }
      final msg = (decoded is Map && decoded['message'] != null)
          ? decoded['message'].toString()
          : 'Gagal mengambil data presensi guru (${response.statusCode}).';
      return {'success': false, 'message': msg};
    } catch (e) {
      return {'success': false, 'message': 'Error presensi: $e'};
    }
  }

  /// Teacher check-in or check-out
  Future<Map<String, dynamic>> teacherCheckIn({
    required String type, // 'checkin' or 'checkout'
    double? lat,
    double? lng,
    String status = 'hadir',
    String notes = 'Presensi mandiri via Mobile App',
  }) async {
    try {
      final response = await _executeWithFallback(
        'teacher_checkin',
        isPost: true,
        body: jsonEncode({
          'type': type,
          'lat': lat,
          'lng': lng,
          'status': status,
          'notes': notes,
        }),
      );
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Presensi berhasil dicatat.',
        };
      }
      return {'success': false, 'message': 'Format respons tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Gagal check-in: $e'};
    }
  }

  /// Get active teaching assignments for current teacher
  Future<List<TeachingAssignmentModel>> getTeachingAssignments() async {
    try {
      final response = await _executeWithFallback('teaching_assignments');
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final list = (decoded['assignments'] as List<dynamic>?) ?? [];
          return list.map((e) => TeachingAssignmentModel.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  /// Get student roster for attendance session
  Future<Map<String, dynamic>> getStudentsForAttendance({
    required int assignmentId,
    String? date,
    int? meetingNo,
  }) async {
    try {
      final query = <String, String>{
        'assignment_id': assignmentId.toString(),
      };
      if (date != null && date.isNotEmpty) query['date'] = date;
      if (meetingNo != null) query['meeting_no'] = meetingNo.toString();

      final response = await _executeWithFallback('students_for_attendance', queryParams: query);
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final studentsRaw = (decoded['students'] as List<dynamic>?) ?? [];
          final students = studentsRaw.map((e) => StudentAttendanceItem.fromJson(e as Map<String, dynamic>)).toList();
          final session = decoded['session'] is Map<String, dynamic> ? decoded['session'] as Map<String, dynamic> : null;
          final assignment = decoded['assignment'] is Map<String, dynamic> ? TeachingAssignmentModel.fromJson(decoded['assignment']) : null;

          return {
            'success': true,
            'students': students,
            'session': session,
            'assignment': assignment,
            'date': decoded['date']?.toString() ?? '',
            'meeting_no': decoded['meeting_no'] is int ? decoded['meeting_no'] : int.tryParse(decoded['meeting_no']?.toString() ?? '1') ?? 1,
            'teacher_checked_in': decoded['teacher_checked_in'] == true,
            'teacher_time_in': decoded['teacher_time_in']?.toString(),
            'teacher_status': decoded['teacher_status']?.toString() ?? 'hadir',
            'has_schedule': decoded['has_schedule'] == true,
            'schedule_info': decoded['schedule_info']?.toString(),
            'day_name': decoded['day_name']?.toString() ?? '',
            'other_schedules': (decoded['other_schedules'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
          };
        }
      }
      return {'success': false, 'message': 'Gagal mengambil data siswa.'};
    } catch (e) {
      return {'success': false, 'message': 'Error presensi siswa: $e'};
    }
  }

  /// Teacher check-in to class session according to schedule / assignment
  Future<Map<String, dynamic>> teacherClassCheckIn({
    required int assignmentId,
    required String date,
    required int meetingNo,
    String status = 'hadir',
  }) async {
    try {
      final response = await _executeWithFallback(
        'teacher_class_checkin',
        isPost: true,
        body: jsonEncode({
          'assignment_id': assignmentId,
          'date': date,
          'meeting_no': meetingNo,
          'status': status,
        }),
      );
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Absen masuk kelas berhasil.',
          'teacher_time_in': decoded['teacher_time_in']?.toString(),
        };
      }
      return {'success': false, 'message': 'Respons server tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Gagal absen masuk kelas: $e'};
    }
  }

  /// Save student attendance session and student rows
  Future<Map<String, dynamic>> saveStudentAttendance({
    required int assignmentId,
    required String date,
    required int meetingNo,
    required String topic,
    required bool queueWhatsapp,
    required List<StudentAttendanceItem> students,
  }) async {
    try {
      final response = await _executeWithFallback(
        'save_student_attendance',
        isPost: true,
        body: jsonEncode({
          'assignment_id': assignmentId,
          'date': date,
          'meeting_no': meetingNo,
          'topic': topic,
          'queue_whatsapp_absence': queueWhatsapp ? 1 : 0,
          'rows': students.map((s) => s.toMap()).toList(),
        }),
      );
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Presensi siswa berhasil disimpan.',
          'wa_queued': decoded['wa_queued'] ?? 0,
        };
      }
      return {'success': false, 'message': 'Respons server tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Gagal menyimpan presensi: $e'};
    }
  }

  /// Get daily teaching journals
  Future<List<JournalModel>> getJournals({int? assignmentId}) async {
    try {
      final query = <String, String>{};
      if (assignmentId != null && assignmentId > 0) query['assignment_id'] = assignmentId.toString();

      final response = await _executeWithFallback('journals', queryParams: query);
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final list = (decoded['journals'] as List<dynamic>?) ?? [];
          return list.map((e) => JournalModel.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  /// Save or update teaching journal
  Future<Map<String, dynamic>> saveJournal({
    int? id,
    required int assignmentId,
    required String date,
    required int meetingNo,
    required String topic,
    required String activities,
    String materials = '',
    String obstacles = '',
    String followUp = '',
  }) async {
    try {
      final response = await _executeWithFallback(
        'save_journal',
        isPost: true,
        body: jsonEncode({
          if (id != null && id > 0) 'id': id,
          'assignment_id': assignmentId,
          'date': date,
          'meeting_no': meetingNo,
          'topic': topic,
          'activities': activities,
          'materials': materials,
          'obstacles': obstacles,
          'follow_up': followUp,
        }),
      );
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Jurnal berhasil disimpan.',
        };
      }
      return {'success': false, 'message': 'Respons server tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Gagal menyimpan jurnal: $e'};
    }
  }

  /// Get grades data for assignment and assessment type
  Future<Map<String, dynamic>> getGradesData({
    required int assignmentId,
    String assessmentType = 'UH',
    int? learningObjectiveId,
  }) async {
    try {
      final query = <String, String>{
        'assignment_id': assignmentId.toString(),
        'assessment_type': assessmentType,
      };
      if (learningObjectiveId != null) {
        query['learning_objective_id'] = learningObjectiveId.toString();
      }

      final response = await _executeWithFallback('grades_data', queryParams: query);
      if (response.statusCode == 200) {
        final dynamic decoded = jsonDecode(response.body);
        if (decoded is Map<String, dynamic> && decoded['ok'] == true) {
          final studentsRaw = (decoded['students'] as List<dynamic>?) ?? [];
          final objRaw = (decoded['objectives'] as List<dynamic>?) ?? [];
          return {
            'success': true,
            'students': studentsRaw.map((e) => GradeStudentItem.fromJson(e as Map<String, dynamic>)).toList(),
            'objectives': objRaw.map((e) => LearningObjectiveModel.fromJson(e as Map<String, dynamic>)).toList(),
          };
        }
      }
      return {'success': false, 'message': 'Gagal memuat data nilai.'};
    } catch (e) {
      return {'success': false, 'message': 'Error penilaian: $e'};
    }
  }

  /// Save student scores
  Future<Map<String, dynamic>> saveGrades({
    required int assignmentId,
    required String assessmentType,
    int? learningObjectiveId,
    required List<GradeStudentItem> students,
  }) async {
    try {
      final response = await _executeWithFallback(
        'save_grades',
        isPost: true,
        body: jsonEncode({
          'assignment_id': assignmentId,
          'assessment_type': assessmentType,
          'learning_objective_id': learningObjectiveId,
          'scores': students.map((s) => s.toMap()).toList(),
        }),
      );
      final dynamic decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        return {
          'success': decoded['ok'] == true,
          'message': decoded['message']?.toString() ?? 'Nilai berhasil disimpan.',
        };
      }
      return {'success': false, 'message': 'Respons server tidak valid.'};
    } catch (e) {
      return {'success': false, 'message': 'Gagal menyimpan nilai: $e'};
    }
  }
}
