/// Leave repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class LeaveRepository {
  LeaveRepository({
    required LeavePort leave,
    required OfflineQueuePort offlineQueue,
  })  : _leave = leave,
        _offlineQueue = offlineQueue;

  final LeavePort _leave;
  final OfflineQueuePort _offlineQueue;

  Future<List<Map<String, Object?>>> loadBalances() => _leave.balances();

  Future<List<Map<String, Object?>>> loadRequests() => _leave.status();

  Future<Map<String, Object?>> loadDetail(String id) {
    if (_leave is ErpLeaveAdapter) {
      return _leave.detail(id);
    }
    return _leave.status().then((rows) {
      for (final r in rows) {
        if ((r['id'] ?? '').toString() == id) return r;
      }
      return <String, Object?>{};
    });
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
        await _enqueueDraft(payload);
        return LeaveApplyResult.queuedOffline;
      }
      rethrow;
    }
  }

  Future<int> pendingOfflineCount() async {
    if (_offlineQueue is LocalOfflineQueueAdapter) {
      final items = await _offlineQueue.pendingItems();
      return items.where((e) => (e['action'] ?? '') == 'leave_request.draft').length;
    }
    return 0;
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
    if (_offlineQueue is! LocalOfflineQueueAdapter) return;
    final local = _offlineQueue;
    final items = await local.pendingItems();
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
    await local.replaceAll(remaining);
  }
}

enum LeaveApplyResult { online, queuedOffline }
