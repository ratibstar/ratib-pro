import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/routing/app_router.dart';
import '../../features/auth/providers/auth_provider.dart';
import 'app_scaffold.dart';

/// Bottom-nav shell for role portals — keeps one scaffold instance so tabs stay tappable.
class PortalShell extends StatelessWidget {
  const PortalShell({
    super.key,
    required this.title,
    required this.navigationShell,
    required this.destinations,
  });

  final String title;
  final StatefulNavigationShell navigationShell;
  final List<NavigationDestination> destinations;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return AppScaffold(
      title: title,
      showLogout: true,
      onLogout: () async {
        await auth.logout();
        if (context.mounted) context.go(AppRouter.login);
      },
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: (index) {
          navigationShell.goBranch(
            index,
            initialLocation: index == navigationShell.currentIndex,
          );
        },
        destinations: destinations,
      ),
    );
  }
}
