/// Shared ERP mobile device registry — presentation adapter only.
///
/// No local device authority. No push UI / notification business rules.
library;

import 'package:ratib_hr_mobile/core/contracts/mobile_device_port.dart';

abstract interface class DeviceRegistryPort implements MobileDevicePort {
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
