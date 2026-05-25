import 'user_role.dart';

class AuthResponse {
  const AuthResponse({
    required this.token,
    required this.role,
    this.userId,
    this.username,
    this.email,
    this.displayName,
  });

  final String token;
  final UserRole role;
  final int? userId;
  final String? username;
  final String? email;
  final String? displayName;

  factory AuthResponse.fromJson(Map<String, dynamic> json) {
    final role = UserRole.fromString(json['role'] as String?);
    if (role == null) {
      throw FormatException('Unknown role: ${json['role']}');
    }
    final token = json['token'] as String?;
    if (token == null || token.isEmpty) {
      throw FormatException('Missing token in auth response');
    }
    return AuthResponse(
      token: token,
      role: role,
      userId: json['user_id'] as int?,
      username: json['username'] as String?,
      email: json['email'] as String?,
      displayName: json['display_name'] as String?,
    );
  }
}

class UserProfile {
  const UserProfile({
    required this.userId,
    required this.username,
    required this.role,
    this.email,
    this.phone,
    this.countryName,
    this.status,
  });

  final int userId;
  final String username;
  final UserRole role;
  final String? email;
  final String? phone;
  final String? countryName;
  final String? status;

  factory UserProfile.fromJson(Map<String, dynamic> json, UserRole role) {
    return UserProfile(
      userId: (json['user_id'] as num?)?.toInt() ?? 0,
      username: json['username'] as String? ?? '',
      role: role,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      countryName: json['country_name'] as String?,
      status: json['status'] as String?,
    );
  }
}
