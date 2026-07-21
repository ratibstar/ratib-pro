/// Phase L1 — iOS production build preparation (static; Mac compile required for binary).
library;

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase marker allows L1', () {
    expect(AppConfig.phase, 'L1');
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
  });

  test('Production Bundle ID is sa.rateb.hr.mobile everywhere needed', () {
    final prod = File('ios/Flutter/Production.xcconfig').readAsStringSync();
    expect(prod.contains('PRODUCT_BUNDLE_IDENTIFIER = sa.rateb.hr.mobile'),
        isTrue);
    final pbx = File('ios/Runner.xcodeproj/project.pbxproj').readAsStringSync();
    expect(
      RegExp(r'PRODUCT_BUNDLE_IDENTIFIER = sa\.rateb\.hr\.mobile;')
          .hasMatch(pbx),
      isTrue,
    );
    expect(pbx.contains('Flutter/Production.xcconfig'), isTrue);
    final example =
        File('ios/Runner/GoogleService-Info.plist.example').readAsStringSync();
    expect(example.contains('sa.rateb.hr.mobile'), isTrue);
  });

  test('Production.xcscheme Archives Release with production dart-define', () {
    final scheme = File(
      'ios/Runner.xcodeproj/xcshareddata/xcschemes/Production.xcscheme',
    ).readAsStringSync();
    expect(scheme.contains('<ArchiveAction'), isTrue);
    expect(scheme.contains('buildConfiguration = "Release"'), isTrue);
    expect(scheme.contains('APP_FLAVOR=production'), isTrue);
  });

  test('Release APS is production; no secrets templates committed as real files',
      () {
    final release =
        File('ios/Runner/RunnerRelease.entitlements').readAsStringSync();
    expect(release.contains('<string>production</string>'), isTrue);
    expect(File('ios/Runner/GoogleService-Info.plist').existsSync(), isFalse);
    expect(File('ios/ExportOptions.plist.example').existsSync(), isTrue);
    final example = File('ios/ExportOptions.plist.example').readAsStringSync();
    expect(example.contains('YOUR_APPLE_TEAM_ID'), isTrue);
    expect(File('ios/ExportOptions.plist').existsSync(), isFalse);
  });

  test('L1 docs and Mac build script exist', () {
    expect(File('docs/PHASE_L1.md').existsSync(), isTrue);
    final script = File('tool/build_ios_macos.sh').readAsStringSync();
    expect(script.contains('Phase L1'), isTrue);
    expect(script.contains('--no-codesign'), isTrue);
    expect(script.contains('sa.rateb.hr.mobile'), isTrue);
  });
}
