import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'api_service.dart';
import '../models/notification_item.dart';

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();
  final Set<String> _notifiedIds = {};
  Timer? _periodicTimer;

  static const String channelScheduleId = 'erapot_schedule_channel';
  static const String channelDailyId = 'erapot_daily_channel';

  /// Stream to listen when user clicks/taps an Android notification
  static final StreamController<Map<String, dynamic>> onNotificationClick =
      StreamController<Map<String, dynamic>>.broadcast();

  Future<void> init() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidInit);

    await _plugin.initialize(
      settings: initSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        if (response.payload != null && response.payload!.isNotEmpty) {
          try {
            final data = jsonDecode(response.payload!) as Map<String, dynamic>;
            onNotificationClick.add(data);
          } catch (_) {}
        }
      },
    );

    // Request permissions for Android 13+
    final androidImpl = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (androidImpl != null) {
      await androidImpl.requestNotificationsPermission();
    }

    startPeriodicChecker();
  }

  Future<Map<String, dynamic>?> getInitialNotificationPayload() async {
    final launchDetails = await _plugin.getNotificationAppLaunchDetails();
    if (launchDetails?.didNotificationLaunchApp ?? false) {
      final payload = launchDetails?.notificationResponse?.payload;
      if (payload != null && payload.isNotEmpty) {
        try {
          return jsonDecode(payload) as Map<String, dynamic>;
        } catch (_) {}
      }
    }
    return null;
  }

  Future<void> showNotification({
    required int id,
    required String title,
    required String body,
    String? channelId,
    String? channelName,
    String? payload,
  }) async {
    final androidDetails = AndroidNotificationDetails(
      channelId ?? channelScheduleId,
      channelName ?? 'Pengingat E-Raport',
      channelDescription: 'Notifikasi jadwal pelajaran, presensi dan jurnal',
      importance: Importance.max,
      priority: Priority.high,
      enableVibration: true,
      playSound: true,
    );

    final notificationDetails = NotificationDetails(android: androidDetails);
    await _plugin.show(
      id: id,
      title: title,
      body: body,
      notificationDetails: notificationDetails,
      payload: payload,
    );
  }

  void startPeriodicChecker([Duration interval = const Duration(seconds: 30)]) {
    _periodicTimer?.cancel();
    _periodicTimer = Timer.periodic(interval, (_) async {
      await checkAndTriggerNotifications();
    });
    // Run immediately once
    checkAndTriggerNotifications();
  }

  void stopPeriodicChecker() {
    _periodicTimer?.cancel();
    _periodicTimer = null;
  }

  Future<List<NotificationItem>> checkAndTriggerNotifications() async {
    final now = DateTime.now();
    final todayStr = '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final dayOfWeek = now.weekday; // 1 = Monday, 7 = Sunday

    // ── 1. Daily Morning Attendance Reminder (Senin - Sabtu, 06:40 - 07:15) ──
    if (dayOfWeek <= 6 && now.hour == 6 && now.minute >= 40 && now.minute <= 55) {
      final morningKey = 'morning-absensi-$todayStr';
      if (!_notifiedIds.contains(morningKey)) {
        _notifiedIds.add(morningKey);
        await showNotification(
          id: 1001,
          title: '📋 Presensi Kehadiran Guru',
          body: 'Selamat pagi! Jangan lupa lakukan absensi kehadiran hari ini.',
          channelId: channelDailyId,
          channelName: 'Pengingat Harian',
          payload: jsonEncode({'url': '/?page=teacher-attendance-self'}),
        );
      }
    }

    // ── 2. Daily Afternoon Journal Reminder (Senin - Sabtu, 13:30 - 14:15) ──
    if (dayOfWeek <= 6 && now.hour == 13 && now.minute >= 30 && now.minute <= 50) {
      final journalKey = 'afternoon-journal-$todayStr';
      if (!_notifiedIds.contains(journalKey)) {
        _notifiedIds.add(journalKey);
        await showNotification(
          id: 1002,
          title: '📝 Jurnal Mengajar Hari Ini',
          body: 'KBM hampir usai. Pastikan presensi siswa dan jurnal kelas sudah terisi.',
          channelId: channelDailyId,
          channelName: 'Pengingat Harian',
          payload: jsonEncode({'url': '/?page=journals'}),
        );
      }
    }

    // ── 3. API-based notifications (if authenticated) ──
    if (!ApiService().isAuthenticated) {
      return [];
    }

    try {
      final notifications = await ApiService().getNotifications();

      for (var item in notifications) {
        if (item.type == 'whatsapp') {
          continue;
        }

        final shouldAlert = (item.type == 'schedule' &&
                (item.isStartingSoon || item.isEndingSoon || item.isActive || item.minutesRemaining <= 15)) ||
            (item.type == 'violation') ||
            (item.type == 'attendance');

        if (shouldAlert && !_notifiedIds.contains(item.id)) {
          _notifiedIds.add(item.id);

          final notificationId = item.id.hashCode & 0x7FFFFFFF;
          final targetUrl = item.type == 'schedule'
              ? (item.isEndingSoon ? '/?page=journals' : '/?page=teaching-wizard')
              : (item.type == 'attendance' ? '/?page=student-attendance' : '/?page=violations');

          final payloadData = jsonEncode({
            'type': item.type,
            'id': item.id,
            'url': targetUrl,
            'assignment_id': item.assignmentId,
            'schedule_id': item.scheduleId,
            'class_name': item.className,
            'subject_name': item.subjectName,
            'period': item.period,
            'is_active': item.isActive,
            'is_starting_soon': item.isStartingSoon,
          });

          await showNotification(
            id: notificationId,
            title: item.title,
            body: item.body,
            channelId: channelScheduleId,
            channelName: 'Jadwal & KBM',
            payload: payloadData,
          );
        }
      }

      return notifications.where((item) => item.type != 'whatsapp').toList();
    } catch (e) {
      debugPrint('Notification fetch error: $e');
      return [];
    }
  }

  void clearNotifiedCache() {
    _notifiedIds.clear();
  }
}
