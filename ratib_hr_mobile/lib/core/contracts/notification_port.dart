/// Notifications — existing ERP notification center only.
library;

abstract interface class NotificationPort {
  Future<List<Map<String, Object?>>> list();

  Future<List<Map<String, Object?>>> listFiltered(String type);

  Future<void> markRead(String notificationId);

  Future<void> markAllRead();
}
