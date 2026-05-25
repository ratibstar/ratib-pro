import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/auth/auth_repository.dart';
import '../../../core/models/user_role.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthProvider extends ChangeNotifier {
  AuthProvider({AuthRepository? repository})
      : _repository = repository ?? AuthRepository();

  final AuthRepository _repository;

  AuthStatus _status = AuthStatus.unauthenticated;
  UserRole? _role;
  String? _username;
  String? _errorMessage;
  bool _isLoading = false;

  AuthStatus get status => _status;
  UserRole? get role => _role;
  String? get username => _username;
  String? get errorMessage => _errorMessage;
  bool get isLoading => _isLoading;

  Future<void> bootstrap() async {
    try {
      final session = await _repository
          .restoreSession()
          .timeout(const Duration(seconds: 3));
      if (session != null) {
        _role = session.role;
        _username = session.username;
        _status = AuthStatus.authenticated;
        notifyListeners();
      }
    } on TimeoutException {
      // Stay on login screen.
    } catch (_) {
      // Stay on login screen.
    }
  }

  Future<bool> login({
    required String email,
    required String password,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _repository
          .login(email: email, password: password)
          .timeout(const Duration(seconds: 25));
      _role = response.role;
      _username = response.username ?? response.email ?? email;
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on TimeoutException {
      _errorMessage = 'Login timed out. Check your connection and try again.';
      _status = AuthStatus.unauthenticated;
      return false;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      return false;
    } on FormatException catch (e) {
      _errorMessage = 'Unexpected server response: ${e.message}';
      _status = AuthStatus.unauthenticated;
      return false;
    } catch (e) {
      _errorMessage = kDebugMode
          ? 'Login failed: $e'
          : 'Login failed. Please try again.';
      _status = AuthStatus.unauthenticated;
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _isLoading = true;
    notifyListeners();
    await _repository.logout();
    _role = null;
    _username = null;
    _status = AuthStatus.unauthenticated;
    _isLoading = false;
    notifyListeners();
  }

  Future<void> handleUnauthorized() async {
    if (_status == AuthStatus.unauthenticated) return;
    await _repository.clearSession();
    _role = null;
    _username = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
