class TeacherAttendanceModel {
  final int? id;
  final int teacherId;
  final String date;
  final String status;
  final String? timeIn;
  final String? timeOut;
  final double? lat;
  final double? lng;
  final String notes;
  final double? schoolLat;
  final double? schoolLng;
  final int radiusMeters;

  TeacherAttendanceModel({
    this.id,
    required this.teacherId,
    required this.date,
    required this.status,
    this.timeIn,
    this.timeOut,
    this.lat,
    this.lng,
    required this.notes,
    this.schoolLat,
    this.schoolLng,
    this.radiusMeters = 500,
  });

  factory TeacherAttendanceModel.fromJson(Map<String, dynamic> json, {double? schoolLat, double? schoolLng, int? radiusMeters}) {
    return TeacherAttendanceModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? ''),
      teacherId: json['teacher_id'] is int ? json['teacher_id'] : int.tryParse(json['teacher_id']?.toString() ?? '0') ?? 0,
      date: json['date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'hadir',
      timeIn: json['time_in']?.toString(),
      timeOut: json['time_out']?.toString(),
      lat: json['location_lat'] != null ? double.tryParse(json['location_lat'].toString()) : null,
      lng: json['location_lng'] != null ? double.tryParse(json['location_lng'].toString()) : null,
      notes: json['notes']?.toString() ?? '',
      schoolLat: schoolLat,
      schoolLng: schoolLng,
      radiusMeters: radiusMeters ?? 500,
    );
  }

  bool get hasCheckedIn => timeIn != null && timeIn!.isNotEmpty;
  bool get hasCheckedOut => timeOut != null && timeOut!.isNotEmpty;
}

class StudentAttendanceItem {
  final int id;
  final String nis;
  final String nisn;
  final String name;
  final String gender;
  String status; // hadir, sakit, izin, alfa
  String notes;

  StudentAttendanceItem({
    required this.id,
    required this.nis,
    required this.nisn,
    required this.name,
    required this.gender,
    required this.status,
    required this.notes,
  });

  factory StudentAttendanceItem.fromJson(Map<String, dynamic> json) {
    return StudentAttendanceItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      nis: json['nis']?.toString() ?? '',
      nisn: json['nisn']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      gender: json['gender']?.toString() ?? 'L',
      status: (json['status']?.toString() ?? 'hadir').toLowerCase(),
      notes: json['notes']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'student_id': id,
      'status': status,
      'notes': notes,
    };
  }
}
