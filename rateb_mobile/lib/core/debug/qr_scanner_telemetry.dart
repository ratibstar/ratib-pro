/// QR scanner state for pilot diagnostics (no payload content stored).
abstract final class QrScannerTelemetry {
  static String state = 'idle';
  static String cameraPermission = 'unknown';
  static bool cameraAvailable = false;
  static DateTime? lastScanAt;
  static int scanAttempts = 0;

  static void setState(String value) {
    state = value;
  }

  static void setCameraPermission(String value) {
    cameraPermission = value;
  }

  static void setCameraAvailable(bool value) {
    cameraAvailable = value;
  }

  static void recordScanAttempt() {
    scanAttempts++;
    lastScanAt = DateTime.now();
  }

  static void reset() {
    state = 'idle';
    lastScanAt = null;
    scanAttempts = 0;
  }
}
