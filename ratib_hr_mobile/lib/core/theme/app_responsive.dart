/// Responsive breakpoints — rules only, no desktop layout chrome.
///
/// Phone / tablet / foldable / wide. Features choose layout via these helpers.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/app_spacing.dart';

abstract final class AppBreakpoints {
  /// Compact phones.
  static const double phone = 600;

  /// Large phones / small fold open.
  static const double phoneLarge = 840;

  /// Tablets.
  static const double tablet = 1200;

  /// Wide / desktop-ready canvas (no desktop nav chrome in ESS).
  static const double wide = 1600;
}

enum AppCanvasSize { phone, phoneLarge, tablet, wide }

abstract final class AppResponsive {
  static AppCanvasSize canvasOf(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    if (w >= AppBreakpoints.wide) return AppCanvasSize.wide;
    if (w >= AppBreakpoints.tablet) return AppCanvasSize.tablet;
    if (w >= AppBreakpoints.phone) return AppCanvasSize.phoneLarge;
    return AppCanvasSize.phone;
  }

  static bool isPhone(BuildContext context) =>
      canvasOf(context) == AppCanvasSize.phone;

  static bool isTabletOrWider(BuildContext context) {
    final c = canvasOf(context);
    return c == AppCanvasSize.tablet || c == AppCanvasSize.wide;
  }

  static double horizontalPadding(BuildContext context) {
    return isTabletOrWider(context)
        ? AppSpacing.screenPaddingWide
        : AppSpacing.screenPadding;
  }

  /// Max content width so tablet/wide stay readable (still mobile ESS, not desktop ERP).
  static double maxContentWidth(BuildContext context) {
    switch (canvasOf(context)) {
      case AppCanvasSize.phone:
        return double.infinity;
      case AppCanvasSize.phoneLarge:
        return 560;
      case AppCanvasSize.tablet:
        return 720;
      case AppCanvasSize.wide:
        return 840;
    }
  }

  /// Suggested columns for card grids (presentation only).
  static int cardColumns(BuildContext context) {
    switch (canvasOf(context)) {
      case AppCanvasSize.phone:
        return 1;
      case AppCanvasSize.phoneLarge:
        return 2;
      case AppCanvasSize.tablet:
      case AppCanvasSize.wide:
        return 3;
    }
  }
}
