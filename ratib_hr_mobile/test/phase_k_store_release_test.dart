/// Phase K — store release preparation static evidence.
library;

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase marker is K3 or L1 and store version is 1.0.0', () {
    expect(['K3', 'L1'].contains(AppConfig.phase), isTrue);
    expect(AppConfig.versionLabel, '1.0.0');
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
  });

  test('pubspec version strategy NAME+CODE', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    expect(pubspec.contains(RegExp(r'^version:\s*1\.0\.0\+200\s*$', multiLine: true)),
        isTrue);
  });

  test('Android production applicationId and R8 release minify', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    expect(
      RegExp(
        r'create\("production"\)[\s\S]*?applicationId = "sa\.rateb\.hr\.mobile"',
      ).hasMatch(gradle),
      isTrue,
    );
    expect(gradle.contains('isMinifyEnabled = true'), isTrue);
    expect(gradle.contains('isShrinkResources = true'), isTrue);
    expect(gradle.contains('proguard-rules.pro'), isTrue);
    expect(File('android/app/proguard-rules.pro').existsSync(), isTrue);
  });

  test('Signing secrets are gitignored; example documents upload alias', () {
    expect(File('android/key.properties.example').existsSync(), isTrue);
    final example = File('android/key.properties.example').readAsStringSync();
    expect(example.contains('ratib_hr_upload'), isTrue);
    expect(example.contains('ratib-hr-upload-key.jks'), isTrue);
    final gi = File('android/.gitignore').readAsStringSync();
    expect(gi.contains('key.properties'), isTrue);
    expect(gi.contains('*.jks'), isTrue);
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    expect(gradle.contains('Missing android/key.properties'), isTrue);
    expect(gradle.contains('signingConfigs.getByName("debug")'), isFalse);
  });

  test('Manifest cleartext disabled and notification permission present', () {
    final manifest =
        File('android/app/src/main/AndroidManifest.xml').readAsStringSync();
    expect(manifest.contains('usesCleartextTraffic="false"'), isTrue);
    expect(manifest.contains('POST_NOTIFICATIONS'), isTrue);
  });

  test('iOS Archive / Export / production APS ready', () {
    final pbx = File('ios/Runner.xcodeproj/project.pbxproj').readAsStringSync();
    expect(pbx.contains('Flutter/Production.xcconfig'), isTrue);
    expect(
      pbx.contains('CODE_SIGN_ENTITLEMENTS = Runner/RunnerRelease.entitlements'),
      isTrue,
    );
    expect(
      File('ios/Runner/RunnerRelease.entitlements')
          .readAsStringSync()
          .contains('<string>production</string>'),
      isTrue,
    );
    expect(File('ios/ExportOptions.plist.example').existsSync(), isTrue);
    expect(
      File('ios/Runner.xcodeproj/xcshareddata/xcschemes/Production.xcscheme')
          .existsSync(),
      isTrue,
    );
  });

  test('Store assets and compliance docs exist', () {
    expect(File('docs/STORE_ASSETS_CHECKLIST.md').existsSync(), isTrue);
    expect(File('docs/COMPLIANCE.md').existsSync(), isTrue);
    expect(File('docs/PHASE_K.md').existsSync(), isTrue);
    expect(File('docs/PHASE_K2.md').existsSync(), isTrue);
    expect(File('docs/PHASE_K3.md').existsSync(), isTrue);
    expect(File('docs/PLAY_STORE_RELEASE.md').existsSync(), isTrue);
    expect(File('tool/build_android_aab.ps1').existsSync(), isTrue);
  });
}
