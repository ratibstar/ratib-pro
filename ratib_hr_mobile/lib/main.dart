/// RATIB HR Mobile — Phase 1 entry (ERP authentication).
library;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/di/phase1_bootstrap.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/app_theme.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  bootstrapPhase1();
  final session = AuthSession();
  AppLocator.bindSessionHandlers(
    onUnauthorized: session.handleUnauthorized,
    onSignOut: session.signOut,
  );
  await session.restore();
  runApp(RatibHrMobileApp(session: session));
}

class RatibHrMobileApp extends StatefulWidget {
  const RatibHrMobileApp({super.key, required this.session});

  final AuthSession session;

  @override
  State<RatibHrMobileApp> createState() => _RatibHrMobileAppState();
}

class _RatibHrMobileAppState extends State<RatibHrMobileApp> {
  Locale _locale = AppConfig.defaultLocale;
  late final GoRouter _router = AppRouter.router(
    session: widget.session,
    onLocaleChanged: _setLocale,
  );

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
      themeMode: ThemeMode.system,
      locale: _locale,
      supportedLocales: AppConfig.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: _router,
    );
  }
}
