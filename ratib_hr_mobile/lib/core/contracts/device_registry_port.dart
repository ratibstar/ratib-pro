/// Shared ERP mobile device registry — presentation adapter only.
///
/// No local device authority. No push UI / notification business rules.
library;

abstract interface class DeviceRegistryPort {
  Future<Map<String, Object?>> register({
    required String deviceId,
    required String platform,
    String? pushToken,
    String? appVersion,
  });

  Future<Map<String, Object?>> heartbeat({
    required String deviceId,
    String? pushToken,
    String? appVersion,
  });

  Future<void> revoke(int devicePk);
}
