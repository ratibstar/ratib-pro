/// Attendance UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';

enum AttendanceLoadStatus { idle, loading, ready, error }

class AttendanceState extends ChangeNotifier {
  AttendanceState({required AttendanceRepository repository})
      : _repository = repository;

  final AttendanceRepository _repository;

  AttendanceLoadStatus status = AttendanceLoadStatus.idle;
  String? errorCode;
  String? errorMessage;
  Map<String, Object?> today = const {};
  List<Map<String, Object?>> history = const [];
  int pendingOfflineCount = 0;
  bool punching = false;
  bool offlineDegraded = false;
  bool fromCache = false;

  bool get hasCheckIn {
    final v = today['check_in']?.toString().trim() ?? '';
    return v.isNotEmpty && v != '00:00:00';
  }

  bool get hasCheckOut {
    final v = today['check_out']?.toString().trim() ?? '';
    return v.isNotEmpty && v != '00:00:00';
  }

  String get statusLabel {
    final s = (today['status'] ?? '').toString().trim();
    if (s.isNotEmpty) return s;
    if (hasCheckOut) return 'completed';
    if (hasCheckIn) return 'checked_in';
    return 'not_checked_in';
  }

  /// Display-only duration from check_in → check_out (or now).
  String? workingDurationLabel() {
    if (!hasCheckIn) return null;
    final start = _parseTime(today['check_in']?.toString());
    if (start == null) return null;
    final end = hasCheckOut
        ? _parseTime(today['check_out']?.toString())
        : DateTime.now();
    if (end == null) return null;
    var diff = end.difference(start);
    if (diff.isNegative) return null;
    final h = diff.inHours;
    final m = diff.inMinutes.remainder(60);
    return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
  }

  Future<void> loadToday() async {
    final keepReady = status == AttendanceLoadStatus.ready;
    if (!keepReady) {
      status = AttendanceLoadStatus.loading;
    }
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      final snap = await _repository.loadToday();
      today = snap.today;
      pendingOfflineCount = snap.pendingOfflineCount;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = AttendanceLoadStatus.ready;
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      if (keepReady && EssFailureUi.isConnectivity(f)) {
        offlineDegraded = true;
        status = AttendanceLoadStatus.ready;
      } else {
        errorCode = f.code;
        errorMessage = f.message;
        status = AttendanceLoadStatus.error;
      }
    }
    notifyListeners();
  }

  Future<void> loadHistory() async {
    final keepReady = status == AttendanceLoadStatus.ready;
    if (!keepReady) {
      status = AttendanceLoadStatus.loading;
    }
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      final historySnap = await _repository.loadHistory();
      history = historySnap.items;
      final snap = await _repository.loadToday();
      pendingOfflineCount = snap.pendingOfflineCount;
      offlineDegraded = historySnap.offlineDegraded || snap.offlineDegraded;
      fromCache = historySnap.fromCache || snap.fromCache;
      status = AttendanceLoadStatus.ready;
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      if (keepReady && EssFailureUi.isConnectivity(f)) {
        offlineDegraded = true;
        status = AttendanceLoadStatus.ready;
      } else {
        try {
          pendingOfflineCount = await _repository.pendingOfflineCount();
        } catch (_) {}
        errorCode = f.code;
        errorMessage = f.message;
        status = AttendanceLoadStatus.error;
      }
    }
    notifyListeners();
  }

  Future<AttendancePunchResult?> checkIn() async {
    punching = true;
    notifyListeners();
    try {
      final result = await _repository.checkIn();
      await loadToday();
      return result;
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      errorCode = f.code;
      errorMessage = f.message;
      notifyListeners();
      rethrow;
    } finally {
      punching = false;
      notifyListeners();
    }
  }

  Future<void> checkOut() async {
    punching = true;
    notifyListeners();
    try {
      await _repository.checkOut();
      await loadToday();
    } catch (e) {
      final f = EssFailureUi.normalize(e);
      EssFailureUi.signalIfOffline(f);
      errorCode = f.code;
      errorMessage = f.message;
      notifyListeners();
      rethrow;
    } finally {
      punching = false;
      notifyListeners();
    }
  }

  DateTime? _parseTime(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final parts = raw.trim().split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    final s = parts.length > 2 ? int.tryParse(parts[2]) ?? 0 : 0;
    if (h == null || m == null) return null;
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, h, m, s);
  }
}
