import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

/// Dark mask with transparent scan window, blue glow frame, and animated scan beam.
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
        final shortest = constraints.maxWidth < constraints.maxHeight
            ? constraints.maxWidth
            : constraints.maxHeight;
        final window =
            scanWindowSize.clamp(220.0, shortest * 0.72).toDouble();
        final top = (constraints.maxHeight - window) / 2 - 32;
        final windowRect = Rect.fromLTWH(
          (constraints.maxWidth - window) / 2,
          top,
          window,
          window,
        );

        return Semantics(
          label: 'Scan workforce identity QR code inside the frame',
          child: Stack(
            fit: StackFit.expand,
            children: [
              CustomPaint(
                painter: _ScanMaskPainter(windowRect: windowRect),
              ),
              Positioned.fromRect(
                rect: windowRect.inflate(10),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.accent.withValues(alpha: 0.35),
                        blurRadius: 28,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                ),
              ),
              Positioned.fromRect(
                rect: windowRect,
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
          ),
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
      Paint()..color = Colors.black.withValues(alpha: 0.68),
    );
  }

  @override
  bool shouldRepaint(covariant _ScanMaskPainter oldDelegate) =>
      oldDelegate.windowRect != windowRect;
}

class _FramePainter extends CustomPainter {
  _FramePainter({required this.progress});

  final double progress;

  static const _corner = 32.0;
  static const _stroke = 3.5;

  @override
  void paint(Canvas canvas, Size size) {
    final glow = Paint()
      ..color = AppColors.accent.withValues(alpha: 0.45)
      ..strokeWidth = _stroke + 4
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6);

    final cornerPaint = Paint()
      ..color = AppColors.accent
      ..strokeWidth = _stroke
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    for (final paint in [glow, cornerPaint]) {
      _drawCorner(canvas, Offset.zero, true, true, paint);
      _drawCorner(canvas, Offset(size.width, 0), false, true, paint);
      _drawCorner(canvas, Offset(0, size.height), true, false, paint);
      _drawCorner(canvas, Offset(size.width, size.height), false, false, paint);
    }

    final lineY = 12 + (size.height - 24) * progress;
    final beamRect = Rect.fromLTWH(12, lineY - 1, size.width - 24, 3);
    final linePaint = Paint()
      ..shader = LinearGradient(
        colors: [
          AppColors.accent.withValues(alpha: 0),
          AppColors.accent,
          Colors.white.withValues(alpha: 0.9),
          AppColors.accent,
          AppColors.accent.withValues(alpha: 0),
        ],
        stops: const [0, 0.35, 0.5, 0.65, 1],
      ).createShader(beamRect);

    canvas.drawRRect(
      RRect.fromRectAndRadius(beamRect, const Radius.circular(2)),
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

/// Success checkmark with fade + scale animation.
class QrScanSuccessOverlay extends StatefulWidget {
  const QrScanSuccessOverlay({super.key});

  @override
  State<QrScanSuccessOverlay> createState() => _QrScanSuccessOverlayState();
}

class _QrScanSuccessOverlayState extends State<QrScanSuccessOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    );
    _scale = CurvedAnimation(parent: _controller, curve: Curves.easeOutBack);
    _fade = CurvedAnimation(parent: _controller, curve: Curves.easeOut);
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _fade,
      child: Container(
        color: Colors.black.withValues(alpha: 0.55),
        alignment: Alignment.center,
        child: ScaleTransition(
          scale: _scale,
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
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.success.withValues(alpha: 0.35),
                      blurRadius: 24,
                    ),
                  ],
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
        ),
      ),
    );
  }
}
