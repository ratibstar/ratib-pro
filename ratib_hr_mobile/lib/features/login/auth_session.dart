/// Presentation auth + ESS identity gate.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
import 'package:ratib_hr_mobile/core/contracts/me_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';

enum AuthStatus { unknown, signedOut, signedIn }

final class AuthSession extends ChangeNotifier {
  AuthSession({AuthPort? auth, MePort? me})
      : _auth = auth,
        _me = me;

  AuthPort get _authPort => _auth ?? AppLocator.auth;
  MePort get _mePort => _me ?? AppLocator.me;
  final AuthPort? _auth;
  final MePort? _me;

  AuthStatus status = AuthStatus.unknown;
  AppFailure? lastError;

  Future<void> restore() async {
    try {
      final ok = await _authPort.hasSession();
      if (!ok) {
        EmployeeContext.clear();
        _clearMeCache();
        status = AuthStatus.signedOut;
        notifyListeners();
        return;
      }
      await _resolveEmployeeOrThrow();
      status = AuthStatus.signedIn;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      await _authPort.signOut();
      EmployeeContext.clear();
      _clearMeCache();
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
      await _authPort.signIn(identifier: identifier, secret: secret);
      await _resolveEmployeeOrThrow();
      status = AuthStatus.signedIn;
      notifyListeners();
      return true;
    } catch (e) {
      lastError = e is AppFailure ? e : AppLocator.errors.map(e);
      await _authPort.signOut();
      EmployeeContext.clear();
      _clearMeCache();
      status = AuthStatus.signedOut;
      notifyListeners();
      return false;
    }
  }

  Future<void> signOut() async {
    await _authPort.signOut();
    EmployeeContext.clear();
    _clearMeCache();
    status = AuthStatus.signedOut;
    lastError = null;
    notifyListeners();
  }

  Future<void> _resolveEmployeeOrThrow() async {
    _clearMeCache();
    EmployeeContext.clear();
    await _mePort.currentEmployee();
    if (!EmployeeContext.isResolved) {
      throw const AppFailure(
        code: 'employee_unbound',
        message: 'No employee linked to this user',
      );
    }
  }

  void _clearMeCache() {
    final me = _mePort;
    if (me is ErpMeAdapter) {
      me.clearCache();
    }
  }
}
