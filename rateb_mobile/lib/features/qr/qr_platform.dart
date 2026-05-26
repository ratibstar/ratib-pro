import 'package:flutter/foundation.dart';

/// Platform behavior for workforce identity QR login.
enum QrScannerMode {
  /// Android / iPhone — native camera scanner.
  nativeCamera,

  /// Flutter web — payload paste fallback only.
  webFallback,

  /// Desktop (Windows/macOS/Linux) — payload paste fallback.
  desktopFallback,
}

QrScannerMode resolveQrScannerMode() {
  if (kIsWeb) {
    return QrScannerMode.webFallback;
  }
  switch (defaultTargetPlatform) {
    case TargetPlatform.android:
    case TargetPlatform.iOS:
      return QrScannerMode.nativeCamera;
    default:
      return QrScannerMode.desktopFallback;
  }
}

bool get qrUsesNativeCamera =>
    resolveQrScannerMode() == QrScannerMode.nativeCamera;

/// Payload paste is shown only when the live camera scanner is unavailable.
bool get qrShowsManualPaste => !qrUsesNativeCamera;
