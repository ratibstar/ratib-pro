import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/device_registry_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/me_port.dart';
import 'package:ratib_hr_mobile/core/contracts/mobile_config_port.dart';
import 'package:ratib_hr_mobile/core/device/device_registry_service.dart';
import 'package:ratib_hr_mobile/core/device/local_device_id_store.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';

class _MemCache implements CacheStore {
  final map = <String, String>{};

  @override
  Future<void> clear() async => map.clear();

  @override
  Future<String?> read(String key) async => map[key];

  @override
  Future<void> remove(String key) async => map.remove(key);

  @override
  Future<void> write(String key, String value) async => map[key] = value;
}

class _SessionAuth implements AuthPort {
  bool has = true;
  bool signedOut = false;

  @override
  Future<bool> hasSession() async => has && !signedOut;

  @override
  Future<void> signIn({
    required String identifier,
    required String secret,
  }) async {}

  @override
  Future<void> signOut() async {
    signedOut = true;
  }

  @override
  Future<void> refreshSession() async {}
}

class _OfflineMe implements MePort {
  @override
  Future<Map<String, Object?>> currentEmployee() async {
    throw const AppFailure(code: 'network', message: 'offline');
  }

  @override
  Future<String?> currentEmployeeId() async => null;
}

class _OfflineHttp implements ErpHttpClient {
  @override
  Future<Map<String, Object?>> get(
    String path, {
    Map<String, String>? query,
  }) async {
    throw const AppFailure(code: 'network');
  }

  @override
  Future<({List<int> bytes, String? contentType, String? filename})> getBytes(
    String path, {
    Map<String, String>? query,
  }) async {
    throw const AppFailure(code: 'network');
  }

  @override
  Future<Map<String, Object?>> post(
    String path, {
    Map<String, Object?>? body,
  }) async {
    throw const AppFailure(code: 'network');
  }

  @override
  Future<Map<String, Object?>> put(
    String path, {
    Map<String, Object?>? body,
  }) async {
    throw const AppFailure(code: 'network');
  }
}

class _PassthroughErrors implements ErrorMapper {
  @override
  AppFailure map(Object error, [StackTrace? stackTrace]) {
    if (error is AppFailure) return error;
    return AppFailure(code: 'unknown', message: '$error');
  }
}

class _OfflineConfigPort implements MobileConfigPort {
  @override
  Future<MobileAppConfiguration> fetchRemote() async {
    throw const AppFailure(code: 'network');
  }
}

class _NoopDevicePort implements DeviceRegistryPort {
  @override
  Future<Map<String, Object?>> register({
    required String deviceId,
    required String platform,
    String? pushToken,
    String? appVersion,
  }) async =>
      {'id': 1, 'status': 'active'};

  @override
  Future<Map<String, Object?>> heartbeat({
    required String deviceId,
    String? pushToken,
    String? appVersion,
  }) async =>
      {'id': 1, 'status': 'active'};

  @override
  Future<void> revoke(int devicePk) async {}

  @override
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  }) async =>
      {'ok': true};
}

MobileAppConfiguration _cfg() => MobileAppConfiguration(
      companyId: 1,
      companyName: 'A',
      appName: 'A',
      logoUrl: '',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#0F766E',
      features: const {
        'home': true,
        'attendance': true,
        'leave': true,
      },
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 22),
    );

void main() {
  tearDown(() {
    EmployeeContext.clear();
  });

  test('ErpMeAdapter hydrateFromDisk binds claims without network', () async {
    final cache = _MemCache();
    await cache.write(
      ErpMeAdapter.claimsCacheKey,
      jsonEncode({'id': 42, 'name': 'Ali', 'employee_code': 'E1'}),
    );
    final me = ErpMeAdapter(
      http: _OfflineHttp(),
      errors: _PassthroughErrors(),
      cache: cache,
    );
    expect(await me.hydrateFromDisk(), isTrue);
    expect(EmployeeContext.isResolved, isTrue);
    expect(EmployeeContext.current!.employeeId, '42');
  });

  test('restore opens shell offline from claims + config cache', () async {
    final cache = _MemCache();
    await cache.write(
      ErpMeAdapter.claimsCacheKey,
      jsonEncode({'id': 7, 'name': 'Sara', 'employee_code': 'E7'}),
    );
    final mobile = MobileConfigurationService(
      port: _OfflineConfigPort(),
      cache: cache,
    );
    await cache.write(
      MobileConfigurationService.cacheKey,
      jsonEncode(_cfg().toJson()),
    );

    final me = ErpMeAdapter(
      http: _OfflineHttp(),
      errors: _PassthroughErrors(),
      cache: cache,
    );
    final auth = _SessionAuth();
    final session = AuthSession(
      auth: auth,
      me: me,
      mobileConfiguration: mobile,
      deviceRegistry: DeviceRegistryService(
        port: _NoopDevicePort(),
        deviceIds: LocalDeviceIdStore(cache: cache),
      ),
    );

    await session.restore();

    expect(session.status, AuthStatus.signedIn);
    expect(session.offlineSession, isTrue);
    expect(EmployeeContext.isResolved, isTrue);
    expect(mobile.current?.mobileActive, isTrue);
    expect(auth.signedOut, isFalse);
  });

  test('restore does not wipe token on network failure without cache', () async {
    final cache = _MemCache();
    final mobile = MobileConfigurationService(
      port: _OfflineConfigPort(),
      cache: cache,
    );
    final auth = _SessionAuth();
    final session = AuthSession(
      auth: auth,
      me: _OfflineMe(),
      mobileConfiguration: mobile,
      deviceRegistry: DeviceRegistryService(
        port: _NoopDevicePort(),
        deviceIds: LocalDeviceIdStore(cache: cache),
      ),
    );

    await session.restore();

    expect(session.status, AuthStatus.signedOut);
    expect(auth.signedOut, isFalse);
    expect(await auth.hasSession(), isTrue);
  });
}
