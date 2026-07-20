/// Workforce ratings — ERP `GET /api/v1/hr/ratings` (read-only).
library;

abstract interface class RatingsPort {
  Future<Map<String, Object?>> summary();
}
