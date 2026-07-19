/// NotificationPort → `GET /api/v1/hr/notifications` (thin ERP adapter).
///
/// Phase 3: [list] only. markRead deferred.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/notification_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpNotificationAdapter implements NotificationPort {
  ErpNotificationAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const listPath = '/api/v1/hr/notifications';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> list() async {
    try {
      final body = await _http.get(listPath);
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'notifications_failed',
          message: body['message']?.toString(),
        );
      }
      final raw = body['notifications'];
      if (raw is! List) {
        return const [];
      }
      return raw
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList(growable: false);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> markRead(String notificationId) {
    throw UnsupportedError('Mark read is not Phase 3');
  }
}
