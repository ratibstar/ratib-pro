/// Payslips — existing ERP payslip list/detail read only.
///
/// Mobile must never calculate payroll.
library;

abstract interface class PayslipPort {
  Future<List<Map<String, Object?>>> listMine();

  Future<Map<String, Object?>> detail(String payslipKey);

  /// Authenticated file bytes for [download_url] path (online only).
  Future<({List<int> bytes, String? contentType, String? filename})> download(
    String payslipKey,
  );
}
