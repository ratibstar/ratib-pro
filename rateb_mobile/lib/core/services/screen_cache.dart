/// In-memory cache for last successful screen responses.
class ScreenCache {
  ScreenCache._();

  static final ScreenCache instance = ScreenCache._();

  final Map<String, _CacheEntry> _store = {};

  T? get<T>(String key) {
    final entry = _store[key];
    if (entry == null) return null;
    return entry.value as T?;
  }

  void set<T>(String key, T value) {
    _store[key] = _CacheEntry(value, DateTime.now());
  }

  void clear() => _store.clear();

  void remove(String key) => _store.remove(key);
}

class _CacheEntry {
  _CacheEntry(this.value, this.storedAt);

  final Object? value;
  final DateTime storedAt;
}

class CacheKeys {
  CacheKeys._();

  static const workerDashboard = 'worker_dashboard';
  static const workerTasks = 'worker_tasks';
  static const workerProfile = 'worker_profile';
  static const companyDashboard = 'company_dashboard';
  static const companyWorkers = 'company_workers';
  static const companyRequests = 'company_requests';
  static const agencyDashboard = 'agency_dashboard';
  static const agencyPipeline = 'agency_pipeline';
  static const agencyAssignments = 'agency_assignments';
}
