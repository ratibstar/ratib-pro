import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_dashboard_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_inquiry_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_notification_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_payment_methods_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_ratings_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_settings_adapter.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/core/theme/brand_theme_factory.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

MobileAppConfiguration _cfg(Map<String, bool> features) {
  return MobileAppConfiguration(
    companyId: 42,
    companyName: 'Tenant C',
    appName: 'C ESS',
    logoUrl: 'https://cdn.example/42/logo.png',
    iconUrl: 'https://cdn.example/42/icon.png',
    splashUrl: 'https://cdn.example/42/splash.png',
    themeColorHex: '#0F766E',
    features: features,
    mobileActive: true,
    role: AppWorkspaceRole.employee,
    fetchedAt: DateTime.utc(2026, 7, 20),
  );
}

void main() {
  test('Phase marker advanced past C', () {
    expect(['C', 'D', 'E'].contains(AppConfig.phase), isTrue);
  });

  test('Phase C feature flags gate More items and routes', () {
    final cfg = _cfg({
      MobileFeatureKey.ratings: true,
      MobileFeatureKey.inquiries: false,
      MobileFeatureKey.payments: false,
      MobileFeatureKey.settings: true,
      MobileFeatureKey.notifications: true,
      MobileFeatureKey.documents: false,
      MobileFeatureKey.payroll: false,
      MobileFeatureKey.profile: true,
      MobileFeatureKey.approvals: false,
      MobileFeatureKey.requests: true,
      MobileFeatureKey.attendance: true,
      MobileFeatureKey.leave: true,
    });

    final more = ShellNavPolicy.visibleMoreItems(cfg);
    expect(more, contains(ShellMoreItem.ratings));
    expect(more, contains(ShellMoreItem.settings));
    expect(more, contains(ShellMoreItem.notifications));
    expect(more, isNot(contains(ShellMoreItem.inquiries)));
    expect(more, isNot(contains(ShellMoreItem.payments)));

    expect(ShellNavPolicy.isRouteAllowed(cfg, '/more/ratings'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/more/inquiries'), isFalse);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/more/payments'), isFalse);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/more/settings'), isTrue);
    expect(ShellNavPolicy.isRouteAllowed(cfg, '/requests'), isTrue);
  });

  test('Tenant branding remains config-driven (no hardcoded brand)', () {
    final a = _cfg({MobileFeatureKey.settings: true});
    final b = MobileAppConfiguration(
      companyId: 99,
      companyName: 'Other Co',
      appName: 'Other App',
      logoUrl: 'https://cdn.example/99/logo.png',
      iconUrl: '',
      splashUrl: '',
      themeColorHex: '#AA3300',
      features: {MobileFeatureKey.settings: true},
      mobileActive: true,
      role: AppWorkspaceRole.employee,
      fetchedAt: DateTime.utc(2026, 7, 20),
    );
    expect(a.displayName, isNot(b.displayName));
    expect(
      BrandThemeFactory.parseColor(a.themeColorHex),
      isNot(BrandThemeFactory.parseColor(b.themeColorHex)),
    );
  });

  test('Role employee tabs respect requests flag', () {
    final on = _cfg({
      MobileFeatureKey.attendance: true,
      MobileFeatureKey.leave: true,
      MobileFeatureKey.requests: true,
    });
    final off = _cfg({
      MobileFeatureKey.attendance: true,
      MobileFeatureKey.leave: true,
      MobileFeatureKey.requests: false,
    });
    expect(ShellNavPolicy.visibleTabs(on), contains(ShellTab.requests));
    expect(ShellNavPolicy.visibleTabs(off), isNot(contains(ShellTab.requests)));
  });

  test('ERP adapter paths are ESS contracts', () {
    expect(ErpDashboardAdapter.path, '/api/v1/hr/dashboard');
    expect(ErpRatingsAdapter.path, '/api/v1/hr/ratings');
    expect(ErpPaymentMethodsAdapter.path, '/api/v1/hr/payment-methods');
    expect(ErpSettingsAdapter.changePasswordPath,
        '/api/v1/hr/settings/change-password');
    expect(ErpNotificationAdapter.listPath, '/api/v1/hr/notifications');
    expect(ErpInquiryAdapter.path, '/api/v1/hr/requests');
  });

  test('Localization has Phase C keys in AR and EN', () {
    final ar = AppLocalizations(const Locale('ar'));
    final en = AppLocalizations(const Locale('en'));
    expect(ar.navSettings, isNotEmpty);
    expect(en.navSettings, isNotEmpty);
    expect(ar.navRatings, isNot(en.navRatings));
    expect(ar.homePendingRequests, isNotEmpty);
    expect(en.notifMarkAllRead, isNotEmpty);
  });
}
