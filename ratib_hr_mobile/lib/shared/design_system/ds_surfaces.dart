/// Shared modern surfaces — glass tiles, page backdrop, gradient CTAs.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

/// Soft atmospheric backdrop used behind transparent app bars.
class DsPageBackdrop extends StatelessWidget {
  const DsPageBackdrop({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: isDark
              ? const [
                  Color(0xFF0A1A2A),
                  Color(0xFF07111F),
                  Color(0xFF050D18),
                ]
              : [
                  const Color(0xFFE8F4F2),
                  AppColors.surface,
                  const Color(0xFFF7FAFC),
                ],
        ),
      ),
      child: child,
    );
  }
}

/// Frosted / soft elevated content tile.
class DsGlassTile extends StatelessWidget {
  const DsGlassTile({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.margin,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final radius = BorderRadius.circular(18);
    final tile = Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        borderRadius: radius,
        color: isDark
            ? Colors.white.withValues(alpha: 0.05)
            : Colors.white.withValues(alpha: 0.92),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.09)
              : AppColors.outline.withValues(alpha: 0.45),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.navy.withValues(alpha: isDark ? 0.25 : 0.06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );

    final wrapped = margin == null
        ? tile
        : Padding(padding: margin!, child: tile);

    if (onTap == null) return wrapped;
    return Padding(
      padding: margin ?? EdgeInsets.zero,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: radius,
              color: isDark
                  ? Colors.white.withValues(alpha: 0.05)
                  : Colors.white.withValues(alpha: 0.92),
              border: Border.all(
                color: isDark
                    ? Colors.white.withValues(alpha: 0.09)
                    : AppColors.outline.withValues(alpha: 0.45),
              ),
            ),
            child: Padding(padding: padding, child: child),
          ),
        ),
      ),
    );
  }
}

/// Compact colored icon badge for menu / list rows.
class DsIconBadge extends StatelessWidget {
  const DsIconBadge({
    super.key,
    required this.icon,
    this.color = AppColors.auroraTeal,
  });

  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, color: color),
    );
  }
}

/// Full-width gradient primary CTA.
class DsGradientButton extends StatelessWidget {
  const DsGradientButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null;
    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(16),
          child: Ink(
            height: AppSpacing.touchTarget,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              gradient: const LinearGradient(
                colors: [Color(0xFF14B8A6), Color(0xFF0F766E)],
              ),
              boxShadow: [
                BoxShadow(
                  color: AppColors.teal.withValues(alpha: 0.35),
                  blurRadius: 16,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (icon != null) ...[
                  Icon(icon, color: Colors.white),
                  const SizedBox(width: 8),
                ],
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Standard page chrome: backdrop + transparent app bar + body.
class DsPageScaffold extends StatelessWidget {
  const DsPageScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.floatingActionButton,
    this.bottomBar,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? floatingActionButton;
  final Widget? bottomBar;

  @override
  Widget build(BuildContext context) {
    return DsPageBackdrop(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        extendBodyBehindAppBar: false,
        appBar: AppBar(
          title: Text(title),
          actions: actions,
          backgroundColor: Colors.transparent,
          elevation: 0,
          scrolledUnderElevation: 0,
        ),
        floatingActionButton: floatingActionButton,
        bottomNavigationBar: bottomBar,
        body: body,
      ),
    );
  }
}
