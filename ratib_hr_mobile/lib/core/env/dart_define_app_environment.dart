/// Loads [AppEnvironment] from `--dart-define` only. No hardcoded hosts.
library;

import 'package:ratib_hr_mobile/core/env/app_environment.dart';
import 'package:ratib_hr_mobile/core/env/app_flavor.dart';

final class DartDefineAppEnvironment implements AppEnvironment {
  const DartDefineAppEnvironment();

  /// Compile-time ERP origin, e.g. `--dart-define=ERP_BASE_URL=https://example.com/rateb-erp/public`
  static const String _baseUrl = String.fromEnvironment('ERP_BASE_URL');

  /// `--dart-define=APP_FLAVOR=dev|development|staging|production`
  /// Native Gradle flavors use: `dev` | `staging` | `production`.
  static const String _flavorRaw = String.fromEnvironment(
    'APP_FLAVOR',
    defaultValue: 'development',
  );

  @override
  AppFlavor get flavor {
    switch (_flavorRaw.toLowerCase().trim()) {
      case 'staging':
        return AppFlavor.staging;
      case 'production':
        return AppFlavor.production;
      case 'dev':
      case 'development':
      default:
        return AppFlavor.development;
    }
  }

  @override
  String get erpBaseUrl => _baseUrl.trim().replaceAll(RegExp(r'/+$'), '');

  @override
  bool get apisEnabled => erpBaseUrl.isNotEmpty;

  @override
  String get channelLabel => flavor.name;
}
