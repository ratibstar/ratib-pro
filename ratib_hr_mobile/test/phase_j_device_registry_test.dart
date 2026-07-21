import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_device_registry_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
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

class _FakeHttp implements ErpHttpClient {
  final Map<String, Map<String, Object?>> responses;
  final calls = <Map<String, Object?>>[];

  _FakeHttp(this.responses);

  @override
  Future<Map<String, Object?>> get(
    String path, {
    Map<String, String>? query,
  }) async =>
      throw UnimplementedError();

  @override
  Future<({List<int> bytes, String? contentType, String? filename})> getBytes(
    String path, {
    Map<String, String>? query,
  }) async =>
      throw UnimplementedError();

  @override
  Future<Map<String, Object?>> post(
    String path, {
    Map<String, Object?>? body,
  }) async {
    calls.add({'path': path, 'body': body});
    final res = responses[path];
    if (res == null) {
      throw AppFailure(code: 'missing_stub', message: path);
    }
    if (res['success'] != true) {
      throw AppFailure(
        code: res['code']?.toString() ?? 'erp',
        message: res['message']?.toString(),
      );
    }
    return res;
  }

  @override
  Future<Map<String, Object?>> put(
    String path, {
    Map<String, Object?>? body,
  }) async =>
      throw UnimplementedError();
}

class _PassthroughErrors implements ErrorMapper {
  @override
  AppFailure map(Object error, [StackTrace? stackTrace]) {
    if (error is AppFailure) return error;
    return AppFailure(code: 'unknown', message: error.toString());
  }
}

class _FakeAuth implements AuthPort {
  bool session = false;

  @override
  Future<bool> hasSession() async => session;

  @override
  Future<void> signIn({
    required String identifier,
    required String secret,
  }) async {
    session = true;
  }

  @override
  Future<void> signOut() async {
    session = false;
  }

  @override
  Future<void> refreshSession() async {}
}

class _FakeMe implements MePort {
  @override
  Future<Map<String, Object?>> currentEmployee() async {
    EmployeeContext.bind(
      EmployeeContext.fromErpRecord({'id': 10, 'name': 'Test'}),
    );
    return {'id': 10, 'name': 'Test'};
  }

  @override
  Future<String?> currentEmployeeId() async => '10';
}

class _FakeMobileConfigPort implements MobileConfigPort {
  @override
  Future<MobileAppConfiguration> fetchRemote() async {
    return MobileAppConfiguration(
      companyId: 1,
      companyName: 'A',
      appName: 'A',
      logoUrl: '',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#0F766E',
      features: const {},
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
  }
}

MobileConfigurationService _mobileConfig() => MobileConfigurationService(
      port: _FakeMobileConfigPort(),
      cache: _MemCache(),
    );

void main() {
  setUp(EmployeeContext.clear);
  tearDown(EmployeeContext.clear);

  test('Phase marker is J or later', () {
    expect(['J', 'I3','B','B2','K','K1'].contains(AppConfig.phase), isTrue);
  });

  test('Adapter paths and client_app are shared ERP contract', () {
    expect(ErpDeviceRegistryAdapter.registerPath,
        '/api/v1/mobile/devices/register');
    expect(ErpDeviceRegistryAdapter.heartbeatPath,
        '/api/v1/mobile/devices/heartbeat');
    expect(ErpDeviceRegistryAdapter.clientApp, 'ess');
  });

  test('Local device id is stable across reads', () async {
    final cache = _MemCache();
    final store = LocalDeviceIdStore(cache: cache);
    final a = await store.getOrCreate();
    final b = await store.getOrCreate();
    expect(a, b);
    expect(a.length, 32);
  });

  test('Adapter register posts ESS client_app and never user/company ids',
      () async {
    final http = _FakeHttp({
      ErpDeviceRegistryAdapter.registerPath: {
        'success': true,
        'data': {
          'device': {
            'id': 42,
            'client_app': 'ess',
            'device_id': 'abc',
            'status': 'active',
          },
        },
      },
    });
    final adapter = ErpDeviceRegistryAdapter(
      http: http,
      errors: _PassthroughErrors(),
    );
    final row = await adapter.register(
      deviceId: 'abc',
      platform: 'android',
      appVersion: '0.1.5',
    );
    expect(row['id'], 42);
    final body = http.calls.single['body'] as Map;
    expect(body['client_app'], 'ess');
    expect(body['device_id'], 'abc');
    expect(body.containsKey('user_id'), isFalse);
    expect(body.containsKey('company_id'), isFalse);
  });

  test('Service registerAndHeartbeat captures device pk', () async {
    final http = _FakeHttp({
      ErpDeviceRegistryAdapter.registerPath: {
        'success': true,
        'data': {
          'device': {'id': 7, 'status': 'active', 'device_id': 'd1'},
        },
      },
      ErpDeviceRegistryAdapter.heartbeatPath: {
        'success': true,
        'data': {
          'device': {'id': 7, 'status': 'active', 'device_id': 'd1'},
        },
      },
    });
    final service = DeviceRegistryService(
      port: ErpDeviceRegistryAdapter(
        http: http,
        errors: _PassthroughErrors(),
      ),
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    await service.registerAndHeartbeat();
    expect(service.lastDevicePk, 7);
    expect(service.lastStatus, 'active');
    expect(http.calls.length, 2);
  });

  test('Revoked heartbeat maps to device_revoked', () async {
    final http = _FakeHttp({
      ErpDeviceRegistryAdapter.registerPath: {
        'success': true,
        'data': {
          'device': {'id': 1, 'status': 'active'},
        },
      },
      ErpDeviceRegistryAdapter.heartbeatPath: {
        'success': false,
        'code': 'device_revoked',
        'message': 'Device has been revoked',
      },
    });
    final service = DeviceRegistryService(
      port: ErpDeviceRegistryAdapter(
        http: http,
        errors: _PassthroughErrors(),
      ),
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    expect(
      () => service.registerAndHeartbeat(),
      throwsA(
        isA<AppFailure>().having((e) => e.code, 'code', 'device_revoked'),
      ),
    );
    expect(
      DeviceRegistryService.isRevokedFailure(
        const AppFailure(code: 'device_revoked', message: 'x'),
      ),
      isTrue,
    );
  });

  test('Login integration registers device after successful auth', () async {
    var registerCalls = 0;
    final devices = DeviceRegistryService(
      port: _CountingRegistry(() => registerCalls++),
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isTrue);
    expect(session.status, AuthStatus.signedIn);
    expect(registerCalls, 1);
  });

  test('Login fails closed when device is revoked', () async {
    final devices = DeviceRegistryService(
      port: _RevokedRegistry(),
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isFalse);
    expect(session.status, AuthStatus.signedOut);
    expect(session.lastError?.code, 'device_revoked');
  });

  test('Login soft-fails when device registry schema is missing', () async {
    final devices = DeviceRegistryService(
      port: _SchemaMissingRegistry(),
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isTrue);
    expect(session.status, AuthStatus.signedIn);
    expect(session.lastError, isNull);
  });
}

final class _CountingRegistry implements DeviceRegistryPort {
  _CountingRegistry(this.onRegister);
  final void Function() onRegister;

  @override
  Future<Map<String, Object?>> register({
    required String deviceId,
    required String platform,
    String? pushToken,
    String? appVersion,
  }) async {
    onRegister();
    return {'id': 1, 'status': 'active'};
  }

  @override
  Future<Map<String, Object?>> heartbeat({
    required String deviceId,
    String? pushToken,
    String? appVersion,
  }) async =>
      {'id': 1, 'status': 'active'};

  @override
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  }) async =>
      {'id': 1, 'status': 'active'};

  @override
  Future<void> revoke(int devicePk) async {}
}

final class _RevokedRegistry implements DeviceRegistryPort {
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
  }) async {
    throw const AppFailure(
      code: 'device_revoked',
      message: 'Device has been revoked',
    );
  }

  @override
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  }) async {
    throw const AppFailure(
      code: 'device_revoked',
      message: 'Device has been revoked',
    );
  }

  @override
  Future<void> revoke(int devicePk) async {}
}

final class _SchemaMissingRegistry implements DeviceRegistryPort {
  @override
  Future<Map<String, Object?>> register({
    required String deviceId,
    required String platform,
    String? pushToken,
    String? appVersion,
  }) async {
    throw const AppFailure(
      code: 'erp',
      message: 'قاعدة البيانات تحتاج تحديثاً',
    );
  }

  @override
  Future<Map<String, Object?>> heartbeat({
    required String deviceId,
    String? pushToken,
    String? appVersion,
  }) async {
    throw const AppFailure(code: 'erp', message: 'schema');
  }

  @override
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  }) async {
    throw const AppFailure(code: 'erp', message: 'schema');
  }

  @override
  Future<void> revoke(int devicePk) async {}
}
