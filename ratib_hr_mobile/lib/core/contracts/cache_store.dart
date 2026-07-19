/// Read-through cache for ESS presentation (lists, profile snapshot).
///
/// Cache is not a source of truth. ERP remains authoritative.
/// Phase 0.6: interface only.
library;

abstract interface class CacheStore {
  Future<void> write(String key, String value);

  Future<String?> read(String key);

  Future<void> remove(String key);

  Future<void> clear();
}
