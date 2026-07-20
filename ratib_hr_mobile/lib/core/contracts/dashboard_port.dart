/// Dashboard aggregate — ERP `GET /api/v1/hr/dashboard`.
library;

abstract interface class DashboardPort {
  Future<Map<String, Object?>> summary();
}
