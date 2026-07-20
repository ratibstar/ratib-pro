/// Local appearance prefs (theme) — persisted via SettingsPort.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/contracts/settings_port.dart';

final class AppearanceController extends ChangeNotifier {
  AppearanceController({required SettingsPort settings}) : _settings = settings;

  final SettingsPort _settings;
  ThemeMode _themeMode = ThemeMode.system;

  ThemeMode get themeMode => _themeMode;

  Future<void> load() async {
    final raw = await _settings.themeMode();
    _themeMode = _parse(raw);
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    await _settings.setThemeMode(_serialize(mode));
    notifyListeners();
  }

  static ThemeMode _parse(String raw) {
    switch (raw) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  static String _serialize(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return 'light';
      case ThemeMode.dark:
        return 'dark';
      case ThemeMode.system:
        return 'system';
    }
  }
}
