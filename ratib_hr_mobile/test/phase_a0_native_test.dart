import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase A0 native shell markers', () {
    expect(AppConfig.phase, 'A0');
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
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
