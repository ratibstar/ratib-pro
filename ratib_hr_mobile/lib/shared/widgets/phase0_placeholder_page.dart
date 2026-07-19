/// Shared Phase 0 placeholder — not a feature implementation.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

enum Phase0TitleKey {
  home,
  attendance,
  checkIn,
  checkOut,
  attendanceHistory,
  leave,
  leaveBalance,
  applyLeave,
  leaveStatus,
  requests,
  permissionRequests,
  employeeRequests,
  more,
  documents,
  payslips,
  notifications,
  profile,
  approvals,
}

class Phase0Link {
  const Phase0Link({required this.route, required this.titleKey});

  final String route;
  final Phase0TitleKey titleKey;
}

class Phase0PlaceholderPage extends StatelessWidget {
  const Phase0PlaceholderPage({
    super.key,
    required this.titleKey,
    this.childLinks = const [],
  });

  final Phase0TitleKey titleKey;
  final List<Phase0Link> childLinks;

  String _title(AppLocalizations l10n) {
    switch (titleKey) {
      case Phase0TitleKey.home:
        return l10n.navHome;
      case Phase0TitleKey.attendance:
        return l10n.navAttendance;
      case Phase0TitleKey.checkIn:
        return l10n.navCheckIn;
      case Phase0TitleKey.checkOut:
        return l10n.navCheckOut;
      case Phase0TitleKey.attendanceHistory:
        return l10n.navAttendanceHistory;
      case Phase0TitleKey.leave:
        return l10n.leave;
      case Phase0TitleKey.leaveBalance:
        return l10n.navLeaveBalance;
      case Phase0TitleKey.applyLeave:
        return l10n.navApplyLeave;
      case Phase0TitleKey.leaveStatus:
        return l10n.navLeaveStatus;
      case Phase0TitleKey.requests:
        return l10n.requests;
      case Phase0TitleKey.permissionRequests:
        return l10n.navPermissionRequests;
      case Phase0TitleKey.employeeRequests:
        return l10n.navEmployeeRequests;
      case Phase0TitleKey.more:
        return l10n.more;
      case Phase0TitleKey.documents:
        return l10n.navDocuments;
      case Phase0TitleKey.payslips:
        return l10n.navPayslips;
      case Phase0TitleKey.notifications:
        return l10n.navNotifications;
      case Phase0TitleKey.profile:
        return l10n.navProfile;
      case Phase0TitleKey.approvals:
        return l10n.navApprovals;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final title = _title(l10n);

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.symmetric(vertical: 8),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    l10n.phase0Placeholder,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    l10n.phase0Subtitle,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                  ),
                ],
              ),
            ),
          ),
          ...childLinks.map(
            (link) => Card(
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 8,
                ),
                title: Text(_titleFor(l10n, link.titleKey)),
                trailing: const Icon(
                  Icons.chevron_right,
                ),
                onTap: () => context.push(link.route),
                minVerticalPadding: 16,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _titleFor(AppLocalizations l10n, Phase0TitleKey key) {
    return Phase0PlaceholderPage(titleKey: key)._title(l10n);
  }
}
