/// Manager approvals — existing ERP oversight / approval authority only.
///
/// Must not invent company-side approve routes or permissions.
/// Hide UI when the user lacks existing approval rights.
/// Online only — no offline approve.
/// Phase 0.6: interface only.
library;

abstract interface class ApprovalPort {
  /// Whether the current user may use the Approvals tab (existing ERP permission).
  Future<bool> canApprove();

  Future<List<Map<String, Object?>>> pending();

  /// Decide using existing ERP approval decide/approve/reject semantics.
  Future<void> decide(Map<String, Object?> payload);
}
