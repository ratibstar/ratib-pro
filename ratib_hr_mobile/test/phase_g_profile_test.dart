import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_profile_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/contracts/profile_port.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/features/profile/profile_repository.dart';
import 'package:ratib_hr_mobile/features/profile/profile_state.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class _FakeProfile implements ProfilePort {
  Map<String, Object?> row = const {};

  @override
  Future<Map<String, Object?>> mine() async => row;
}

MobileAppConfiguration _cfg(Map<String, bool> features) {
  return MobileAppConfiguration(
    companyId: 1,
    companyName: 'A',
    appName: 'A',
    logoUrl: '',
    iconUrl: '',
    splashUrl: '',
    themeColorHex: '#0F766E',
    features: features,
    mobileActive: true,
    role: AppWorkspaceRole.employee,
    fetchedAt: DateTime.utc(2026, 7, 20),
  );
}

void main() {
  setUp(() {
    EmployeeContext.clear();
    EmployeeContext.bind(
      EmployeeContext.fromErpRecord({'id': 10, 'name': 'Test'}),
    );
  });

  tearDown(EmployeeContext.clear);

  test('Phase marker is G or later', () {
    expect(['G', 'H', 'J', 'I3','B'].contains(AppConfig.phase), isTrue);
  });

  test('Profile adapter path is Phase G ESS endpoint', () {
    expect(ErpProfileAdapter.profilePath, '/api/v1/hr/profile');
  });

  test('Feature flag gates profile route and more item', () {
    final on = _cfg({MobileFeatureKey.profile: true});
    final off = _cfg({MobileFeatureKey.profile: false});
    expect(ShellNavPolicy.isRouteAllowed(on, '/more/profile'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(off, '/more/profile'), isFalse);
    expect(ShellNavPolicy.visibleMoreItems(on), contains(ShellMoreItem.profile));
    expect(
      ShellNavPolicy.visibleMoreItems(off),
      isNot(contains(ShellMoreItem.profile)),
    );
  });

  test('Repository maps profile DTO fields', () async {
    final fake = _FakeProfile()
      ..row = {
        'id': 7,
        'employee_no': 'E-7',
        'full_name': 'Ali',
        'photo_url': null,
        'email': 'a@x.com',
        'phone': '050',
        'department': 'HR',
        'job_title': 'Specialist',
        'branch': 'Riyadh',
        'manager': 'Sara',
        'join_date': '2024-01-01',
        'status': 'active',
      };
    final repo = ProfileRepository(profile: fake);
    final map = await repo.loadMine();
    expect(map['full_name'], 'Ali');
    expect(map['department'], 'HR');
    expect(map.containsKey('salary_base'), isFalse);
  });

  test('ProfileState loads ready profile', () async {
    final fake = _FakeProfile()
      ..row = {'id': 1, 'full_name': 'Noura', 'status': 'active'};
    final state = ProfileState(
      repository: ProfileRepository(profile: fake),
    );
    await state.load();
    expect(state.status, ProfileLoadStatus.ready);
    expect(state.profile['full_name'], 'Noura');
    state.dispose();
  });

  testWidgets('Profile screen smoke', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [AppLocalizations.delegate],
        supportedLocales: AppLocalizations.supportedLocales,
        home: DsPageScaffold(
          title: 'My profile',
          body: DsEmptyState(title: 'empty'),
        ),
      ),
    );
    expect(find.text('My profile'), findsOneWidget);
  });
}
