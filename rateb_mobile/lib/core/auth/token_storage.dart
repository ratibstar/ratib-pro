import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/user_role.dart';

/// Session storage with an in-memory cache so login never blocks on I/O.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? secureStorage})
      : _secureStorage = secureStorage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  static const _tokenKey = 'rateb_auth_token';
  static const _roleKey = 'rateb_user_role';
  static const _usernameKey = 'rateb_username';

  final FlutterSecureStorage _secureStorage;

  String? _cachedToken;
  UserRole? _cachedRole;
  String? _cachedUsername;

  Future<void> saveSession({
    required String token,
    required UserRole role,
    String? username,
  }) async {
    _cachedToken = token;
    _cachedRole = role;
    _cachedUsername = username;

    // Web: memory-only (secure storage / shared_preferences hang or throw on localhost).
    if (kIsWeb) return;

    try {
      await Future.wait([
        _secureStorage.write(key: _tokenKey, value: token),
        _secureStorage.write(key: _roleKey, value: role.apiValue),
        if (username != null)
          _secureStorage.write(key: _usernameKey, value: username),
      ]);
    } catch (_) {
      // Memory cache still holds the active session.
    }
  }

  Future<String?> readToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) {
      return _cachedToken;
    }
    if (kIsWeb) return null;

    try {
      _cachedToken = await _secureStorage.read(key: _tokenKey);
    } catch (_) {
      return null;
    }
    return _cachedToken;
  }

  Future<UserRole?> readRole() async {
    if (_cachedRole != null) return _cachedRole;
    if (kIsWeb) return null;

    try {
      final value = await _secureStorage.read(key: _roleKey);
      _cachedRole = UserRole.fromString(value);
    } catch (_) {
      return null;
    }
    return _cachedRole;
  }

  Future<String?> readUsername() async {
    if (_cachedUsername != null) return _cachedUsername;
    if (kIsWeb) return null;

    try {
      _cachedUsername = await _secureStorage.read(key: _usernameKey);
    } catch (_) {
      return null;
    }
    return _cachedUsername;
  }

  Future<void> clear() async {
    _cachedToken = null;
    _cachedRole = null;
    _cachedUsername = null;
    if (kIsWeb) return;

    try {
      await Future.wait([
        _secureStorage.delete(key: _tokenKey),
        _secureStorage.delete(key: _roleKey),
        _secureStorage.delete(key: _usernameKey),
      ]);
    } catch (_) {}
  }
}
