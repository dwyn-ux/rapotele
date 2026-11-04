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
  static const String channelWaId = 'erapot_wa_channel';

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
      channelDescription: 'Notifikasi jadwal pelajaran dan pembaruan WhatsApp',
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

  void startPeriodicChecker([Duration interval = const Duration(seconds: 45)]) {
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
        // If it's a schedule trigger that is upcoming (<= 20 min) or newly active
        final shouldAlert = (item.type == 'schedule' && (item.isActive || item.minutesRemaining <= 20)) ||
            (item.type == 'whatsapp' && item.level == 'danger') ||
            (item.type == 'violation') ||
            (item.type == 'attendance');

        if (shouldAlert && !_notifiedIds.contains(item.id)) {
          _notifiedIds.add(item.id);

          final notificationId = item.id.hashCode & 0x7FFFFFFF;
          await showNotification(
            id: notificationId,
            title: item.title,
            body: item.body,
            channelId: item.type == 'whatsapp' ? channelWaId : channelScheduleId,
            channelName: item.type == 'whatsapp' ? 'Update WhatsApp' : 'Jadwal & KBM',
            payload: item.id,
          );
        }
      }

      return notifications;
    } catch (_) {
      return [];
    }
  }

  void clearNotifiedCache() {
    _notifiedIds.clear();
  }
}

