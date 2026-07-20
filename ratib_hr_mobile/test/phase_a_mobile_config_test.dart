import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/mobile_config_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/core/theme/brand_theme_factory.dart';

final class _MemoryCache implements CacheStore {
  final map = <String, String>{};

  @override
  Future<void> clear() async => map.clear();

  @override
  Future<String?> read(String key) async => map[key];

  @override
  Future<void> remove(String key) async {
    map.remove(key);
  }

  @override
  Future<void> write(String key, String value) async {
    map[key] = value;
  }
}

final class _FakePort implements MobileConfigPort {
  _FakePort(this._fn);
  final Future<MobileAppConfiguration> Function() _fn;

  @override
  Future<MobileAppConfiguration> fetchRemote() => _fn();
}

MobileAppConfiguration _cfg({
  required int companyId,
  required String name,
  required String color,
  required Map<String, bool> features,
  bool active = true,
}) {
  return MobileAppConfiguration(
    companyId: companyId,
    companyName: name,
    appName: name,
    logoUrl: 'https://cdn.example/$companyId/logo.png',
    iconUrl: 'https://cdn.example/$companyId/icon.png',
    splashUrl: 'https://cdn.example/$companyId/splash.png',
    themeColorHex: color,
    features: features,
    mobileActive: active,
    role: AppWorkspaceRole.employee,
    fetchedAt: DateTime.utc(2026, 7, 20),
  );
}

void main() {
  test('Company A vs Company B branding differs', () {
    final a = _cfg(
      companyId: 1,
      name: 'Agency A',
      color: '#112233',
      features: {MobileFeatureKey.attendance: true},
    );
    final b = _cfg(
      companyId: 2,
      name: 'Agency B',
      color: '#AABBCC',
      features: {MobileFeatureKey.attendance: false},
    );
    expect(a.displayName, 'Agency A');
    expect(b.displayName, 'Agency B');
    expect(a.themeColorHex, isNot(b.themeColorHex));
    expect(BrandThemeFactory.parseColor(a.themeColorHex), isNotNull);
    expect(
      BrandThemeFactory.parseColor(a.themeColorHex),
      isNot(BrandThemeFactory.parseColor(b.themeColorHex)),
    );
  });

  test('Disabled company fails closed', () async {
    final cache = _MemoryCache();
    final svc = MobileConfigurationService(
      port: _FakePort(
        () async => throw const AppFailure(
          code: 'mobile_disabled',
          message: 'Mobile app is not enabled for this company',
        ),
      ),
      cache: cache,
    );

    await expectLater(svc.refreshAfterLogin(), throwsA(isA<AppFailure>()));
    expect(svc.hasConfig, isFalse);
    expect(cache.map.containsKey(MobileConfigurationService.cacheKey), isFalse);
  });

  test('Feature flags drive shell tabs and more items', () {
    final cfg = _cfg(
      companyId: 9,
      name: 'Flags Co',
      color: '#0D9488',
      features: {
        MobileFeatureKey.attendance: true,
        MobileFeatureKey.leave: false,
        MobileFeatureKey.documents: true,
        MobileFeatureKey.payroll: false,
        MobileFeatureKey.notifications: true,
        MobileFeatureKey.profile: true,
        MobileFeatureKey.requests: false,
      },
    );
    final tabs = ShellNavPolicy.visibleTabs(cfg);
    expect(tabs, contains(ShellTab.home));
    expect(tabs, contains(ShellTab.attendance));
    expect(tabs, isNot(contains(ShellTab.leave)));
    expect(tabs, isNot(contains(ShellTab.requests)));
    expect(tabs, contains(ShellTab.more));

    final more = ShellNavPolicy.visibleMoreItems(cfg);
    expect(more, contains(ShellMoreItem.documents));
    expect(more, isNot(contains(ShellMoreItem.payslips)));
    expect(more, contains(ShellMoreItem.notifications));
    expect(more, contains(ShellMoreItem.profile));

    expect(ShellNavPolicy.isRouteAllowed(cfg, '/attendance'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/leave'), isFalse);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/more/payslips'), isFalse);
  });

  test('Cache replaced on login; offline uses cache', () async {
    final cache = _MemoryCache();
    var call = 0;
    late MobileAppConfiguration lastRemote;

    final svc = MobileConfigurationService(
      port: _FakePort(() async {
        call++;
        if (call == 1) {
          lastRemote = _cfg(
            companyId: 10,
            name: 'First',
            color: '#111111',
            features: {MobileFeatureKey.attendance: true},
          );
          return lastRemote;
        }
        if (call == 2) {
          lastRemote = _cfg(
            companyId: 10,
            name: 'Second',
            color: '#222222',
            features: {MobileFeatureKey.attendance: true},
          );
          return lastRemote;
        }
        throw const AppFailure(code: 'network', message: 'offline');
      }),
      cache: cache,
    );

    await svc.refreshAfterLogin();
    expect(svc.current?.displayName, 'First');
    expect(svc.current?.fromCache, isFalse);

    await svc.refreshAfterLogin();
    expect(svc.current?.displayName, 'Second');
    expect(cache.map[MobileConfigurationService.cacheKey], contains('Second'));

    await svc.clearSession();
    expect(svc.current, isNull);

    await svc.refreshAfterLogin(); // call 3 → network fail → cache
    expect(svc.current?.displayName, 'Second');
    expect(svc.current?.fromCache, isTrue);
  });

  test('ERP body parser maps /api/mobile/config payload', () {
    final cfg = MobileAppConfiguration.fromErpBody(
      {
        'success': true,
        'company_id': 55,
        'company_name': 'Tenant X',
        'app_name': 'X Workforce',
        'logo': '/media/x-logo.png',
        'icon': '/media/x-icon.png',
        'splash': '/media/x-splash.png',
        'theme_color': '#0055AA',
        'features': {
          'attendance': true,
          'leave': true,
          'payroll': false,
        },
      },
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
    expect(cfg.companyId, 55);
    expect(cfg.displayName, 'X Workforce');
    expect(cfg.isFeatureEnabled(MobileFeatureKey.attendance), isTrue);
    expect(cfg.isFeatureEnabled(MobileFeatureKey.payroll), isFalse);
    expect(cfg.role, AppWorkspaceRole.employee);
  });
}
