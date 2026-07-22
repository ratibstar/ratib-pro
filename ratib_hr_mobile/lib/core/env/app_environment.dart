/// Environment contract — configuration only.
///
/// Implementations (Phase 1+) MUST load [erpBaseUrl] from external config
/// (`--dart-define`, CI secrets, or secure remote config). Never hardcode hosts.
///
/// No ERP connection in this file.
library;

import 'package:ratib_hr_mobile/core/env/app_flavor.dart';

/// Read-only environment surface for the ESS presentation app.
abstract interface class AppEnvironment {
  /// Active deployment flavor.
  AppFlavor get flavor;

  /// RATEB ERP HTTP origin. Source: configuration only.
  String get erpBaseUrl;

  /// When false, adapters must not perform network I/O (Phase 0/0.6 default).
  bool get apisEnabled;

  /// Optional human-readable label for diagnostics (e.g. build channel).
  String get channelLabel;
}
