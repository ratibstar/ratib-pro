/// Leave repository — presentation orchestration over ports only.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';

final class LeaveRepository {
  LeaveRepository({
    required LeavePort leave,
    required OfflineQueuePort offlineQueue,
    CacheStore? cache,
  })  : _leave = leave,
        _offlineQueue = offlineQueue,
        _cache = cache;

  static const balancesCacheKey = 'ess.leave.balances.v1';

  final LeavePort _leave;
  final OfflineQueuePort _offlineQueue;
  final CacheStore? _cache;

  Future<LeaveBalancesSnapshot> loadBalances() async {
    try {
      final rows = await _leave.balances();
      await _writeBalancesCache(rows);
      final pending = await pendingOfflineCount();
      return LeaveBalancesSnapshot(
        balances: rows,
        pendingOfflineCount: pending,
      );
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        _markConnectivityOffline(e.message);
        final pending = await pendingOfflineCount();
        final cached = await _readBalancesCache();
        return LeaveBalancesSnapshot(
          balances: cached ?? const [],
          pendingOfflineCount: pending,
          fromCache: cached != null && cached.isNotEmpty,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  Future<LeaveRequestsSnapshot> loadRequests() async {
    try {
      final rows = await _leave.status();
      await EssReadCache.writeList(EssReadCache.leaveRequests, rows, cache: _cache);
      final pending = await pendingOfflineCount();
      return LeaveRequestsSnapshot(
        requests: rows,
        pendingOfflineCount: pending,
      );
    } on AppFailure catch (e) {
      if (EssReadCache.isConnectivity(e)) {
        EssReadCache.markOffline(e.message);
        final pending = await pendingOfflineCount();
        final cached =
            await EssReadCache.readList(EssReadCache.leaveRequests, cache: _cache);
        return LeaveRequestsSnapshot(
          requests: cached ?? const [],
          pendingOfflineCount: pending,
          fromCache: cached != null && cached.isNotEmpty,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  Future<EssCachedMap> loadDetail(String id) async {
    try {
      Map<String, Object?> row;
      if (_leave is ErpLeaveAdapter) {
        row = await _leave.detail(id);
      } else {
        final rows = await _leave.status();
        row = EssReadCache.findById(rows, id) ?? <String, Object?>{};
      }
      return EssCachedMap(data: row);
    } on AppFailure catch (e) {
      if (EssReadCache.isConnectivity(e)) {
        EssReadCache.markOffline(e.message);
        final cached =
            await EssReadCache.readList(EssReadCache.leaveRequests, cache: _cache);
        final hit = cached == null ? null : EssReadCache.findById(cached, id);
        return EssCachedMap(
          data: hit ?? const {},
          fromCache: hit != null,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  /// Online apply; on network failure enqueue `leave_request.draft` only.
  Future<LeaveApplyResult> apply({
    required int leaveTypeId,
    required String startDate,
    required String endDate,
    String? reason,
  }) async {
    final payload = <String, Object?>{
      'leave_type_id': leaveTypeId,
      'start_date': startDate,
      'end_date': endDate,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    };
    try {
      await _leave.apply(payload);
      await _flushPendingDrafts();
      return LeaveApplyResult.online;
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        // Enqueue first — never lose the offline draft if connectivity UI is unbound.
        await _enqueueDraft(payload);
        _markConnectivityOffline(e.message);
        return LeaveApplyResult.queuedOffline;
      }
      rethrow;
    }
  }

  Future<int> pendingOfflineCount() async {
    final items = await _offlineQueue.pendingItems();
    return items.where((e) => (e['action'] ?? '') == 'leave_request.draft').length;
  }

  void _markConnectivityOffline(String? message) {
    try {
      AppLocator.connectivity.markOffline(message);
    } catch (_) {
      // Presentation-only signal; queue already persisted.
    }
  }

  CacheStore? get _resolvedCache {
    if (_cache != null) return _cache;
    try {
      return AppLocator.cache;
    } catch (_) {
      return null;
    }
  }

  Future<void> _writeBalancesCache(List<Map<String, Object?>> rows) async {
    final cache = _resolvedCache;
    if (cache == null) return;
    try {
      await cache.write(balancesCacheKey, jsonEncode(rows));
    } catch (_) {}
  }

  Future<List<Map<String, Object?>>?> _readBalancesCache() async {
    final cache = _resolvedCache;
    if (cache == null) return null;
    try {
      final raw = await cache.read(balancesCacheKey);
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

  Future<void> _enqueueDraft(Map<String, Object?> payload) async {
    final ctx = EmployeeContext.requireResolved();
    await _offlineQueue.enqueue(
      existingAction: 'leave_request.draft',
      payload: {
        ...payload,
        'employee_id': int.tryParse(ctx.employeeId) ?? ctx.employeeId,
      },
    );
  }

  Future<void> _flushPendingDrafts() async {
    final items = await _offlineQueue.pendingItems();
    if (items.isEmpty) return;
    final remaining = <Map<String, Object?>>[];
    for (final item in items) {
      final action = (item['action'] ?? '').toString();
      if (action != 'leave_request.draft') {
        remaining.add(item);
        continue;
      }
      final payload = item['payload'];
      final map = payload is Map
          ? payload.map((k, v) => MapEntry(k.toString(), v))
          : <String, Object?>{};
      try {
        await _leave.apply({
          'leave_type_id': map['leave_type_id'],
          'start_date': map['start_date'],
          'end_date': map['end_date'],
          if (map['reason'] != null) 'reason': map['reason'],
        });
      } on AppFailure catch (e) {
        if (e.code == 'duplicate_request') {
          continue;
        }
        remaining.add(item);
      } catch (_) {
        remaining.add(item);
      }
    }
    await _offlineQueue.replaceAll(remaining);
  }
}

enum LeaveApplyResult { online, queuedOffline }

final class LeaveBalancesSnapshot {
  const LeaveBalancesSnapshot({
    required this.balances,
    required this.pendingOfflineCount,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final List<Map<String, Object?>> balances;
  final int pendingOfflineCount;
  final bool fromCache;
  final bool offlineDegraded;
}

final class LeaveRequestsSnapshot {
  const LeaveRequestsSnapshot({
    required this.requests,
    required this.pendingOfflineCount,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final List<Map<String, Object?>> requests;
  final int pendingOfflineCount;
  final bool fromCache;
  final bool offlineDegraded;
}
