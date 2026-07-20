/// PaymentMethodsPort → `GET /api/v1/hr/payment-methods`.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/payment_methods_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpPaymentMethodsAdapter implements PaymentMethodsPort {
  ErpPaymentMethodsAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const path = '/api/v1/hr/payment-methods';
  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> list() async {
    try {
      final body = await _http.get(path);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'payments_failed',
          message: body['message']?.toString(),
        );
      }
      return body;
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }
}
