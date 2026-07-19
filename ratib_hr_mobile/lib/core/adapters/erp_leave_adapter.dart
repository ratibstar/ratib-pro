/// LeavePort → `GET /api/v1/hr/leave/balances` (thin ERP adapter).
///
/// Phase 3: [balances] only. Other methods deferred.
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

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> balances() async {
    try {
      final ctx = EmployeeContext.requireResolved();
      final body = await _http.get(
        balancesPath,
        query: {
          'employee_id': ctx.employeeId,
          'year': DateTime.now().year.toString(),
        },
      );
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'leave_balances_failed',
          message: body['message']?.toString(),
        );
      }
      final raw = body['balances'];
      if (raw is! List) {
        return const [];
      }
      return raw
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList(growable: false);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<List<Map<String, Object?>>> status() {
    throw UnsupportedError('Leave status is not Phase 3');
  }

  @override
  Future<void> apply(Map<String, Object?> payload) {
    throw UnsupportedError('Apply leave is not Phase 3');
  }
}
