/// Stable install device id — local cache only (not auth).
library;

import 'dart:math';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';

final class LocalDeviceIdStore {
  LocalDeviceIdStore({required CacheStore cache}) : _cache = cache;

  static const cacheKey = 'mobile.device_id.v1';

  final CacheStore _cache;

  Future<String> getOrCreate() async {
    final existing = await _cache.read(cacheKey);
    if (existing != null && existing.trim().isNotEmpty) {
      return existing.trim();
    }
    final id = _generate();
    await _cache.write(cacheKey, id);
    return id;
  }

  String _generate() {
    final rnd = Random.secure();
    final bytes = List<int>.generate(16, (_) => rnd.nextInt(256));
    return bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  }
}
