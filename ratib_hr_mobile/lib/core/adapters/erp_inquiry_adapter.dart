/// InquiryPort → employee requests typed inquiry/complaint.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/inquiry_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpInquiryAdapter implements InquiryPort {
  ErpInquiryAdapter({required ErpHttpClient http, required ErrorMapper errors})
      : _http = http,
        _errors = errors;

  static const path = '/api/v1/hr/requests';
  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> listMine({String? type}) async {
    try {
      final body = await _http.get(
        path,
        query: type == null || type.isEmpty ? null : {'type': type},
      );
      if (body['success'] != true) {
        throw AppFailure(
          code: 'inquiries_failed',
          message: body['message']?.toString(),
        );
      }
      final raw = body['requests'];
      if (raw is! List) return const [];
      return raw
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .where((e) {
            final t = (e['request_type'] ?? '').toString().toLowerCase();
            return t == 'inquiry' || t == 'complaint';
          })
          .toList(growable: false);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> submit(Map<String, Object?> payload) async {
    try {
      final body = await _http.post(path, body: payload);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'inquiry_submit_failed',
          message: body['message']?.toString(),
        );
      }
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
