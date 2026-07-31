/// Dependency injection — ESS + Phase A/C/D ports.
library;

import 'package:ratib_hr_mobile/core/appearance/appearance_controller.dart';
import 'package:ratib_hr_mobile/core/contracts/contracts.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';
import 'package:ratib_hr_mobile/features/documents/documents_repository.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';
import 'package:ratib_hr_mobile/features/payslips/payslip_repository.dart';
import 'package:ratib_hr_mobile/features/profile/profile_repository.dart';
import 'package:ratib_hr_mobile/core/offline/connectivity_controller.dart';
import 'package:ratib_hr_mobile/core/offline/offline_sync_service.dart';
import 'package:ratib_hr_mobile/core/device/device_registry_service.dart';
import 'package:ratib_hr_mobile/core/push/push_notification_service.dart';

abstract final class AppLocator {
  static AppEnvironment? _environment;
  static ErpHttpClient? _http;
  static AuthPort? _auth;
  static MePort? _me;
  static SecureTokenStore? _tokenStore;
  static ErrorMapper? _errors;
  static AttendancePort? _attendance;
  static LeavePort? _leave;
  static NotificationPort? _notifications;
  static MobileConfigPort? _mobileConfigPort;
  static MobileConfigurationService? _mobileConfiguration;
  static CacheStore? _cache;
  static DashboardPort? _dashboard;
  static EmployeeRequestPort? _employeeRequests;
  static PermissionRequestPort? _permissionRequests;
  static RatingsPort? _ratings;
  static InquiryPort? _inquiries;
  static PaymentMethodsPort? _payments;
  static SettingsPort? _settings;
  static AppearanceController? _appearance;
  static OfflineQueuePort? _offlineQueue;
  static AttendanceRepository? _attendanceRepository;
  static LeaveRepository? _leaveRepository;
  static PayslipPort? _payslips;
  static DocumentsPort? _documents;
  static PayslipRepository? _payslipRepository;
  static DocumentsRepository? _documentsRepository;
  static ProfilePort? _profile;
  static ProfileRepository? _profileRepository;
  static ConnectivityController? _connectivity;
  static OfflineSyncService? _offlineSync;
  static DeviceRegistryPort? _deviceRegistry;
  static DeviceRegistryService? _deviceRegistryService;
  static PushNotificationService? _pushNotifications;
  static BiometricUnlock? _biometric;
  static void Function()? _unauthorizedHandler;
  static Future<void> Function()? _signOutHandler;

  static Never _notRegistered(String name) {
    throw StateError(
      'AppLocator.$name is not registered. '
      'Bind in phase bootstrap before use.',
    );
  }

  static void registerPhase1({
    required AppEnvironment environment,
    required ErpHttpClient http,
    required AuthPort auth,
    required SecureTokenStore tokenStore,
    required ErrorMapper errors,
    BiometricUnlock? biometric,
  }) {
    _environment = environment;
    _http = http;
    _auth = auth;
    _tokenStore = tokenStore;
    _errors = errors;
    if (biometric != null) {
      _biometric = biometric;
    }
  }

  static void registerPhase2({required MePort me}) {
    _me = me;
  }

  static void registerPhase3({
    required AttendancePort attendance,
    required LeavePort leave,
    required NotificationPort notifications,
  }) {
    _attendance = attendance;
    _leave = leave;
    _notifications = notifications;
  }

  static void registerPhaseA({
    required MobileConfigPort mobileConfigPort,
    required MobileConfigurationService mobileConfiguration,
    required CacheStore cache,
  }) {
    _mobileConfigPort = mobileConfigPort;
    _mobileConfiguration = mobileConfiguration;
    _cache = cache;
  }

  static void registerPhaseC({
    required DashboardPort dashboard,
    required EmployeeRequestPort employeeRequests,
    required PermissionRequestPort permissionRequests,
    required RatingsPort ratings,
    required InquiryPort inquiries,
    required PaymentMethodsPort payments,
    required SettingsPort settings,
    required AppearanceController appearance,
  }) {
    _dashboard = dashboard;
    _employeeRequests = employeeRequests;
    _permissionRequests = permissionRequests;
    _ratings = ratings;
    _inquiries = inquiries;
    _payments = payments;
    _settings = settings;
    _appearance = appearance;
  }

  static void registerPhaseD({
    required OfflineQueuePort offlineQueue,
    required AttendanceRepository attendanceRepository,
    required LeaveRepository leaveRepository,
  }) {
    _offlineQueue = offlineQueue;
    _attendanceRepository = attendanceRepository;
    _leaveRepository = leaveRepository;
  }

  static void registerPhaseF({
    required PayslipPort payslips,
    required DocumentsPort documents,
    required PayslipRepository payslipRepository,
    required DocumentsRepository documentsRepository,
  }) {
    _payslips = payslips;
    _documents = documents;
    _payslipRepository = payslipRepository;
    _documentsRepository = documentsRepository;
  }

  static void registerPhaseG({
    required ProfilePort profile,
    required ProfileRepository profileRepository,
  }) {
    _profile = profile;
    _profileRepository = profileRepository;
  }

  static void registerPhaseH({
    required ConnectivityController connectivity,
    required OfflineSyncService offlineSync,
  }) {
    _connectivity = connectivity;
    _offlineSync = offlineSync;
  }

  static void registerPhaseJ({
    required DeviceRegistryPort deviceRegistry,
    required DeviceRegistryService deviceRegistryService,
  }) {
    _deviceRegistry = deviceRegistry;
    _deviceRegistryService = deviceRegistryService;
  }

  static void registerPhaseI3({
    required PushNotificationService pushNotifications,
  }) {
    _pushNotifications = pushNotifications;
  }

  static void bindSessionHandlers({
    required void Function() onUnauthorized,
    required Future<void> Function() onSignOut,
  }) {
    _unauthorizedHandler = onUnauthorized;
    _signOutHandler = onSignOut;
  }

  static void notifyUnauthorized() {
    _unauthorizedHandler?.call();
  }

  static Future<void> signOut() async {
    final fn = _signOutHandler;
    if (fn != null) {
      await fn();
    }
  }

  static AppEnvironment get environment =>
      _environment ?? _notRegistered('environment');
  static ErpHttpClient get http => _http ?? _notRegistered('http');
  static AuthPort get auth => _auth ?? _notRegistered('auth');
  static MePort get me => _me ?? _notRegistered('me');
  static SecureTokenStore get tokenStore =>
      _tokenStore ?? _notRegistered('tokenStore');
  static ErrorMapper get errors => _errors ?? _notRegistered('errors');
  static AttendancePort get attendance =>
      _attendance ?? _notRegistered('attendance');
  static LeavePort get leave => _leave ?? _notRegistered('leave');
  static NotificationPort get notifications =>
      _notifications ?? _notRegistered('notifications');
  static CacheStore get cache => _cache ?? _notRegistered('cache');
  static MobileConfigPort get mobileConfigPort =>
      _mobileConfigPort ?? _notRegistered('mobileConfigPort');
  static MobileConfigurationService get mobileConfiguration =>
      _mobileConfiguration ?? _notRegistered('mobileConfiguration');
  static DashboardPort get dashboard =>
      _dashboard ?? _notRegistered('dashboard');
  static EmployeeRequestPort get employeeRequests =>
      _employeeRequests ?? _notRegistered('employeeRequests');
  static PermissionRequestPort get permissionRequests =>
      _permissionRequests ?? _notRegistered('permissionRequests');
  static RatingsPort get ratings => _ratings ?? _notRegistered('ratings');
  static InquiryPort get inquiries =>
      _inquiries ?? _notRegistered('inquiries');
  static PaymentMethodsPort get payments =>
      _payments ?? _notRegistered('payments');
  static SettingsPort get settings =>
      _settings ?? _notRegistered('settings');
  static AppearanceController get appearance =>
      _appearance ?? _notRegistered('appearance');
  static OfflineQueuePort get offlineQueue =>
      _offlineQueue ?? _notRegistered('offlineQueue');
  static AttendanceRepository get attendanceRepository =>
      _attendanceRepository ?? _notRegistered('attendanceRepository');
  static LeaveRepository get leaveRepository =>
      _leaveRepository ?? _notRegistered('leaveRepository');
  static PayslipPort get payslips => _payslips ?? _notRegistered('payslips');
  static DocumentsPort get documents =>
      _documents ?? _notRegistered('documents');
  static PayslipRepository get payslipRepository =>
      _payslipRepository ?? _notRegistered('payslipRepository');
  static DocumentsRepository get documentsRepository =>
      _documentsRepository ?? _notRegistered('documentsRepository');
  static ProfilePort get profile => _profile ?? _notRegistered('profile');
  static ProfileRepository get profileRepository =>
      _profileRepository ?? _notRegistered('profileRepository');
  static ConnectivityController get connectivity =>
      _connectivity ?? _notRegistered('connectivity');
  static OfflineSyncService get offlineSync =>
      _offlineSync ?? _notRegistered('offlineSync');
  static DeviceRegistryPort get deviceRegistry =>
      _deviceRegistry ?? _notRegistered('deviceRegistry');
  static DeviceRegistryService get deviceRegistryService =>
      _deviceRegistryService ?? _notRegistered('deviceRegistryService');
  static PushNotificationService get pushNotifications =>
      _pushNotifications ?? _notRegistered('pushNotifications');
  static MobileDevicePort get mobileDevice =>
      _deviceRegistry ?? _notRegistered('mobileDevice');
  static ApprovalPort get approvals => _notRegistered('approvals');
  static BiometricUnlock get biometric =>
      _biometric ?? _notRegistered('biometric');
}
