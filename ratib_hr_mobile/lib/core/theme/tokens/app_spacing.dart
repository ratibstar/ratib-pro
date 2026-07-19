/// Spacing scale (4pt base).
library;

abstract final class AppSpacing {
  static const double xxs = 4;
  static const double xs = 8;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 24;
  static const double xxl = 32;
  static const double xxxl = 40;
  static const double huge = 48;

  /// Screen horizontal inset (phone).
  static const double screenPadding = md;

  /// Screen horizontal inset (tablet+).
  static const double screenPaddingWide = xl;

  /// Gap between cards in a list.
  static const double cardGap = xs;

  /// Minimum touch target.
  static const double touchTarget = 48;
}
