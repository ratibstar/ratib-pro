/// Phase 1–A composition root — auth, me, home ports + mobile config.
library;

import 'package:ratib_hr_mobile/core/adapters/default_error_mapper.dart';
import 'package:ratib_hr_mobile/core/adapters/dio_erp_http_client.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_auth_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_mobile_config_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_notification_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/secure_token_store_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/shared_preferences_cache_store.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';

/// Registers Phase 1–A ESS + MobileConfiguration. Call once from [main].
void bootstrapPhase1() {
  bootstrapEssCore();
}

void bootstrapEssCore() {
  const environment = DartDefineAppEnvironment();
  const errors = DefaultErrorMapper();
  final tokenStore = SecureTokenStoreAdapter();
  final cache = SharedPreferencesCacheStore();
  final http = DioErpHttpClient(
    environment: environment,
    tokenStore: tokenStore,
  );
  final auth = ErpAuthAdapter(
    http: http,
    tokenStore: tokenStore,
    errors: errors,
  );
  final me = ErpMeAdapter(http: http, errors: errors);
  final attendance = ErpAttendanceAdapter(http: http, errors: errors);
  final leave = ErpLeaveAdapter(http: http, errors: errors);
  final notifications = ErpNotificationAdapter(http: http, errors: errors);
  final mobileConfigPort = ErpMobileConfigAdapter(http: http, errors: errors);
  final mobileConfiguration = MobileConfigurationService(
    port: mobileConfigPort,
    cache: cache,
  );

  AppLocator.registerPhase1(
    environment: environment,
    http: http,
    auth: auth,
    tokenStore: tokenStore,
    errors: errors,
  );
  AppLocator.registerPhase2(me: me);
  AppLocator.registerPhase3(
    attendance: attendance,
    leave: leave,
    notifications: notifications,
  );
  AppLocator.registerPhaseA(
    mobileConfigPort: mobileConfigPort,
    mobileConfiguration: mobileConfiguration,
    cache: cache,
  );
}
