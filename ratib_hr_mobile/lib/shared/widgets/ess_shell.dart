/// ESS bottom navigation shell — max 5 tabs.
///
/// Phase 0/0.7: presentation chrome using design system.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_bottom_nav.dart';

class EssShell extends StatelessWidget {
  const EssShell({
    super.key,
    required this.navigationShell,
    required this.onLocaleChanged,
    required this.currentLocale,
  });

  final StatefulNavigationShell navigationShell;
  final LocaleChanged onLocaleChanged;
  final Locale currentLocale;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final destinations = DsBottomNavigation.essTabs(
      home: l10n.tabHome,
      attendance: l10n.tabAttendance,
      leave: l10n.tabLeave,
      requests: l10n.tabRequests,
      more: l10n.tabMore,
    );

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: DsBottomNavigation(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: navigationShell.goBranch,
        destinations: destinations,
      ),
      floatingActionButton: _LocaleChip(
        currentLocale: currentLocale,
        onLocaleChanged: onLocaleChanged,
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.miniEndTop,
    );
  }
}

class _LocaleChip extends StatelessWidget {
  const _LocaleChip({
    required this.currentLocale,
    required this.onLocaleChanged,
  });

  final Locale currentLocale;
  final LocaleChanged onLocaleChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isAr = currentLocale.languageCode == 'ar';

    return SafeArea(
      child: Padding(
        padding: const EdgeInsetsDirectional.only(end: 8, top: 4),
        child: FilterChip(
          label: Text(isAr ? l10n.english : l10n.arabic),
          onSelected: (_) {
            onLocaleChanged(
              isAr ? const Locale('en') : AppConfig.defaultLocale,
            );
          },
          visualDensity: VisualDensity.compact,
        ),
      ),
    );
  }
}

void phase0OpenLogin(BuildContext context) {
  context.go(AppRoutes.login);
}
