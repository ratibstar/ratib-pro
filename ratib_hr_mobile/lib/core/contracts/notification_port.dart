/// Notifications — existing ERP notification center only.
///
/// Phase 0.6: interface only.
library;

abstract interface class NotificationPort {
  Future<List<Map<String, Object?>>> list();

  Future<void> markRead(String notificationId);
}
