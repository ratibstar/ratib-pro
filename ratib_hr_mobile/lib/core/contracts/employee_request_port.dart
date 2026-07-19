/// Employee requests — existing ERP employee requests only.
///
/// Writes are online-only in Controlled GO (no new offline queue).
/// Phase 0.6: interface only.
library;

abstract interface class EmployeeRequestPort {
  Future<List<Map<String, Object?>>> listMine();

  Future<void> submit(Map<String, Object?> payload);
}
