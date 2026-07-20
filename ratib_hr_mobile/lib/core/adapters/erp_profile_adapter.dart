/// ProfilePort → GET /api/v1/hr/profile (thin ERP adapter).
///
/// Identity is server-resolved — never send employee_id / company_id.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/profile_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpProfileAdapter implements ProfilePort {
  ErpProfileAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const profilePath = '/api/v1/hr/profile';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> mine() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(profilePath);
      _ensureSuccess(body, 'profile_failed');
      final data = body['data'];
      final row = data is Map ? data['profile'] : body['profile'];
      if (row is! Map) return <String, Object?>{};
      return row.map((k, v) => MapEntry(k.toString(), v));
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
