/// Presentation auth + ESS identity + mobile config gate + device + push.
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
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';
import 'package:ratib_hr_mobile/core/push/push_notification_service.dart';

enum AuthStatus { unknown, signedOut, signedIn }

final class AuthSession extends ChangeNotifier {
  AuthSession({
    AuthPort? auth,
    MePort? me,
    MobileConfigurationService? mobileConfiguration,
    DeviceRegistryService? deviceRegistry,
    PushNotificationService? pushNotifications,
  })  : _auth = auth,
        _me = me,
        _mobileConfiguration = mobileConfiguration,
        _deviceRegistry = deviceRegistry,
        _pushNotifications = pushNotifications;

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
  final PushNotificationService? _pushNotifications;

  AuthStatus status = AuthStatus.unknown;
  AppFailure? lastError;
  bool _signInInProgress = false;

  /// True when the active session was restored from local caches (offline).
  bool offlineSession = false;

  PushNotificationService? get _pushOrNull {
    if (_pushNotifications != null) return _pushNotifications;
    try {
      return AppLocator.pushNotifications;
    } catch (_) {
      return null;
    }
  }

  Future<void> restore() async {
    _signInInProgress = true;
    offlineSession = false;
    lastError = null;
    try {
      final ok = await _authPort.hasSession();
      if (!ok) {
        await _resetLocal(wipeDisk: true);
        status = AuthStatus.signedOut;
        notifyListeners();
        return;
      }
      final bioGate = await _requireBiometricUnlockIfEnabled();
      if (!bioGate) {
        // Keep token — user can unlock from login with biometrics or password.
        status = AuthStatus.signedOut;
        notifyListeners();
        return;
      }
      try {
        await _resolveEmployeeOrThrow();
        await _mobileConfig.refreshAfterLogin();
        await _registerDeviceSafe();
        await _registerPushSafe();
        status = AuthStatus.signedIn;
        offlineSession = false;
        await _warmEssCaches();
      } on AppFailure catch (e) {
        lastError = e;
        if (_isConnectivityFailure(e)) {
          final hydrated = await _hydrateOfflineSession();
          if (hydrated) {
            offlineSession = true;
            _markConnectivityOffline(e.message);
            status = AuthStatus.signedIn;
          } else {
            // Keep the token — do not wipe session caches on transport failure.
            await _resetLocal(wipeDisk: false);
            status = AuthStatus.signedOut;
          }
        } else if (_isHardSessionFailure(e)) {
          await _authPort.signOut();
          await _resetLocal(wipeDisk: true);
          status = AuthStatus.signedOut;
        } else {
          // Unknown domain failure: try offline hydrate before forcing login.
          final hydrated = await _hydrateOfflineSession();
          if (hydrated) {
            offlineSession = true;
            status = AuthStatus.signedIn;
          } else {
            await _authPort.signOut();
            await _resetLocal(wipeDisk: true);
            status = AuthStatus.signedOut;
          }
        }
      } catch (e) {
        lastError = e is AppFailure ? e : AppLocator.errors.map(e);
        if (_isConnectivityFailure(lastError!)) {
          final hydrated = await _hydrateOfflineSession();
          if (hydrated) {
            offlineSession = true;
            _markConnectivityOffline(lastError!.message);
            status = AuthStatus.signedIn;
          } else {
            await _resetLocal(wipeDisk: false);
            status = AuthStatus.signedOut;
          }
        } else {
          await _authPort.signOut();
          await _resetLocal(wipeDisk: true);
          status = AuthStatus.signedOut;
        }
      }
    } finally {
      _signInInProgress = false;
      notifyListeners();
    }
  }

  Future<bool> signIn({
    required String identifier,
    required String secret,
  }) async {
    lastError = null;
    offlineSession = false;
    _signInInProgress = true;
    try {
      await _authPort.signIn(identifier: identifier, secret: secret);
      await _resolveEmployeeOrThrow();
      await _mobileConfig.refreshAfterLogin();
      await _registerDeviceSafe();
      await _registerPushSafe();
      status = AuthStatus.signedIn;
      await _warmEssCaches();
      notifyListeners();
      return true;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      await _authPort.signOut();
      await _resetLocal(wipeDisk: true);
      status = AuthStatus.signedOut;
      notifyListeners();
      return false;
    } finally {
      _signInInProgress = false;
    }
  }

  /// Unlock a stored ERP session with device biometrics (no password re-entry).
  Future<bool> unlockWithBiometric() async {
    lastError = null;
    offlineSession = false;
    _signInInProgress = true;
    try {
      final has = await _authPort.hasSession();
      if (!has) {
        lastError = const AppFailure(code: 'unauthorized', message: 'No session');
        status = AuthStatus.signedOut;
        notifyListeners();
        return false;
      }
      final bioOn = await AppLocator.settings.biometricEnabled();
      if (!bioOn) {
        lastError = const AppFailure(code: 'biometric_disabled');
        return false;
      }
      final unlocked = await AppLocator.biometric.unlock();
      if (!unlocked) {
        lastError = const AppFailure(code: 'biometric_failed');
        status = AuthStatus.signedOut;
        notifyListeners();
        return false;
      }
      await _resolveEmployeeOrThrow();
      await _mobileConfig.refreshAfterLogin();
      await _registerDeviceSafe();
      await _registerPushSafe();
      status = AuthStatus.signedIn;
      await _warmEssCaches();
      notifyListeners();
      return true;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      status = AuthStatus.signedOut;
      notifyListeners();
      return false;
    } finally {
      _signInInProgress = false;
    }
  }

  Future<bool> hasStoredSession() => _authPort.hasSession();

  Future<bool> biometricUnlockAvailable() async {
    try {
      final bioOn = await AppLocator.settings.biometricEnabled();
      if (!bioOn) return false;
      final has = await _authPort.hasSession();
      if (!has) return false;
      return AppLocator.biometric.isAvailable();
    } catch (_) {
      return false;
    }
  }

  Future<bool> _requireBiometricUnlockIfEnabled() async {
    try {
      final bioOn = await AppLocator.settings.biometricEnabled();
      if (!bioOn) return true;
      final available = await AppLocator.biometric.isAvailable();
      if (!available) return true;
      return AppLocator.biometric.unlock();
    } catch (_) {
      return true;
    }
  }

  Future<void> signOut() async {
    await _authPort.signOut();
    await _resetLocal(wipeDisk: true);
    status = AuthStatus.signedOut;
    offlineSession = false;
    lastError = null;
    notifyListeners();
  }

  /// Phase 3.2 — HTTP 401: drop token + local ESS session (router redirects to login).
  void handleUnauthorized() {
    if (_signInInProgress) {
      return;
    }
    _authPort.signOut().then((_) async {
      await _resetLocal(wipeDisk: true);
      status = AuthStatus.signedOut;
      offlineSession = false;
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
      // Soft-fail: missing migrations / ERP 5xx / validation must not block ESS login.
      // Hard-fail only when the device is explicitly revoked.
      return;
    } catch (_) {
      return;
    }
  }

  /// Push token → ERP after device register. Soft-fail network; hard-fail revoke.
  Future<void> _registerPushSafe() async {
    final push = _pushOrNull;
    if (push == null) return;
    try {
      await push.registerPushAfterDevice();
    } on AppFailure catch (e) {
      if (PushNotificationService.isRevokedFailure(e)) {
        throw AppFailure(
          code: 'device_revoked',
          message: e.message ?? 'Device has been revoked',
        );
      }
      if (e.code == 'network' ||
          e.code == 'timeout' ||
          e.code == 'config' ||
          e.code == 'not_found') {
        return;
      }
      // Missing Firebase / permission denied — do not break login.
      return;
    } catch (_) {
      return;
    }
  }

  Future<void> _resolveEmployeeOrThrow() async {
    _clearMeCache(wipeDisk: false);
    EmployeeContext.clear();
    await _mePort.currentEmployee();
    if (!EmployeeContext.isResolved) {
      throw const AppFailure(
        code: 'employee_unbound',
        message: 'No employee linked to this user',
      );
    }
  }

  Future<bool> _hydrateOfflineSession() async {
    final me = _mePort;
    var employeeOk = EmployeeContext.isResolved;
    if (!employeeOk && me is ErpMeAdapter) {
      employeeOk = await me.hydrateFromDisk();
    }
    if (!employeeOk) return false;

    if (_mobileConfig.current != null && _mobileConfig.current!.mobileActive) {
      return true;
    }
    return _mobileConfig.hydrateFromCache();
  }

  Future<void> _resetLocal({required bool wipeDisk}) async {
    EmployeeContext.clear();
    _clearMeCache(wipeDisk: wipeDisk);
    if (wipeDisk) {
      await _mobileConfig.clearSession();
    } else {
      // Keep disk branding/claims; clear only in-memory config pointer if unbound.
      // Do not delete mobile_app_config.v1 on transport failures.
    }
  }

  void _clearMeCache({required bool wipeDisk}) {
    final me = _mePort;
    if (me is ErpMeAdapter) {
      me.clearCache(wipeDisk: wipeDisk);
    }
  }

  bool _isConnectivityFailure(AppFailure e) =>
      e.code == 'network' || e.code == 'timeout';

  bool _isHardSessionFailure(AppFailure e) {
    switch (e.code) {
      case 'unauthorized':
      case 'employee_unbound':
      case 'employee_ambiguous':
      case 'mobile_disabled':
      case 'forbidden':
      case 'device_revoked':
        return true;
      default:
        return false;
    }
  }

  void _markConnectivityOffline(String? message) {
    try {
      AppLocator.connectivity.markOffline(message);
    } catch (_) {}
  }

  /// Prefetch ALL ESS read caches while online so every screen opens offline
  /// without requiring the user to visit that screen first.
  /// Awaited on login/restore so caches exist before the user goes offline.
  Future<void> _warmEssCaches() async {
    Future<void> safe(Future<void> Function() fn) async {
      try {
        await fn();
      } catch (_) {}
    }

    await Future.wait<void>([
      safe(() async {
        await AppLocator.attendanceRepository.loadToday();
      }),
      safe(() async {
        await AppLocator.attendanceRepository.loadHistory();
      }),
      safe(() async {
        await AppLocator.leaveRepository.loadBalances();
      }),
      safe(() async {
        await AppLocator.leaveRepository.loadRequests();
      }),
      safe(() async {
        await AppLocator.profileRepository.loadMine();
      }),
      safe(() async {
        final body = await AppLocator.dashboard.summary();
        await EssReadCache.writeMap(EssReadCache.dashboard, body);
      }),
      safe(() async {
        await AppLocator.documentsRepository.loadList();
      }),
      safe(() async {
        await AppLocator.payslipRepository.loadList();
      }),
      safe(() async {
        await EssReadCache.fetchList(
          key: EssReadCache.permissionRequests,
          fetch: () => AppLocator.permissionRequests.listMine(),
        );
      }),
      safe(() async {
        await EssReadCache.fetchList(
          key: EssReadCache.employeeRequests,
          fetch: () => AppLocator.employeeRequests.listMine(),
        );
      }),
      safe(() async {
        await EssReadCache.fetchList(
          key: EssReadCache.notifications,
          fetch: () => AppLocator.notifications.listFiltered(''),
        );
      }),
      safe(() async {
        await EssReadCache.fetchMap(
          key: EssReadCache.ratings,
          fetch: () => AppLocator.ratings.summary(),
        );
      }),
      safe(() async {
        await EssReadCache.fetchList(
          key: EssReadCache.inquiries,
          fetch: () => AppLocator.inquiries.listMine(),
        );
      }),
      safe(() async {
        await EssReadCache.fetchMap(
          key: EssReadCache.payments,
          fetch: () => AppLocator.payments.list(),
        );
      }),
    ]).timeout(
      const Duration(seconds: 60),
      onTimeout: () => <void>[],
    );
  }
}
