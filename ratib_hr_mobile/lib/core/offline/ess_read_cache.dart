/// Soft read-through cache for ESS list/summary payloads (presentation only).
///
/// ERP remains source of truth. Cache is last-known online snapshot for offline open
/// without requiring the user to visit each screen while online.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

abstract final class EssReadCache {
  static const dashboard = 'ess.dashboard.summary.v1';
  static const attendanceToday = 'ess.attendance.today.v1';
  static const attendanceHistory = 'ess.attendance.history.v1';
  static const leaveBalances = 'ess.leave.balances.v1';
  static const leaveRequests = 'ess.leave.requests.v1';
  static const profile = 'ess.profile.mine.v1';
  static const documentsList = 'ess.documents.list.v1';
  static const payslipsList = 'ess.payslips.list.v1';
  static const permissionRequests = 'ess.permission_requests.list.v1';
  static const employeeRequests = 'ess.employee_requests.list.v1';
  static const notifications = 'ess.notifications.list.v1';
  static const ratings = 'ess.ratings.summary.v1';
  static const inquiries = 'ess.inquiries.list.v1';
  static const payments = 'ess.payments.list.v1';

  static CacheStore? resolve([CacheStore? cache]) {
    if (cache != null) return cache;
    try {
      return AppLocator.cache;
    } catch (_) {
      return null;
    }
  }

  static bool isConnectivity(AppFailure f) =>
      f.code == 'network' || f.code == 'timeout';

  static Future<void> writeMap(
    String key,
    Map<String, Object?> value, {
    CacheStore? cache,
  }) async {
    final store = resolve(cache);
    if (store == null) return;
    try {
      await store.write(key, jsonEncode(value));
    } catch (_) {}
  }

  static Future<void> writeList(
    String key,
    List<Map<String, Object?>> value, {
    CacheStore? cache,
  }) async {
    final store = resolve(cache);
    if (store == null) return;
    try {
      await store.write(key, jsonEncode(value));
    } catch (_) {}
  }

  static Future<Map<String, Object?>?> readMap(
    String key, {
    CacheStore? cache,
  }) async {
    final store = resolve(cache);
    if (store == null) return null;
    try {
      final raw = await store.read(key);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      return null;
    }
  }

  static Future<List<Map<String, Object?>>?> readList(
    String key, {
    CacheStore? cache,
  }) async {
    final store = resolve(cache);
    if (store == null) return null;
    try {
      final raw = await store.read(key);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! List) return null;
      return decoded
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList();
    } catch (_) {
      return null;
    }
  }

  static Map<String, Object?>? findById(
    List<Map<String, Object?>> rows,
    String id,
  ) {
    for (final row in rows) {
      if ((row['id'] ?? '').toString() == id) return row;
    }
    return null;
  }

  static void markOffline([String? message]) {
    try {
      AppLocator.connectivity.markOffline(message);
    } catch (_) {}
  }

  static Future<EssCachedList> fetchList({
    required String key,
    required Future<List<Map<String, Object?>>> Function() fetch,
    CacheStore? cache,
  }) async {
    try {
      final rows = await fetch();
      await writeList(key, rows, cache: cache);
      return EssCachedList(items: rows);
    } on AppFailure catch (e) {
      if (isConnectivity(e)) {
        markOffline(e.message);
        final cached = await readList(key, cache: cache);
        return EssCachedList(
          items: cached ?? const [],
          fromCache: cached != null && cached.isNotEmpty,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  static Future<EssCachedMap> fetchMap({
    required String key,
    required Future<Map<String, Object?>> Function() fetch,
    CacheStore? cache,
  }) async {
    try {
      final row = await fetch();
      await writeMap(key, row, cache: cache);
      return EssCachedMap(data: row);
    } on AppFailure catch (e) {
      if (isConnectivity(e)) {
        markOffline(e.message);
        final cached = await readMap(key, cache: cache);
        return EssCachedMap(
          data: cached ?? const {},
          fromCache: cached != null && cached.isNotEmpty,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }
}

/// Result of a soft-cached list load.
final class EssCachedList {
  const EssCachedList({
    required this.items,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final List<Map<String, Object?>> items;
  final bool fromCache;
  final bool offlineDegraded;
}

/// Result of a soft-cached map load.
final class EssCachedMap {
  const EssCachedMap({
    required this.data,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final Map<String, Object?> data;
  final bool fromCache;
  final bool offlineDegraded;
}
