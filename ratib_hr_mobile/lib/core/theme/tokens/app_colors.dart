/// Design color tokens — enterprise navy + teal.
///
/// Avoid purple-on-white and cream/terracotta AI defaults.
library;

import 'package:flutter/material.dart';

abstract final class AppColors {
  // Brand
  static const Color navy = Color(0xFF0B1F33);
  static const Color navySoft = Color(0xFF163A56);
  static const Color teal = Color(0xFF0D9488);
  static const Color tealDark = Color(0xFF0F766E);

  // Neutrals (light)
  static const Color surface = Color(0xFFF4F7FA);
  static const Color surfaceElevated = Color(0xFFFFFFFF);
  static const Color outline = Color(0xFFD0D7DE);
  static const Color textPrimary = Color(0xFF0B1F33);
  static const Color textSecondary = Color(0xFF5B6B7C);
  static const Color textInverse = Color(0xFFFFFFFF);

  // Neutrals (dark)
  static const Color surfaceDark = Color(0xFF0F172A);
  static const Color surfaceElevatedDark = Color(0xFF1E293B);
  static const Color outlineDark = Color(0xFF334155);
  static const Color textPrimaryDark = Color(0xFFF1F5F9);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  // Semantic
  static const Color success = Color(0xFF027A48);
  static const Color successContainer = Color(0xFFD1FADF);
  static const Color warning = Color(0xFFB54708);
  static const Color warningContainer = Color(0xFFFEF0C7);
  static const Color error = Color(0xFFB42318);
  static const Color errorContainer = Color(0xFFFEE4E2);
  static const Color info = Color(0xFF026AA2);
  static const Color infoContainer = Color(0xFFD1E9FF);

  // High-contrast accents for badges
  static const Color badgeNeutral = Color(0xFF667085);
  static const Color badgeSuccess = success;
  static const Color badgeWarning = warning;
  static const Color badgeError = error;
  static const Color badgeInfo = info;
}
