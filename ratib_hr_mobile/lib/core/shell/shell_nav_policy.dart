/// Role + config driven shell modules — no hardcoded feature availability.
library;

import 'package:ratib_hr_mobile/core/mobile_config/mobile_app_configuration.dart';

enum ShellTab {
  home(0),
  attendance(1),
  leave(2),
  requests(3),
  more(4);

  const ShellTab(this.branchIndex);
  final int branchIndex;
}

enum ShellMoreItem {
  documents(MobileFeatureKey.documents),
  payslips(MobileFeatureKey.payslips),
  notifications(MobileFeatureKey.notifications),
  ratings(MobileFeatureKey.ratings),
  inquiries(MobileFeatureKey.inquiries),
  payments(MobileFeatureKey.payments),
  settings(MobileFeatureKey.settings),
  profile(MobileFeatureKey.profile),
  approvals(MobileFeatureKey.approvals);

  const ShellMoreItem(this.featureKey);
  final String featureKey;
}

abstract final class ShellNavPolicy {
  static List<ShellTab> visibleTabs(MobileAppConfiguration config) {
    if (!config.mobileActive) return const [];

    switch (config.role) {
      case AppWorkspaceRole.employee:
      case AppWorkspaceRole.manager:
      case AppWorkspaceRole.hr:
      case AppWorkspaceRole.supervisor:
      case AppWorkspaceRole.ceo:
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
      return config.isFeatureEnabled(MobileFeatureKey.payslips);
    }
    if (location.startsWith('/more/notifications')) {
      return config.isFeatureEnabled(MobileFeatureKey.notifications);
    }
    if (location.startsWith('/more/ratings')) {
      return config.isFeatureEnabled(MobileFeatureKey.ratings);
    }
    if (location.startsWith('/more/inquiries')) {
      return config.isFeatureEnabled(MobileFeatureKey.inquiries);
    }
    if (location.startsWith('/more/payments')) {
      return config.isFeatureEnabled(MobileFeatureKey.payments);
    }
    if (location.startsWith('/more/settings')) {
      return config.isFeatureEnabled(MobileFeatureKey.settings);
    }
    if (location.startsWith('/more/profile')) {
      return config.isFeatureEnabled(MobileFeatureKey.profile);
    }
    if (location.startsWith('/more/sync')) {
      return config.isFeatureEnabled(MobileFeatureKey.attendance) ||
          config.isFeatureEnabled(MobileFeatureKey.leave);
    }
    if (location.startsWith('/more/approvals')) {
      return config.isFeatureEnabled(MobileFeatureKey.approvals);
    }
    return true;
  }
}
