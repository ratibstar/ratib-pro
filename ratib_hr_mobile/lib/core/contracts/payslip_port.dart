/// Payslips — existing ERP payslip list/detail read only.
///
/// Mobile must never calculate payroll.
/// Phase 0.6: interface only.
library;

abstract interface class PayslipPort {
  Future<List<Map<String, Object?>>> listMine();

  Future<Map<String, Object?>> detail(String payslipKey);
}
