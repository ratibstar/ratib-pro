/// Home DTOs — field projection only. No HR calculations.
library;

final class HomeAttendanceDto {
  const HomeAttendanceDto({
    required this.hasRecord,
    this.status,
    this.checkIn,
    this.checkOut,
    this.attendanceDate,
  });

  final bool hasRecord;
  final String? status;
  final String? checkIn;
  final String? checkOut;
  final String? attendanceDate;

  factory HomeAttendanceDto.fromErp(Map<String, Object?> map) {
    if (map.isEmpty) {
      return const HomeAttendanceDto(hasRecord: false);
    }
    return HomeAttendanceDto(
      hasRecord: true,
      status: map['status']?.toString(),
      checkIn: map['check_in']?.toString(),
      checkOut: map['check_out']?.toString(),
      attendanceDate: map['attendance_date']?.toString(),
    );
  }
}

final class HomeLeaveBalanceDto {
  const HomeLeaveBalanceDto({
    required this.typeName,
    required this.entitledDays,
    required this.usedDays,
  });

  final String typeName;
  final String entitledDays;
  final String usedDays;

  factory HomeLeaveBalanceDto.fromErp(Map<String, Object?> map) {
    return HomeLeaveBalanceDto(
      typeName: map['leave_type_name']?.toString() ??
          map['leave_type_code']?.toString() ??
          '',
      entitledDays: map['entitled_days']?.toString() ?? '',
      usedDays: map['used_days']?.toString() ?? '',
    );
  }
}

final class HomeNotificationDto {
  const HomeNotificationDto({
    required this.id,
    required this.title,
    this.message,
    this.createdAt,
    this.unread = false,
  });

  final String id;
  final String title;
  final String? message;
  final String? createdAt;
  final bool unread;

  factory HomeNotificationDto.fromErp(Map<String, Object?> map) {
    final readFlag = map['is_read'];
    final unread = readFlag == 0 ||
        readFlag == '0' ||
        readFlag == false ||
        readFlag == null;
    return HomeNotificationDto(
      id: map['id']?.toString() ?? '',
      title: map['title']?.toString() ?? '',
      message: map['message']?.toString(),
      createdAt: map['created_at']?.toString(),
      unread: unread,
    );
  }
}
