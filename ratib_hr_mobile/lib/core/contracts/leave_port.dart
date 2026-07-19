/// Leave ESS port — existing ERP leave balances and requests only.
///
/// Offline apply must use existing `leave_request.draft` queue only.
/// No new leave policies in Flutter.
/// Phase 0.6: interface only.
library;

abstract interface class LeavePort {
  /// Leave balances for the current employee.
  Future<List<Map<String, Object?>>> balances();

  /// Leave requests for the current employee.
  Future<List<Map<String, Object?>>> status();

  /// Apply leave — fields defined by existing ERP leave create.
  Future<void> apply(Map<String, Object?> payload);
}
