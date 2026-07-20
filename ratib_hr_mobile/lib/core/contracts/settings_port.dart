/// App settings — local prefs + ERP change-password.
library;

abstract interface class SettingsPort {
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  });

  Future<bool> biometricEnabled();

  Future<void> setBiometricEnabled(bool enabled);

  Future<bool> notificationsEnabled();

  Future<void> setNotificationsEnabled(bool enabled);

  Future<String> themeMode(); // system | light | dark

  Future<void> setThemeMode(String mode);
}
