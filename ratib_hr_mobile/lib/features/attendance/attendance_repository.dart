/// Attendance repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_state.dart';

final class AttendanceRepository {
  AttendanceRepository({
    required AttendancePort attendance,
    required OfflineQueuePort offlineQueue,
  })  : _attendance = attendance,
        _offlineQueue = offlineQueue;

  final AttendancePort _attendance;
  final OfflineQueuePort _offlineQueue;

  Future<AttendanceSnapshot> loadToday() async {
    final row = await _attendance.today();
    final pending = await _pendingCount();
    return AttendanceSnapshot(
      today: row,
      pendingOfflineCount: pending,
    );
  }

  Future<List<Map<String, Object?>>> loadHistory() {
    return _attendance.history();
  }

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
  });

  final Map<String, Object?> today;
  final int pendingOfflineCount;
}
