import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import 'workforce_badge_data.dart';

/// Printable-style workforce identity card — dark enterprise theme.
class WorkforceBadgeCard extends StatelessWidget {
  const WorkforceBadgeCard({
    super.key,
    required this.data,
    this.qrSize = 160,
    this.compact = false,
    this.darkTheme = true,
  });

  final WorkforceBadgeData data;
  final double qrSize;
  final bool compact;
  final bool darkTheme;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final surface = darkTheme ? AppColors.darkSurface : Colors.white;
    final textPrimary = darkTheme ? AppColors.darkText : AppColors.lightText;
    final textMuted = darkTheme ? AppColors.darkMuted : AppColors.lightMuted;
    final borderColor = darkTheme
        ? AppColors.accent.withValues(alpha: 0.25)
        : const Color(0xFFE2E8F0);

    return Semantics(
      label: 'RATEB workforce identity badge for ${data.workerName}',
      child: Container(
        width: compact ? 320 : 360,
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: borderColor, width: 1.2),
          boxShadow: [
            BoxShadow(
              color: AppColors.accent.withValues(alpha: darkTheme ? 0.12 : 0.08),
              blurRadius: 28,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.primary,
                    AppColors.primaryLight,
                  ],
                ),
              ),
              child: Column(
                children: [
                  Text(
                    'RATEB',
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: Colors.white,
                      letterSpacing: 5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Workforce identity credential',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.88),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(
                20,
                compact ? 16 : 20,
                20,
                compact ? 16 : 20,
              ),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: compact ? 32 : 40,
                    backgroundColor: AppColors.primary.withValues(alpha: 0.2),
                    backgroundImage: data.photoUrl != null
                        ? NetworkImage(data.photoUrl!)
                        : null,
                    child: data.photoUrl == null
                        ? Icon(
                            Icons.person_rounded,
                            size: compact ? 36 : 44,
                            color: AppColors.accent,
                          )
                        : null,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    data.workerName,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: textPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.roleLabel,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.accent,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.companyName,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: textMuted,
                    ),
                  ),
                  const SizedBox(height: 10),
                  _StatusChip(label: data.statusLabel),
                  const SizedBox(height: 8),
                  Text(
                    'ID ${data.workerId}',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: textMuted,
                      letterSpacing: 0.6,
                    ),
                  ),
                  SizedBox(height: compact ? 14 : 18),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: AppColors.accent.withValues(alpha: 0.35),
                      ),
                      borderRadius: BorderRadius.circular(14),
                      color: darkTheme
                          ? AppColors.darkBackground
                          : const Color(0xFFFAFBFC),
                    ),
                    child: Column(
                      children: [
                        _QrPlaceholder(size: qrSize, dark: darkTheme),
                        const SizedBox(height: 10),
                        Text(
                          data.qrPayloadHint,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: textMuted,
                            fontFamily: 'monospace',
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (!compact) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Scan to sign in to RATEB Mobile',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: textMuted,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.4)),
      ),
      child: Text(
        label.toUpperCase(),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.success,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
            ),
      ),
    );
  }
}

class _QrPlaceholder extends StatelessWidget {
  const _QrPlaceholder({required this.size, required this.dark});

  final double size;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _FakeQrPainter(
          foreground: dark ? AppColors.accent : AppColors.primary,
        ),
      ),
    );
  }
}

class _FakeQrPainter extends CustomPainter {
  _FakeQrPainter({required this.foreground});

  final Color foreground;

  @override
  void paint(Canvas canvas, Size size) {
    final cell = size.width / 11;
    final paint = Paint()..color = foreground;
    for (var row = 0; row < 11; row++) {
      for (var col = 0; col < 11; col++) {
        if ((row + col) % 2 == 0 ||
            row < 3 && col < 3 ||
            row < 3 && col > 7 ||
            row > 7 && col < 3) {
          canvas.drawRect(
            Rect.fromLTWH(col * cell, row * cell, cell * 0.92, cell * 0.92),
            paint,
          );
        }
      }
    }
  }

  @override
  bool shouldRepaint(covariant _FakeQrPainter oldDelegate) =>
      oldDelegate.foreground != foreground;
}
