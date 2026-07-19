/// Material 3 theme built from design tokens.
///
/// RTL-first via MaterialApp locale. Dark mode ready.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

abstract final class AppTheme {
  static ThemeData get light => _build(
        brightness: Brightness.light,
        scheme: ColorScheme.fromSeed(
          seedColor: AppColors.teal,
          primary: AppColors.navy,
          secondary: AppColors.teal,
          surface: AppColors.surface,
          error: AppColors.error,
          brightness: Brightness.light,
        ),
        scaffold: AppColors.surface,
        card: AppColors.surfaceElevated,
        onCard: AppColors.textPrimary,
      );

  static ThemeData get dark => _build(
        brightness: Brightness.dark,
        scheme: ColorScheme.fromSeed(
          seedColor: AppColors.teal,
          primary: AppColors.teal,
          secondary: AppColors.tealDark,
          surface: AppColors.surfaceDark,
          error: AppColors.error,
          brightness: Brightness.dark,
        ),
        scaffold: AppColors.surfaceDark,
        card: AppColors.surfaceElevatedDark,
        onCard: AppColors.textPrimaryDark,
      );

  static ThemeData _build({
    required Brightness brightness,
    required ColorScheme scheme,
    required Color scaffold,
    required Color card,
    required Color onCard,
  }) {
    final text = brightness == Brightness.light
        ? AppTypography.lightTextTheme(scheme)
        : AppTypography.darkTextTheme(scheme);

    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.button),
    );

    final outline = brightness == Brightness.light
        ? AppColors.outline.withOpacity(0.6)
        : AppColors.outlineDark;

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: scaffold,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      textTheme: text,
      primaryTextTheme: text,
      appBarTheme: AppBarTheme(
        centerTitle: true,
        elevation: AppElevation.appBar,
        scrolledUnderElevation: 0.5,
        backgroundColor: card,
        foregroundColor: onCard,
        titleTextStyle: text.titleLarge,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        elevation: AppElevation.bottomNav,
        backgroundColor: card,
        indicatorColor: AppColors.teal.withOpacity(0.15),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(88, AppSpacing.touchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          shape: shape,
          textStyle: text.labelLarge,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(88, AppSpacing.touchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          shape: shape,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(88, AppSpacing.touchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          shape: shape,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(64, AppSpacing.touchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.sm,
          ),
        ),
      ),
      cardColor: card,
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: card,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
      ),
      dividerColor: outline,
      bottomSheetTheme: const BottomSheetThemeData(
        showDragHandle: true,
        elevation: AppElevation.sheet,
      ),
    );
  }
}
