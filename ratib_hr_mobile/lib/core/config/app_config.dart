/// Application-level constants for Phase 0 foundation.
///
/// No environment secrets. No API wiring in Phase 0.
library;

import 'package:flutter/material.dart';

abstract final class AppConfig {
  static const String appName = 'RATIB HR Mobile';
  static const String appId = 'sa.rateb.hr.mobile';
  static const String phase = '0';

  /// ERP remains the single source of truth (documentation constant only).
  static const String sourceOfTruth = 'RATIB ERP';

  /// Arabic is the default / primary locale (RTL-first).
  static const Locale defaultLocale = Locale('ar');

  static const List<Locale> supportedLocales = [
    Locale('ar'),
    Locale('en'),
  ];

  /// Minimum touch target (Material / accessibility).
  static const double minTouchTarget = 48;

  /// Phase 0: adapters and ERP endpoints are not connected.
  static const bool apisEnabled = false;
}
