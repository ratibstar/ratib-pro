/// Android: VIEW intents from QR / browser — persisted before Flutter engine starts.
library;

import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract final class ActivationIntentBridge {
  static const _channel = MethodChannel('sa.rateb.hr.mobile/activation_intent');
  static const _prefKey = 'rateb_pending_activation_uri';

  /// Reads URI stashed by [MainActivity] (FlutterSharedPreferences) or MethodChannel.
  static Future<String?> consumePendingUri() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = (prefs.getString(_prefKey) ?? '').trim();
    if (stored.isNotEmpty) {
      await prefs.remove(_prefKey);
      return stored;
    }
    try {
      final value = await _channel.invokeMethod<String>('consumePendingUri');
      final trimmed = (value ?? '').trim();
      return trimmed.isEmpty ? null : trimmed;
    } catch (_) {
      return null;
    }
  }
}
