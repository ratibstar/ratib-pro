/// Lightweight API timing for pilot diagnostics (no request/response bodies logged).
class ApiTelemetry {
  ApiTelemetry._();

  static int? lastLatencyMs;
  static String? lastEndpoint;
  static DateTime? lastCompletedAt;
  static int requestCount = 0;

  static void recordSuccess(String endpoint, int latencyMs) {
    lastEndpoint = endpoint;
    lastLatencyMs = latencyMs;
    lastCompletedAt = DateTime.now();
    requestCount++;
  }

  static void recordFailure(String endpoint, int latencyMs) {
    lastEndpoint = endpoint;
    lastLatencyMs = latencyMs;
    lastCompletedAt = DateTime.now();
    requestCount++;
  }

  static void reset() {
    lastLatencyMs = null;
    lastEndpoint = null;
    lastCompletedAt = null;
    requestCount = 0;
  }
}
