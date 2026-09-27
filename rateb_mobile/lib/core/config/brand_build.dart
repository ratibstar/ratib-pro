/// Company-branded build values. Empty in the shared build; scripts/build-branded-app.ps1 writes
/// them into this file for a company build (dart-defines are dropped by flavor builds on Windows).
library;

abstract final class BrandBuild {
  /// Company update channel: `downloads/company/<key>.json`.
  static const String key = '';

  /// Company activation code, applied on first launch.
  static const String activationCode = '';
}
