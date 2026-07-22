/// Profile repository — presentation orchestration over ProfilePort.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/profile_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ProfileRepository {
  ProfileRepository({
    required ProfilePort profile,
    CacheStore? cache,
  })  : _profile = profile,
        _cache = cache;

  static const cacheKey = 'ess.profile.mine.v1';

  final ProfilePort _profile;
  final CacheStore? _cache;

  Future<ProfileSnapshot> loadMine() async {
    try {
      final row = await _profile.mine();
      await _writeCache(row);
      return ProfileSnapshot(profile: row);
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        _markOffline(e.message);
        final cached = await _readCache();
        return ProfileSnapshot(
          profile: cached ?? const {},
          fromCache: cached != null,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  void _markOffline(String? message) {
    try {
      AppLocator.connectivity.markOffline(message);
    } catch (_) {}
  }

  CacheStore? get _resolvedCache {
    if (_cache != null) return _cache;
    try {
      return AppLocator.cache;
    } catch (_) {
      return null;
    }
  }

  Future<void> _writeCache(Map<String, Object?> row) async {
    final cache = _resolvedCache;
    if (cache == null) return;
    try {
      await cache.write(cacheKey, jsonEncode(row));
    } catch (_) {}
  }

  Future<Map<String, Object?>?> _readCache() async {
    final cache = _resolvedCache;
    if (cache == null) return null;
    try {
      final raw = await cache.read(cacheKey);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      return null;
    }
  }
}

final class ProfileSnapshot {
  const ProfileSnapshot({
    required this.profile,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final Map<String, Object?> profile;
  final bool fromCache;
  final bool offlineDegraded;
}
