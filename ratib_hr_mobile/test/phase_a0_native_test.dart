import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase A0 native shell markers remain', () {
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
    expect(['A0', 'A', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'I3','B','B2','K','K1','K2','K3','L1'].contains(AppConfig.phase), isTrue);
  });

  test('Android and iOS project trees exist', () {
    expect(Directory('android/app').existsSync(), isTrue);
    expect(Directory('ios/Runner').existsSync(), isTrue);
    expect(
      File('android/app/src/main/kotlin/sa/rateb/hr/mobile/MainActivity.kt')
          .existsSync(),
      isTrue,
    );
    expect(File('ios/Flutter/Production.xcconfig').existsSync(), isTrue);
    expect(File('android/key.properties.example').existsSync(), isTrue);
  });
}
