/// Central Mobile Configuration service — single runtime source for branding + flags.
///
/// Widgets must not call ERP APIs. Consume [current] via [AppLocator.mobileConfiguration].
library;

import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/mobile_config_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';

final class MobileConfigurationService extends ChangeNotifier {
  MobileConfigurationService({
    required MobileConfigPort port,
    required CacheStore cache,
  })  : _port = port,
        _cache = cache;

  static const cacheKey = 'mobile_app_config.v1';

  final MobileConfigPort _port;
  final CacheStore _cache;

  MobileAppConfiguration? _current;
  AppFailure? lastError;

  MobileAppConfiguration? get current => _current;

  bool get hasConfig => _current != null && _current!.mobileActive;

  bool isFeatureEnabled(String key) =>
      _current?.isFeatureEnabled(key) ?? false;

  /// After successful ERP auth: pull latest config, replace cache.
  /// On transient network failure, fall back to cached config if present.
  Future<void> refreshAfterLogin() async {
    lastError = null;
    try {
      final remote = await _port.fetchRemote();
      await _persist(remote);
      _current = remote;
      notifyListeners();
      return;
    } on AppFailure catch (e) {
      lastError = e;
      if (e.code == 'mobile_disabled' || e.code == 'forbidden') {
        await _cache.remove(cacheKey);
        _current = null;
        notifyListeners();
        throw AppFailure(
          code: 'mobile_disabled',
          message: e.message ?? 'Mobile app is not enabled for this company',
        );
      }
      if (e.code == 'network' || e.code == 'timeout') {
        final cached = await _readCache();
        if (cached != null && cached.mobileActive) {
          _current = cached.copyWith(fromCache: true);
          notifyListeners();
          return;
        }
      }
      _current = null;
      notifyListeners();
      rethrow;
    } catch (e) {
      final cached = await _readCache();
      if (cached != null && cached.mobileActive) {
        _current = cached.copyWith(fromCache: true);
        notifyListeners();
        return;
      }
      _current = null;
      notifyListeners();
      rethrow;
    }
  }

  /// Cold offline start — load last active config without hitting the network.
  Future<bool> hydrateFromCache() async {
    lastError = null;
    final cached = await _readCache();
    if (cached == null || !cached.mobileActive) {
      return false;
    }
    _current = cached.copyWith(fromCache: true);
    notifyListeners();
    return true;
  }

  /// Clears in-memory + disk config on sign-out so next login gets fresh branding.
  Future<void> clearSession() async {
    _current = null;
    lastError = null;
    await _cache.remove(cacheKey);
    notifyListeners();
  }

  /// Test / diagnostics — expose cache wipe.
  Future<void> clearCache() async {
    await _cache.remove(cacheKey);
  }

  Future<void> _persist(MobileAppConfiguration cfg) async {
    await _cache.write(cacheKey, jsonEncode(cfg.toJson()));
  }

  Future<MobileAppConfiguration?> _readCache() async {
    final raw = await _cache.read(cacheKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return MobileAppConfiguration.fromJson(
        decoded.map((k, v) => MapEntry(k.toString(), v)),
        fromCache: true,
      );
    } catch (_) {
      return null;
    }
  }
}
