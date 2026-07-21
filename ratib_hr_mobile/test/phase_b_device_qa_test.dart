/// Phase B — Device QA static + widget evidence (no ERP business rules).
library;

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/theme/app_theme.dart';
import 'package:ratib_hr_mobile/core/theme/brand_theme_factory.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

class _MemCache implements CacheStore {
  final map = <String, String>{};

  @override
  Future<void> clear() async => map.clear();

  @override
  Future<String?> read(String key) async => map[key];

  @override
  Future<void> remove(String key) async => map.remove(key);

  @override
  Future<void> write(String key, String value) async => map[key] = value;
}

void main() {
  test('Phase marker is B', () {
    expect(['B','B2','K','K1','K2'].contains(AppConfig.phase), isTrue);
    expect(AppConfig.appId, 'sa.rateb.hr.mobile');
  });

  test('Android production applicationId matches Architecture Lock', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    expect(gradle.contains('applicationId = "sa.rateb.hr.mobile.z812"'), isFalse);
    expect(
      RegExp(
        r'create\("production"\)[\s\S]*?applicationId = "sa\.rateb\.hr\.mobile"',
      ).hasMatch(gradle),
      isTrue,
    );
  });

  test('AndroidManifest declares POST_NOTIFICATIONS and rotation configChanges',
      () {
    final manifest =
        File('android/app/src/main/AndroidManifest.xml').readAsStringSync();
    expect(manifest.contains('android.permission.POST_NOTIFICATIONS'), isTrue);
    expect(manifest.contains('android.permission.INTERNET'), isTrue);
    expect(manifest.contains('orientation|keyboardHidden'), isTrue);
    expect(
      manifest.contains('android.intent.action.VIEW'),
      isFalse,
      reason: 'Deep links not shipped — documented Phase B gap',
    );
  });

  test('iOS Info.plist has background modes and Face ID usage string', () {
    final plist = File('ios/Runner/Info.plist').readAsStringSync();
    expect(plist.contains('UIBackgroundModes'), isTrue);
    expect(plist.contains('remote-notification'), isTrue);
    expect(plist.contains('NSFaceIDUsageDescription'), isTrue);
  });

  test('iOS Runner entitlements wire aps-environment', () {
    final debugEnt =
        File('ios/Runner/RunnerDebug.entitlements').readAsStringSync();
    final releaseEnt =
        File('ios/Runner/RunnerRelease.entitlements').readAsStringSync();
    expect(debugEnt.contains('aps-environment'), isTrue);
    expect(releaseEnt.contains('aps-environment'), isTrue);
    final pbx = File('ios/Runner.xcodeproj/project.pbxproj').readAsStringSync();
    expect(
      pbx.contains('CODE_SIGN_ENTITLEMENTS = Runner/RunnerDebug.entitlements'),
      isTrue,
    );
    expect(
      pbx.contains('CODE_SIGN_ENTITLEMENTS = Runner/RunnerRelease.entitlements'),
      isTrue,
    );
  });

  test('iOS flavor xcconfigs and signing placeholders exist', () {
    expect(File('ios/Flutter/Dev.xcconfig').existsSync(), isTrue);
    expect(File('ios/Flutter/Staging.xcconfig').existsSync(), isTrue);
    expect(File('ios/Flutter/Production.xcconfig').existsSync(), isTrue);
    expect(File('ios/SIGNING.md').existsSync(), isTrue);
    expect(File('ios/ExportOptions.plist.example').existsSync(), isTrue);
    expect(
      File('ios/Runner/GoogleService-Info.plist.example').existsSync(),
      isTrue,
    );
  });

  test('Offline queue contract supports pending list APIs used by sync UI',
      () async {
    final q = LocalOfflineQueueAdapter(cache: _MemCache());
    await q.enqueue(
      existingAction: 'attendance.create',
      payload: {'attendance_date': '2026-07-21'},
    );
    expect(await q.pendingCount(), 1);
    expect((await q.pendingItems()).length, 1);
    await q.replaceAll(const []);
    expect(await q.pendingCount(), 0);
  });

  testWidgets('RTL Arabic and LTR English locales load', (tester) async {
    for (final locale in const [Locale('ar'), Locale('en')]) {
      await tester.pumpWidget(
        MaterialApp(
          locale: locale,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: AppLocalizations.supportedLocales,
          home: Builder(
            builder: (context) {
              final l10n = AppLocalizations.of(context);
              final dir = Directionality.of(context);
              return Text('${l10n.navHome}|$dir');
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(Text), findsOneWidget);
      final expectedDir =
          locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr;
      expect(Directionality.of(tester.element(find.byType(Text))), expectedDir);
    }
  });

  testWidgets('Brand theme from tenant hex produces ThemeData', (tester) async {
    final seed = BrandThemeFactory.parseColor('#0F766E');
    expect(seed, isNotNull);
    final theme = AppTheme.lightFromSeed(seed!);
    await tester.pumpWidget(
      MaterialApp(
        theme: theme,
        home: const Scaffold(body: Text('theme')),
      ),
    );
    expect(find.text('theme'), findsOneWidget);
  });

  test('Release signing requires key.properties (no silent debug fallback)', () {
    expect(File('android/key.properties.example').existsSync(), isTrue);
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    expect(gradle.contains('Missing android/key.properties'), isTrue);
    expect(gradle.contains('signingConfigs.getByName("debug")'), isFalse);
  });
}
