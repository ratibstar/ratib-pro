/// Secure token / session material store.
///
/// Store opaque session tokens only — never passwords, password hashes,
/// or authentication secrets in an Identity vault.
/// Phase 0.6: interface only. No flutter_secure_storage wiring.
library;

abstract interface class SecureTokenStore {
  Future<void> writeToken(String token);

  Future<String?> readToken();

  Future<void> clearToken();
}
