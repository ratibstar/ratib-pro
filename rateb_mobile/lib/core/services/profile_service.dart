import '../api/api_client.dart';
import '../models/auth_response.dart';
import '../models/user_role.dart';

/// Fetches role-specific data from the RATEB backend.
class ProfileService {
  ProfileService(this._client);

  final ApiClient _client;

  Future<UserProfile> getProfile(UserRole role) async {
    final payload = await _client.get('/mobile/profile.php');
    final data = payload['data'] as Map<String, dynamic>? ?? payload;
    return UserProfile.fromJson(data, role);
  }
}
