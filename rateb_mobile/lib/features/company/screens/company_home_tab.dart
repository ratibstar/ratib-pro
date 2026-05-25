import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/company_models.dart';
import '../../../core/routing/app_router.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class CompanyHomeTab extends StatefulWidget {
  const CompanyHomeTab({super.key, required this.username});

  final String username;

  @override
  State<CompanyHomeTab> createState() => _CompanyHomeTabState();
}

class _CompanyHomeTabState extends State<CompanyHomeTab> {
  ScreenLoadResult<CompanyDashboardData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<CompanyDashboardData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.companyDashboard,
      fetch: RatebApiService.instance.getCompanyDashboard,
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
            'Workforce status',
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
            'Live view of your roster, approvals, and open requests.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: 'Active workers',
            subtitle: data == null
                ? '—'
                : '${data.activeWorkers} on assignment · ${data.pendingWorkers} pending approval',
            icon: Icons.groups_outlined,
            onTap: () => context.go('${AppRouter.companyHome}/workers'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Open requests',
            subtitle: data == null
                ? '—'
                : '${data.openRequests} recruitment request${data.openRequests == 1 ? '' : 's'} in progress',
            icon: Icons.request_quote_outlined,
            onTap: () => context.go('${AppRouter.companyHome}/requests'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: 'Total roster',
            subtitle: data == null
                ? '—'
                : '${data.totalWorkers} worker${data.totalWorkers == 1 ? '' : 's'} in your workforce',
            icon: Icons.verified_user_outlined,
            onTap: () => context.go('${AppRouter.companyHome}/workers'),
          ),
        ],
      ),
    );
  }
}
