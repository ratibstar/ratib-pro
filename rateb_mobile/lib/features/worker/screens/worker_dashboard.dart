import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../auth/providers/auth_provider.dart';
import 'worker_profile.dart';
import 'worker_tasks.dart';

class WorkerDashboard extends StatefulWidget {
  const WorkerDashboard({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<WorkerDashboard> createState() => _WorkerDashboardState();
}

class _WorkerDashboardState extends State<WorkerDashboard> {
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final pages = [
      _WorkerHomeTab(username: auth.username ?? 'Worker'),
      const WorkerProfile(),
      const WorkerTasks(),
    ];

    return AppScaffold(
      title: 'Worker Portal',
      showLogout: true,
      onLogout: () async {
        await auth.logout();
        if (context.mounted) context.go('/login');
      },
      body: pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Profile',
          ),
          NavigationDestination(
            icon: Icon(Icons.task_alt_outlined),
            selectedIcon: Icon(Icons.task_alt),
            label: 'Tasks',
          ),
        ],
      ),
    );
  }
}

class _WorkerHomeTab extends StatelessWidget {
  const _WorkerHomeTab({required this.username});

  final String username;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Welcome, $username',
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
        const SizedBox(height: 6),
        Text(
          'Your workforce assignments and profile at a glance.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .onSurface
                    .withValues(alpha: 0.65),
              ),
        ),
        const SizedBox(height: 20),
        DashboardCard(
          title: 'Active tasks',
          subtitle: '3 pending · 1 due today',
          icon: Icons.assignment_outlined,
          onTap: () {},
        ),
        const SizedBox(height: 12),
        DashboardCard(
          title: 'Profile & documents',
          subtitle: 'View contact info and status',
          icon: Icons.badge_outlined,
          onTap: () {},
        ),
        const SizedBox(height: 12),
        DashboardCard(
          title: 'QR check-in',
          subtitle: 'Coming soon — scan to authenticate',
          icon: Icons.qr_code_scanner_rounded,
          onTap: () {},
        ),
      ],
    );
  }
}
