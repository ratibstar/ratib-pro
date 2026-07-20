/// RatingsPort → `GET /api/v1/hr/ratings`.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/ratings_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpRatingsAdapter implements RatingsPort {
  ErpRatingsAdapter({required ErpHttpClient http, required ErrorMapper errors})
      : _http = http,
        _errors = errors;

  static const path = '/api/v1/hr/ratings';
  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> summary() async {
    try {
      final body = await _http.get(path);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'ratings_failed',
          message: body['message']?.toString(),
        );
      }
      return body;
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
