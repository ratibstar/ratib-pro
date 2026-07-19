/// More tab — Sign Out (Phase 3.2) + existing placeholder links.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: DsAppBar(title: l10n.tabMore),
      body: ListView(
        children: [
          DsListItem(
            title: l10n.signOut,
            leading: const Icon(Icons.logout),
            trailing: const SizedBox.shrink(),
            onTap: () async {
              await AppLocator.signOut();
            },
          ),
          DsListItem(
            title: l10n.navDocuments,
            leading: const Icon(Icons.folder_open_outlined),
            onTap: () => context.go(AppRoutes.documents),
          ),
          DsListItem(
            title: l10n.navPayslips,
            leading: const Icon(Icons.receipt_long_outlined),
            onTap: () => context.go(AppRoutes.payslips),
          ),
          DsListItem(
            title: l10n.navNotifications,
            leading: const Icon(Icons.notifications_outlined),
            onTap: () => context.go(AppRoutes.notifications),
          ),
          DsListItem(
            title: l10n.navProfile,
            leading: const Icon(Icons.person_outline),
            onTap: () => context.go(AppRoutes.profile),
          ),
          DsListItem(
            title: l10n.navApprovals,
            leading: const Icon(Icons.fact_check_outlined),
            onTap: () => context.go(AppRoutes.approvals),
          ),
        ],
      ),
    );
  }
}
