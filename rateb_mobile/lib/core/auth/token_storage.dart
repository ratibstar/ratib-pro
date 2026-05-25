import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/user_role.dart';

class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  static const _tokenKey = 'rateb_auth_token';
  static const _roleKey = 'rateb_user_role';
  static const _usernameKey = 'rateb_username';

  final FlutterSecureStorage _storage;

  Future<void> saveSession({
    required String token,
    required UserRole role,
    String? username,
  }) async {
    await Future.wait([
      _storage.write(key: _tokenKey, value: token),
      _storage.write(key: _roleKey, value: role.apiValue),
      if (username != null)
        _storage.write(key: _usernameKey, value: username),
    ]);
  }

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<UserRole?> readRole() async {
    final value = await _storage.read(key: _roleKey);
    return UserRole.fromString(value);
  }

  Future<String?> readUsername() => _storage.read(key: _usernameKey);

  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: _tokenKey),
      _storage.delete(key: _roleKey),
      _storage.delete(key: _usernameKey),
    ]);
  }
}
