class WhatsAppStats {
  final int totalPending;
  final int totalSent;
  final int totalFailed;
  final int totalToday;

  WhatsAppStats({
    required this.totalPending,
    required this.totalSent,
    required this.totalFailed,
    required this.totalToday,
  });

  factory WhatsAppStats.fromJson(Map<String, dynamic> json) {
    return WhatsAppStats(
      totalPending: int.tryParse(json['total_pending']?.toString() ?? '0') ?? 0,
      totalSent: int.tryParse(json['total_sent']?.toString() ?? '0') ?? 0,
      totalFailed: int.tryParse(json['total_failed']?.toString() ?? '0') ?? 0,
      totalToday: int.tryParse(json['total_today']?.toString() ?? '0') ?? 0,
    );
  }

  int get pending => totalPending;
  int get sentToday => totalToday;
  int get failedToday => totalFailed;
}

class WhatsAppQueueItem {
  final int id;
  final String createdAt;
  final String scheduledAt;
  final String studentName;
  final String guardianName;
  final String className;
  final String phone;
  final String messageType;
  final String status;
  final String message;
  final String errorMessage;

  WhatsAppQueueItem({
    required this.id,
    required this.createdAt,
    required this.scheduledAt,
    required this.studentName,
    required this.guardianName,
    required this.className,
    required this.phone,
    required this.messageType,
    required this.status,
    required this.message,
    required this.errorMessage,
  });

  factory WhatsAppQueueItem.fromJson(Map<String, dynamic> json) {
    return WhatsAppQueueItem(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      createdAt: json['created_at']?.toString() ?? '',
      scheduledAt: json['scheduled_at']?.toString() ?? '',
      studentName: json['student_name']?.toString() ?? '-',
      guardianName: json['guardian_name']?.toString() ?? '-',
      className: json['class_name']?.toString() ?? '-',
      phone: json['phone']?.toString() ?? '',
      messageType: json['message_type']?.toString() ?? 'umum',
      status: json['status']?.toString() ?? 'pending',
      message: json['message']?.toString() ?? '',
      errorMessage: json['error_message']?.toString() ?? '',
    );
  }
}

class WhatsAppLogItem {
  final int id;
  final String createdAt;
  final String studentName;
  final String guardianName;
  final String className;
  final String phone;
  final String status;
  final int responseCode;
  final String errorMessage;
  final String message;

  WhatsAppLogItem({
    required this.id,
    required this.createdAt,
    required this.studentName,
    required this.guardianName,
    required this.className,
    required this.phone,
    required this.status,
    required this.responseCode,
    required this.errorMessage,
    required this.message,
  });

  factory WhatsAppLogItem.fromJson(Map<String, dynamic> json) {
    return WhatsAppLogItem(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      createdAt: json['created_at']?.toString() ?? '',
      studentName: json['student_name']?.toString() ?? '-',
      guardianName: json['guardian_name']?.toString() ?? '-',
      className: json['class_name']?.toString() ?? '-',
      phone: json['phone']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      responseCode: int.tryParse(json['response_code']?.toString() ?? '0') ?? 0,
      errorMessage: json['error_message']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
    );
  }
}

