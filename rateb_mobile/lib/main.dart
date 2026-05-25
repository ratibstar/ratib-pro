import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_repository.dart';
import 'core/auth/token_storage.dart';
import 'core/config/app_config.dart';
import 'core/routing/app_router.dart';
import 'core/services/rateb_api_service.dart';
import 'core/theme/app_theme.dart';
import 'core/api/api_client.dart';
import 'features/auth/providers/auth_provider.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    debugPrint('FlutterError: ${details.exceptionAsString()}');
  };
  runApp(const RatebMobileApp());
}

class RatebMobileApp extends StatefulWidget {
  const RatebMobileApp({super.key});

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
    return ChangeNotifierProvider.value(
      value: _authProvider,
      child: MaterialApp.router(
        title: AppConfig.appName,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: ThemeMode.system,
        routerConfig: _appRouter.router,
      ),
    );
  }
}
