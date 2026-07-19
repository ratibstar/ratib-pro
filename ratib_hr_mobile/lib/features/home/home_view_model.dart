/// Home dashboard state — loads ERP ports only. No HR math.
library;

import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/contracts/attendance_port.dart';
import 'package:ratib_hr_mobile/core/contracts/leave_port.dart';
import 'package:ratib_hr_mobile/core/contracts/notification_port.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/identity/employee_context.dart';
import 'package:ratib_hr_mobile/features/home/home_dtos.dart';

enum HomeLoadState { idle, loading, ready, error }

final class HomeViewModel extends ChangeNotifier {
  HomeViewModel({
    AttendancePort? attendance,
    LeavePort? leave,
    NotificationPort? notifications,
  })  : _attendance = attendance,
        _leave = leave,
        _notifications = notifications;

  final AttendancePort? _attendance;
  final LeavePort? _leave;
  final NotificationPort? _notifications;

  AttendancePort get _attendancePort =>
      _attendance ?? AppLocator.attendance;
  LeavePort get _leavePort => _leave ?? AppLocator.leave;
  NotificationPort get _notificationPort =>
      _notifications ?? AppLocator.notifications;

  HomeLoadState state = HomeLoadState.idle;
  String? errorMessage;

  String employeeName = '';
  HomeAttendanceDto attendance = const HomeAttendanceDto(hasRecord: false);
  List<HomeLeaveBalanceDto> leaveBalances = const [];
  List<HomeNotificationDto> notifications = const [];

  Future<void> load() async {
    state = HomeLoadState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final ctx = EmployeeContext.requireResolved();
      employeeName = ctx.name ?? ctx.employeeCode ?? ctx.employeeId;

      final attMap = await _attendancePort.today();
      final balMaps = await _leavePort.balances();
      final notifMaps = await _notificationPort.list();

      attendance = HomeAttendanceDto.fromErp(attMap);
      leaveBalances = balMaps
          .map(HomeLeaveBalanceDto.fromErp)
          .toList(growable: false);
      notifications = notifMaps
          .map(HomeNotificationDto.fromErp)
          .take(5)
          .toList(growable: false);

      state = HomeLoadState.ready;
    } catch (e) {
      state = HomeLoadState.error;
      if (e is AppFailure) {
        errorMessage = e.message ?? e.code;
      } else {
        errorMessage = e.toString();
      }
    }
    notifyListeners();
  }
}
