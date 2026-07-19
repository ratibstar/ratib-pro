/// Material 3 enterprise theme — Arabic RTL-first, no desktop chrome.
///
/// Color direction: deep navy + teal accent (enterprise SaaS).
/// Avoids generic purple gradients and cream/terracotta AI defaults.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';

abstract final class AppTheme {
  static const Color _navy = Color(0xFF0B1F33);
  static const Color _teal = Color(0xFF0D9488);
  static const Color _surface = Color(0xFFF4F7FA);
  static const Color _card = Color(0xFFFFFFFF);
  static const Color _danger = Color(0xFFB42318);

  static ThemeData get light {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: _teal,
      primary: _navy,
      secondary: _teal,
      surface: _surface,
      error: _danger,
      brightness: Brightness.light,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: _surface,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      appBarTheme: const AppBarTheme(
        centerTitle: true,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        backgroundColor: _card,
        foregroundColor: _navy,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: _card,
        indicatorColor: _teal.withOpacity(0.15),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, AppConfig.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, AppConfig.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: _card,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
      textTheme: const TextTheme(
        headlineMedium: TextStyle(
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
        titleLarge: TextStyle(fontWeight: FontWeight.w600),
        bodyLarge: TextStyle(height: 1.45),
        bodyMedium: TextStyle(height: 1.4),
      ),
    );
  }

  static ThemeData get dark {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: _teal,
      brightness: Brightness.dark,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      materialTapTargetSize: MaterialTapTargetSize.padded,
    );
  }
}
