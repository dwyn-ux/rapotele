class TeachingAssignmentModel {
  final int id;
  final int teacherId;
  final int classId;
  final int subjectId;
  final String academicYear;
  final String semester;
  final String className;
  final int grade;
  final String subjectName;
  final String subjectCode;

  TeachingAssignmentModel({
    required this.id,
    required this.teacherId,
    required this.classId,
    required this.subjectId,
    required this.academicYear,
    required this.semester,
    required this.className,
    required this.grade,
    required this.subjectName,
    required this.subjectCode,
  });

  factory TeachingAssignmentModel.fromJson(Map<String, dynamic> json) {
    return TeachingAssignmentModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      teacherId: json['teacher_id'] is int ? json['teacher_id'] : int.tryParse(json['teacher_id']?.toString() ?? '0') ?? 0,
      classId: json['class_id'] is int ? json['class_id'] : int.tryParse(json['class_id']?.toString() ?? '0') ?? 0,
      subjectId: json['subject_id'] is int ? json['subject_id'] : int.tryParse(json['subject_id']?.toString() ?? '0') ?? 0,
      academicYear: json['academic_year']?.toString() ?? '',
      semester: json['semester']?.toString() ?? '',
      className: json['class_name']?.toString() ?? '',
      grade: json['grade'] is int ? json['grade'] : int.tryParse(json['grade']?.toString() ?? '7') ?? 7,
      subjectName: json['subject_name']?.toString() ?? '',
      subjectCode: json['subject_code']?.toString() ?? '',
    );
  }

  String get displayName => '$className • $subjectName';
}
