/// AttendancePort → ESS attendance APIs (thin ERP adapter).
///
/// Identity is server-resolved — never send employee_id.
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
  static const historyPath = '/api/v1/hr/attendance/history';
  static const checkInPath = '/api/v1/hr/attendance/check-in';
  static const checkOutPath = '/api/v1/hr/attendance/check-out';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> today() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(
        todayPath,
        query: {'date': calendarDateIso()},
      );
      _ensureSuccess(body, 'attendance_failed');
      return _attendanceMap(body);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<List<Map<String, Object?>>> history() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(historyPath);
      _ensureSuccess(body, 'attendance_history_failed');
      final data = body['data'];
      final raw = data is Map ? data['items'] : body['items'];
      if (raw is! List) return const [];
      return raw
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList(growable: false);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> checkIn(Map<String, Object?> payload) async {
    try {
      EmployeeContext.requireResolved();
      // Never forward client employee_id — ERP resolves identity.
      final safe = Map<String, Object?>.from(payload)
        ..remove('employee_id')
        ..remove('company_id')
        ..remove('user_id');
      final body = await _http.post(checkInPath, body: safe);
      _ensureSuccess(body, 'check_in_failed');
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> checkOut(Map<String, Object?> payload) async {
    try {
      EmployeeContext.requireResolved();
      final safe = Map<String, Object?>.from(payload)
        ..remove('employee_id')
        ..remove('company_id')
        ..remove('user_id');
      final body = await _http.post(checkOutPath, body: safe);
      _ensureSuccess(body, 'check_out_failed');
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  void _ensureSuccess(Map<String, Object?> body, String fallbackCode) {
    if (body['success'] == true) return;
    throw AppFailure(
      code: body['code']?.toString() ?? fallbackCode,
      message: body['message']?.toString(),
    );
  }

  Map<String, Object?> _attendanceMap(Map<String, Object?> body) {
    final data = body['data'];
    final attendance = data is Map ? data['attendance'] : body['attendance'];
    if (attendance == null) return <String, Object?>{};
    if (attendance is! Map) return <String, Object?>{};
    return attendance.map((k, v) => MapEntry(k.toString(), v));
  }

  /// Request calendar date only — not an attendance status rule.
  static String calendarDateIso([DateTime? at]) {
    final n = at ?? DateTime.now();
    final y = n.year.toString().padLeft(4, '0');
    final m = n.month.toString().padLeft(2, '0');
    final d = n.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }
}
