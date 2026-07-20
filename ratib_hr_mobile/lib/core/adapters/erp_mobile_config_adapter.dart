/// MobileConfigPort → existing ERP `GET /api/mobile/config`.
///
/// Never sends company_id. Tenant comes from API token only.
library;

import 'package:dio/dio.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/mobile_config_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';

final class ErpMobileConfigAdapter implements MobileConfigPort {
  ErpMobileConfigAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const configPath = '/api/mobile/config';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<MobileAppConfiguration> fetchRemote() async {
    try {
      final body = await _http.get(configPath);
      if (body['success'] != true) {
        throw AppFailure(
          code: 'mobile_config_failed',
          message: body['message']?.toString(),
        );
      }
      final cfg = MobileAppConfiguration.fromErpBody(
        body,
        fetchedAt: DateTime.now().toUtc(),
        fromCache: false,
      );
      if (!cfg.mobileActive || cfg.companyId < 1) {
        throw const AppFailure(
          code: 'mobile_disabled',
          message: 'Mobile app is not enabled for this company',
        );
      }
      return cfg;
    } catch (e, st) {
      if (e is AppFailure) rethrow;
      if (e is DioException && e.response?.statusCode == 403) {
        final data = e.response?.data;
        String? msg;
        if (data is Map && data['message'] != null) {
          msg = data['message'].toString();
        }
        throw AppFailure(
          code: 'mobile_disabled',
          message: msg ?? 'Mobile app is not enabled for this company',
        );
      }
      throw _errors.map(e, st);
    }
  }
}
