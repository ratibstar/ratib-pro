/// Transport contract for calling RATIB ERP.
///
/// Implementations must not contain HR business rules.
/// No Dio / HTTP client code in Phase 0.6 — interface only.
library;

/// Minimal HTTP port. Method names are transport-level, not domain workflows.
abstract interface class ErpHttpClient {
  /// Performs an authenticated GET against an ERP-relative path.
  Future<Map<String, Object?>> get(
    String path, {
    Map<String, String>? query,
  });

  /// Performs an authenticated POST with a JSON-compatible body map.
  Future<Map<String, Object?>> post(
    String path, {
    Map<String, Object?>? body,
  });

  /// Performs an authenticated PUT with a JSON-compatible body map.
  Future<Map<String, Object?>> put(
    String path, {
    Map<String, Object?>? body,
  });
}
