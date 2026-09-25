import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// App language (Arabic by default), persisted on the device.
class LocaleController extends ChangeNotifier {
  static const _prefsKey = 'app_locale';

  Locale _locale = const Locale('ar');

  Locale get locale => _locale;
  bool get isArabic => _locale.languageCode == 'ar';

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final code = prefs.getString(_prefsKey);
      if (code == 'ar' || code == 'en') {
        _locale = Locale(code!);
      }
    } catch (_) {
      // Keep the Arabic default when preferences are unavailable.
    }
  }

  Future<void> setLocale(Locale locale) async {
    if (locale.languageCode == _locale.languageCode) return;
    _locale = Locale(locale.languageCode);
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, _locale.languageCode);
    } catch (_) {
      // The choice still applies for this session.
    }
  }

  Future<void> toggle() =>
      setLocale(isArabic ? const Locale('en') : const Locale('ar'));
}
