class GradeStudentItem {
  final int id;
  final String nis;
  final String name;
  double? score;
  String description;

  GradeStudentItem({
    required this.id,
    required this.nis,
    required this.name,
    this.score,
    required this.description,
  });

  factory GradeStudentItem.fromJson(Map<String, dynamic> json) {
    return GradeStudentItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      nis: json['nis']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      score: json['score'] != null ? double.tryParse(json['score'].toString()) : null,
      description: json['description']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'student_id': id,
      'score': score,
    };
  }
}

class LearningObjectiveModel {
  final int id;
  final String code;
  final String description;

  LearningObjectiveModel({
    required this.id,
    required this.code,
    required this.description,
  });

  factory LearningObjectiveModel.fromJson(Map<String, dynamic> json) {
    return LearningObjectiveModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      code: json['code']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
    );
  }

  String get displayName => code.isNotEmpty ? '$code: $description' : description;
}
