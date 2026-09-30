/// Android: VIEW intents from QR / browser sometimes arrive before app_links is ready.
library;

import 'package:flutter/services.dart';

abstract final class ActivationIntentBridge {
  static const _channel = MethodChannel('sa.rateb.hr.mobile/activation_intent');

  static Future<String?> consumePendingUri() async {
    try {
      final value = await _channel.invokeMethod<String>('consumePendingUri');
      final trimmed = (value ?? '').trim();
      return trimmed.isEmpty ? null : trimmed;
    } catch (_) {
      return null;
    }
  }
}
