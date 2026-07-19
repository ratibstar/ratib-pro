/// Phase 0 route paths — navigation skeleton only.
///
/// Feature implementations are deferred. Do not attach ERP adapters here yet.
library;

abstract final class AppRoutes {
  static const login = '/login';
  static const home = '/home';
  static const attendance = '/attendance';
  static const attendanceCheckIn = '/attendance/check-in';
  static const attendanceCheckOut = '/attendance/check-out';
  static const attendanceHistory = '/attendance/history';
  static const leave = '/leave';
  static const leaveBalance = '/leave/balance';
  static const leaveApply = '/leave/apply';
  static const leaveStatus = '/leave/status';
  static const requests = '/requests';
  static const permissionRequests = '/requests/permissions';
  static const employeeRequests = '/requests/employee';
  static const more = '/more';
  static const documents = '/more/documents';
  static const payslips = '/more/payslips';
  static const notifications = '/more/notifications';
  static const profile = '/more/profile';
  static const approvals = '/more/approvals';
}
