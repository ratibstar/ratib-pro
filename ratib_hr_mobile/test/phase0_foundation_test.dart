import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase 0 foundation constants', () {
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
    expect(AppConfig.apisEnabled, isFalse);
    expect(AppConfig.defaultLocale.languageCode, 'ar');
    expect(AppConfig.sourceOfTruth, 'RATIB ERP');
  });
}
