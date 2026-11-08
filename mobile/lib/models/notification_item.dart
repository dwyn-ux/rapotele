class NotificationItem {
  final String id;
  final String type; // 'schedule', 'whatsapp', 'attendance', 'violation'
  final String level; // 'info', 'warning', 'danger', 'success'
  final String title;
  final String body;
  final String time;
  final int minutesRemaining;
  final bool isActive;
  final bool isEndingSoon;
  final bool isStartingSoon;
  final int assignmentId;
  final int scheduleId;
  final String className;
  final String subjectName;
  final int period;

  NotificationItem({
    required this.id,
    required this.type,
    required this.level,
    required this.title,
    required this.body,
    required this.time,
    this.minutesRemaining = 0,
    this.isActive = false,
    this.isEndingSoon = false,
    this.isStartingSoon = false,
    this.assignmentId = 0,
    this.scheduleId = 0,
    this.className = '',
    this.subjectName = '',
    this.period = 0,
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
      isEndingSoon: json['is_ending_soon'] == true,
      isStartingSoon: json['is_starting_soon'] == true,
      assignmentId: int.tryParse(json['assignment_id']?.toString() ?? '0') ?? 0,
      scheduleId: int.tryParse(json['schedule_id']?.toString() ?? '0') ?? 0,
      className: json['class_name']?.toString() ?? '',
      subjectName: json['subject_name']?.toString() ?? '',
      period: int.tryParse(json['period']?.toString() ?? '0') ?? 0,
    );
  }
}

