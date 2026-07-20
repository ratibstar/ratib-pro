/// Enterprise card surface — soft glass elevation.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_surfaces.dart';

class DsCard extends StatelessWidget {
  const DsCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.margin,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    return DsGlassTile(
      onTap: onTap,
      padding: padding,
      margin: margin ??
          const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.xs,
          ),
      child: child,
    );
  }
}
