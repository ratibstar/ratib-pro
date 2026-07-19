/// Dependency injection structure — Phase 0.6.
///
/// Registers **nothing**. Implementations are forbidden until Phase 1+.
/// Composition root will bind contracts → adapters without feature↔feature links.
library;

import 'package:ratib_hr_mobile/core/contracts/contracts.dart';

/// Typed slots for future registration. All getters intentionally unimplemented.
///
/// Calling any getter before Phase 1 registration must fail fast.
abstract final class AppLocator {
  static Never _notRegistered(String name) {
    throw StateError(
      'AppLocator.$name is not registered. Phase 0.6 contracts only — '
      'bind implementations in a later phase.',
    );
  }

  static AppEnvironment get environment => _notRegistered('environment');

  static ErpHttpClient get http => _notRegistered('http');

  static AuthPort get auth => _notRegistered('auth');

  static MePort get me => _notRegistered('me');

  static AttendancePort get attendance => _notRegistered('attendance');

  static LeavePort get leave => _notRegistered('leave');

  static PermissionRequestPort get permissionRequests =>
      _notRegistered('permissionRequests');

  static EmployeeRequestPort get employeeRequests =>
      _notRegistered('employeeRequests');

  static PayslipPort get payslips => _notRegistered('payslips');

  static DocumentsPort get documents => _notRegistered('documents');

  static NotificationPort get notifications => _notRegistered('notifications');

  static ApprovalPort get approvals => _notRegistered('approvals');

  static SecureTokenStore get tokenStore => _notRegistered('tokenStore');

  static CacheStore get cache => _notRegistered('cache');

  static BiometricUnlock get biometric => _notRegistered('biometric');

  static OfflineQueuePort get offlineQueue => _notRegistered('offlineQueue');

  static ErrorMapper get errors => _notRegistered('errors');
}
