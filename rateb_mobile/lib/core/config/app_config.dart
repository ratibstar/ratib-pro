/// Application configuration — override via `--dart-define` at build time.
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'RATEB_API_BASE_URL',
    defaultValue: 'https://out.ratib.sa/api',
  );

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);

  static const String appName = 'RATEB';
  static const String appTagline = 'Workforce Management Portal';

  /// Keep aligned with pubspec.yaml version.
  static const String appVersion = '1.0.0+1';
}
