/// Presentation auth session gate — not an ERP auth authority.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

enum AuthStatus { unknown, signedOut, signedIn }

final class AuthSession extends ChangeNotifier {
  AuthSession({AuthPort? auth}) : _auth = auth;

  AuthPort get _port => _auth ?? AppLocator.auth;
  final AuthPort? _auth;

  AuthStatus status = AuthStatus.unknown;
  AppFailure? lastError;

  Future<void> restore() async {
    try {
      final ok = await _port.hasSession();
      status = ok ? AuthStatus.signedIn : AuthStatus.signedOut;
    } catch (_) {
      status = AuthStatus.signedOut;
    }
    notifyListeners();
  }

  Future<bool> signIn({
    required String identifier,
    required String secret,
  }) async {
    lastError = null;
    try {
      await _port.signIn(identifier: identifier, secret: secret);
      status = AuthStatus.signedIn;
      notifyListeners();
      return true;
    } catch (e) {
      lastError = e is AppFailure
          ? e
          : AppLocator.errors.map(e);
      status = AuthStatus.signedOut;
      notifyListeners();
      return false;
    }
  }

  Future<void> signOut() async {
    await _port.signOut();
    status = AuthStatus.signedOut;
    lastError = null;
    notifyListeners();
  }
}
