import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_repository.dart';
import 'core/auth/token_storage.dart';
import 'core/config/app_config.dart';
import 'core/config/company_activation.dart';
import 'core/routing/app_router.dart';
import 'core/services/rateb_api_service.dart';
import 'core/theme/app_theme.dart';
import 'core/api/api_client.dart';
import 'features/auth/providers/auth_provider.dart';
import 'l10n/app_localizations.dart';
import 'l10n/locale_controller.dart';
import 'shared/widgets/offline_banner.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  _configureErrorHandling();
  await CompanyActivation.load();
  final localeController = LocaleController();
  await localeController.load();
  runApp(RatebMobileApp(localeController: localeController));
}

void _configureErrorHandling() {
  FlutterError.onError = (details) {
    if (kReleaseMode) {
      debugPrint('Unhandled Flutter error');
      return;
    }
    FlutterError.presentError(details);
    debugPrint('FlutterError: ${details.exceptionAsString()}');
  };

  PlatformDispatcher.instance.onError = (error, stack) {
    if (kReleaseMode) {
      debugPrint('Unhandled async error');
      return true;
    }
    debugPrint('AsyncError: $error');
    return false;
  };
}

class RatebMobileApp extends StatefulWidget {
  const RatebMobileApp({super.key, required this.localeController});

  final LocaleController localeController;

  @override
  State<RatebMobileApp> createState() => _RatebMobileAppState();
}

class _RatebMobileAppState extends State<RatebMobileApp> {
  late final TokenStorage _tokenStorage;
  late final AuthProvider _authProvider;
  late final AppRouter _appRouter;

  @override
  void initState() {
    super.initState();
    _tokenStorage = TokenStorage();
    late AuthProvider authProvider;
    authProvider = AuthProvider(
      repository: AuthRepository(
        tokenStorage: _tokenStorage,
        onUnauthorized: () => authProvider.handleUnauthorized(),
      ),
    )..bootstrap();
    _authProvider = authProvider;
    _appRouter = AppRouter(_authProvider);

    RatebApiService.instance.init(
      apiClient: ApiClient(
        tokenProvider: _tokenStorage.readToken,
        onUnauthorized: _authProvider.handleUnauthorized,
      ),
    );
  }

  @override
  void dispose() {
    _authProvider.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _authProvider),
        ChangeNotifierProvider.value(value: widget.localeController),
      ],
      child: Consumer<LocaleController>(
        builder: (context, localeController, _) => MaterialApp.router(
          title: AppConfig.appName,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: ThemeMode.system,
          locale: localeController.locale,
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          routerConfig: _appRouter.router,
          builder: (context, child) {
            return OfflineBannerHost(
              child: child ?? const SizedBox.shrink(),
            );
          },
        ),
      ),
    );
  }
}
