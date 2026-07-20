/// ESS bottom navigation shell — tabs from MobileConfiguration + role policy.
library;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/shell/shell_nav_policy.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_bottom_nav.dart';

class EssShell extends StatelessWidget {
  const EssShell({
    super.key,
    required this.navigationShell,
    required this.onLocaleChanged,
  });

  final StatefulNavigationShell navigationShell;
  final LocaleChanged onLocaleChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final currentBranch = navigationShell.currentIndex;

    return ListenableBuilder(
      listenable: AppLocator.mobileConfiguration,
      builder: (context, _) {
        final cfg = AppLocator.mobileConfiguration.current;
        final liveTabs = cfg == null
            ? const <ShellTab>[ShellTab.home, ShellTab.more]
            : ShellNavPolicy.visibleTabs(cfg);
        final liveDest = <DsNavDestination>[
          for (final tab in liveTabs) _destinationFor(tab, l10n),
        ];
        var sel = liveTabs.indexWhere((t) => t.branchIndex == currentBranch);
        if (sel < 0) sel = 0;

        return Scaffold(
          body: navigationShell,
          bottomNavigationBar: liveDest.isEmpty
              ? null
              : DsBottomNavigation(
                  selectedIndex: sel.clamp(0, liveDest.length - 1),
                  onDestinationSelected: (i) {
                    navigationShell.goBranch(liveTabs[i].branchIndex);
                  },
                  destinations: liveDest,
                ),
          floatingActionButton: _LocaleChip(onLocaleChanged: onLocaleChanged),
          floatingActionButtonLocation: FloatingActionButtonLocation.miniEndTop,
        );
      },
    );
  }

  static DsNavDestination _destinationFor(ShellTab tab, AppLocalizations l10n) {
    switch (tab) {
      case ShellTab.home:
        return DsNavDestination(
          label: l10n.tabHome,
          icon: AppIcons.home,
          selectedIcon: AppIcons.homeFilled,
        );
      case ShellTab.attendance:
        return DsNavDestination(
          label: l10n.tabAttendance,
          icon: AppIcons.attendance,
          selectedIcon: AppIcons.attendanceFilled,
        );
      case ShellTab.leave:
        return DsNavDestination(
          label: l10n.tabLeave,
          icon: AppIcons.leave,
          selectedIcon: AppIcons.leaveFilled,
        );
      case ShellTab.requests:
        return DsNavDestination(
          label: l10n.tabRequests,
          icon: AppIcons.requests,
          selectedIcon: AppIcons.requestsFilled,
        );
      case ShellTab.more:
        return DsNavDestination(
          label: l10n.tabMore,
          icon: AppIcons.more,
          selectedIcon: AppIcons.more,
        );
    }
  }
}

class _LocaleChip extends StatelessWidget {
  const _LocaleChip({required this.onLocaleChanged});

  final LocaleChanged onLocaleChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isAr = Localizations.localeOf(context).languageCode == 'ar';

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
