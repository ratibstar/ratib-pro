/// Dependency injection — Phase 1–3 ESS ports.
library;

import 'package:ratib_hr_mobile/core/contracts/contracts.dart';

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

  static PermissionRequestPort get permissionRequests =>
      _notRegistered('permissionRequests');

  static EmployeeRequestPort get employeeRequests =>
      _notRegistered('employeeRequests');

  static PayslipPort get payslips => _notRegistered('payslips');

  static DocumentsPort get documents => _notRegistered('documents');

  static NotificationPort get notifications =>
      _notifications ?? _notRegistered('notifications');

  static ApprovalPort get approvals => _notRegistered('approvals');

  static CacheStore get cache => _notRegistered('cache');

  static BiometricUnlock get biometric => _notRegistered('biometric');

  static OfflineQueuePort get offlineQueue => _notRegistered('offlineQueue');
}
