import 'package:flutter/foundation.dart';

/// Platform behavior for workforce identity QR login.
enum QrScannerMode {
  /// Android / iPhone — native camera scanner.
  nativeCamera,

  /// Flutter web — manual payload fallback only.
  webFallback,

  /// Desktop (Windows/macOS/Linux) — paste fallback; no mobile camera UX.
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

bool get qrShowsManualPaste {
  if (qrUsesNativeCamera) {
    return false;
  }
  return kIsWeb || kDebugMode || resolveQrScannerMode() == QrScannerMode.desktopFallback;
}

bool get qrUsesNativeCamera =>
    resolveQrScannerMode() == QrScannerMode.nativeCamera;
