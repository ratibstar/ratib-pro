/// Phase 1–3 composition root — auth, me, home ports.
library;

import 'package:ratib_hr_mobile/core/adapters/default_error_mapper.dart';
import 'package:ratib_hr_mobile/core/adapters/dio_erp_http_client.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_auth_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_notification_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/secure_token_store_adapter.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';

/// Registers Phase 1–3 ESS ports. Call once from [main].
void bootstrapPhase1() {
  bootstrapEssCore();
}

void bootstrapEssCore() {
  const environment = DartDefineAppEnvironment();
  const errors = DefaultErrorMapper();
  final tokenStore = SecureTokenStoreAdapter();
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
}
