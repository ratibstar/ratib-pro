/// Offline enqueue façade — wraps **existing** RATIB ERP offline queues only.
///
/// Allowed (ESS): `attendance.create`, `leave_request.draft`.
/// Forbidden: inventing new queue action types (e.g. attendance.update).
/// Flutter never replays/conflicts — only queues and displays ERP outcomes.
library;

abstract interface class OfflineQueuePort {
  /// Enqueues a payload for an **existing** ERP offline action name.
  Future<void> enqueue({
    required String existingAction,
    required Map<String, Object?> payload,
  });

  /// Whether the named existing ERP offline action is available on this device/build.
  Future<bool> supportsExistingAction(String existingAction);

  /// Persisted pending items (presentation / flush orchestration).
  Future<List<Map<String, Object?>>> pendingItems();

  Future<int> pendingCount();

  /// Replace the entire pending list after a flush attempt.
  Future<void> replaceAll(List<Map<String, Object?>> items);
}
