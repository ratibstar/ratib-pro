/// Placeholder contracts for upcoming mobile features.

abstract class PushNotificationService {
  Future<void> initialize();
  Future<String?> getDeviceToken();
  Future<void> registerDeviceToken(String token);
}

class PushNotificationServiceStub implements PushNotificationService {
  @override
  Future<void> initialize() async {}

  @override
  Future<String?> getDeviceToken() async => null;

  @override
  Future<void> registerDeviceToken(String token) async {}
}

abstract class OfflineCacheService {
  Future<void> cacheJson(String key, Map<String, dynamic> data);
  Future<Map<String, dynamic>?> readJson(String key);
  Future<void> clearAll();
}

class OfflineCacheServiceStub implements OfflineCacheService {
  final Map<String, Map<String, dynamic>> _memory = {};

  @override
  Future<void> cacheJson(String key, Map<String, dynamic> data) async {
    _memory[key] = Map<String, dynamic>.from(data);
  }

  @override
  Future<Map<String, dynamic>?> readJson(String key) async {
    final value = _memory[key];
    return value == null ? null : Map<String, dynamic>.from(value);
  }

  @override
  Future<void> clearAll() async => _memory.clear();
}
