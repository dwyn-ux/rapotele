class UserModel {
  final int id;
  final String username;
  final String name;
  final String role;
  final String email;
  final int? teacherId;
  final int? studentId;

  UserModel({
    required this.id,
    required this.username,
    required this.name,
    required this.role,
    this.email = '',
    this.teacherId,
    this.studentId,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0,
      username: json['username']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      role: json['role']?.toString() ?? 'guru',
      email: json['email']?.toString() ?? '',
      teacherId: json['teacher_id'] != null ? int.tryParse(json['teacher_id'].toString()) : null,
      studentId: json['student_id'] != null ? int.tryParse(json['student_id'].toString()) : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'name': name,
    'role': role,
    'email': email,
    'teacher_id': teacherId,
    'student_id': studentId,
  };
}

class SchoolModel {
  final String name;
  final String npsn;
  final String address;
  final String academicYear;
  final String semester;
  final String logoUrl;

  SchoolModel({
    required this.name,
    required this.npsn,
    required this.address,
    required this.academicYear,
    required this.semester,
    required this.logoUrl,
  });

  factory SchoolModel.fromJson(Map<String, dynamic> json) {
    return SchoolModel(
      name: json['name']?.toString() ?? 'SMP Muhammadiyah Unggulan Ashidiq',
      npsn: json['npsn']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      academicYear: json['academic_year']?.toString() ?? '2026/2027',
      semester: json['semester']?.toString() ?? 'Gasal',
      logoUrl: json['logo_url']?.toString() ?? '',
    );
  }
}

