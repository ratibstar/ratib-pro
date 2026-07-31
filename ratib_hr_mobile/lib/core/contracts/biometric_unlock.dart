/// Device biometric unlock of an already-stored session/token.
///
/// Not an ERP authentication method. No PIN system.
/// Online ERP remains Authentication Authority.
library;

abstract interface class BiometricUnlock {
  Future<bool> isAvailable();

  /// Unlocks local access to the secure token store for this device session.
  Future<bool> unlock();
}
