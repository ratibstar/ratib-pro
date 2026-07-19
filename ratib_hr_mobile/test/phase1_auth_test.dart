import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_auth_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/env/app_flavor.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';

void main() {
  test('Phase 1 uses existing ERP token path', () {
    expect(ErpAuthAdapter.tokenPath, '/api/v1/auth/token');
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
  });

  test('Environment has no hardcoded host', () {
    const env = DartDefineAppEnvironment();
    expect(env.erpBaseUrl, isEmpty); // tests run without dart-define
    expect(env.apisEnabled, isFalse);
    expect(AppFlavor.values, contains(env.flavor));
  });
}
