/// Shared Phase 0 placeholder — not a feature implementation.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

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

  IconData _hubIcon() {
    switch (titleKey) {
      case Phase0TitleKey.attendance:
      case Phase0TitleKey.checkIn:
      case Phase0TitleKey.checkOut:
      case Phase0TitleKey.attendanceHistory:
        return Icons.fingerprint_rounded;
      case Phase0TitleKey.leave:
      case Phase0TitleKey.leaveBalance:
      case Phase0TitleKey.applyLeave:
      case Phase0TitleKey.leaveStatus:
        return Icons.beach_access_rounded;
      case Phase0TitleKey.requests:
      case Phase0TitleKey.permissionRequests:
      case Phase0TitleKey.employeeRequests:
        return Icons.assignment_outlined;
      case Phase0TitleKey.documents:
        return Icons.folder_open_outlined;
      case Phase0TitleKey.payslips:
        return Icons.receipt_long_outlined;
      case Phase0TitleKey.notifications:
        return Icons.notifications_outlined;
      case Phase0TitleKey.profile:
        return Icons.person_outline;
      case Phase0TitleKey.approvals:
        return Icons.fact_check_outlined;
      default:
        return Icons.apps_rounded;
    }
  }

  Color _hubAccent() {
    switch (titleKey) {
      case Phase0TitleKey.attendance:
      case Phase0TitleKey.checkIn:
      case Phase0TitleKey.checkOut:
      case Phase0TitleKey.attendanceHistory:
        return AppColors.auroraAmber;
      case Phase0TitleKey.leave:
      case Phase0TitleKey.leaveBalance:
      case Phase0TitleKey.applyLeave:
      case Phase0TitleKey.leaveStatus:
        return AppColors.auroraCyan;
      case Phase0TitleKey.requests:
      case Phase0TitleKey.permissionRequests:
      case Phase0TitleKey.employeeRequests:
        return AppColors.auroraRose;
      default:
        return AppColors.auroraTeal;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final title = _title(l10n);
    final accent = _hubAccent();

    return DsPageScaffold(
      title: title,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            child: DsGlassTile(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      DsIconBadge(icon: _hubIcon(), color: accent),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Text(
                          title,
                          style:
                              Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Text(
                    l10n.phase0Placeholder,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    l10n.phase0Subtitle,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color:
                              Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                  ),
                ],
              ),
            ),
          ),
          if (childLinks.isNotEmpty) ...[
            DsSectionHeader(title: title),
            for (final link in childLinks)
              DsListItem(
                title: _titleFor(l10n, link.titleKey),
                leading: DsIconBadge(
                  icon: Icons.arrow_outward_rounded,
                  color: accent,
                ),
                accentColor: accent,
                onTap: () => context.push(link.route),
              ),
          ],
        ],
      ),
    );
  }

  String _titleFor(AppLocalizations l10n, Phase0TitleKey key) {
    return Phase0PlaceholderPage(titleKey: key)._title(l10n);
  }
}
