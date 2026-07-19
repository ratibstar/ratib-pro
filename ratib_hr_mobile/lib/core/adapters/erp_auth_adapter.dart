/// AuthPort → existing RATIB ERP `POST /api/v1/auth/token`.
///
/// Reuses ERP credential verification. No PIN/OTP/biometric.
library;

import 'package:ratib_hr_mobile/core/adapters/secure_token_store_adapter.dart';
import 'package:ratib_hr_mobile/core/contracts/auth_port.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpAuthAdapter implements AuthPort {
  ErpAuthAdapter({
    required ErpHttpClient http,
    required SecureTokenStoreAdapter tokenStore,
    required ErrorMapper errors,
  })  : _http = http,
        _tokenStore = tokenStore,
        _errors = errors;

  /// Existing ERP route (routes/modules/api.php).
  static const tokenPath = '/api/v1/auth/token';

  final ErpHttpClient _http;
  final SecureTokenStoreAdapter _tokenStore;
  final ErrorMapper _errors;

  @override
  Future<void> signIn({
    required String identifier,
    required String secret,
  }) async {
    try {
      // ERP ApiController::createToken expects JSON keys email + password.
      final body = await _http.post(
        tokenPath,
        body: <String, Object?>{
          'email': identifier.trim(),
          'password': secret,
        },
      );

      final success = body['success'] == true;
      final token = body['token']?.toString() ?? '';
      if (!success || token.isEmpty) {
        throw AppFailure(
          code: 'unauthorized',
          message: body['message']?.toString(),
        );
      }

      await _tokenStore.writeToken(token);
      await _tokenStore.writeExpiresAt(body['expires_at']?.toString());
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<void> signOut() async {
    // ERP has no public revoke endpoint for this token flow — clear local only.
    await _tokenStore.clearToken();
  }

  @override
  Future<bool> hasSession() async {
    final token = await _tokenStore.readToken();
    return token != null && token.isNotEmpty;
  }

  @override
  Future<void> refreshSession() async {
    // Existing token API has no refresh endpoint — keep token until expiry/401.
    final ok = await hasSession();
    if (!ok) {
      throw const AppFailure(code: 'unauthorized', message: 'No session');
    }
  }
}
