import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

/// Dark mask with transparent scan window and animated scan line.
class QrScanOverlay extends StatelessWidget {
  const QrScanOverlay({
    super.key,
    required this.scanLineProgress,
    this.scanWindowSize = 260,
    this.showHint = true,
  });

  final double scanLineProgress;
  final double scanWindowSize;
  final bool showHint;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final shortest =
            constraints.maxWidth < constraints.maxHeight
                ? constraints.maxWidth
                : constraints.maxHeight;
        final window =
            scanWindowSize.clamp(220.0, shortest * 0.72).toDouble();
        final top = (constraints.maxHeight - window) / 2 - 32;

        return Stack(
          fit: StackFit.expand,
          children: [
            CustomPaint(
              painter: _ScanMaskPainter(
                windowRect: Rect.fromLTWH(
                  (constraints.maxWidth - window) / 2,
                  top,
                  window,
                  window,
                ),
              ),
            ),
            Positioned(
              left: (constraints.maxWidth - window) / 2,
              top: top,
              width: window,
              height: window,
              child: _ScanFrameBorder(progress: scanLineProgress),
            ),
            if (showHint)
              Positioned(
                left: 24,
                right: 24,
                bottom: 120,
                child: Column(
                  children: [
                    Text(
                      'Align QR inside frame',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Hold steady — sign-in is automatic',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.white.withValues(alpha: 0.75),
                          ),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

class _ScanFrameBorder extends StatelessWidget {
  const _ScanFrameBorder({required this.progress});

  final double progress;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _FramePainter(progress: progress),
      child: const SizedBox.expand(),
    );
  }
}

class _ScanMaskPainter extends CustomPainter {
  _ScanMaskPainter({required this.windowRect});

  final Rect windowRect;

  @override
  void paint(Canvas canvas, Size size) {
    final overlay = Path()
      ..addRect(Rect.fromLTWH(0, 0, size.width, size.height));
    final hole = Path()
      ..addRRect(RRect.fromRectAndRadius(windowRect, const Radius.circular(20)));
    final mask = Path.combine(PathOperation.difference, overlay, hole);

    canvas.drawPath(
      mask,
      Paint()..color = Colors.black.withValues(alpha: 0.62),
    );
  }

  @override
  bool shouldRepaint(covariant _ScanMaskPainter oldDelegate) =>
      oldDelegate.windowRect != windowRect;
}

class _FramePainter extends CustomPainter {
  _FramePainter({required this.progress});

  final double progress;

  static const _corner = 28.0;
  static const _stroke = 3.0;

  @override
  void paint(Canvas canvas, Size size) {
    final cornerPaint = Paint()
      ..color = AppColors.accent
      ..strokeWidth = _stroke
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    _drawCorner(canvas, const Offset(0, 0), true, true, cornerPaint);
    _drawCorner(canvas, Offset(size.width, 0), false, true, cornerPaint);
    _drawCorner(canvas, Offset(0, size.height), true, false, cornerPaint);
    _drawCorner(
      canvas,
      Offset(size.width, size.height),
      false,
      false,
      cornerPaint,
    );

    final lineY = 12 + (size.height - 24) * progress;
    final linePaint = Paint()
      ..shader = LinearGradient(
        colors: [
          AppColors.accent.withValues(alpha: 0),
          AppColors.accent,
          AppColors.accent.withValues(alpha: 0),
        ],
      ).createShader(Rect.fromLTWH(16, lineY, size.width - 32, 2))
      ..strokeWidth = 2;

    canvas.drawLine(
      Offset(16, lineY),
      Offset(size.width - 16, lineY),
      linePaint,
    );
  }

  void _drawCorner(
    Canvas canvas,
    Offset origin,
    bool left,
    bool top,
    Paint paint,
  ) {
    final dx = left ? 1.0 : -1.0;
    final dy = top ? 1.0 : -1.0;
    canvas.drawLine(origin, origin + Offset(_corner * dx, 0), paint);
    canvas.drawLine(origin, origin + Offset(0, _corner * dy), paint);
  }

  @override
  bool shouldRepaint(covariant _FramePainter oldDelegate) =>
      oldDelegate.progress != progress;
}

/// Brief success checkmark overlay after verified scan.
class QrScanSuccessOverlay extends StatelessWidget {
  const QrScanSuccessOverlay({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black.withValues(alpha: 0.55),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: AppColors.success.withValues(alpha: 0.15),
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.success, width: 2),
            ),
            child: const Icon(
              Icons.check_rounded,
              color: AppColors.success,
              size: 48,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Identity verified',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }
}
