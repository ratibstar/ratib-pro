/// RATEB HR Mobile — Phase C entry (enterprise ESS modules).
library;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/di/phase1_bootstrap.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/brand_theme_factory.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  bootstrapPhase1();
  await AppLocator.appearance.load();
  final session = AuthSession();
  AppLocator.bindSessionHandlers(
    onUnauthorized: session.handleUnauthorized,
    onSignOut: session.signOut,
  );
  await session.restore();
  runApp(RatebHrMobileApp(session: session));
}

class RatebHrMobileApp extends StatefulWidget {
  const RatebHrMobileApp({super.key, required this.session});

  final AuthSession session;

  @override
  State<RatebHrMobileApp> createState() => _RatebHrMobileAppState();
}

class _RatebHrMobileAppState extends State<RatebHrMobileApp> {
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
    return ListenableBuilder(
      listenable: Listenable.merge([
        AppLocator.mobileConfiguration,
        AppLocator.appearance,
      ]),
      builder: (context, _) {
        final cfg = AppLocator.mobileConfiguration.current;
        final title = (cfg?.displayName.isNotEmpty == true)
            ? cfg!.displayName
            : AppConfig.appName;
        return MaterialApp.router(
          title: title,
          debugShowCheckedModeBanner: false,
          theme: BrandThemeFactory.lightFrom(cfg),
          darkTheme: BrandThemeFactory.darkFrom(cfg),
          themeMode: AppLocator.appearance.themeMode,
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
      },
    );
  }
}
