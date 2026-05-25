import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_role.dart';

/// Session storage — memory cache + SharedPreferences (all platforms).
class TokenStorage {
  static const _tokenKey = 'rateb_auth_token';
  static const _roleKey = 'rateb_user_role';
  static const _usernameKey = 'rateb_username';

  String? _cachedToken;
  UserRole? _cachedRole;
  String? _cachedUsername;
  SharedPreferences? _prefs;

  Future<SharedPreferences?> _preferences() async {
    try {
      return _prefs ??= await SharedPreferences.getInstance();
    } catch (_) {
      return null;
    }
  }

  Future<void> saveSession({
    required String token,
    required UserRole role,
    String? username,
  }) async {
    _cachedToken = token;
    _cachedRole = role;
    _cachedUsername = username;

    final prefs = await _preferences();
    if (prefs == null) return;

    await prefs.setString(_tokenKey, token);
    await prefs.setString(_roleKey, role.apiValue);
    if (username != null) {
      await prefs.setString(_usernameKey, username);
    }
  }

  Future<String?> readToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) {
      return _cachedToken;
    }
    final prefs = await _preferences();
    if (prefs == null) return null;
    _cachedToken = prefs.getString(_tokenKey);
    return _cachedToken;
  }

  Future<UserRole?> readRole() async {
    if (_cachedRole != null) return _cachedRole;
    final prefs = await _preferences();
    if (prefs == null) return null;
    _cachedRole = UserRole.fromString(prefs.getString(_roleKey));
    return _cachedRole;
  }

  Future<String?> readUsername() async {
    if (_cachedUsername != null) return _cachedUsername;
    final prefs = await _preferences();
    if (prefs == null) return null;
    _cachedUsername = prefs.getString(_usernameKey);
    return _cachedUsername;
  }

  Future<void> clear() async {
    _cachedToken = null;
    _cachedRole = null;
    _cachedUsername = null;
    final prefs = await _preferences();
    if (prefs == null) return;
    await prefs.remove(_tokenKey);
    await prefs.remove(_roleKey);
    await prefs.remove(_usernameKey);
  }
}
