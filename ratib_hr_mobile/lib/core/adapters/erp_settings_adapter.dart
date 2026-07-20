/// SettingsPort — local prefs + ERP change-password.
library;

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/settings_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class ErpSettingsAdapter implements SettingsPort {
  ErpSettingsAdapter({
    required ErpHttpClient http,
    required ErrorMapper errors,
    required CacheStore cache,
  })  : _http = http,
        _errors = errors,
        _cache = cache;

  static const changePasswordPath = '/api/v1/hr/settings/change-password';
  static const kBiometric = 'settings.biometric';
  static const kNotifications = 'settings.notifications';
  static const kTheme = 'settings.theme_mode';

  final ErpHttpClient _http;
  final ErrorMapper _errors;
  final CacheStore _cache;

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      final body = await _http.post(
        changePasswordPath,
        body: {
          'current_password': currentPassword,
          'new_password': newPassword,
        },
      );
      if (body['success'] != true) {
        throw AppFailure(
          code: body['code']?.toString() ?? 'password_change_failed',
          message: body['message']?.toString(),
        );
      }
    } catch (e, st) {
      throw _errors.map(e, st);
    }
  }

  @override
  Future<bool> biometricEnabled() async {
    final v = await _cache.read(kBiometric);
    return v == '1' || v == 'true';
  }

  @override
  Future<void> setBiometricEnabled(bool enabled) async {
    await _cache.write(kBiometric, enabled ? '1' : '0');
  }

  @override
  Future<bool> notificationsEnabled() async {
    final v = await _cache.read(kNotifications);
    if (v == null) return true;
    return v == '1' || v == 'true';
  }

  @override
  Future<void> setNotificationsEnabled(bool enabled) async {
    await _cache.write(kNotifications, enabled ? '1' : '0');
  }

  @override
  Future<String> themeMode() async {
    return (await _cache.read(kTheme)) ?? 'system';
  }

  @override
  Future<void> setThemeMode(String mode) async {
    await _cache.write(kTheme, mode);
  }
}
