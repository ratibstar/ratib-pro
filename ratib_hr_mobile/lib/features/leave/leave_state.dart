/// Leave UI state — presentation only.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/errors/ess_failure_ui.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';

enum LeaveLoadStatus { idle, loading, ready, error }

class LeaveState extends ChangeNotifier {
  LeaveState({required LeaveRepository repository}) : _repository = repository;

  final LeaveRepository _repository;

  LeaveLoadStatus status = LeaveLoadStatus.idle;
  String? errorCode;
  String? errorMessage;
  List<Map<String, Object?>> balances = const [];
  List<Map<String, Object?>> requests = const [];
  Map<String, Object?> detail = const {};
  int pendingOfflineCount = 0;
  bool submitting = false;
  bool offlineDegraded = false;
  bool fromCache = false;

  Future<void> loadBalances() async {
    status = LeaveLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    offlineDegraded = false;
    fromCache = false;
    notifyListeners();
    try {
      final snap = await _repository.loadBalances();
      balances = snap.balances;
      pendingOfflineCount = snap.pendingOfflineCount;
      offlineDegraded = snap.offlineDegraded;
      fromCache = snap.fromCache;
      status = LeaveLoadStatus.ready;
    } catch (e) {
      _setError(e);
    }
    notifyListeners();
  }

  Future<void> loadRequests() async {
    status = LeaveLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      requests = await _repository.loadRequests();
      pendingOfflineCount = await _repository.pendingOfflineCount();
      status = LeaveLoadStatus.ready;
    } catch (e) {
      _setError(e);
    }
    notifyListeners();
  }

  Future<void> loadDetail(String id) async {
    status = LeaveLoadStatus.loading;
    errorCode = null;
    errorMessage = null;
    notifyListeners();
    try {
      detail = await _repository.loadDetail(id);
      status = LeaveLoadStatus.ready;
    } catch (e) {
      _setError(e);
    }
    notifyListeners();
  }

  Future<LeaveApplyResult> apply({
    required int leaveTypeId,
    required String startDate,
    required String endDate,
    String? reason,
  }) async {
    submitting = true;
    notifyListeners();
    try {
      final result = await _repository.apply(
        leaveTypeId: leaveTypeId,
        startDate: startDate,
        endDate: endDate,
        reason: reason,
      );
      await loadRequests();
      return result;
    } catch (e) {
      _setError(e);
      notifyListeners();
      rethrow;
    } finally {
      submitting = false;
      notifyListeners();
    }
  }

  void _setError(Object e) {
    final f = EssFailureUi.normalize(e);
    EssFailureUi.signalIfOffline(f);
    errorCode = f.code;
    errorMessage = f.message;
    status = LeaveLoadStatus.error;
  }
}
