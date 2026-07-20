/// PayslipPort → ESS payslip APIs (thin ERP adapter).
///
/// Identity is server-resolved — never send employee_id.
/// No payroll calculation on device.
library;

import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/payslip_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpPayslipAdapter implements PayslipPort {
  ErpPayslipAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const listPath = '/api/v1/hr/payslips';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> listMine() async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get(listPath);
      _ensureSuccess(body, 'payslips_failed');
      final data = body['data'];
      final raw = data is Map ? data['items'] : body['items'];
      if (raw is! List) return const [];
      return raw
          .whereType<Map>()
          .map((e) => e.map((k, v) => MapEntry(k.toString(), v)))
          .toList(growable: false);
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<Map<String, Object?>> detail(String payslipKey) async {
    try {
      EmployeeContext.requireResolved();
      final body = await _http.get('$listPath/${Uri.encodeComponent(payslipKey)}');
      _ensureSuccess(body, 'payslip_failed');
      final data = body['data'];
      final row = data is Map ? data['payslip'] : body['payslip'];
      if (row is! Map) return <String, Object?>{};
      return row.map((k, v) => MapEntry(k.toString(), v));
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<({List<int> bytes, String? contentType, String? filename})> download(
    String payslipKey,
  ) async {
    try {
      EmployeeContext.requireResolved();
      return await _http.getBytes(
        '$listPath/${Uri.encodeComponent(payslipKey)}/file',
      );
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  void _ensureSuccess(Map<String, Object?> body, String fallbackCode) {
    if (body['success'] == true) return;
    throw AppFailure(
      code: body['code']?.toString() ?? fallbackCode,
      message: body['message']?.toString(),
    );
  }
}
