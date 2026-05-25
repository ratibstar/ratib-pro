import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/models/agency_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
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

class _AgencyHomeTab extends StatefulWidget {
  const _AgencyHomeTab({required this.username});

  final String username;

  @override
  State<_AgencyHomeTab> createState() => _AgencyHomeTabState();
}

class _AgencyHomeTabState extends State<_AgencyHomeTab> {
  ScreenLoadResult<AgencyDashboardData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<AgencyDashboardData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.agencyDashboard,
      fetch: RatebApiService.instance.getAgencyDashboard,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final data = result?.data;

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: false,
      emptyTitle: '',
      emptyMessage: '',
      skeletonType: SkeletonType.dashboard,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Pipeline flow',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            'Welcome, ${widget.username}',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.75),
                ),
          ),
          const SizedBox(height: 6),
          Text(
            'Track candidates, deployments, and client assignments.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: 'Candidates in pipeline',
            subtitle: data == null
                ? '—'
                : '${data.totalCandidates} total · ${data.deployed} deployed',
            icon: Icons.timeline_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Active assignments',
            subtitle: data == null
                ? '—'
                : '${data.activeAssignments} client destination${data.activeAssignments == 1 ? '' : 's'} with workers',
            icon: Icons.assignment_ind_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'CV pool',
            subtitle: data == null
                ? '—'
                : '${data.cvs} shared CV${data.cvs == 1 ? '' : 's'} ready for clients',
            icon: Icons.folder_shared_outlined,
            onTap: () {},
          ),
        ],
      ),
    );
  }
}
