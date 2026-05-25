import '../../core/api/api_client.dart';
import '../../core/api/api_endpoints.dart';
import '../../core/models/auth_response.dart';

/// QR identity login API (mobile workforce badges).
class QrService {
  QrService({ApiClient? loginClient}) : _client = loginClient ?? ApiClient();

  final ApiClient _client;

  Future<AuthResponse> loginWithPayload(String qrPayload) async {
    final payload = await _client.post(
      ApiEndpoints.authQrLogin,
      body: {'qr_payload': qrPayload.trim()},
    );
    return AuthResponse.fromJson(payload);
  }

  Future<Map<String, dynamic>> generateForUser({
    required int userId,
    required ApiClient authenticatedClient,
    int ttlSeconds = 600,
    String accountType = 'staff',
  }) async {
    final payload = await authenticatedClient.post(
      ApiEndpoints.authQrGenerate,
      body: {
        'user_id': userId,
        'ttl_seconds': ttlSeconds,
        'account_type': accountType,
      },
    );
    return payload['data'] as Map<String, dynamic>? ?? payload;
  }
}
