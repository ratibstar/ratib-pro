/// DeviceRegistryPort → shared ERP `/api/v1/mobile/devices/*`.
library;

import 'package:ratib_hr_mobile/core/contracts/device_registry_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpDeviceRegistryAdapter implements DeviceRegistryPort {
  ErpDeviceRegistryAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const registerPath = '/api/v1/mobile/devices/register';
  static const heartbeatPath = '/api/v1/mobile/devices/heartbeat';
  static const clientApp = 'ess';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<Map<String, Object?>> register({
    required String deviceId,
    required String platform,
    String? pushToken,
    String? appVersion,
  }) async {
    try {
      final body = await _http.post(
        registerPath,
        body: {
          'client_app': clientApp,
          'device_id': deviceId,
          'platform': platform,
          if (pushToken != null && pushToken.isNotEmpty) 'push_token': pushToken,
          if (appVersion != null && appVersion.isNotEmpty)
            'app_version': appVersion,
        },
      );
      _ensureSuccess(body, 'device_register_failed');
      return _device(body);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<Map<String, Object?>> heartbeat({
    required String deviceId,
    String? pushToken,
    String? appVersion,
  }) async {
    try {
      final body = await _http.post(
        heartbeatPath,
        body: {
          'client_app': clientApp,
          'device_id': deviceId,
          if (pushToken != null) 'push_token': pushToken,
          if (appVersion != null) 'app_version': appVersion,
        },
      );
      _ensureSuccess(body, 'device_heartbeat_failed');
      return _device(body);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> revoke(int devicePk) async {
    try {
      final body = await _http.post('/api/v1/mobile/devices/$devicePk/revoke');
      _ensureSuccess(body, 'device_revoke_failed');
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  Map<String, Object?> _device(Map<String, Object?> body) {
    final data = body['data'];
    final row = data is Map ? data['device'] : body['device'];
    if (row is! Map) return <String, Object?>{};
    return row.map((k, v) => MapEntry(k.toString(), v));
  }

  void _ensureSuccess(Map<String, Object?> body, String fallbackCode) {
    if (body['success'] == true) return;
    throw AppFailure(
      code: body['code']?.toString() ?? fallbackCode,
      message: body['message']?.toString(),
    );
  }
}
