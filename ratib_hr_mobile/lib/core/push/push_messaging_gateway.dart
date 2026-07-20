/// OS / FCM messaging gateway — no ERP business rules.
library;

/// Opaque foreground message for local display only.
final class PushDisplayMessage {
  const PushDisplayMessage({
    this.title,
    this.body,
    this.data = const {},
  });

  final String? title;
  final String? body;
  final Map<String, String> data;
}

abstract interface class PushMessagingGateway {
  /// Returns false when Firebase/native config is unavailable.
  Future<bool> ensureInitialized();

  Future<bool> requestPermission();

  Future<String?> getToken();

  Stream<String> get onTokenRefresh;

  /// Foreground messages — display only.
  Stream<PushDisplayMessage> get onForegroundMessage;

  /// Register background handler (no-op if unsupported).
  Future<void> registerBackgroundHandler();
}
