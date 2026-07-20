/// DashboardPort → `GET /api/v1/hr/dashboard`.
library;

import 'package:ratib_hr_mobile/core/contracts/dashboard_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpDashboardAdapter implements DashboardPort {
  ErpDashboardAdapter({required ErpHttpClient http, required ErrorMapper errors})
      : _http = http,
        _errors = errors;

  static const path = '/api/v1/hr/dashboard';
  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> summary() async {
    try {
      final body = await _http.get(
        path,
        query: {'rateb_r': DateTime.now().millisecondsSinceEpoch.toString()},
      );
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'dashboard_failed',
          message: body['message']?.toString(),
        );
      }
      return body;
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
