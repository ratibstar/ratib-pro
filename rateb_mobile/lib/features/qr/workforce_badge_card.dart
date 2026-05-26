import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import 'workforce_badge_data.dart';

/// Printable-style workforce identity card with QR placeholder.
class WorkforceBadgeCard extends StatelessWidget {
  const WorkforceBadgeCard({
    super.key,
    required this.data,
    this.qrSize = 160,
    this.compact = false,
  });

  final WorkforceBadgeData data;
  final double qrSize;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: compact ? 320 : 360,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            color: AppColors.primary,
            child: Column(
              children: [
                Text(
                  'RATEB',
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: Colors.white,
                    letterSpacing: 4,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Workforce identity credential',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.85),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(20, compact ? 16 : 20, 20, compact ? 16 : 20),
            child: Column(
              children: [
                CircleAvatar(
                  radius: compact ? 32 : 40,
                  backgroundColor: AppColors.lightBackground,
                  backgroundImage:
                      data.photoUrl != null ? NetworkImage(data.photoUrl!) : null,
                  child: data.photoUrl == null
                      ? Icon(
                          Icons.person_rounded,
                          size: compact ? 36 : 44,
                          color: AppColors.primary,
                        )
                      : null,
                ),
                const SizedBox(height: 12),
                Text(
                  data.workerName,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.lightText,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  data.companyName,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.lightMuted,
                  ),
                ),
                const SizedBox(height: 8),
                _StatusChip(label: data.statusLabel),
                const SizedBox(height: 6),
                Text(
                  'ID ${data.workerId}',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: AppColors.lightMuted,
                    letterSpacing: 0.5,
                  ),
                ),
                SizedBox(height: compact ? 14 : 18),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    borderRadius: BorderRadius.circular(12),
                    color: const Color(0xFFFAFBFC),
                  ),
                  child: Column(
                    children: [
                      _QrPlaceholder(size: qrSize),
                      const SizedBox(height: 8),
                      Text(
                        data.qrPayloadHint,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: AppColors.lightMuted,
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
                      color: AppColors.lightMuted,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.35)),
      ),
      child: Text(
        label.toUpperCase(),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.success,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.6,
            ),
      ),
    );
  }
}

class _QrPlaceholder extends StatelessWidget {
  const _QrPlaceholder({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _FakeQrPainter(),
      ),
    );
  }
}

class _FakeQrPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final cell = size.width / 11;
    final paint = Paint()..color = AppColors.primary;
    for (var row = 0; row < 11; row++) {
      for (var col = 0; col < 11; col++) {
        if ((row + col) % 2 == 0 || row < 3 && col < 3 || row < 3 && col > 7 || row > 7 && col < 3) {
          canvas.drawRect(
            Rect.fromLTWH(col * cell, row * cell, cell * 0.92, cell * 0.92),
            paint,
          );
        }
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
