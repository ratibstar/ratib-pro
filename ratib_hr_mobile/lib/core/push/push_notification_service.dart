/// ESS push client — token ↔ ERP registry only. No notification business rules.
library;

import 'dart:async';
import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/device_registry_port.dart';
import 'package:ratib_hr_mobile/core/device/device_registry_service.dart';
import 'package:ratib_hr_mobile/core/device/local_device_id_store.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/push/local_notification_presenter.dart';
import 'package:ratib_hr_mobile/core/push/push_messaging_gateway.dart';

final class PushNotificationService {
  PushNotificationService({
    required DeviceRegistryPort devices,
    required LocalDeviceIdStore deviceIds,
    required PushMessagingGateway messaging,
    required LocalNotificationPresenter localNotifications,
  })  : _devices = devices,
        _deviceIds = deviceIds,
        _messaging = messaging,
        _local = localNotifications;

  final DeviceRegistryPort _devices;
  final LocalDeviceIdStore _deviceIds;
  final PushMessagingGateway _messaging;
  final LocalNotificationPresenter _local;

  StreamSubscription<String>? _tokenSub;
  StreamSubscription<PushDisplayMessage>? _fgSub;
  bool _handlersBound = false;

  /// After device register: init messaging, permission, send token to ERP.
  /// Soft-fails on network / missing Firebase — never invents business rules.
  Future<void> registerPushAfterDevice() async {
    final ready = await _messaging.ensureInitialized();
    if (!ready) return;

    await _local.ensureInitialized();
    await _bindHandlers();
    await _messaging.requestPermission();
    await _messaging.registerBackgroundHandler();

    final token = await _messaging.getToken();
    if (token == null || token.isEmpty) return;
    await _sendTokenToErp(token);
  }

  Future<void> _bindHandlers() async {
    if (_handlersBound) return;
    _handlersBound = true;
    _tokenSub = _messaging.onTokenRefresh.listen((token) async {
      try {
        await _sendTokenToErp(token);
      } catch (_) {
        // Soft-fail refresh — session remains valid.
      }
    });
    _fgSub = _messaging.onForegroundMessage.listen((msg) async {
      try {
        await _local.show(msg);
      } catch (_) {}
    });
  }

  Future<void> _sendTokenToErp(String pushToken) async {
    final deviceId = await _deviceIds.getOrCreate();
    await _devices.updatePushToken(
      deviceId: deviceId,
      pushToken: pushToken,
      pushProvider: _provider(),
      platform: _platform(),
      appVersion: '0.1.12',
    );
  }

  String _provider() {
    // Firebase Messaging yields an FCM registration token on Android and iOS.
    return 'fcm';
  }

  String _platform() {
    if (kIsWeb) return 'other';
    try {
      if (Platform.isAndroid) return 'android';
      if (Platform.isIOS) return 'ios';
    } catch (_) {}
    return 'other';
  }

  static bool isRevokedFailure(Object e) =>
      DeviceRegistryService.isRevokedFailure(e) ||
      (e is AppFailure && e.code == 'device_revoked');

  Future<void> dispose() async {
    await _tokenSub?.cancel();
    await _fgSub?.cancel();
  }
}
