/// Permission (short exit) requests — existing ERP permission requests only.
///
/// Writes are online-only. Identity is server-resolved.
library;

abstract interface class PermissionRequestPort {
  Future<List<Map<String, Object?>>> listMine();

  Future<void> submit(Map<String, Object?> payload);
}
