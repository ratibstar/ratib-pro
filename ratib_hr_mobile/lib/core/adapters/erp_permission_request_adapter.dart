/// PermissionRequestPort → `/api/v1/hr/permission-requests`.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/permission_request_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpPermissionRequestAdapter implements PermissionRequestPort {
  ErpPermissionRequestAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const listPath = '/api/v1/hr/permission-requests';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> listMine() async {
    try {
      final body = await _http.get(listPath);
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'permission_list_failed',
          message: body['message']?.toString(),
        );
      }
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
  Future<void> submit(Map<String, Object?> payload) async {
    try {
      final clean = Map<String, Object?>.from(payload)
        ..remove('employee_id')
        ..remove('company_id')
        ..remove('user_id')
        ..remove('status');
      final body = await _http.post(listPath, body: clean);
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'permission_submit_failed',
          message: body['message']?.toString(),
        );
      }
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
