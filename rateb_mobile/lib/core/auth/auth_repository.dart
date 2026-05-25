import '../api/api_client.dart';
import '../api/api_endpoints.dart';import '../api/api_exception.dart';
import '../models/auth_response.dart';
import '../models/user_role.dart';
import 'token_storage.dart';

class AuthRepository {
  AuthRepository({
    TokenStorage? tokenStorage,
    ApiClient? apiClient,
    ApiClient? loginClient,
    UnauthorizedHandler? onUnauthorized,
  }) : _tokenStorage = tokenStorage ?? TokenStorage() {
    _loginClient = loginClient ?? ApiClient();
    _apiClient = apiClient ??
        ApiClient(
          tokenProvider: _tokenStorage.readToken,
          onUnauthorized: onUnauthorized,
        );
  }

  final TokenStorage _tokenStorage;
  late final ApiClient _loginClient;
  late final ApiClient _apiClient;

  Future<AuthResponse> login({
    required String email,
    required String password,
  }) async {
    final payload = await _loginClient.post(
      ApiEndpoints.authLogin,
      body: {
        'email': email.trim(),
        'password': password,
      },
    );

    final auth = AuthResponse.fromJson(payload);
    await _tokenStorage.saveSession(
      token: auth.token,
      role: auth.role,
      username: auth.username ?? auth.email,
    );
    return auth;
  }

  Future<UserProfile?> fetchProfile(UserRole role) async {
    try {
      final payload = await _apiClient.get(ApiEndpoints.authProfile);
      final data = payload['data'] as Map<String, dynamic>? ?? payload;
      return UserProfile.fromJson(data, role);
    } on ApiException catch (e) {
      if (e.statusCode == 401) return null;
      rethrow;
    }
  }

  Future<void> logout() async {
    try {
      await _apiClient.post(ApiEndpoints.authLogout);
    } catch (_) {
      // Best-effort server logout; always clear local session.
    }
    await clearSession();
  }

  Future<void> clearSession() async {
    await _tokenStorage.clear();
  }

  Future<AuthSession?> restoreSession() async {
    final token = await _tokenStorage.readToken();
    final role = await _tokenStorage.readRole();
    final username = await _tokenStorage.readUsername();
    if (token == null || role == null) return null;
    return AuthSession(token: token, role: role, username: username);
  }
}

class AuthSession {
  const AuthSession({
    required this.token,
    required this.role,
    this.username,
  });

  final String token;
  final UserRole role;
  final String? username;
}
