import 'package:flutter_test/flutter_test.dart';
import 'package:erapot_mobile/models/user.dart';
import 'package:erapot_mobile/models/whatsapp_item.dart';
import 'package:erapot_mobile/models/schedule_item.dart';
import 'package:erapot_mobile/models/notification_item.dart';

void main() {
  group('Mobile Models Tests', () {
    test('UserModel parses from JSON correctly', () {
      final json = {
        'id': 12,
        'username': 'guru1',
        'name': 'Ustadz Ahmad',
        'role': 'guru',
        'teacher_id': '5',
      };
      final user = UserModel.fromJson(json);
      expect(user.id, 12);
      expect(user.username, 'guru1');
      expect(user.name, 'Ustadz Ahmad');
      expect(user.role, 'guru');
      expect(user.teacherId, 5);
    });

    test('WhatsAppStats parses from JSON correctly', () {
      final json = {
        'total_pending': 4,
        'total_sent': 15,
        'total_failed': 1,
        'total_today': 20,
      };
      final stats = WhatsAppStats.fromJson(json);
      expect(stats.totalPending, 4);
      expect(stats.totalSent, 15);
      expect(stats.totalFailed, 1);
      expect(stats.totalToday, 20);
    });

    test('ScheduleItem parses correctly with active status', () {
      final json = {
        'id': 101,
        'day_of_week': 2,
        'day_name': 'Selasa',
        'period': 3,
        'start_time': '08:15',
        'end_time': '08:55',
        'subject_name': 'Matematika',
        'class_name': '7A',
        'teacher_name': 'Ustadz Ahmad',
        'status': 'active',
        'minutes_until_start': 0,
      };
      final item = ScheduleItem.fromJson(json);
      expect(item.id, 101);
      expect(item.subjectName, 'Matematika');
      expect(item.status, 'active');
      expect(item.className, '7A');
    });

    test('NotificationItem parses trigger payload', () {
      final json = {
        'id': 'kbm-upcoming-101',
        'type': 'schedule',
        'level': 'warning',
        'title': '15 Menit Lagi: KBM Matematika',
        'body': 'Kelas 7A jam ke-3',
        'time': '08:15',
        'minutes_remaining': 15,
        'is_active': false,
      };
      final notif = NotificationItem.fromJson(json);
      expect(notif.type, 'schedule');
      expect(notif.level, 'warning');
      expect(notif.minutesRemaining, 15);
      expect(notif.isActive, false);
    });
  });
}

