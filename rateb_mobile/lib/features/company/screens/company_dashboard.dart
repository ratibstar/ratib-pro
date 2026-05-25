import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/company_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../auth/providers/auth_provider.dart';
import 'requests.dart';
import 'workers_management.dart';

class CompanyDashboard extends StatefulWidget {
  const CompanyDashboard({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<CompanyDashboard> createState() => _CompanyDashboardState();
}

class _CompanyDashboardState extends State<CompanyDashboard> {
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
      _CompanyHomeTab(username: auth.username ?? 'Company'),
      const WorkersManagement(),
      const Requests(),
    ];

    return AppScaffold(
      title: 'Company Portal',
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
            icon: Icon(Icons.groups_outlined),
            selectedIcon: Icon(Icons.groups),
            label: 'Workers',
          ),
          NavigationDestination(
            icon: Icon(Icons.inbox_outlined),
            selectedIcon: Icon(Icons.inbox),
            label: 'Requests',
          ),
        ],
      ),
    );
  }
}

class _CompanyHomeTab extends StatefulWidget {
  const _CompanyHomeTab({required this.username});

  final String username;

  @override
  State<_CompanyHomeTab> createState() => _CompanyHomeTabState();
}

class _CompanyHomeTabState extends State<_CompanyHomeTab> {
  bool _isLoading = true;
  String? _error;
  CompanyDashboardData? _data;

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
      final data = await RatebApiService.instance.getCompanyDashboard();
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
            'Welcome, ${widget.username}',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 6),
          Text(
            'Manage your workforce and hiring requests.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: 'Workers on assignment',
            subtitle: _data == null
                ? '—'
                : '${_data!.activeWorkers} active · ${_data!.pendingWorkers} pending approval',
            icon: Icons.groups_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Open requests',
            subtitle: _data == null
                ? '—'
                : '${_data!.openRequests} recruitment requests in progress',
            icon: Icons.request_quote_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Compliance overview',
            subtitle: _data == null
                ? '—'
                : '${_data!.totalWorkers} workers in roster',
            icon: Icons.verified_user_outlined,
            onTap: () {},
          ),
        ],
      ),
    );
  }
}
