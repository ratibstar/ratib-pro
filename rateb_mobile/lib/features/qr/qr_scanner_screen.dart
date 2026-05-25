import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/models/user_role.dart';
import '../../core/routing/app_router.dart';
import '../auth/providers/auth_provider.dart';
import 'qr_login_controller.dart';

class QrScannerScreen extends StatefulWidget {
  const QrScannerScreen({super.key});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  late final QrLoginController _controller;
  MobileScannerController? _scannerController;
  final _manualController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _controller = QrLoginController()..startScanning();
    if (!kIsWeb) {
      _scannerController = MobileScannerController(
        detectionSpeed: DetectionSpeed.normal,
        facing: CameraFacing.back,
      );
    }
  }

  @override
  void dispose() {
    _scannerController?.dispose();
    _manualController.dispose();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _handlePayload(String payload) async {
    final auth = context.read<AuthProvider>();
    auth.clearError();
    auth.clearSessionMessage();

    final response = await _controller.submitPayload(payload);
    if (!mounted || response == null) return;

    final ok = await auth.completeQrLogin(response);
    if (!mounted || !ok) return;

    final role = auth.role;
    final destination = switch (role) {
      UserRole.worker => AppRouter.workerHome,
      UserRole.company => AppRouter.companyHome,
      UserRole.agency => AppRouter.agencyHome,
      null => AppRouter.login,
    };

    if (destination != AppRouter.login) {
      Navigator.of(context).pop();
      context.go(destination);
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (_controller.isBusy) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null || raw.trim().isEmpty) return;
    _scannerController?.stop();
    _handlePayload(raw);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListenableBuilder(
      listenable: _controller,
      builder: (context, _) {
        final status = _controller.status;
        final error = _controller.errorMessage;

        return Scaffold(
          appBar: AppBar(
            title: const Text('Login with QR'),
            leading: IconButton(
              icon: const Icon(Icons.close),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ),
          body: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Text(
                  'Scan your workforce identity badge to sign in instantly.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: ColoredBox(
                      color: Colors.black,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          if (_scannerController != null)
                            MobileScanner(
                              controller: _scannerController,
                              onDetect: _onDetect,
                            )
                          else
                            Center(
                              child: Padding(
                                padding: const EdgeInsets.all(24),
                                child: Text(
                                  'Camera preview is limited on web.\nPaste QR payload below.',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: Colors.white70,
                                  ),
                                ),
                              ),
                            ),
                          if (status == QrLoginStatus.processing)
                            Container(
                              color: Colors.black54,
                              child: const Center(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    CircularProgressIndicator(),
                                    SizedBox(height: 12),
                                    Text(
                                      'Verifying identity…',
                                      style: TextStyle(color: Colors.white),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              if (error != null) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Material(
                    color: theme.colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(10),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Icon(
                            Icons.error_outline,
                            color: theme.colorScheme.onErrorContainer,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              error,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onErrorContainer,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      controller: _manualController,
                      decoration: const InputDecoration(
                        labelText: 'Paste QR payload (dev / web)',
                        prefixIcon: Icon(Icons.qr_code_2),
                      ),
                      minLines: 1,
                      maxLines: 3,
                    ),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      onPressed: _controller.isBusy
                          ? null
                          : () {
                              _controller.reset();
                              _controller.startScanning();
                              _scannerController?.start();
                              _handlePayload(_manualController.text);
                            },
                      icon: const Icon(Icons.login),
                      label: const Text('Submit QR payload'),
                    ),
                    if (status == QrLoginStatus.error) ...[
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed: _controller.isBusy
                            ? null
                            : () {
                                _controller.reset();
                                _controller.startScanning();
                                _scannerController?.start();
                              },
                        icon: const Icon(Icons.refresh),
                        label: const Text('Scan again'),
                      ),
                    ],
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
