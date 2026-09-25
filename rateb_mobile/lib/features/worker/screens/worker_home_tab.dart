import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/worker_models.dart';
import '../../../core/routing/app_router.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class WorkerHomeTab extends StatefulWidget {
  const WorkerHomeTab({super.key, required this.username});

  final String username;

  @override
  State<WorkerHomeTab> createState() => _WorkerHomeTabState();
}

class _WorkerHomeTabState extends State<WorkerHomeTab> {
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
    final l10n = AppLocalizations.of(context);

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
            l10n.todaysOverview,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.welcome(data?.profile.username ?? widget.username),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.75),
                ),
          ),
          const SizedBox(height: 6),
          Text(
            l10n.workerHomeSubtitle,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: l10n.dueToday,
            subtitle: data == null
                ? '—'
                : l10n.dueTodayDetail(data.stats.dueToday),
            icon: Icons.today_outlined,
            onTap: () => context.go('${AppRouter.workerHome}/tasks'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: l10n.pendingTasks,
            subtitle: data == null
                ? '—'
                : l10n.pendingTasksDetail(data.stats.pendingTasks),
            icon: Icons.assignment_outlined,
            onTap: () => context.go('${AppRouter.workerHome}/tasks'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: l10n.yourStatus,
            subtitle: data?.worker != null
                ? '${data!.worker!.name} · ${l10n.server(data.worker!.status)}'
                : data?.profile.status != null
                    ? l10n.server(data!.profile.status!)
                    : l10n.activeAccount,
            icon: Icons.badge_outlined,
            onTap: () => context.go('${AppRouter.workerHome}/profile'),
          ),
        ],
      ),
    );
  }
}
