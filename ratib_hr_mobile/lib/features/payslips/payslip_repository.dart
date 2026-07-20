/// Payslips repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/contracts/payslip_port.dart';

final class PayslipRepository {
  PayslipRepository({required PayslipPort payslips}) : _payslips = payslips;

  final PayslipPort _payslips;

  Future<List<Map<String, Object?>>> loadList() => _payslips.listMine();

  Future<Map<String, Object?>> loadDetail(String id) => _payslips.detail(id);

  Future<({List<int> bytes, String? contentType, String? filename})> download(
    String id,
  ) =>
      _payslips.download(id);
}
