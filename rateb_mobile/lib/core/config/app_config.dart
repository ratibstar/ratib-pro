import 'company_activation.dart';

/// Application configuration — override via `--dart-define` at build time.
/// A company activation code (see [CompanyActivation]) overrides the servers at runtime.
class AppConfig {
  AppConfig._();

  static const String _apiBaseUrl = String.fromEnvironment(
    'RATEB_API_BASE_URL',
    defaultValue: 'https://rateb.sa/api',
  );

  static String get apiBaseUrl => CompanyActivation.apiBaseUrl ?? _apiBaseUrl;

  /// ERP that publishes this app's offers and content pages (Mobile Apps panel).
  /// Empty = same host as [apiBaseUrl] under `/rateb-erp/public`.
  static const String _erpBaseUrlOverride = String.fromEnvironment(
    'RATEB_ERP_BASE_URL',
  );

  /// Company slug this build was made for (empty = shared platform build).
  static const String _companySlug = String.fromEnvironment(
    'RATEB_COMPANY_SLUG',
  );

  static String get companySlug =>
      CompanyActivation.isActive ? CompanyActivation.slug : _companySlug;

  /// Control Panel agency (control_agencies.id) whose database staff log in to.
  /// 0 = not configured.
  static const int _agencyId = int.fromEnvironment('RATEB_AGENCY_ID');

  static int get agencyId =>
      CompanyActivation.isActive ? CompanyActivation.agencyId : _agencyId;

  static String get erpBaseUrl {
    final activated = CompanyActivation.erpBaseUrl;
    if (CompanyActivation.isActive && activated != null) return activated;
    if (_erpBaseUrlOverride.trim().isNotEmpty) {
      return _erpBaseUrlOverride.trim().replaceAll(RegExp(r'/+$'), '');
    }
    final api = Uri.tryParse(apiBaseUrl);
    if (api == null || api.host.isEmpty) {
      return 'https://rateb.sa/rateb-erp/public';
    }
    return '${api.scheme}://${api.authority}/rateb-erp/public';
  }

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);

  static const String appName = 'RATEB';
  static const String appTagline = 'Workforce Management Portal';

  /// Keep aligned with pubspec.yaml version.
  static const String appVersion = '1.0.3+4';

  /// Keep aligned with the pubspec.yaml build number (after "+").
  static const int buildNumber = 4;
}
