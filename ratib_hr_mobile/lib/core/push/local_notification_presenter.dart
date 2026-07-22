/// Local notification presenter — foreground display only.
library;

import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:ratib_hr_mobile/core/push/push_messaging_gateway.dart';

abstract interface class LocalNotificationPresenter {
  Future<void> ensureInitialized();
  Future<void> show(PushDisplayMessage message);
}

final class FlutterLocalNotificationPresenter
    implements LocalNotificationPresenter {
  FlutterLocalNotificationPresenter({
    FlutterLocalNotificationsPlugin? plugin,
  }) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;
  bool _ready = false;
  static const _channelId = 'ratib_ess_push';
  static const _channelName = 'RATEB ESS';

  @override
  Future<void> ensureInitialized() async {
    if (_ready) return;
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings();
    await _plugin.initialize(
      const InitializationSettings(android: android, iOS: ios),
    );
    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        _channelId,
        _channelName,
        description: 'ESS push display',
        importance: Importance.defaultImportance,
      ),
    );
    _ready = true;
  }

  @override
  Future<void> show(PushDisplayMessage message) async {
    if (!_ready) await ensureInitialized();
    final title = message.title?.trim();
    final body = message.body?.trim();
    if ((title == null || title.isEmpty) && (body == null || body.isEmpty)) {
      return;
    }
    await _plugin.show(
      DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title ?? 'RATEB',
      body ?? '',
      const NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          _channelName,
          channelDescription: 'ESS push display',
          importance: Importance.defaultImportance,
          priority: Priority.defaultPriority,
        ),
        iOS: DarwinNotificationDetails(),
      ),
    );
  }
}

final class NoopLocalNotificationPresenter
    implements LocalNotificationPresenter {
  final shown = <PushDisplayMessage>[];

  @override
  Future<void> ensureInitialized() async {}

  @override
  Future<void> show(PushDisplayMessage message) async {
    shown.add(message);
  }
}
