/// Dependency injection — ESS + Phase A/C/D ports.
library;

import 'package:ratib_hr_mobile/core/appearance/appearance_controller.dart';
import 'package:ratib_hr_mobile/core/contracts/contracts.dart';
import 'package:ratib_hr_mobile/core/mobile_config/mobile_configuration_service.dart';
import 'package:ratib_hr_mobile/features/attendance/attendance_repository.dart';
import 'package:ratib_hr_mobile/features/leave/leave_repository.dart';

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
  static RatingsPort? _ratings;
  static InquiryPort? _inquiries;
  static PaymentMethodsPort? _payments;
  static SettingsPort? _settings;
  static AppearanceController? _appearance;
  static OfflineQueuePort? _offlineQueue;
  static AttendanceRepository? _attendanceRepository;
  static LeaveRepository? _leaveRepository;
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
  }) {
    _environment = environment;
    _http = http;
    _auth = auth;
    _tokenStore = tokenStore;
    _errors = errors;
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
    required RatingsPort ratings,
    required InquiryPort inquiries,
    required PaymentMethodsPort payments,
    required SettingsPort settings,
    required AppearanceController appearance,
  }) {
    _dashboard = dashboard;
    _employeeRequests = employeeRequests;
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
  static PermissionRequestPort get permissionRequests =>
      _notRegistered('permissionRequests');
  static PayslipPort get payslips => _notRegistered('payslips');
  static DocumentsPort get documents => _notRegistered('documents');
  static ApprovalPort get approvals => _notRegistered('approvals');
  static BiometricUnlock get biometric => _notRegistered('biometric');
}
