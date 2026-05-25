import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/auth/auth_repository.dart';
import '../../../core/models/auth_response.dart';
import '../../../core/models/user_role.dart';
import '../../../core/services/screen_cache.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthProvider extends ChangeNotifier {
  AuthProvider({AuthRepository? repository})
      : _repository = repository ?? AuthRepository();

  final AuthRepository _repository;

  AuthStatus _status = AuthStatus.unauthenticated;
  UserRole? _role;
  String? _username;
  String? _errorMessage;
  String? _sessionMessage;
  bool _isLoading = false;

  AuthStatus get status => _status;
  UserRole? get role => _role;
  String? get username => _username;
  String? get errorMessage => _errorMessage;
  String? get sessionMessage => _sessionMessage;
  bool get isLoading => _isLoading;

  Future<void> bootstrap() async {
    try {
      final session = await _repository
          .restoreSession()
          .timeout(const Duration(seconds: 5));
      if (session == null) return;

      final profile = await _repository
          .fetchProfile(session.role)
          .timeout(const Duration(seconds: 8));
      if (profile == null) {
        await _repository.clearSession();
        return;
      }

      _role = session.role;
      _username = profile.username.isNotEmpty
          ? profile.username
          : session.username;
      _status = AuthStatus.authenticated;
      notifyListeners();
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
      _applyAuthResponse(response, fallbackUsername: email);
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

  Future<bool> loginWithQr(String qrPayload) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _repository
          .loginWithQr(qrPayload)
          .timeout(const Duration(seconds: 25));
      _applyAuthResponse(response);
      return true;
    } on TimeoutException {
      _errorMessage = 'QR login timed out. Check your connection and try again.';
      _status = AuthStatus.unauthenticated;
      return false;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      return false;
    } catch (e) {
      _errorMessage = kDebugMode
          ? 'QR login failed: $e'
          : 'QR login failed. Please try again.';
      _status = AuthStatus.unauthenticated;
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Called when [QrLoginController] already received a valid auth response.
  Future<bool> completeQrLogin(AuthResponse response) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();
    try {
      await _repository.persistAuthResponse(response);
      _applyAuthResponse(response);
      return true;
    } catch (e) {
      _errorMessage = kDebugMode
          ? 'Could not save session: $e'
          : 'Could not save session.';
      _status = AuthStatus.unauthenticated;
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void _applyAuthResponse(AuthResponse response, {String? fallbackUsername}) {
    _role = response.role;
    _username = response.username ??
        response.displayName ??
        response.email ??
        fallbackUsername;
    _status = AuthStatus.authenticated;
    _sessionMessage = null;
    notifyListeners();
  }

  Future<void> logout() async {
    _isLoading = true;
    notifyListeners();
    await _repository.logout();
    ScreenCache.instance.clear();
    _role = null;
    _username = null;
    _status = AuthStatus.unauthenticated;
    _sessionMessage = null;
    _isLoading = false;
    notifyListeners();
  }

  Future<void> handleUnauthorized() async {
    if (_status == AuthStatus.unauthenticated) return;
    await _repository.clearSession();
    ScreenCache.instance.clear();
    _role = null;
    _username = null;
    _status = AuthStatus.unauthenticated;
    _sessionMessage = 'Session expired. Please sign in again.';
    notifyListeners();
  }

  void clearSessionMessage() {
    _sessionMessage = null;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
