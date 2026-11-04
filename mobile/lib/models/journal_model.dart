class JournalModel {
  final int id;
  final int assignmentId;
  final int teacherId;
  final int classId;
  final int subjectId;
  final String date;
  final int meetingNo;
  final String topic;
  final String activities;
  final String materials;
  final String obstacles;
  final String followUp;
  final String className;
  final String subjectName;
  final String teacherName;

  JournalModel({
    required this.id,
    required this.assignmentId,
    required this.teacherId,
    required this.classId,
    required this.subjectId,
    required this.date,
    required this.meetingNo,
    required this.topic,
    required this.activities,
    required this.materials,
    required this.obstacles,
    required this.followUp,
    required this.className,
    required this.subjectName,
    required this.teacherName,
  });

  factory JournalModel.fromJson(Map<String, dynamic> json) {
    return JournalModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      assignmentId: json['assignment_id'] is int ? json['assignment_id'] : int.tryParse(json['assignment_id']?.toString() ?? '0') ?? 0,
      teacherId: json['teacher_id'] is int ? json['teacher_id'] : int.tryParse(json['teacher_id']?.toString() ?? '0') ?? 0,
      classId: json['class_id'] is int ? json['class_id'] : int.tryParse(json['class_id']?.toString() ?? '0') ?? 0,
      subjectId: json['subject_id'] is int ? json['subject_id'] : int.tryParse(json['subject_id']?.toString() ?? '0') ?? 0,
      date: json['date']?.toString() ?? '',
      meetingNo: json['meeting_no'] is int ? json['meeting_no'] : int.tryParse(json['meeting_no']?.toString() ?? '1') ?? 1,
      topic: json['topic']?.toString() ?? '',
      activities: json['activities']?.toString() ?? '',
      materials: json['materials']?.toString() ?? '',
      obstacles: json['obstacles']?.toString() ?? '',
      followUp: json['follow_up']?.toString() ?? '',
      className: json['class_name']?.toString() ?? '',
      subjectName: json['subject_name']?.toString() ?? '',
      teacherName: json['teacher_name']?.toString() ?? '',
    );
  }
}
