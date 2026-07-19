/// Canonical icon set for ESS chrome (Material Symbols via Icons).
library;

import 'package:flutter/material.dart';

abstract final class AppIcons {
  static const IconData home = Icons.home_outlined;
  static const IconData homeFilled = Icons.home;
  static const IconData attendance = Icons.fingerprint_outlined;
  static const IconData attendanceFilled = Icons.fingerprint;
  static const IconData leave = Icons.event_available_outlined;
  static const IconData leaveFilled = Icons.event_available;
  static const IconData requests = Icons.assignment_outlined;
  static const IconData requestsFilled = Icons.assignment;
  static const IconData more = Icons.more_horiz;
  static const IconData search = Icons.search;
  static const IconData notifications = Icons.notifications_outlined;
  static const IconData profile = Icons.person_outline;
  static const IconData documents = Icons.folder_open_outlined;
  static const IconData payslip = Icons.receipt_long_outlined;
  static const IconData approvals = Icons.fact_check_outlined;
  static const IconData checkIn = Icons.login;
  static const IconData checkOut = Icons.logout;
  static const IconData calendar = Icons.calendar_today_outlined;
  static const IconData empty = Icons.inbox_outlined;
  static const IconData error = Icons.error_outline;
  static const IconData success = Icons.check_circle_outline;
  static const IconData loading = Icons.hourglass_empty;
  static const IconData chevron = Icons.chevron_right;
  static const IconData close = Icons.close;
  static const double sizeSm = 20;
  static const double sizeMd = 24;
  static const double sizeLg = 32;
  static const double sizeXl = 48;
}
