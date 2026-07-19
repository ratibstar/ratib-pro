/// ESS bottom navigation shell — max 5 tabs.
///
/// Phase 0: presentation chrome only.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/routing/app_routes.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

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

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: navigationShell.goBranch,
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home),
            label: l10n.tabHome,
          ),
          NavigationDestination(
            icon: const Icon(Icons.fingerprint_outlined),
            selectedIcon: const Icon(Icons.fingerprint),
            label: l10n.tabAttendance,
          ),
          NavigationDestination(
            icon: const Icon(Icons.event_available_outlined),
            selectedIcon: const Icon(Icons.event_available),
            label: l10n.tabLeave,
          ),
          NavigationDestination(
            icon: const Icon(Icons.assignment_outlined),
            selectedIcon: const Icon(Icons.assignment),
            label: l10n.tabRequests,
          ),
          NavigationDestination(
            icon: const Icon(Icons.more_horiz),
            selectedIcon: const Icon(Icons.more_horiz),
            label: l10n.tabMore,
          ),
        ],
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

/// Convenience: jump to login from More (Phase 0 demo exit).
void phase0OpenLogin(BuildContext context) {
  context.go(AppRoutes.login);
}
