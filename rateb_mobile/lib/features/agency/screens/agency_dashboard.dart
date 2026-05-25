import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/agency_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/app_scaffold.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
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
  bool _isLoading = true;
  String? _error;
  AgencyDashboardData? _data;

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
      final data = await RatebApiService.instance.getAgencyDashboard();
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
            subtitle: _data == null
                ? '—'
                : '${_data!.totalCandidates} candidates · ${_data!.deployed} deployed',
            icon: Icons.timeline_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Active assignments',
            subtitle: _data == null
                ? '—'
                : '${_data!.activeAssignments} client destinations · ${_data!.deployed} workers deployed',
            icon: Icons.assignment_ind_outlined,
            onTap: () {},
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Partner documents',
            subtitle: _data == null
                ? '—'
                : '${_data!.cvs} shared CVs and compliance files',
            icon: Icons.folder_shared_outlined,
            onTap: () {},
          ),
        ],
      ),
    );
  }
}
