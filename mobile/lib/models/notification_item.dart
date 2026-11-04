class NotificationItem {
  final String id;
  final String type; // 'schedule', 'whatsapp', 'attendance', 'violation'
  final String level; // 'info', 'warning', 'danger', 'success'
  final String title;
  final String body;
  final String time;
  final int minutesRemaining;
  final bool isActive;

  NotificationItem({
    required this.id,
    required this.type,
    required this.level,
    required this.title,
    required this.body,
    required this.time,
    this.minutesRemaining = 0,
    this.isActive = false,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    return NotificationItem(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString() ?? 'info',
      level: json['level']?.toString() ?? 'info',
      title: json['title']?.toString() ?? '',
      body: json['body']?.toString() ?? '',
      time: json['time']?.toString() ?? '',
      minutesRemaining: int.tryParse(json['minutes_remaining']?.toString() ?? '0') ?? 0,
      isActive: json['is_active'] == true,
    );
  }
}

