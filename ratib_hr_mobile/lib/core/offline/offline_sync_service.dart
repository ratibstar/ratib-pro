/// Flushes ESS offline queue via online ESS APIs (ERP remains SoT).
///
/// No local conflict resolution. Allowed actions only.
library;

import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/contracts/offline_queue_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/offline/connectivity_controller.dart';

enum OfflineFlushResult { synced, partial, failed, empty, offline }

final class OfflineSyncService {
  OfflineSyncService({
    required OfflineQueuePort queue,
    required AttendancePort attendance,
    required LeavePort leave,
    required ConnectivityController connectivity,
  })  : _queue = queue,
        _attendance = attendance,
        _leave = leave,
        _connectivity = connectivity;

  final OfflineQueuePort _queue;
  final AttendancePort _attendance;
  final LeavePort _leave;
  final ConnectivityController _connectivity;

  Future<int> pendingCount() => _queue.pendingCount();

  Future<List<Map<String, Object?>>> pendingItems() => _queue.pendingItems();

  Future<OfflineFlushResult> flush() async {
    final reachable = await _connectivity.probe();
    if (!reachable) {
      _connectivity.markOffline();
      return OfflineFlushResult.offline;
    }

    final items = await _queue.pendingItems();
    if (items.isEmpty) {
      _connectivity.reportCompleted();
      return OfflineFlushResult.empty;
    }

    final remaining = <Map<String, Object?>>[];
    var synced = 0;
    var failed = 0;

    for (var i = 0; i < items.length; i++) {
      final item = items[i];
      final action = (item['action'] ?? '').toString();
      final payload = item['payload'];
      final map = payload is Map
          ? payload.map((k, v) => MapEntry(k.toString(), v))
          : <String, Object?>{};

      try {
        if (action == 'attendance.create') {
          await _attendance.checkIn({
            'date': (map['attendance_date'] ?? map['date'] ?? '').toString(),
            'check_in': (map['check_in'] ?? '').toString(),
          });
          synced++;
        } else if (action == 'leave_request.draft') {
          await _leave.apply({
            'leave_type_id': map['leave_type_id'],
            'start_date': map['start_date'],
            'end_date': map['end_date'],
            if (map['reason'] != null) 'reason': map['reason'],
          });
          synced++;
        } else {
          failed++;
        }
      } on AppFailure catch (e) {
        if (e.code == 'network' || e.code == 'timeout') {
          remaining.addAll(items.sublist(i));
          await _queue.replaceAll(remaining);
          _connectivity.markOffline(e.message);
          return OfflineFlushResult.offline;
        }
        if (e.code == 'already_checked_in' || e.code == 'duplicate_request') {
          synced++;
          continue;
        }
        failed++;
        remaining.add({...item, 'last_error': e.code});
      } catch (_) {
        failed++;
        remaining.add(item);
      }
    }

    await _queue.replaceAll(remaining);

    if (failed > 0 && synced == 0 && remaining.isNotEmpty) {
      _connectivity.reportFailed();
      return OfflineFlushResult.failed;
    }
    if (remaining.isNotEmpty) {
      _connectivity.reportFailed();
      return OfflineFlushResult.partial;
    }
    _connectivity.reportCompleted();
    return OfflineFlushResult.synced;
  }
}
