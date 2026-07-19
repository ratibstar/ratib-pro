/// RATIB HR Mobile — Phase 0 application entry.
///
/// Presentation layer only. No ERP business logic lives here.
library;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/app_theme.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const RatibHrMobileApp());
}

/// Root widget — Material 3, Arabic RTL-first, English supported.
class RatibHrMobileApp extends StatefulWidget {
  const RatibHrMobileApp({super.key});

  @override
  State<RatibHrMobileApp> createState() => _RatibHrMobileAppState();
}

class _RatibHrMobileAppState extends State<RatibHrMobileApp> {
  Locale _locale = AppConfig.defaultLocale;

  void _setLocale(Locale locale) {
    setState(() => _locale = locale);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'RATIB HR',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.light,
      locale: _locale,
      supportedLocales: AppConfig.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: AppRouter.router(
        onLocaleChanged: _setLocale,
        currentLocale: _locale,
      ),
    );
  }
}
