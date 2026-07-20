/// More tab — items from MobileConfiguration feature flags.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return ListenableBuilder(
      listenable: AppLocator.mobileConfiguration,
      builder: (context, _) {
        final cfg = AppLocator.mobileConfiguration.current;
        final items = cfg == null
            ? const <ShellMoreItem>[]
            : ShellNavPolicy.visibleMoreItems(cfg);

        return Scaffold(
          appBar: DsAppBar(
            title: cfg?.displayName.isNotEmpty == true
                ? cfg!.displayName
                : l10n.tabMore,
          ),
          body: ListView(
            children: [
              for (final item in items)
                DsListItem(
                  title: _title(item, l10n),
                  leading: Icon(_icon(item)),
                  onTap: () => context.go(_route(item)),
                ),
              if (cfg == null ||
                  !cfg.isFeatureEnabled(ShellMoreItem.settings.featureKey))
                DsListItem(
                  title: l10n.signOut,
                  leading: const Icon(Icons.logout),
                  trailing: const SizedBox.shrink(),
                  onTap: () async {
                    await AppLocator.signOut();
                  },
                ),
            ],
          ),
        );
      },
    );
  }

  static String _title(ShellMoreItem item, AppLocalizations l10n) {
    switch (item) {
      case ShellMoreItem.documents:
        return l10n.navDocuments;
      case ShellMoreItem.payslips:
        return l10n.navPayslips;
      case ShellMoreItem.notifications:
        return l10n.navNotifications;
      case ShellMoreItem.ratings:
        return l10n.navRatings;
      case ShellMoreItem.inquiries:
        return l10n.navInquiries;
      case ShellMoreItem.payments:
        return l10n.navPayments;
      case ShellMoreItem.settings:
        return l10n.navSettings;
      case ShellMoreItem.profile:
        return l10n.navProfile;
      case ShellMoreItem.approvals:
        return l10n.navApprovals;
    }
  }

  static IconData _icon(ShellMoreItem item) {
    switch (item) {
      case ShellMoreItem.documents:
        return Icons.folder_open_outlined;
      case ShellMoreItem.payslips:
        return Icons.receipt_long_outlined;
      case ShellMoreItem.notifications:
        return Icons.notifications_outlined;
      case ShellMoreItem.ratings:
        return Icons.stars_outlined;
      case ShellMoreItem.inquiries:
        return Icons.support_agent_outlined;
      case ShellMoreItem.payments:
        return Icons.account_balance_wallet_outlined;
      case ShellMoreItem.settings:
        return Icons.settings_outlined;
      case ShellMoreItem.profile:
        return Icons.person_outline;
      case ShellMoreItem.approvals:
        return Icons.fact_check_outlined;
    }
  }

  static String _route(ShellMoreItem item) {
    switch (item) {
      case ShellMoreItem.documents:
        return AppRoutes.documents;
      case ShellMoreItem.payslips:
        return AppRoutes.payslips;
      case ShellMoreItem.notifications:
        return AppRoutes.notifications;
      case ShellMoreItem.ratings:
        return AppRoutes.ratings;
      case ShellMoreItem.inquiries:
        return AppRoutes.inquiries;
      case ShellMoreItem.payments:
        return AppRoutes.payments;
      case ShellMoreItem.settings:
        return AppRoutes.settings;
      case ShellMoreItem.profile:
        return AppRoutes.profile;
      case ShellMoreItem.approvals:
        return AppRoutes.approvals;
    }
  }
}
