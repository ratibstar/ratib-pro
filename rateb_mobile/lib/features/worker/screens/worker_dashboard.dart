import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/worker_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
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
  bool _isLoading = true;
  String? _error;
  WorkerDashboardData? _data;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await RatebApiService.instance.getWorkerDashboard();
      if (!mounted) return;
      setState(() {
        _data = data;
        _isLoading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return DataStateView(
      isLoading: _isLoading,
      errorMessage: _error,
      onRetry: _load,
      isEmpty: false,
      emptyTitle: '',
      emptyMessage: '',
      loadingMessage: 'Loading dashboard…',
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Welcome, ${_data?.profile.username ?? widget.username}',
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
            subtitle: _data == null
                ? '—'
                : '${_data!.stats.pendingTasks} pending · ${_data!.stats.dueToday} due today',
            icon: Icons.assignment_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Profile & documents',
            subtitle: _data?.worker != null
                ? '${_data!.worker!.name} · ${_data!.worker!.status}'
                : 'View contact info and status',
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
      ),
    );
  }
}
