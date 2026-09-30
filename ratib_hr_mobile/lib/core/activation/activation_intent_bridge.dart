/// Android activation VIEW intents (QR / browser green button).
library;

import 'package:flutter/services.dart';

abstract final class ActivationIntentBridge {
  static const _channel = MethodChannel('sa.rateb.hr.mobile/activation_intent');

  static Future<String?> peekPendingUri() async {
    try {
      final value = await _channel.invokeMethod<String>('peekPendingUri');
      final trimmed = (value ?? '').trim();
      return trimmed.isEmpty ? null : trimmed;
    } catch (_) {
      return null;
    }
  }

  static Future<void> clearPendingUri() async {
    try {
      await _channel.invokeMethod<void>('clearPendingUri');
    } catch (_) {}
  }

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
