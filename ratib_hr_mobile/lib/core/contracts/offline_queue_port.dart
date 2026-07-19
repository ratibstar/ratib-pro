/// Offline enqueue façade — wraps **existing** RATIB ERP offline queues only.
///
/// Allowed (existing): `attendance.create`, `leave_request.draft`.
/// Forbidden: inventing new queue action types (e.g. attendance.update).
/// Phase 0.6: interface only. No replay/sync engine in Flutter.
library;

abstract interface class OfflineQueuePort {
  /// Enqueues a payload for an **existing** ERP offline action name.
  Future<void> enqueue({
    required String existingAction,
    required Map<String, Object?> payload,
  });

  /// Whether the named existing ERP offline action is available on this device/build.
  Future<bool> supportsExistingAction(String existingAction);
}
