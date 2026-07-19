/// MePort → existing ERP `GET /api/v1/hr/me` (user_id linkage only).
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/me_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpMeAdapter implements MePort {
  ErpMeAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const mePath = '/api/v1/hr/me';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  Map<String, Object?>? _sessionCache;

  @override
  Future<Map<String, Object?>> currentEmployee() async {
    if (_sessionCache != null) {
      return Map<String, Object?>.from(_sessionCache!);
    }
    try {
      final body = await _http.get(mePath);
      final success = body['success'] == true;
      final employee = body['employee'];
      if (!success || employee is! Map) {
        final code = body['code']?.toString() ?? 'employee_unbound';
        throw AppFailure(
          code: code,
          message: body['message']?.toString(),
        );
      }
      final record = employee.map((k, v) => MapEntry(k.toString(), v));
      _sessionCache = record;
      EmployeeContext.bind(EmployeeContext.fromErpRecord(record));
      return Map<String, Object?>.from(record);
    } catch (e, st) {
      _sessionCache = null;
      EmployeeContext.clear();
      throw _errors.map(e, st);
    }
  }

  @override
  Future<String?> currentEmployeeId() async {
    if (EmployeeContext.isResolved) {
      return EmployeeContext.current!.employeeId;
    }
    final record = await currentEmployee();
    return record['id']?.toString();
  }

  void clearCache() {
    _sessionCache = null;
    EmployeeContext.clear();
  }
}
