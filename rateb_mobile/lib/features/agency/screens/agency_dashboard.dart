import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../auth/providers/auth_provider.dart';
import 'assignments.dart';
import 'recruitment_pipeline.dart';

class AgencyDashboard extends StatefulWidget {
  const AgencyDashboard({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<AgencyDashboard> createState() => _AgencyDashboardState();
}

class _AgencyDashboardState extends State<AgencyDashboard> {
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
      _AgencyHomeTab(username: auth.username ?? 'Agency'),
      const RecruitmentPipeline(),
      const Assignments(),
    ];

    return AppScaffold(
      title: 'Agency Portal',
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
            icon: Icon(Icons.timeline_outlined),
            selectedIcon: Icon(Icons.timeline),
            label: 'Pipeline',
          ),
          NavigationDestination(
            icon: Icon(Icons.assignment_ind_outlined),
            selectedIcon: Icon(Icons.assignment_ind),
            label: 'Assignments',
          ),
        ],
      ),
    );
  }
}

class _AgencyHomeTab extends StatelessWidget {
  const _AgencyHomeTab({required this.username});

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
          'Recruitment pipeline and client assignments.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .onSurface
                    .withValues(alpha: 0.65),
              ),
        ),
        const SizedBox(height: 20),
        DashboardCard(
          title: 'Pipeline',
          subtitle: '28 candidates · 6 interviews scheduled',
          icon: Icons.timeline_outlined,
          onTap: () {},
        ),
        const SizedBox(height: 12),
        DashboardCard(
          title: 'Active assignments',
          subtitle: '9 workers deployed to clients',
          icon: Icons.assignment_ind_outlined,
          onTap: () {},
        ),
        const SizedBox(height: 12),
        DashboardCard(
          title: 'Partner documents',
          subtitle: 'Shared CVs and compliance files',
          icon: Icons.folder_shared_outlined,
          onTap: () {},
        ),
      ],
    );
  }
}
