/// DI module markers — documentation structure only.
///
/// Do not register concrete adapters here in Phase 0.6.
library;

/// Lists contract types expected at the composition root.
abstract final class DiModules {
  static const List<String> contractSlots = [
    'AppEnvironment',
    'ErpHttpClient',
    'AuthPort',
    'MePort',
    'AttendancePort',
    'LeavePort',
    'PermissionRequestPort',
    'EmployeeRequestPort',
    'PayslipPort',
    'DocumentsPort',
    'NotificationPort',
    'ApprovalPort',
    'SecureTokenStore',
    'CacheStore',
    'BiometricUnlock',
    'OfflineQueuePort',
    'ErrorMapper',
    'MobileConfigPort',
  ];
}
