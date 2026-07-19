/// AttendancePort → `GET /api/v1/hr/attendance/today` (thin ERP adapter).
///
/// Phase 3: [today] only. Other methods deferred.
library;

import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpAttendanceAdapter implements AttendancePort {
  ErpAttendanceAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const todayPath = '/api/v1/hr/attendance/today';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> today() async {
    try {
      final ctx = EmployeeContext.requireResolved();
      final body = await _http.get(
        todayPath,
        query: {
          'employee_id': ctx.employeeId,
          'date': _calendarDateIso(),
        },
      );
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'attendance_failed',
          message: body['message']?.toString(),
        );
      }
      final attendance = body['attendance'];
      if (attendance == null) {
        return <String, Object?>{};
      }
      if (attendance is! Map) {
        return <String, Object?>{};
      }
      return attendance.map((k, v) => MapEntry(k.toString(), v));
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<List<Map<String, Object?>>> history() {
    throw UnsupportedError('Attendance history is not Phase 3');
  }

  @override
  Future<void> checkIn(Map<String, Object?> payload) {
    throw UnsupportedError('Check-in is not Phase 3');
  }

  @override
  Future<void> checkOut(Map<String, Object?> payload) {
    throw UnsupportedError('Check-out is not Phase 3');
  }

  /// Request calendar date only — not an attendance status rule.
  static String _calendarDateIso() {
    final n = DateTime.now();
    final y = n.year.toString().padLeft(4, '0');
    final m = n.month.toString().padLeft(2, '0');
    final d = n.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }
}
