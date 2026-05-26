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
  bool _torchOn = false;
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

  Future<void> _toggleTorch() async {
    final scanner = _scannerController;
    if (scanner == null) return;
    await scanner.toggleTorch();
    if (mounted) {
      setState(() => _torchOn = !_torchOn);
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
    return ListenableBuilder(
      listenable: _controller,
      builder: (context, _) {
        final error = _controller.errorMessage;
        final isProcessing =
            _controller.status == QrLoginStatus.processing;

        return Scaffold(
          backgroundColor:
              qrUsesNativeCamera ? Colors.black : AppColors.darkBackground,
          extendBodyBehindAppBar: qrUsesNativeCamera,
          appBar: AppBar(
            backgroundColor: qrUsesNativeCamera
                ? Colors.transparent
                : AppColors.darkSurface,
            elevation: 0,
            foregroundColor:
                qrUsesNativeCamera ? Colors.white : AppColors.darkText,
            title: const Text('Workforce identity'),
            leading: IconButton(
              icon: const Icon(Icons.close),
              tooltip: 'Close',
              onPressed: () => Navigator.of(context).pop(),
            ),
            actions: [
              if (qrUsesNativeCamera)
                IconButton(
                  tooltip: _torchOn ? 'Turn off flashlight' : 'Turn on flashlight',
                  icon: Icon(
                    _torchOn ? Icons.flashlight_on : Icons.flashlight_off_outlined,
                  ),
                  onPressed: _toggleTorch,
                ),
              IconButton(
                tooltip: 'Preview workforce badge',
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
          body: AnimatedSwitcher(
            duration: const Duration(milliseconds: 280),
            child: qrUsesNativeCamera
                ? _NativeScannerBody(
                    key: const ValueKey('native'),
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
                    key: const ValueKey('fallback'),
                    manualController: _manualController,
                    controller: _controller,
                    isProcessing: isProcessing,
                    errorMessage: error,
                    onSubmitPaste: () => _handlePayload(_manualController.text),
                    onScanAgain: _resumeScanning,
                  ),
          ),
        );
      },
    );
  }
}

class _NativeScannerBody extends StatelessWidget {
  const _NativeScannerBody({
    super.key,
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
          const _ProcessingOverlay(
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
      return 'Camera access is required to scan your workforce badge. Enable camera permission in Settings, then tap Try again.';
    }
    return 'Unable to start the camera. Check permissions and try again.';
  }
}

class _FallbackBody extends StatelessWidget {
  const _FallbackBody({
    super.key,
    required this.manualController,
    required this.controller,
    required this.isProcessing,
    required this.errorMessage,
    required this.onSubmitPaste,
    required this.onScanAgain,
  });

  final TextEditingController manualController;
  final QrLoginController controller;
  final bool isProcessing;
  final String? errorMessage;
  final VoidCallback onSubmitPaste;
  final VoidCallback onScanAgain;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: 8),
            Icon(
              Icons.verified_user_outlined,
              size: 52,
              color: AppColors.accent,
              semanticLabel: 'Workforce identity',
            ),
            const SizedBox(height: 20),
            Text(
              'Paste workforce identity payload',
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.darkText,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              'Use the QR payload generated from RATEB System Settings.',
              style: theme.textTheme.bodyLarge?.copyWith(
                color: AppColors.darkMuted,
                height: 1.45,
              ),
            ),
            if (isProcessing) ...[
              const SizedBox(height: 36),
              const Center(
                child: CircularProgressIndicator(color: AppColors.accent),
              ),
              const SizedBox(height: 14),
              Text(
                'Verifying workforce identity…',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.darkMuted),
              ),
            ],
            if (errorMessage != null) ...[
              const SizedBox(height: 24),
              _ErrorBanner(
                message: errorMessage!,
                onRetry: onScanAgain,
                lightBackground: true,
              ),
            ],
            const SizedBox(height: 28),
            Semantics(
              label: 'Workforce identity payload',
              child: TextField(
                controller: manualController,
                style: const TextStyle(
                  color: AppColors.darkText,
                  fontFamily: 'monospace',
                  fontSize: 13,
                ),
                decoration: InputDecoration(
                  labelText: 'Identity payload',
                  hintText: 'RATEBMOBQR:…',
                  filled: true,
                  fillColor: AppColors.darkSurface,
                  prefixIcon: const Icon(Icons.qr_code_2, color: AppColors.accent),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(
                      color: AppColors.accent.withValues(alpha: 0.35),
                    ),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(
                      color: AppColors.accent.withValues(alpha: 0.25),
                    ),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.accent, width: 1.5),
                  ),
                ),
                minLines: 4,
                maxLines: 6,
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: controller.isBusy ? null : onSubmitPaste,
              icon: const Icon(Icons.login_rounded),
              label: const Text('Verify and sign in'),
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
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
    return Semantics(
      label: message,
      child: Container(
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
        ? AppColors.error.withValues(alpha: 0.12)
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
                  color: lightBackground ? AppColors.error : Colors.white,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    message,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: lightBackground
                              ? AppColors.darkText
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
                  qrUsesNativeCamera ? 'Scan again' : 'Try again',
                  style: TextStyle(
                    color: lightBackground ? AppColors.accent : Colors.white,
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
