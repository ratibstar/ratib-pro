/// Resolves the logged-in ERP user to the bound employee ("me").
///
/// Uses existing ERP User→Employee linkage (`user_id` on employee).
/// No new tables. No invented identity store.
/// Phase 0.6: interface only.
library;

/// Self-scope identity for ESS. Returns opaque maps shaped by ERP — not local models.
abstract interface class MePort {
  /// Returns the current employee record for the authenticated user, or empty if unbound.
  Future<Map<String, Object?>> currentEmployee();

  /// Stable employee id string for self-scoping subsequent ESS calls, if available.
  Future<String?> currentEmployeeId();
}
