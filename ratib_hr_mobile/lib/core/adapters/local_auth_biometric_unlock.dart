/// Device biometric unlock — unlocks an already-stored ERP session token.
///
/// Not an ERP authentication method. Online ERP remains Authentication Authority.
/// Never stores passwords or credentials.
library;

import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:ratib_hr_mobile/core/contracts/biometric_unlock.dart';

final class LocalAuthBiometricUnlock implements BiometricUnlock {
  LocalAuthBiometricUnlock({LocalAuthentication? auth})
      : _auth = auth ?? LocalAuthentication();

  final LocalAuthentication _auth;

  @override
  Future<bool> isAvailable() async {
    try {
      final supported = await _auth.isDeviceSupported();
      if (!supported) return false;
      final canCheck = await _auth.canCheckBiometrics;
      if (!canCheck) return false;
      final biometrics = await _auth.getAvailableBiometrics();
      return biometrics.isNotEmpty;
    } on PlatformException {
      return false;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<bool> unlock() async {
    try {
      final available = await isAvailable();
      if (!available) return false;
      return await _auth.authenticate(
        localizedReason: 'Unlock RATEB HR session',
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } on PlatformException {
      return false;
    } catch (_) {
      return false;
    }
  }
}
