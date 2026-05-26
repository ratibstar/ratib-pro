import 'package:flutter/foundation.dart';

/// Pilot / device diagnostics — never enabled in release unless explicitly defined.
abstract final class DiagnosticsConfig {
  static const diagnosticsDefine = bool.fromEnvironment(
    'RATEB_DIAGNOSTICS',
    defaultValue: false,
  );

  static bool get enabled => kDebugMode || diagnosticsDefine;
}
