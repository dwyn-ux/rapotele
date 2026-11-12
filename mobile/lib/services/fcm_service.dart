import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'api_service.dart';
import 'notification_service.dart';

/// Push notification via Firebase Cloud Messaging.
/// Tampilan tetap dari web (WebView); service ini hanya mengurus token & notifikasi.
class FcmService {
  static final FcmService _instance = FcmService._internal();
  factory FcmService() => _instance;
  FcmService._internal();

  bool _ready = false;
  String? _fcmToken;
  String? _apiToken;
  DateTime? _lastRegisterAt;
  bool _registering = false;

  /// URL tujuan dari notifikasi yang membuka app saat sedang tertutup.
  String? initialUrl;

  Future<void> init() async {
    try {
      await Firebase.initializeApp();
      final messaging = FirebaseMessaging.instance;

      await messaging.requestPermission();
      await NotificationService().ensureChannels();

      FirebaseMessaging.onMessage.listen(_onForeground);
      FirebaseMessaging.onMessageOpenedApp.listen(_onOpened);

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        initialUrl = initial.data['url']?.toString();
      }

      messaging.onTokenRefresh.listen((t) {
        _fcmToken = t;
        _lastRegisterAt = null;
        _registerWithServer();
      });

      _fcmToken = await messaging.getToken();
      _ready = true;
    } catch (e) {
      debugPrint('FCM init gagal (cek google-services.json): $e');
    }
  }

  String? takeInitialUrl() {
    final u = initialUrl;
    initialUrl = null;
    return u;
  }

  /// Dipanggil dari WebView setiap halaman selesai dimuat.
  /// [apiToken] null berarti user belum/sudah logout.
  Future<void> onWebSession(String? apiToken) async {
    if (apiToken == null || apiToken.isEmpty) {
      _apiToken = null;
      _lastRegisterAt = null;
      NotificationService().fcmActive = false;
      return;
    }
    _apiToken = apiToken;
    await _registerWithServer();
  }

  void _onForeground(RemoteMessage message) {
    final data = message.data;
    final title = message.notification?.title ?? data['title']?.toString() ?? 'E-Raport';
    final body = message.notification?.body ?? data['body']?.toString() ?? '';
    NotificationService().showNotification(
      id: message.hashCode & 0x7FFFFFFF,
      title: title,
      body: body,
      payload: jsonEncode({'url': data['url'] ?? '/'}),
    );
  }

  void _onOpened(RemoteMessage message) {
    final url = message.data['url']?.toString();
    if (url != null && url.isNotEmpty) {
      NotificationService.onNotificationClick.add({'url': url});
    }
  }

  Future<void> _registerWithServer() async {
    final fcmToken = _fcmToken;
    final apiToken = _apiToken;
    if (!_ready || fcmToken == null || apiToken == null || _registering) return;

    final last = _lastRegisterAt;
    if (last != null && DateTime.now().difference(last) < const Duration(minutes: 2)) return;

    _registering = true;
    try {
      final base = ApiService().baseUrl;
      final candidates = <String>[
        '$base/api.php?action=save_fcm_token',
        '$base/public/api.php?action=save_fcm_token',
      ];

      for (final url in candidates) {
        try {
          final resp = await http
              .post(
                Uri.parse(url),
                headers: {'X-Auth-Token': apiToken, 'Accept': 'application/json'},
                body: {'token': fcmToken, 'platform': 'android'},
              )
              .timeout(const Duration(seconds: 10));

          if (resp.headers['content-type']?.contains('json') != true) continue;
          final decoded = jsonDecode(resp.body);
          if (resp.statusCode == 200 && decoded is Map && decoded['ok'] == true) {
            _lastRegisterAt = DateTime.now();
            NotificationService().fcmActive = true;
            return;
          }
          debugPrint('Daftar token FCM ditolak: ${resp.statusCode} ${resp.body}');
        } catch (e) {
          debugPrint('Daftar token FCM error ($url): $e');
        }
      }
    } finally {
      _registering = false;
    }
  }
}
