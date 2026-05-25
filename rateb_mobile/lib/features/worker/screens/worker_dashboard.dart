import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/models/worker_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';
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

class _WorkerHomeTab extends StatefulWidget {
  const _WorkerHomeTab({required this.username});

  final String username;

  @override
  State<_WorkerHomeTab> createState() => _WorkerHomeTabState();
}

class _WorkerHomeTabState extends State<_WorkerHomeTab> {
  ScreenLoadResult<WorkerDashboardData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<WorkerDashboardData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.workerDashboard,
      fetch: RatebApiService.instance.getWorkerDashboard,
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
      isAutoRetrying: result?.isAutoRetrying ?? false,
      autoRetryAttempt: result?.autoRetryAttempt ?? 0,
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
            "Today's overview",
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            'Welcome, ${data?.profile.username ?? widget.username}',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.75),
                ),
          ),
          const SizedBox(height: 6),
          Text(
            'Tasks due today and your current workforce status.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: 'Due today',
            subtitle: data == null
                ? '—'
                : '${data.stats.dueToday} task${data.stats.dueToday == 1 ? '' : 's'} need attention today',
            icon: Icons.today_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Pending tasks',
            subtitle: data == null
                ? '—'
                : '${data.stats.pendingTasks} open item${data.stats.pendingTasks == 1 ? '' : 's'} in your queue',
            icon: Icons.assignment_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Your status',
            subtitle: data?.worker != null
                ? '${data!.worker!.name} · ${data.worker!.status}'
                : data?.profile.status ?? 'Active account',
            icon: Icons.badge_outlined,
            onTap: () {},
          ),
        ],
      ),
    );
  }
}
