import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_role.dart';

/// Persists auth session. Web uses SharedPreferences (secure storage throws
/// OperationError in many browsers on localhost); native uses secure storage.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? secureStorage})
      : _secureStorage = secureStorage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
              webOptions: WebOptions(useSessionStorage: true),
            );

  static const _tokenKey = 'rateb_auth_token';
  static const _roleKey = 'rateb_user_role';
  static const _usernameKey = 'rateb_username';

  final FlutterSecureStorage _secureStorage;
  SharedPreferences? _webPrefs;

  Future<SharedPreferences> _webPreferences() async {
    return _webPrefs ??= await SharedPreferences.getInstance();
  }

  Future<void> saveSession({
    required String token,
    required UserRole role,
    String? username,
  }) async {
    if (kIsWeb) {
      final prefs = await _webPreferences();
      await prefs.setString(_tokenKey, token);
      await prefs.setString(_roleKey, role.apiValue);
      if (username != null) {
        await prefs.setString(_usernameKey, username);
      }
      return;
    }

    await Future.wait([
      _secureStorage.write(key: _tokenKey, value: token),
      _secureStorage.write(key: _roleKey, value: role.apiValue),
      if (username != null)
        _secureStorage.write(key: _usernameKey, value: username),
    ]);
  }

  Future<String?> readToken() async {
    if (kIsWeb) {
      return (await _webPreferences()).getString(_tokenKey);
    }
    return _secureStorage.read(key: _tokenKey);
  }

  Future<UserRole?> readRole() async {
    final value = kIsWeb
        ? (await _webPreferences()).getString(_roleKey)
        : await _secureStorage.read(key: _roleKey);
    return UserRole.fromString(value);
  }

  Future<String?> readUsername() async {
    if (kIsWeb) {
      return (await _webPreferences()).getString(_usernameKey);
    }
    return _secureStorage.read(key: _usernameKey);
  }

  Future<void> clear() async {
    if (kIsWeb) {
      final prefs = await _webPreferences();
      await prefs.remove(_tokenKey);
      await prefs.remove(_roleKey);
      await prefs.remove(_usernameKey);
      return;
    }

    await Future.wait([
      _secureStorage.delete(key: _tokenKey),
      _secureStorage.delete(key: _roleKey),
      _secureStorage.delete(key: _usernameKey),
    ]);
  }
}
