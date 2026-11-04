import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../models/whatsapp_item.dart';
import '../models/notification_item.dart';
import '../models/schedule_item.dart';

class ApiService {
  static const String _defaultBaseUrl = 'https://raport.smpmuashidiq.sch.id';
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
  String? _workingEndpointPrefix; // caches working path format (e.g. '/api.php' or '/public/api.php')

  String get baseUrl => _baseUrl;
  String? get token => _token;
  UserModel? get currentUser => _currentUser;
  SchoolModel? get currentSchool => _currentSchool;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final savedBase = prefs.getString(_keyBaseUrl);
    if (savedBase != null && savedBase.isNotEmpty) {
      // Fix potential typo from earlier builds
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
    final params = {'action': action, ...?queryParams};

    // If we already know the working format, put it first
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
        request.followRedirects = false; // Don't silently follow redirect to HTML login page

        if (isPost && body != null) {
          request.body = body;
        }

        final streamed = await client.send(request).timeout(const Duration(seconds: 10));
        final response = await http.Response.fromStream(streamed);

        // If returned valid JSON (not 301/302 redirect and not HTML)
        if (response.statusCode != 301 &&
            response.statusCode != 302 &&
            !response.body.trim().startsWith('<') &&
            response.headers['content-type']?.contains('json') == true) {
          // Remember working path prefix
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

      // Check if redirected to HTML
      if (response.statusCode == 301 || response.statusCode == 302 || response.body.trim().startsWith('<')) {
        return {
          'success': false,
          'message': 'Server mengalihkan ke halaman login web. Pastikan file API sudah terupdate di server atau periksa URL server.',
        };
      }

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
      return {'success': false, 'message': 'Gagal terhubung ke server ($baseUrl): $e'};
    }
  }

  Future<Map<String, dynamic>> getWhatsAppFeed({int limit = 40}) async {
    try {
      final response = await _executeWithFallback('whatsapp_feed', queryParams: {'limit': limit.toString()});
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
    try {
      final response = await _executeWithFallback(
        'send_whatsapp_queue',
        isPost: true,
        body: jsonEncode({'limit': limit}),
      );

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
    try {
      final response = await _executeWithFallback('notifications');
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
    try {
      final response = await _executeWithFallback('my_schedule');
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
    try {
      final response = await _executeWithFallback('trigger_schedule_reminders', isPost: true);
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
