/// Role + config driven shell modules — no hardcoded feature availability.
library;

import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';

/// Stable branch indices for [StatefulShellRoute] (do not reorder without router update).
enum ShellTab {
  home(0),
  attendance(1),
  leave(2),
  requests(3),
  more(4);

  const ShellTab(this.branchIndex);
  final int branchIndex;
}

/// More-menu entries gated by ERP features.
enum ShellMoreItem {
  documents(MobileFeatureKey.documents),
  payslips(MobileFeatureKey.payroll),
  notifications(MobileFeatureKey.notifications),
  profile(MobileFeatureKey.profile),
  approvals(MobileFeatureKey.approvals);

  const ShellMoreItem(this.featureKey);
  final String featureKey;
}

/// Builds visible navigation from [MobileAppConfiguration] + workspace role.
///
/// Future Manager / HR / Supervisor / CEO shells extend policies here —
/// do not fork the Flutter project.
abstract final class ShellNavPolicy {
  /// Bottom tabs for the active role. Home + More are always present when mobile is active.
  static List<ShellTab> visibleTabs(MobileAppConfiguration config) {
    if (!config.mobileActive) return const [];

    switch (config.role) {
      case AppWorkspaceRole.employee:
        return _employeeTabs(config);
      case AppWorkspaceRole.manager:
      case AppWorkspaceRole.hr:
      case AppWorkspaceRole.supervisor:
      case AppWorkspaceRole.ceo:
        // Future role shells — until ERP enables role-specific UIs, reuse employee tabs
        // filtered by the same feature flags.
        return _employeeTabs(config);
    }
  }

  static List<ShellTab> _employeeTabs(MobileAppConfiguration config) {
    final tabs = <ShellTab>[ShellTab.home];
    if (config.isFeatureEnabled(MobileFeatureKey.attendance)) {
      tabs.add(ShellTab.attendance);
    }
    if (config.isFeatureEnabled(MobileFeatureKey.leave)) {
      tabs.add(ShellTab.leave);
    }
    // Requests: show only when ERP explicitly enables (forward-compatible key).
    if (config.isFeatureEnabled(MobileFeatureKey.requests)) {
      tabs.add(ShellTab.requests);
    }
    tabs.add(ShellTab.more);
    return tabs;
  }

  static List<ShellMoreItem> visibleMoreItems(MobileAppConfiguration config) {
    if (!config.mobileActive) return const [];
    return ShellMoreItem.values
        .where((i) => config.isFeatureEnabled(i.featureKey))
        .toList(growable: false);
  }

  static bool isRouteAllowed(MobileAppConfiguration config, String location) {
    if (!config.mobileActive) return false;
    if (location.startsWith('/attendance')) {
      return config.isFeatureEnabled(MobileFeatureKey.attendance);
    }
    if (location.startsWith('/leave')) {
      return config.isFeatureEnabled(MobileFeatureKey.leave);
    }
    if (location.startsWith('/requests')) {
      return config.isFeatureEnabled(MobileFeatureKey.requests);
    }
    if (location.startsWith('/more/documents')) {
      return config.isFeatureEnabled(MobileFeatureKey.documents);
    }
    if (location.startsWith('/more/payslips')) {
      return config.isFeatureEnabled(MobileFeatureKey.payroll);
    }
    if (location.startsWith('/more/notifications')) {
      return config.isFeatureEnabled(MobileFeatureKey.notifications);
    }
    if (location.startsWith('/more/profile')) {
      return config.isFeatureEnabled(MobileFeatureKey.profile);
    }
    if (location.startsWith('/more/approvals')) {
      return config.isFeatureEnabled(MobileFeatureKey.approvals);
    }
    return true;
  }
}
