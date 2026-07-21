import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_permission_request_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';

class _FakeHttp implements ErpHttpClient {
  final Map<String, Map<String, Object?>> getResponses;
  final Map<String, Map<String, Object?>> postResponses;
  final calls = <Map<String, Object?>>[];

  _FakeHttp({
    this.getResponses = const {},
    this.postResponses = const {},
  });

  @override
  Future<Map<String, Object?>> get(
    String path, {
    Map<String, String>? query,
  }) async {
    calls.add({'method': 'GET', 'path': path});
    final res = getResponses[path];
    if (res == null) throw AppFailure(code: 'missing', message: path);
    return res;
  }

  @override
  Future<({List<int> bytes, String? contentType, String? filename})> getBytes(
    String path, {
    Map<String, String>? query,
  }) async =>
      throw UnimplementedError();

  @override
  Future<Map<String, Object?>> post(
    String path, {
    Map<String, Object?>? body,
  }) async {
    calls.add({'method': 'POST', 'path': path, 'body': body});
    final res = postResponses[path];
    if (res == null) throw AppFailure(code: 'missing', message: path);
    if (res['success'] != true) {
      throw AppFailure(
        code: res['code']?.toString() ?? 'erp',
        message: res['message']?.toString(),
      );
    }
    return res;
  }

  @override
  Future<Map<String, Object?>> put(
    String path, {
    Map<String, Object?>? body,
  }) async =>
      throw UnimplementedError();
}

class _PassthroughErrors implements ErrorMapper {
  @override
  AppFailure map(Object error, [StackTrace? stackTrace]) {
    if (error is AppFailure) return error;
    return AppFailure(code: 'unknown', message: error.toString());
  }
}

void main() {
  test('Adapter lists permission requests from data.items', () async {
    final http = _FakeHttp(getResponses: {
      ErpPermissionRequestAdapter.listPath: {
        'success': true,
        'data': {
          'items': [
            {
              'id': 1,
              'permission_date': '2026-07-21',
              'time_from': '09:00',
              'time_to': '11:00',
              'status': 'pending',
            },
          ],
        },
      },
    });
    final port = ErpPermissionRequestAdapter(
      http: http,
      errors: _PassthroughErrors(),
    );
    final rows = await port.listMine();
    expect(rows, hasLength(1));
    expect(rows.first['id'], 1);
    expect(http.calls.single['path'], ErpPermissionRequestAdapter.listPath);
  });

  test('Adapter submit strips identity fields', () async {
    final http = _FakeHttp(postResponses: {
      ErpPermissionRequestAdapter.listPath: {
        'success': true,
        'data': {'permission_request_id': 9},
      },
    });
    final port = ErpPermissionRequestAdapter(
      http: http,
      errors: _PassthroughErrors(),
    );
    await port.submit({
      'permission_date': '2026-07-21',
      'time_from': '09:00',
      'time_to': '10:00',
      'employee_id': 999,
      'company_id': 1,
      'status': 'approved',
    });
    final body = http.calls.single['body'] as Map<String, Object?>;
    expect(body.containsKey('employee_id'), isFalse);
    expect(body.containsKey('company_id'), isFalse);
    expect(body.containsKey('status'), isFalse);
    expect(body['permission_date'], '2026-07-21');
  });

  test('Permission apply route is under requests/permissions', () {
    expect(AppRoutes.permissionApply, '/requests/permissions/apply');
  });
}
