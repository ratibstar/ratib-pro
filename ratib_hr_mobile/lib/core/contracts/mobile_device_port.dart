/// Mobile device registry APIs shared by ESS / Manager (ERP-owned).
///
/// Presentation adapter only — no notification business rules.
library;

/// Thin contract for push-token updates (Phase I.3).
abstract interface class MobileDevicePort {
  /// Upserts delivery handle via `POST /api/v1/mobile/devices/push-token`.
  /// Never send user_id / company_id — ERP binds from bearer token.
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  });
}
