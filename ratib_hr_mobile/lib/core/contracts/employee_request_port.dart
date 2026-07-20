/// Employee requests — existing ERP employee requests only.
///
/// Writes are online-only. No Flutter business rules.
library;

abstract interface class EmployeeRequestPort {
  Future<List<Map<String, Object?>>> listMine();

  Future<Map<String, Object?>> detail(String id);

  Future<void> submit(Map<String, Object?> payload);
}
