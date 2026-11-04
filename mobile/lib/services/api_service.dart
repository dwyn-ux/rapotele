import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../models/whatsapp_item.dart';
import '../models/notification_item.dart';
import '../models/schedule_item.dart';

class ApiService {
  static const String _defaultBaseUrl = 'https://raport.smpmuhashidiq.sch.id';
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

  String get baseUrl => _baseUrl;
  String? get token => _token;
  UserModel? get currentUser => _currentUser;
  SchoolModel? get currentSchool => _currentSchool;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = prefs.getString(_keyBaseUrl) ?? _defaultBaseUrl;
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

  Uri _buildUri(String action, [Map<String, String>? queryParams]) {
    final params = {'action': action, ...?queryParams};
    // Support URLs with /public or root
    final endpoint = _baseUrl.endsWith('/public') ? '$_baseUrl/api.php' : '$_baseUrl/public/api.php';
    return Uri.parse(endpoint).replace(queryParameters: params);
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

  Future<Map<String, dynamic>> login(String username, String password) async {
    final uri = _buildUri('login');
    try {
      final response = await http
          .post(
            uri,
            headers: _headers(),
            body: jsonEncode({
              'username': username,
              'password': password,
            }),
          )
          .timeout(const Duration(seconds: 15));

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode == 200 && data['ok'] == true) {
        final token = data['token'] as String;
        final user = UserModel.fromJson(data['user'] as Map<String, dynamic>);
        final school = SchoolModel.fromJson((data['school'] as Map<String, dynamic>?) ?? {});
        await saveAuth(token: token, user: user, school: school);
        return {'success': true, 'message': data['message'] ?? 'Login berhasil'};
      } else {
        return {'success': false, 'message': data['message'] ?? 'Login gagal'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  Future<Map<String, dynamic>> getWhatsAppFeed({int limit = 40}) async {
    final uri = _buildUri('whatsapp_feed', {'limit': limit.toString()});
    try {
      final response = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 15));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        if (data['ok'] == true) {
          final stats = WhatsAppStats.fromJson((data['stats'] as Map<String, dynamic>?) ?? {});
          final queueRaw = (data['queue'] as List<dynamic>?) ?? [];
          final logsRaw = (data['logs'] as List<dynamic>?) ?? [];

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
    final uri = _buildUri('send_whatsapp_queue');
    try {
      final response = await http
          .post(uri, headers: _headers(), body: jsonEncode({'limit': limit}))
          .timeout(const Duration(seconds: 30));

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      return {
        'success': data['ok'] == true,
        'message': data['message'] ?? 'Selesai memproses antrian.',
        'sent': data['sent'] ?? 0,
        'failed': data['failed'] ?? 0,
      };
    } catch (e) {
      return {'success': false, 'message': 'Koneksi gagal: $e'};
    }
  }

  Future<List<NotificationItem>> getNotifications() async {
    final uri = _buildUri('notifications');
    try {
      final response = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 15));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        if (data['ok'] == true) {
          final list = (data['notifications'] as List<dynamic>?) ?? [];
          return list.map((e) => NotificationItem.fromJson(e as Map<String, dynamic>)).toList();
        }
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  Future<Map<String, dynamic>> getMySchedule() async {
    final uri = _buildUri('my_schedule');
    try {
      final response = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 15));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        if (data['ok'] == true) {
          final todayRaw = (data['today'] as List<dynamic>?) ?? [];
          final weekRaw = (data['week'] as List<dynamic>?) ?? [];

          return {
            'success': true,
            'today_day_name': data['today_day_name'] ?? '',
            'today_date': data['today_date'] ?? '',
            'today': todayRaw.map((e) => ScheduleItem.fromJson(e as Map<String, dynamic>)).toList(),
            'week': weekRaw.map((e) => DaySchedule.fromJson(e as Map<String, dynamic>)).toList(),
          };
        }
      }
      return {'success': false, 'message': 'Gagal mengambil jadwal'};
    } catch (e) {
      return {'success': false, 'message': 'Koneksi error: $e'};
    }
  }

  Future<Map<String, dynamic>> triggerScheduleReminders() async {
    final uri = _buildUri('trigger_schedule_reminders');
    try {
      final response = await http.post(uri, headers: _headers()).timeout(const Duration(seconds: 20));
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      return {
        'success': data['ok'] == true,
        'message': data['message'] ?? 'Reminder jadwal berhasil dipicu.',
      };
    } catch (e) {
      return {'success': false, 'message': 'Error: $e'};
    }
  }
}

