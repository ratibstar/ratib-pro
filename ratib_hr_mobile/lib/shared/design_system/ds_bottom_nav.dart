/// Bottom navigation destinations model + bar.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

class DsNavDestination {
  const DsNavDestination({
    required this.label,
    required this.icon,
    required this.selectedIcon,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;
}

class DsBottomNavigation extends StatelessWidget {
  const DsBottomNavigation({
    super.key,
    required this.selectedIndex,
    required this.onDestinationSelected,
    required this.destinations,
  });

  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final List<DsNavDestination> destinations;

  /// Approved ESS tabs (5).
  static List<DsNavDestination> essTabs({
    required String home,
    required String attendance,
    required String leave,
    required String requests,
    required String more,
  }) {
    return [
      DsNavDestination(
        label: home,
        icon: AppIcons.home,
        selectedIcon: AppIcons.homeFilled,
      ),
      DsNavDestination(
        label: attendance,
        icon: AppIcons.attendance,
        selectedIcon: AppIcons.attendanceFilled,
      ),
      DsNavDestination(
        label: leave,
        icon: AppIcons.leave,
        selectedIcon: AppIcons.leaveFilled,
      ),
      DsNavDestination(
        label: requests,
        icon: AppIcons.requests,
        selectedIcon: AppIcons.requestsFilled,
      ),
      DsNavDestination(
        label: more,
        icon: AppIcons.more,
        selectedIcon: AppIcons.more,
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    assert(destinations.length <= 5, 'ESS bottom nav max 5 tabs');
    return NavigationBar(
      selectedIndex: selectedIndex,
      onDestinationSelected: onDestinationSelected,
      destinations: [
        for (final d in destinations)
          NavigationDestination(
            icon: Icon(d.icon),
            selectedIcon: Icon(d.selectedIcon),
            label: d.label,
          ),
      ],
    );
  }
}
