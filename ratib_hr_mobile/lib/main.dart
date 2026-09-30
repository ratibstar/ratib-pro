/// RATEB HR Mobile — Phase C entry (enterprise ESS modules).
library;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/activation/activation_link_listener.dart';
import 'package:ratib_hr_mobile/core/activation/activation_bootstrap.dart';
import 'package:ratib_hr_mobile/core/activation/activation_install_guard.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:ratib_hr_mobile/core/activation/unified_app_guard.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/di/phase1_bootstrap.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/brand_theme_factory.dart';
import 'package:ratib_hr_mobile/core/update/app_update_banner.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ActivationInstallGuard.runBeforeLoad();
  final launchUri = await ActivationBootstrap.resolveLaunchUri();
  await CompanyActivation.load(deferBackgroundRefresh: true);
  await CompanyActivation.purgeWrongTenantForUnifiedPackage();
  if (launchUri != null) {
    await ActivationBootstrap.applyLaunchUri(launchUri);
  }
  if (launchUri == null && BrandBuild.activationCode.isNotEmpty) {
    await CompanyActivation.activate(BrandBuild.activationCode);
  }
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
  void initState() {
    super.initState();
    CompanyActivation.revision.addListener(_onActivationChanged);
  }

  @override
  void dispose() {
    CompanyActivation.revision.removeListener(_onActivationChanged);
    super.dispose();
  }

  void _onActivationChanged() {
    if (mounted) setState(() {});
  }

  String _windowTitle() {
    if (CompanyActivation.isActive) {
      final ar = _locale.languageCode == 'ar';
      final linked = CompanyActivation.localizedName(arabic: ar);
      if (linked.isNotEmpty) return linked;
    }
    final cfg = AppLocator.mobileConfiguration.current;
    if (cfg != null && cfg.displayName.isNotEmpty) return cfg.displayName;
    return AppConfig.appName;
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
        final title = _windowTitle();
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
          builder: (context, child) => UnifiedAppGuard(
            child: ActivationLinkListener(
              onCompanyChanged: widget.session.signOut,
              child: AppUpdateBanner(child: child ?? const SizedBox.shrink()),
            ),
          ),
        );
      },
    );
  }
}
