/// Primary / Secondary / Outline buttons — large touch targets.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_surfaces.dart';

class DsPrimaryButton extends StatelessWidget {
  const DsPrimaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.expanded = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final button = DsGradientButton(
      label: label,
      onPressed: onPressed,
      icon: icon,
    );
    return expanded ? button : Align(child: button);
  }
}

class DsSecondaryButton extends StatelessWidget {
  const DsSecondaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.expanded = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final child = icon == null
        ? Text(label)
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: AppIcons.sizeSm),
              const SizedBox(width: AppSpacing.xs),
              Text(label),
            ],
          );
    final button = FilledButton.tonal(onPressed: onPressed, child: child);
    return expanded ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class DsOutlineButton extends StatelessWidget {
  const DsOutlineButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.expanded = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final child = icon == null
        ? Text(label)
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: AppIcons.sizeSm),
              const SizedBox(width: AppSpacing.xs),
              Text(label),
            ],
          );
    final button = OutlinedButton(onPressed: onPressed, child: child);
    return expanded ? SizedBox(width: double.infinity, child: button) : button;
  }
}
