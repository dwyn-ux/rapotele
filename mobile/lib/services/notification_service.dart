import 'dart:async';
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

  Future<void> init() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidInit);

    await _plugin.initialize(
      settings: initSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        // Notification tapped
      },
    );

    // Request permissions for Android 13+
    final androidImpl = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (androidImpl != null) {
      await androidImpl.requestNotificationsPermission();
    }

    startPeriodicChecker();
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
      channelName ?? 'Pengingat Jadwal & KBM',
      channelDescription: 'Notifikasi jadwal pelajaran dan agenda KBM',
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
  }

  void stopPeriodicChecker() {
    _periodicTimer?.cancel();
    _periodicTimer = null;
  }

  Future<List<NotificationItem>> checkAndTriggerNotifications() async {
    if (!ApiService().isAuthenticated) {
      return [];
    }

    try {
      final notifications = await ApiService().getNotifications();

      for (var item in notifications) {
        // Skip WhatsApp notifications (dipending)
        if (item.type == 'whatsapp') {
          continue;
        }

        // Trigger for schedule (5 min before start, active, 5 min before end, or upcoming <= 15 min)
        final shouldAlert = (item.type == 'schedule' &&
                (item.isStartingSoon || item.isEndingSoon || item.isActive || item.minutesRemaining <= 15)) ||
            (item.type == 'violation') ||
            (item.type == 'attendance');

        if (shouldAlert && !_notifiedIds.contains(item.id)) {
          _notifiedIds.add(item.id);

          final notificationId = item.id.hashCode & 0x7FFFFFFF;
          await showNotification(
            id: notificationId,
            title: item.title,
            body: item.body,
            channelId: channelScheduleId,
            channelName: 'Jadwal & KBM',
            payload: item.id,
          );
        }
      }

      return notifications.where((item) => item.type != 'whatsapp').toList();
    } catch (_) {
      return [];
    }
  }

  void clearNotifiedCache() {
    _notifiedIds.clear();
  }
}

