/// ESS device registry orchestration — register + heartbeat after auth.
library;

import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/device_registry_port.dart';
import 'package:ratib_hr_mobile/core/device/local_device_id_store.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class DeviceRegistryService {
  DeviceRegistryService({
    required DeviceRegistryPort port,
    required LocalDeviceIdStore deviceIds,
  })  : _port = port,
        _deviceIds = deviceIds;

  final DeviceRegistryPort _port;
  final LocalDeviceIdStore _deviceIds;

  int? lastDevicePk;
  String? lastStatus;

  Future<void> registerAndHeartbeat() async {
    final deviceId = await _deviceIds.getOrCreate();
    final platform = _platform();
    const appVersion = '0.1.9';

    final registered = await _port.register(
      deviceId: deviceId,
      platform: platform,
      appVersion: appVersion,
    );
    _capture(registered);

    final heart = await _port.heartbeat(
      deviceId: deviceId,
      appVersion: appVersion,
    );
    _capture(heart);
  }

  void _capture(Map<String, Object?> device) {
    final id = device['id'];
    if (id is int) {
      lastDevicePk = id;
    } else {
      lastDevicePk = int.tryParse(id?.toString() ?? '');
    }
    lastStatus = device['status']?.toString();
  }

  static bool isRevokedFailure(Object e) {
    return e is AppFailure && e.code == 'device_revoked';
  }

  String _platform() {
    if (kIsWeb) return 'other';
    try {
      if (Platform.isAndroid) return 'android';
      if (Platform.isIOS) return 'ios';
    } catch (_) {}
    return 'other';
  }
}
