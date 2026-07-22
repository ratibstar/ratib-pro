/// Attendance repository — presentation orchestration over ports only.
library;

import 'dart:convert';

import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_state.dart';

final class AttendanceRepository {
  AttendanceRepository({
    required AttendancePort attendance,
    required OfflineQueuePort offlineQueue,
    CacheStore? cache,
  })  : _attendance = attendance,
        _offlineQueue = offlineQueue,
        _cache = cache;

  static const todayCacheKey = 'ess.attendance.today.v1';

  final AttendancePort _attendance;
  final OfflineQueuePort _offlineQueue;
  final CacheStore? _cache;

  Future<AttendanceSnapshot> loadToday() async {
    try {
      final row = await _attendance.today();
      await _writeTodayCache(row);
      final pending = await _pendingCount();
      return AttendanceSnapshot(
        today: row,
        pendingOfflineCount: pending,
      );
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        _markConnectivityOffline(e.message);
        final pending = await _pendingCount();
        final cached = await _readTodayCache();
        return AttendanceSnapshot(
          today: cached ?? const {},
          pendingOfflineCount: pending,
          fromCache: cached != null,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  Future<List<Map<String, Object?>>> loadHistory() {
    return _attendance.history();
  }

  Future<int> pendingOfflineCount() => _pendingCount();

  /// Online check-in; on network failure enqueue `attendance.create` only.
  Future<AttendancePunchResult> checkIn() async {
    final date = ErpAttendanceAdapter.calendarDateIso();
    final now = DateTime.now();
    final time =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';
    try {
      await _attendance.checkIn({
        'date': date,
        'check_in': time,
      });
      await _flushPendingCheckIns();
      return AttendancePunchResult.online;
    } on AppFailure catch (e) {
      if (e.code == 'network' || e.code == 'timeout') {
        // Enqueue first — never lose the offline action if connectivity UI is unbound.
        await _enqueueCreate(date: date, checkIn: time);
        _markConnectivityOffline(e.message);
        return AttendancePunchResult.queuedOffline;
      }
      rethrow;
    }
  }

  /// Check-out is online-only — never enqueue.
  Future<void> checkOut() async {
    final date = ErpAttendanceAdapter.calendarDateIso();
    final now = DateTime.now();
    final time =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';
    await _attendance.checkOut({
      'date': date,
      'check_out': time,
    });
  }

  Future<void> _enqueueCreate({
    required String date,
    required String checkIn,
  }) async {
    final ctx = EmployeeContext.requireResolved();
    final payload = <String, Object?>{
      'attendance_date': date,
      'check_in': checkIn,
      'status': 'present',
      // Replay guard requires employee_id; taken from session context, not UI input.
      'employee_id': int.tryParse(ctx.employeeId) ?? ctx.employeeId,
    };
    await _offlineQueue.enqueue(
      existingAction: 'attendance.create',
      payload: payload,
    );
  }

  Future<int> _pendingCount() => _offlineQueue.pendingCount();

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

  Future<void> _writeTodayCache(Map<String, Object?> row) async {
    final cache = _resolvedCache;
    if (cache == null) return;
    try {
      await cache.write(todayCacheKey, jsonEncode(row));
    } catch (_) {}
  }

  Future<Map<String, Object?>?> _readTodayCache() async {
    final cache = _resolvedCache;
    if (cache == null) return null;
    try {
      final raw = await cache.read(todayCacheKey);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      return null;
    }
  }

  /// Replays pending `attendance.create` via online check-in (no attendance.update).
  Future<void> _flushPendingCheckIns() async {
    final items = await _offlineQueue.pendingItems();
    if (items.isEmpty) return;
    final remaining = <Map<String, Object?>>[];
    for (final item in items) {
      final action = (item['action'] ?? '').toString();
      if (action != 'attendance.create') {
        remaining.add(item);
        continue;
      }
      final payload = item['payload'];
      final map = payload is Map
          ? payload.map((k, v) => MapEntry(k.toString(), v))
          : <String, Object?>{};
      try {
        await _attendance.checkIn({
          'date': (map['attendance_date'] ?? map['date'] ?? '').toString(),
          'check_in': (map['check_in'] ?? '').toString(),
        });
      } on AppFailure catch (e) {
        if (e.code == 'already_checked_in') {
          continue; // treated synced
        }
        remaining.add(item);
      } catch (_) {
        remaining.add(item);
      }
    }
    await _offlineQueue.replaceAll(remaining);
  }
}

enum AttendancePunchResult { online, queuedOffline }

final class AttendanceSnapshot {
  const AttendanceSnapshot({
    required this.today,
    required this.pendingOfflineCount,
    this.fromCache = false,
    this.offlineDegraded = false,
  });

  final Map<String, Object?> today;
  final int pendingOfflineCount;
  final bool fromCache;
  final bool offlineDegraded;
}
