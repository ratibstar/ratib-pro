/// Phase B2 — iOS production validation (static evidence; compile requires macOS).
library;

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

void main() {
  test('Phase marker is B2', () {
    expect(AppConfig.phase, 'B2');
  });

  test('Release/Profile use Production.xcconfig and production APS', () {
    final pbx = File('ios/Runner.xcodeproj/project.pbxproj').readAsStringSync();
    expect(pbx.contains('Flutter/Production.xcconfig'), isTrue);
    expect(
      pbx.contains('CODE_SIGN_ENTITLEMENTS = Runner/RunnerRelease.entitlements'),
      isTrue,
    );
    expect(
      pbx.contains('CODE_SIGN_ENTITLEMENTS = Runner/RunnerDebug.entitlements'),
      isTrue,
    );
    final releaseEnt =
        File('ios/Runner/RunnerRelease.entitlements').readAsStringSync();
    expect(releaseEnt.contains('<string>production</string>'), isTrue);
    final debugEnt =
        File('ios/Runner/RunnerDebug.entitlements').readAsStringSync();
    expect(debugEnt.contains('<string>development</string>'), isTrue);
  });

  test('Info.plist production privacy and ATS', () {
    final plist = File('ios/Runner/Info.plist').readAsStringSync();
    expect(plist.contains('NSFaceIDUsageDescription'), isTrue);
    expect(plist.contains('NSAppTransportSecurity'), isTrue);
    expect(plist.contains('NSAllowsArbitraryLoads'), isTrue);
    expect(plist.contains('<false/>'), isTrue);
    expect(plist.contains('remote-notification'), isTrue);
    expect(plist.contains('UILaunchStoryboardName'), isTrue);
    expect(plist.contains('\$(DISPLAY_NAME)'), isTrue);
    // Not required — no camera / photos / location plugins.
    expect(plist.contains('NSCameraUsageDescription'), isFalse);
    expect(plist.contains('NSPhotoLibraryUsageDescription'), isFalse);
    expect(plist.contains('NSLocationWhenInUseUsageDescription'), isFalse);
    expect(plist.contains('com.apple.developer.associated-domains'), isFalse);
  });

  test('Firebase example maps production bundle; secrets gitignored', () {
    final example =
        File('ios/Runner/GoogleService-Info.plist.example').readAsStringSync();
    expect(example.contains('sa.rateb.hr.mobile'), isTrue);
    expect(example.contains('REPLACE_WITH_FIREBASE_IOS_API_KEY'), isTrue);
    expect(File('ios/Runner/GoogleService-Info.plist').existsSync(), isFalse);
    final gi = File('ios/.gitignore').readAsStringSync();
    expect(gi.contains('GoogleService-Info.plist'), isTrue);
  });

  test('Production scheme archives Release', () {
    final scheme = File(
      'ios/Runner.xcodeproj/xcshareddata/xcschemes/Production.xcscheme',
    ).readAsStringSync();
    expect(scheme.contains('buildConfiguration = "Release"'), isTrue);
    expect(scheme.contains('ArchiveAction'), isTrue);
  });

  test('No Dart print/debugPrint in lib (release hygiene)', () {
    final lib = Directory('lib');
    final offenders = <String>[];
    for (final f in lib.listSync(recursive: true).whereType<File>()) {
      if (!f.path.endsWith('.dart')) continue;
      final text = f.readAsStringSync();
      if (RegExp(r'\bprint\s*\(').hasMatch(text) ||
          RegExp(r'\bdebugPrint\s*\(').hasMatch(text)) {
        offenders.add(f.path);
      }
    }
    expect(offenders, isEmpty, reason: offenders.join(', '));
  });

  test('macOS build helper exists', () {
    expect(File('tool/build_ios_macos.sh').existsSync(), isTrue);
  });

  test('Keychain sharing not required', () {
    final release =
        File('ios/Runner/RunnerRelease.entitlements').readAsStringSync();
    expect(release.contains('keychain-access-groups'), isFalse);
  });
}
