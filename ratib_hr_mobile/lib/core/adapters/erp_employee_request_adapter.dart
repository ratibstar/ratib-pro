/// EmployeeRequestPort → `/api/v1/hr/requests`.
library;

import 'package:ratib_hr_mobile/core/contracts/employee_request_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpEmployeeRequestAdapter implements EmployeeRequestPort {
  ErpEmployeeRequestAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const listPath = '/api/v1/hr/requests';
  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> listMine() async {
    try {
      final body = await _http.get(listPath);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'requests_failed',
          message: body['message']?.toString(),
        );
      }
      final raw = body['requests'];
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
      final body = await _http.post(listPath, body: payload);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'request_submit_failed',
          message: body['message']?.toString(),
        );
      }
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<Map<String, Object?>> detail(String id) async {
    try {
      final body = await _http.get('$listPath/$id');
      if (body['success'] != true) {
        throw AppFailure(
          code: 'request_not_found',
          message: body['message']?.toString(),
        );
      }
      final req = body['request'];
      final map = <String, Object?>{};
      if (req is Map) {
        map.addAll(req.map((k, v) => MapEntry(k.toString(), v)));
      }
      map['history'] = body['history'];
      return map;
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
