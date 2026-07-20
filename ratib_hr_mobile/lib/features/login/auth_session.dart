/// Presentation auth + ESS identity + mobile config gate + device registry.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
import 'package:ratib_hr_mobile/core/contracts/me_port.dart';
import 'package:ratib_hr_mobile/core/device/device_registry_service.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';

enum AuthStatus { unknown, signedOut, signedIn }

final class AuthSession extends ChangeNotifier {
  AuthSession({
    AuthPort? auth,
    MePort? me,
    MobileConfigurationService? mobileConfiguration,
    DeviceRegistryService? deviceRegistry,
  })  : _auth = auth,
        _me = me,
        _mobileConfiguration = mobileConfiguration,
        _deviceRegistry = deviceRegistry;

  AuthPort get _authPort => _auth ?? AppLocator.auth;
  MePort get _mePort => _me ?? AppLocator.me;
  MobileConfigurationService get _mobileConfig =>
      _mobileConfiguration ?? AppLocator.mobileConfiguration;
  DeviceRegistryService get _devices =>
      _deviceRegistry ?? AppLocator.deviceRegistryService;
  final AuthPort? _auth;
  final MePort? _me;
  final MobileConfigurationService? _mobileConfiguration;
  final DeviceRegistryService? _deviceRegistry;

  AuthStatus status = AuthStatus.unknown;
  AppFailure? lastError;
  bool _signInInProgress = false;

  Future<void> restore() async {
    _signInInProgress = true;
    try {
      final ok = await _authPort.hasSession();
      if (!ok) {
        await _resetLocal();
        status = AuthStatus.signedOut;
        notifyListeners();
        return;
      }
      await _resolveEmployeeOrThrow();
      await _mobileConfig.refreshAfterLogin();
      await _registerDeviceSafe();
      status = AuthStatus.signedIn;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      await _authPort.signOut();
      await _resetLocal();
      status = AuthStatus.signedOut;
    }
    _signInInProgress = false;
    notifyListeners();
  }

  Future<bool> signIn({
    required String identifier,
    required String secret,
  }) async {
    lastError = null;
    _signInInProgress = true;
    try {
      await _authPort.signIn(identifier: identifier, secret: secret);
      await _resolveEmployeeOrThrow();
      await _mobileConfig.refreshAfterLogin();
      await _registerDeviceSafe();
      status = AuthStatus.signedIn;
      notifyListeners();
      return true;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      await _authPort.signOut();
      await _resetLocal();
      status = AuthStatus.signedOut;
      notifyListeners();
      return false;
    } finally {
      _signInInProgress = false;
    }
  }

  Future<void> signOut() async {
    await _authPort.signOut();
    await _resetLocal();
    status = AuthStatus.signedOut;
    lastError = null;
    notifyListeners();
  }

  /// Phase 3.2 — HTTP 401: drop token + local ESS session (router redirects to login).
  void handleUnauthorized() {
    if (_signInInProgress) {
      return;
    }
    _authPort.signOut().then((_) async {
      await _resetLocal();
      status = AuthStatus.signedOut;
      lastError = const AppFailure(
        code: 'unauthorized',
        message: 'Session expired',
      );
      notifyListeners();
    });
  }

  Future<void> _registerDeviceSafe() async {
    try {
      await _devices.registerAndHeartbeat();
    } on AppFailure catch (e) {
      if (DeviceRegistryService.isRevokedFailure(e)) {
        throw AppFailure(
          code: 'device_revoked',
          message: e.message ?? 'Device has been revoked',
        );
      }
      if (e.code == 'network' || e.code == 'timeout' || e.code == 'config') {
        return;
      }
      rethrow;
    }
  }

  Future<void> _resolveEmployeeOrThrow() async {
    _clearMeCache();
    EmployeeContext.clear();
    await _mePort.currentEmployee();
    if (!EmployeeContext.isResolved) {
      throw const AppFailure(
        code: 'employee_unbound',
        message: 'No employee linked to this user',
      );
    }
  }

  Future<void> _resetLocal() async {
    EmployeeContext.clear();
    _clearMeCache();
    await _mobileConfig.clearSession();
  }

  void _clearMeCache() {
    final me = _mePort;
    if (me is ErpMeAdapter) {
      me.clearCache();
    }
  }
}
