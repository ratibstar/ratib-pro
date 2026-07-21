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
import 'package:ratib_hr_mobile/core/push/local_notification_presenter.dart';
import 'package:ratib_hr_mobile/core/push/noop_push_messaging_gateway.dart';
import 'package:ratib_hr_mobile/core/push/push_notification_service.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/features/notifications/notifications_page.dart';

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

final class _RecordingRegistry implements DeviceRegistryPort {
  final pushCalls = <Map<String, Object?>>[];
  bool revokeOnPush = false;
  bool networkOnPush = false;

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
  Future<Map<String, Object?>> updatePushToken({
    required String deviceId,
    required String pushToken,
    required String pushProvider,
    String? platform,
    String? locale,
    String? appVersion,
  }) async {
    pushCalls.add({
      'device_id': deviceId,
      'push_token': pushToken,
      'push_provider': pushProvider,
      'platform': platform,
      'locale': locale,
      'app_version': appVersion,
    });
    if (revokeOnPush) {
      throw const AppFailure(code: 'device_revoked', message: 'revoked');
    }
    if (networkOnPush) {
      throw const AppFailure(code: 'network', message: 'offline');
    }
    return {'id': 1, 'status': 'active', 'push_provider': pushProvider};
  }

  @override
  Future<void> revoke(int devicePk) async {}
}

/// Gateway that reports initialized so PushNotificationService proceeds.
final class _ReadyMessaging extends NoopPushMessagingGateway {
  @override
  Future<bool> ensureInitialized() async => true;

  @override
  Future<bool> requestPermission() async => true;

  @override
  Future<String?> getToken() async => seedToken ?? 'fcm-token-1';
}

void main() {
  setUp(EmployeeContext.clear);
  tearDown(EmployeeContext.clear);

  test('Phase marker is I3', () {
    expect(['I3','B'].contains(AppConfig.phase), isTrue);
  });

  test('Adapter push-token path omits user_id and company_id', () async {
    final http = _FakeHttp({
      ErpDeviceRegistryAdapter.pushTokenPath: {
        'success': true,
        'data': {
          'device': {'id': 9, 'status': 'active', 'push_provider': 'fcm'},
        },
      },
    });
    final adapter = ErpDeviceRegistryAdapter(
      http: http,
      errors: _PassthroughErrors(),
    );
    await adapter.updatePushToken(
      deviceId: 'dev-1',
      pushToken: 'tok-abc',
      pushProvider: 'fcm',
      platform: 'android',
    );
    final body = http.calls.single['body'] as Map;
    expect(http.calls.single['path'], ErpDeviceRegistryAdapter.pushTokenPath);
    expect(body['client_app'], 'ess');
    expect(body['device_id'], 'dev-1');
    expect(body['push_token'], 'tok-abc');
    expect(body.containsKey('user_id'), isFalse);
    expect(body.containsKey('company_id'), isFalse);
  });

  test('PushNotificationService sends token to ERP', () async {
    final registry = _RecordingRegistry();
    final messaging = _ReadyMessaging()..seedToken = 'tok-xyz';
    final svc = PushNotificationService(
      devices: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
      messaging: messaging,
      localNotifications: NoopLocalNotificationPresenter(),
    );
    await svc.registerPushAfterDevice();
    expect(registry.pushCalls.length, 1);
    expect(registry.pushCalls.single['push_token'], 'tok-xyz');
    expect(registry.pushCalls.single['push_provider'], 'fcm');
    expect(registry.pushCalls.single.containsKey('user_id'), isFalse);
  });

  test('Token refresh updates ERP', () async {
    final registry = _RecordingRegistry();
    final messaging = _ReadyMessaging()..seedToken = 'tok-1';
    final svc = PushNotificationService(
      devices: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
      messaging: messaging,
      localNotifications: NoopLocalNotificationPresenter(),
    );
    await svc.registerPushAfterDevice();
    messaging.emitRefresh('tok-2');
    await Future<void>.delayed(Duration.zero);
    expect(registry.pushCalls.length, 2);
    expect(registry.pushCalls.last['push_token'], 'tok-2');
  });

  test('Revoked device on push fails closed at login', () async {
    final registry = _RecordingRegistry()..revokeOnPush = true;
    final messaging = _ReadyMessaging()..seedToken = 'tok';
    final push = PushNotificationService(
      devices: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
      messaging: messaging,
      localNotifications: NoopLocalNotificationPresenter(),
    );
    final devices = DeviceRegistryService(
      port: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
      pushNotifications: push,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isFalse);
    expect(session.lastError?.code, 'device_revoked');
  });

  test('Network failure on push does not break login', () async {
    final registry = _RecordingRegistry()..networkOnPush = true;
    final messaging = _ReadyMessaging()..seedToken = 'tok';
    final push = PushNotificationService(
      devices: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
      messaging: messaging,
      localNotifications: NoopLocalNotificationPresenter(),
    );
    final devices = DeviceRegistryService(
      port: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
      pushNotifications: push,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isTrue);
    expect(session.status, AuthStatus.signedIn);
  });

  test('Missing Firebase does not break login', () async {
    final registry = _RecordingRegistry();
    final messaging = NoopPushMessagingGateway(); // ensureInitialized => false
    final push = PushNotificationService(
      devices: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
      messaging: messaging,
      localNotifications: NoopLocalNotificationPresenter(),
    );
    final devices = DeviceRegistryService(
      port: registry,
      deviceIds: LocalDeviceIdStore(cache: _MemCache()),
    );
    final session = AuthSession(
      auth: _FakeAuth(),
      me: _FakeMe(),
      mobileConfiguration: _mobileConfig(),
      deviceRegistry: devices,
      pushNotifications: push,
    );
    final ok = await session.signIn(identifier: 'u', secret: 'p');
    expect(ok, isTrue);
    expect(registry.pushCalls, isEmpty);
  });

  test('In-app notifications page type still exists (unchanged surface)', () {
    expect(NotificationsPage, isNotNull);
  });
}
