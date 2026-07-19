/// Secure token store — opaque ERP API token only. Never stores passwords.
library;

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:ratib_hr_mobile/core/contracts/secure_token_store.dart';

final class SecureTokenStoreAdapter implements SecureTokenStore {
  SecureTokenStoreAdapter({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  static const _tokenKey = 'ratib_hr_erp_api_token';
  static const _expiresKey = 'ratib_hr_erp_api_token_expires';

  final FlutterSecureStorage _storage;

  @override
  Future<void> writeToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<void> writeExpiresAt(String? expiresAt) async {
    if (expiresAt == null || expiresAt.isEmpty) {
      await _storage.delete(key: _expiresKey);
      return;
    }
    await _storage.write(key: _expiresKey, value: expiresAt);
  }

  Future<String?> readExpiresAt() => _storage.read(key: _expiresKey);

  @override
  Future<String?> readToken() => _storage.read(key: _tokenKey);

  @override
  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _expiresKey);
  }
}
