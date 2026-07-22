/// Composition root — Phases 1–A–C.
library;

import 'package:ratib_hr_mobile/core/adapters/default_error_mapper.dart';
import 'package:ratib_hr_mobile/core/adapters/dio_erp_http_client.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_attendance_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_auth_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_dashboard_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_employee_request_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_permission_request_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_inquiry_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_leave_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_me_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_mobile_config_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_notification_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_payment_methods_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_payslip_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_documents_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_profile_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_device_registry_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_ratings_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/erp_settings_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/local_offline_queue_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/secure_token_store_adapter.dart';
import 'package:ratib_hr_mobile/core/adapters/shared_preferences_cache_store.dart';
import 'package:ratib_hr_mobile/core/appearance/appearance_controller.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/device/device_registry_service.dart';
import 'package:ratib_hr_mobile/core/device/local_device_id_store.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';
import 'package:ratib_hr_mobile/core/push/firebase_push_messaging_gateway.dart';
import 'package:ratib_hr_mobile/core/push/local_notification_presenter.dart';
import 'package:ratib_hr_mobile/core/push/push_notification_service.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';
import 'package:ratib_hr_mobile/features/documents/documents_repository.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_repository.dart';
import 'package:ratib_hr_mobile/features/profile/profile_repository.dart';
import 'package:ratib_hr_mobile/core/offline/connectivity_controller.dart';
import 'package:ratib_hr_mobile/core/offline/offline_sync_service.dart';

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
  final me = ErpMeAdapter(http: http, errors: errors, cache: cache);
  final attendance = ErpAttendanceAdapter(http: http, errors: errors);
  final leave = ErpLeaveAdapter(http: http, errors: errors);
  final notifications = ErpNotificationAdapter(http: http, errors: errors);
  final mobileConfigPort = ErpMobileConfigAdapter(http: http, errors: errors);
  final mobileConfiguration = MobileConfigurationService(
    port: mobileConfigPort,
    cache: cache,
  );
  final dashboard = ErpDashboardAdapter(http: http, errors: errors);
  final employeeRequests =
      ErpEmployeeRequestAdapter(http: http, errors: errors);
  final permissionRequests =
      ErpPermissionRequestAdapter(http: http, errors: errors);
  final ratings = ErpRatingsAdapter(http: http, errors: errors);
  final inquiries = ErpInquiryAdapter(http: http, errors: errors);
  final payments = ErpPaymentMethodsAdapter(http: http, errors: errors);
  final settings = ErpSettingsAdapter(
    http: http,
    errors: errors,
    cache: cache,
  );
  final appearance = AppearanceController(settings: settings);

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
  AppLocator.registerPhaseC(
    dashboard: dashboard,
    employeeRequests: employeeRequests,
    permissionRequests: permissionRequests,
    ratings: ratings,
    inquiries: inquiries,
    payments: payments,
    settings: settings,
    appearance: appearance,
  );
  final offlineQueue = LocalOfflineQueueAdapter(cache: cache);
  final attendanceRepository = AttendanceRepository(
    attendance: attendance,
    offlineQueue: offlineQueue,
    cache: cache,
  );
  final leaveRepository = LeaveRepository(
    leave: leave,
    offlineQueue: offlineQueue,
    cache: cache,
  );
  AppLocator.registerPhaseD(
    offlineQueue: offlineQueue,
    attendanceRepository: attendanceRepository,
    leaveRepository: leaveRepository,
  );
  final payslips = ErpPayslipAdapter(http: http, errors: errors);
  final documents = ErpDocumentsAdapter(http: http, errors: errors);
  AppLocator.registerPhaseF(
    payslips: payslips,
    documents: documents,
    payslipRepository: PayslipRepository(payslips: payslips, cache: cache),
    documentsRepository: DocumentsRepository(documents: documents, cache: cache),
  );
  final profile = ErpProfileAdapter(http: http, errors: errors);
  AppLocator.registerPhaseG(
    profile: profile,
    profileRepository: ProfileRepository(profile: profile, cache: cache),
  );
  final connectivity = ConnectivityController(http: http);
  final offlineSync = OfflineSyncService(
    queue: offlineQueue,
    attendance: attendance,
    leave: leave,
    connectivity: connectivity,
  );
  AppLocator.registerPhaseH(
    connectivity: connectivity,
    offlineSync: offlineSync,
  );
  final deviceRegistry = ErpDeviceRegistryAdapter(http: http, errors: errors);
  final deviceRegistryService = DeviceRegistryService(
    port: deviceRegistry,
    deviceIds: LocalDeviceIdStore(cache: cache),
  );
  AppLocator.registerPhaseJ(
    deviceRegistry: deviceRegistry,
    deviceRegistryService: deviceRegistryService,
  );
  final pushNotifications = PushNotificationService(
    devices: deviceRegistry,
    deviceIds: LocalDeviceIdStore(cache: cache),
    messaging: FirebasePushMessagingGateway(),
    localNotifications: FlutterLocalNotificationPresenter(),
  );
  AppLocator.registerPhaseI3(pushNotifications: pushNotifications);
}
