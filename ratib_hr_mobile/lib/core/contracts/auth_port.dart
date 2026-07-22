/// Authentication port — reuses RATEB ERP authentication only.
///
/// No PIN system. No local credential authority.
/// Online ERP remains Authentication Authority.
/// Phase 0.6: interface definition only.
library;

/// Session / token auth against ERP. No business HR logic.
abstract interface class AuthPort {
  /// Signs in with ERP-accepted identifiers (e.g. email / employee id + password).
  /// Field semantics are defined by ERP auth — mobile must not invent rules.
  Future<void> signIn({
    required String identifier,
    required String secret,
  });

  /// Ends the ERP session / invalidates the stored token per ERP rules.
  Future<void> signOut();

  /// Whether a usable session/token is present (presentation gate only).
  Future<bool> hasSession();

  /// Refreshes session if ERP supports it; otherwise no-op / re-auth required.
  Future<void> refreshSession();
}
