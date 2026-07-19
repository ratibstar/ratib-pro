import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

void main() {
  tearDown(EmployeeContext.clear);

  test('Phase 2 me path and context', () {
    expect(ErpMeAdapter.mePath, '/api/v1/hr/me');
    expect(EmployeeContext.isResolved, isFalse);
  });

  test('EmployeeContext binds single employee', () {
    final ctx = EmployeeContext.fromErpRecord({
      'id': 42,
      'name': 'Test',
      'user_id': 7,
    });
    EmployeeContext.bind(ctx);
    expect(EmployeeContext.requireResolved().employeeId, '42');
  });
}
