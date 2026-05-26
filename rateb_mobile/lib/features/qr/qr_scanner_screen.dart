import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/models/user_role.dart';
import '../../core/routing/app_router.dart';
import '../../core/theme/app_colors.dart';
import '../auth/providers/auth_provider.dart';
import 'qr_badge_preview_screen.dart';
import 'qr_login_controller.dart';
import 'qr_platform.dart';
import 'widgets/qr_scan_overlay.dart';

class QrScannerScreen extends StatefulWidget {
  const QrScannerScreen({super.key});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen>
    with WidgetsBindingObserver, SingleTickerProviderStateMixin {
  late final QrLoginController _controller;
  MobileScannerController? _scannerController;
  late final AnimationController _scanLineController;
  final _manualController = TextEditingController();

  bool _scanLocked = false;
  bool _showSuccess = false;
  String? _cameraError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _controller = QrLoginController()..startScanning();
    _scanLineController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    )..repeat(reverse: true);

    if (qrUsesNativeCamera) {
      _scannerController = MobileScannerController(
        detectionSpeed: DetectionSpeed.noDuplicates,
        facing: CameraFacing.back,
        autoStart: true,
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _scanLineController.dispose();
    _scannerController?.dispose();
    _manualController.dispose();
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final scanner = _scannerController;
    if (scanner == null) return;
    switch (state) {
      case AppLifecycleState.resumed:
        if (!_scanLocked && !_controller.isBusy && _cameraError == null) {
          unawaited(scanner.start());
        }
      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
        unawaited(scanner.stop());
      case AppLifecycleState.hidden:
        break;
    }
  }

  Future<void> _resumeScanning() async {
    _scanLocked = false;
    _showSuccess = false;
    _cameraError = null;
    _controller.reset();
    _controller.startScanning();
    if (qrUsesNativeCamera) {
      await _scannerController?.start();
    }
    if (mounted) setState(() {});
  }

  Future<void> _handlePayload(String payload) async {
    if (_scanLocked || _controller.isBusy) return;

    _scanLocked = true;
    await _scannerController?.stop();

    final auth = context.read<AuthProvider>();
    auth.clearError();
    auth.clearSessionMessage();

    final response = await _controller.submitPayload(payload);
    if (!mounted) return;

    if (response == null) {
      _scanLocked = false;
      if (qrUsesNativeCamera) {
        await _scannerController?.start();
      }
      setState(() {});
      return;
    }

    await HapticFeedback.mediumImpact();

    final ok = await auth.completeQrLogin(response);
    if (!mounted) return;

    if (!ok) {
      _scanLocked = false;
      if (qrUsesNativeCamera) {
        await _scannerController?.start();
      }
      setState(() {});
      return;
    }

    setState(() => _showSuccess = true);
    await HapticFeedback.heavyImpact();
    await Future<void>.delayed(const Duration(milliseconds: 900));
    if (!mounted) return;

    final destination = _destinationForRole(auth.role);
    if (destination != AppRouter.login) {
      Navigator.of(context).pop();
      context.go(destination);
    }
  }

  String _destinationForRole(UserRole? role) {
    return switch (role) {
      UserRole.worker => AppRouter.workerHome,
      UserRole.company => AppRouter.companyHome,
      UserRole.agency => AppRouter.agencyHome,
      null => AppRouter.login,
    };
  }

  void _onDetect(BarcodeCapture capture) {
    if (_scanLocked || _controller.isBusy) return;
    final raw = capture.barcodes.firstOrNull?.rawValue?.trim();
    if (raw == null || raw.isEmpty) return;
    _handlePayload(raw);
  }

  @override
  Widget build(BuildContext context) {
    final mode = resolveQrScannerMode();
    final showPaste = qrShowsManualPaste && !qrUsesNativeCamera;

    return ListenableBuilder(
      listenable: _controller,
      builder: (context, _) {
        final status = _controller.status;
        final error = _controller.errorMessage;
        final isProcessing = status == QrLoginStatus.processing;

        return Scaffold(
          backgroundColor: Colors.black,
          extendBodyBehindAppBar: qrUsesNativeCamera,
          appBar: AppBar(
            backgroundColor:
                qrUsesNativeCamera ? Colors.transparent : null,
            elevation: 0,
            foregroundColor: qrUsesNativeCamera ? Colors.white : null,
            title: const Text('Workforce identity'),
            leading: IconButton(
              icon: const Icon(Icons.close),
              onPressed: () => Navigator.of(context).pop(),
            ),
            actions: [
              IconButton(
                tooltip: 'Sample badge',
                icon: const Icon(Icons.badge_outlined),
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const QrBadgePreviewScreen(),
                    ),
                  );
                },
              ),
            ],
          ),
          body: qrUsesNativeCamera
              ? _NativeScannerBody(
                  scannerController: _scannerController!,
                  scanLineAnimation: _scanLineController,
                  cameraError: _cameraError,
                  showSuccess: _showSuccess,
                  isProcessing: isProcessing,
                  errorMessage: error,
                  onDetect: _onDetect,
                  onCameraError: (message) {
                    setState(() => _cameraError = message);
                  },
                  onRetryCamera: _resumeScanning,
                  onScanAgain: _resumeScanning,
                )
              : _FallbackBody(
                  mode: mode,
                  showPaste: showPaste,
                  manualController: _manualController,
                  controller: _controller,
                  isProcessing: isProcessing,
                  errorMessage: error,
                  onSubmitPaste: () {
                    _handlePayload(_manualController.text);
                  },
                  onScanAgain: _resumeScanning,
                ),
        );
      },
    );
  }
}

class _NativeScannerBody extends StatelessWidget {
  const _NativeScannerBody({
    required this.scannerController,
    required this.scanLineAnimation,
    required this.cameraError,
    required this.showSuccess,
    required this.isProcessing,
    required this.errorMessage,
    required this.onDetect,
    required this.onCameraError,
    required this.onRetryCamera,
    required this.onScanAgain,
  });

  final MobileScannerController scannerController;
  final AnimationController scanLineAnimation;
  final String? cameraError;
  final bool showSuccess;
  final bool isProcessing;
  final String? errorMessage;
  final void Function(BarcodeCapture) onDetect;
  final void Function(String message) onCameraError;
  final VoidCallback onRetryCamera;
  final VoidCallback onScanAgain;

  @override
  Widget build(BuildContext context) {
    if (cameraError != null) {
      return _CameraPermissionView(
        message: cameraError!,
        onRetry: onRetryCamera,
      );
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: scannerController,
          onDetect: onDetect,
          errorBuilder: (context, error, child) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              onCameraError(_cameraErrorMessage(error));
            });
            return child ?? const SizedBox.shrink();
          },
        ),
        AnimatedBuilder(
          animation: scanLineAnimation,
          builder: (context, _) => QrScanOverlay(
            scanLineProgress: scanLineAnimation.value,
          ),
        ),
        if (isProcessing)
          _ProcessingOverlay(
            message: 'Verifying workforce identity…',
          ),
        if (showSuccess) const QrScanSuccessOverlay(),
        if (errorMessage != null)
          Positioned(
            left: 16,
            right: 16,
            bottom: 32,
            child: _ErrorBanner(
              message: errorMessage!,
              onRetry: onScanAgain,
            ),
          ),
      ],
    );
  }

  static String _cameraErrorMessage(MobileScannerException error) {
    if (error.errorCode == MobileScannerErrorCode.permissionDenied) {
      return 'Camera access is required to scan your workforce badge. Enable camera permission in Settings.';
    }
    return 'Unable to start the camera. Check permissions and try again.';
  }
}

class _FallbackBody extends StatelessWidget {
  const _FallbackBody({
    required this.mode,
    required this.showPaste,
    required this.manualController,
    required this.controller,
    required this.isProcessing,
    required this.errorMessage,
    required this.onSubmitPaste,
    required this.onScanAgain,
  });

  final QrScannerMode mode;
  final bool showPaste;
  final TextEditingController manualController;
  final QrLoginController controller;
  final bool isProcessing;
  final String? errorMessage;
  final VoidCallback onSubmitPaste;
  final VoidCallback onScanAgain;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final headline = mode == QrScannerMode.webFallback
        ? 'Workforce identity login'
        : 'Workforce identity login';

    final subtitle = mode == QrScannerMode.webFallback
        ? 'Use the RATEB mobile app on Android or iPhone to scan your badge QR. On web, paste a badge payload from System Settings.'
        : 'Install RATEB on a phone to scan your workforce badge. On this device, paste a badge payload from RATEB System Settings.';

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Icon(
              Icons.qr_code_scanner_rounded,
              size: 56,
              color: theme.colorScheme.primary,
            ),
            const SizedBox(height: 16),
            Text(
              headline,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
              ),
            ),
            if (isProcessing) ...[
              const SizedBox(height: 32),
              const Center(child: CircularProgressIndicator()),
              const SizedBox(height: 12),
              Text(
                'Verifying workforce identity…',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
            ],
            if (errorMessage != null) ...[
              const SizedBox(height: 20),
              _ErrorBanner(
                message: errorMessage!,
                onRetry: onScanAgain,
                lightBackground: true,
              ),
            ],
            if (showPaste) ...[
              const SizedBox(height: 28),
              TextField(
                controller: manualController,
                decoration: const InputDecoration(
                  labelText: 'Badge payload',
                  hintText: 'Paste from RATEB System Settings',
                  prefixIcon: Icon(Icons.qr_code_2),
                  border: OutlineInputBorder(),
                ),
                minLines: 2,
                maxLines: 4,
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: controller.isBusy ? null : onSubmitPaste,
                icon: const Icon(Icons.verified_user_outlined),
                label: const Text('Verify identity'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ProcessingOverlay extends StatelessWidget {
  const _ProcessingOverlay({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black.withValues(alpha: 0.55),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(
            width: 44,
            height: 44,
            child: CircularProgressIndicator(
              strokeWidth: 3,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            message,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({
    required this.message,
    required this.onRetry,
    this.lightBackground = false,
  });

  final String message;
  final VoidCallback onRetry;
  final bool lightBackground;

  @override
  Widget build(BuildContext context) {
    final bg = lightBackground
        ? Theme.of(context).colorScheme.errorContainer
        : AppColors.error.withValues(alpha: 0.92);

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.error_outline_rounded,
                  color: lightBackground
                      ? Theme.of(context).colorScheme.onErrorContainer
                      : Colors.white,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    message,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: lightBackground
                              ? Theme.of(context)
                                  .colorScheme
                                  .onErrorContainer
                              : Colors.white,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: onRetry,
                child: Text(
                  'Scan again',
                  style: TextStyle(
                    color: lightBackground
                        ? Theme.of(context).colorScheme.primary
                        : Colors.white,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CameraPermissionView extends StatelessWidget {
  const _CameraPermissionView({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.no_photography_outlined,
              size: 56,
              color: Colors.white.withValues(alpha: 0.85),
            ),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: Colors.white,
                  ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
