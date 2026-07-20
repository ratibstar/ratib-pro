/// LeavePort → ESS leave APIs (thin ERP adapter).
///
/// Identity is server-resolved — never send employee_id.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpLeaveAdapter implements LeavePort {
  ErpLeaveAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const balancesPath = '/api/v1/hr/leave/balances';
  static const requestsPath = '/api/v1/hr/leave/requests';
  static const applyPath = '/api/v1/hr/leave/apply';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> balances() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(
        balancesPath,
        query: {'year': DateTime.now().year.toString()},
      );
      _ensureSuccess(body, 'leave_balances_failed');
      final data = body['data'];
      final raw = data is Map ? data['items'] : body['balances'];
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
  Future<List<Map<String, Object?>>> status() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(requestsPath);
      _ensureSuccess(body, 'leave_requests_failed');
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

  Future<Map<String, Object?>> detail(String requestId) async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get('$requestsPath/$requestId');
      _ensureSuccess(body, 'leave_request_failed');
      final data = body['data'];
      final row = data is Map ? data['request'] : body['request'];
      if (row is! Map) return <String, Object?>{};
      return row.map((k, v) => MapEntry(k.toString(), v));
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> apply(Map<String, Object?> payload) async {
    try {
      EmployeeContext.requireResolved();
      final safe = Map<String, Object?>.from(payload)
        ..remove('employee_id')
        ..remove('company_id')
        ..remove('user_id')
        ..remove('status');
      final body = await _http.post(applyPath, body: safe);
      _ensureSuccess(body, 'leave_apply_failed');
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
}
