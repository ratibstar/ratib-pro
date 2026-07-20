/// Theme from tenant [MobileAppConfiguration] — no hardcoded company colors.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';
import 'package:ratib_hr_mobile/core/theme/app_theme.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

abstract final class BrandThemeFactory {
  static ThemeData lightFrom(MobileAppConfiguration? config) {
    final seed = parseColor(config?.themeColorHex) ?? AppColors.teal;
    return AppTheme.lightFromSeed(seed);
  }

  static ThemeData darkFrom(MobileAppConfiguration? config) {
    final seed = parseColor(config?.themeColorHex) ?? AppColors.teal;
    return AppTheme.darkFromSeed(seed);
  }

  static Color? parseColor(String? hex) {
    if (hex == null) return null;
    var s = hex.trim();
    if (s.isEmpty) return null;
    if (s.startsWith('#')) s = s.substring(1);
    if (s.length == 3) {
      s = s.split('').map((c) => '$c$c').join();
    }
    if (s.length == 6) s = 'FF$s';
    if (s.length != 8) return null;
    final value = int.tryParse(s, radix: 16);
    if (value == null) return null;
    return Color(value);
  }
}
