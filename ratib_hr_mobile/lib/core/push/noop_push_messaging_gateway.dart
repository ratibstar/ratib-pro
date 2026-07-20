/// No-op messaging gateway (tests / web / missing Firebase config).
library;

import 'dart:async';

import 'package:ratib_hr_mobile/core/push/push_messaging_gateway.dart';

class NoopPushMessagingGateway implements PushMessagingGateway {
  final _refresh = StreamController<String>.broadcast();
  final _foreground = StreamController<PushDisplayMessage>.broadcast();

  String? seedToken;

  @override
  Future<bool> ensureInitialized() async => false;

  @override
  Future<bool> requestPermission() async => false;

  @override
  Future<String?> getToken() async => seedToken;

  @override
  Stream<String> get onTokenRefresh => _refresh.stream;

  @override
  Stream<PushDisplayMessage> get onForegroundMessage => _foreground.stream;

  @override
  Future<void> registerBackgroundHandler() async {}

  void emitRefresh(String token) => _refresh.add(token);

  void emitForeground(PushDisplayMessage msg) => _foreground.add(msg);

  void dispose() {
    _refresh.close();
    _foreground.close();
  }
}
