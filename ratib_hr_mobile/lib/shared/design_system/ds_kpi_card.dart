/// KPI / summary card — presentation metrics only (caller supplies strings).
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_surfaces.dart';

class DsKpiCard extends StatelessWidget {
  const DsKpiCard({
    super.key,
    required this.label,
    required this.value,
    this.icon,
    this.onTap,
    this.accent = AppColors.auroraTeal,
  });

  final String label;
  final String value;
  final IconData? icon;
  final VoidCallback? onTap;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return DsGlassTile(
      onTap: onTap,
      margin: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs,
      ),
      child: Row(
        children: [
          if (icon != null) ...[
            DsIconBadge(icon: icon!, color: accent),
            const SizedBox(width: 14),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: AppTypography.kpiValue.copyWith(
                    color: accent,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
