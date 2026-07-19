/// Status badge — semantic colors, caller supplies label.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

enum DsBadgeTone { neutral, success, warning, error, info }

class DsStatusBadge extends StatelessWidget {
  const DsStatusBadge({
    super.key,
    required this.label,
    this.tone = DsBadgeTone.neutral,
  });

  final String label;
  final DsBadgeTone tone;

  Color get _bg {
    switch (tone) {
      case DsBadgeTone.neutral:
        return AppColors.badgeNeutral.withOpacity(0.12);
      case DsBadgeTone.success:
        return AppColors.successContainer;
      case DsBadgeTone.warning:
        return AppColors.warningContainer;
      case DsBadgeTone.error:
        return AppColors.errorContainer;
      case DsBadgeTone.info:
        return AppColors.infoContainer;
    }
  }

  Color get _fg {
    switch (tone) {
      case DsBadgeTone.neutral:
        return AppColors.badgeNeutral;
      case DsBadgeTone.success:
        return AppColors.success;
      case DsBadgeTone.warning:
        return AppColors.warning;
      case DsBadgeTone.error:
        return AppColors.error;
      case DsBadgeTone.info:
        return AppColors.info;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: AppSpacing.xxs,
      ),
      decoration: BoxDecoration(
        color: _bg,
        borderRadius: BorderRadius.circular(AppRadius.badge),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: _fg,
              fontWeight: FontWeight.w600,
            ),
      ),
    );
  }
}
