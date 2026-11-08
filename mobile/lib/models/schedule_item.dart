class ScheduleItem {
  final int id;
  final int assignmentId;
  final int dayOfWeek;
  final String dayName;
  final int period;
  final String startTime;
  final String endTime;
  final String subjectName;
  final String className;
  final String teacherName;
  final String room;
  final String status; // 'active', 'upcoming', 'finished'
  final int minutesUntilStart;

  ScheduleItem({
    required this.id,
    this.assignmentId = 0,
    required this.dayOfWeek,
    required this.dayName,
    required this.period,
    required this.startTime,
    required this.endTime,
    required this.subjectName,
    required this.className,
    required this.teacherName,
    this.room = '',
    this.status = 'upcoming',
    this.minutesUntilStart = 0,
  });

  factory ScheduleItem.fromJson(Map<String, dynamic> json) {
    return ScheduleItem(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      assignmentId: int.tryParse(json['assignment_id']?.toString() ?? '0') ?? 0,
      dayOfWeek: int.tryParse(json['day_of_week']?.toString() ?? '1') ?? 1,
      dayName: json['day_name']?.toString() ?? '',
      period: int.tryParse(json['period']?.toString() ?? '1') ?? 1,
      startTime: json['start_time']?.toString() ?? '',
      endTime: json['end_time']?.toString() ?? '',
      subjectName: json['subject_name']?.toString() ?? '',
      className: json['class_name']?.toString() ?? '',
      teacherName: json['teacher_name']?.toString() ?? '',
      room: json['room']?.toString() ?? '',
      status: json['status']?.toString() ?? 'upcoming',
      minutesUntilStart: int.tryParse(json['minutes_until_start']?.toString() ?? '0') ?? 0,
    );
  }

  String get timeRange => '$startTime - $endTime';

  String get remainingLabel {
    if (status == 'active') return 'Sedang Berlangsung';
    if (minutesUntilStart > 0) return '$minutesUntilStart mnt lagi';
    return 'Selesai';
  }
}

class DaySchedule {
  final int dayNumber;
  final String dayName;
  final List<ScheduleItem> items;

  DaySchedule({
    required this.dayNumber,
    required this.dayName,
    required this.items,
  });

  factory DaySchedule.fromJson(Map<String, dynamic> json) {
    final list = (json['items'] as List<dynamic>?) ?? [];
    return DaySchedule(
      dayNumber: int.tryParse(json['day_number']?.toString() ?? '1') ?? 1,
      dayName: json['day_name']?.toString() ?? '',
      items: list.map((e) => ScheduleItem.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }
}

