/// Shadow tokens for soft enterprise surfaces.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/app_colors.dart';

abstract final class AppShadows {
  static List<BoxShadow> get none => const [];

  static List<BoxShadow> get soft => [
        BoxShadow(
          color: AppColors.navy.withOpacity(0.06),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ];

  static List<BoxShadow> get medium => [
        BoxShadow(
          color: AppColors.navy.withOpacity(0.10),
          blurRadius: 20,
          offset: const Offset(0, 8),
        ),
      ];

  static List<BoxShadow> get sheet => [
        BoxShadow(
          color: AppColors.navy.withOpacity(0.14),
          blurRadius: 28,
          offset: const Offset(0, -4),
        ),
      ];
}
