/// Requests hub — permission requests + employee requests.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class RequestsHubPage extends StatelessWidget {
  const RequestsHubPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return DsPageScaffold(
      title: l10n.tabRequests,
      body: ListView(
        padding: const EdgeInsets.only(top: 8, bottom: 32),
        children: [
          DsListItem(
            title: l10n.navPermissionRequests,
            subtitle: l10n.permissionApplyHint,
            leading: const DsIconBadge(
              icon: Icons.schedule_outlined,
              color: AppColors.auroraTeal,
            ),
            onTap: () => context.go(AppRoutes.permissionRequests),
          ),
          DsListItem(
            title: l10n.navEmployeeRequests,
            subtitle: l10n.employeeRequestsSubtitle,
            leading: const DsIconBadge(
              icon: Icons.assignment_outlined,
              color: AppColors.auroraRose,
            ),
            onTap: () => context.go(AppRoutes.employeeRequests),
          ),
        ],
      ),
    );
  }
}
