/// Application configuration — override via `--dart-define` at build time.
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'RATEB_API_BASE_URL',
    defaultValue: 'https://rateb.sa/api',
  );

  /// ERP that publishes this app's offers and content pages (Mobile Apps panel).
  /// Empty = same host as [apiBaseUrl] under `/rateb-erp/public`.
  static const String _erpBaseUrlOverride = String.fromEnvironment(
    'RATEB_ERP_BASE_URL',
  );

  /// Company slug this build was made for (empty = shared platform build).
  static const String companySlug = String.fromEnvironment(
    'RATEB_COMPANY_SLUG',
  );

  static String get erpBaseUrl {
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
  static const String appVersion = '1.0.0+1';
}
