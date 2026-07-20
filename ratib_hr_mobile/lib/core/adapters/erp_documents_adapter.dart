/// DocumentsPort → ESS document APIs (thin ERP adapter).
///
/// Metadata only from list/detail. File bytes via authenticated /file route.
library;

import 'package:ratib_hr_mobile/core/contracts/documents_port.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

final class ErpDocumentsAdapter implements DocumentsPort {
  ErpDocumentsAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
  })  : _http = http,
        _errors = errors;

  static const listPath = '/api/v1/hr/documents';

  final ErpHttpClient _http;
  final ErrorMapper _errors;

  @override
  Future<List<Map<String, Object?>>> listMine({String? category}) async {
    try {
      EmployeeContext.requireResolved();
      final query = <String, String>{};
      if (category != null && category.trim().isNotEmpty) {
        query['category'] = category.trim();
      }
      final body = await _http.get(
        listPath,
        query: query.isEmpty ? null : query,
      );
      _ensureSuccess(body, 'documents_failed');
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
  Future<Map<String, Object?>> detail(String documentKey) async {
    try {
      EmployeeContext.requireResolved();
      final body =
          await _http.get('$listPath/${Uri.encodeComponent(documentKey)}');
      _ensureSuccess(body, 'document_failed');
      final data = body['data'];
      final row = data is Map ? data['document'] : body['document'];
      if (row is! Map) return <String, Object?>{};
      return row.map((k, v) => MapEntry(k.toString(), v));
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<({List<int> bytes, String? contentType, String? filename})?> download(
    String documentKey,
  ) async {
    try {
      EmployeeContext.requireResolved();
      final meta = await detail(documentKey);
      final url = (meta['file_url'] ?? '').toString();
      if (url.isEmpty) return null;
      return await _http.getBytes(
        '$listPath/${Uri.encodeComponent(documentKey)}/file',
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
