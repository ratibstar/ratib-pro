/// Firebase Messaging gateway — display/token only, no business routing.
library;

import 'dart:async';
import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:ratib_hr_mobile/core/push/push_messaging_gateway.dart';

/// Top-level background handler — display registration only (no ERP routing).
@pragma('vm:entry-point')
Future<void> ratibFirebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Payload is OS-displayed when present; no business decisions here.
}

final class FirebasePushMessagingGateway implements PushMessagingGateway {
  final _foreground = StreamController<PushDisplayMessage>.broadcast();
  StreamSubscription<RemoteMessage>? _fgSub;
  StreamSubscription<String>? _tokenSub;
  final _tokenRefresh = StreamController<String>.broadcast();
  bool _ready = false;

  @override
  Future<bool> ensureInitialized() async {
    if (kIsWeb) return false;
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }
      FirebaseMessaging.onBackgroundMessage(
        ratibFirebaseMessagingBackgroundHandler,
      );
      _fgSub ??= FirebaseMessaging.onMessage.listen((msg) {
        _foreground.add(_map(msg));
      });
      _tokenSub ??= FirebaseMessaging.instance.onTokenRefresh.listen((t) {
        if (t.isNotEmpty) _tokenRefresh.add(t);
      });
      _ready = true;
      return true;
    } catch (_) {
      _ready = false;
      return false;
    }
  }

  @override
  Future<bool> requestPermission() async {
    if (!_ready) return false;
    try {
      if (!kIsWeb && Platform.isAndroid) {
        final status = await Permission.notification.request();
        if (!(status.isGranted || status.isLimited)) {
          // Still try FCM permission API below for completeness.
        }
      }
      final settings = await FirebaseMessaging.instance.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
      );
      final ok = settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;
      if (!kIsWeb && Platform.isIOS) {
        await FirebaseMessaging.instance
            .setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        );
      }
      return ok;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<String?> getToken() async {
    if (!_ready) return null;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) return null;
      return token;
    } catch (_) {
      return null;
    }
  }

  @override
  Stream<String> get onTokenRefresh => _tokenRefresh.stream;

  @override
  Stream<PushDisplayMessage> get onForegroundMessage => _foreground.stream;

  @override
  Future<void> registerBackgroundHandler() async {
    // Registered in ensureInitialized when Firebase is available.
  }

  PushDisplayMessage _map(RemoteMessage msg) {
    final n = msg.notification;
    final data = <String, String>{};
    msg.data.forEach((k, v) {
      data[k] = '$v';
    });
    return PushDisplayMessage(
      title: n?.title,
      body: n?.body,
      data: data,
    );
  }

  Future<void> dispose() async {
    await _fgSub?.cancel();
    await _tokenSub?.cancel();
    await _foreground.close();
    await _tokenRefresh.close();
  }
}
