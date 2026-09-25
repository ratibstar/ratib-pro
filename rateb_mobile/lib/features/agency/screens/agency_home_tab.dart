import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/models/agency_models.dart';
import '../../../core/routing/app_router.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/dashboard_card.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class AgencyHomeTab extends StatefulWidget {
  const AgencyHomeTab({super.key, required this.username});

  final String username;

  @override
  State<AgencyHomeTab> createState() => _AgencyHomeTabState();
}

class _AgencyHomeTabState extends State<AgencyHomeTab> {
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
    final l10n = AppLocalizations.of(context);

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
            l10n.pipelineFlow,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.welcome(widget.username),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.75),
                ),
          ),
          const SizedBox(height: 6),
          Text(
            l10n.agencyHomeSubtitle,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .onSurface
                      .withValues(alpha: 0.65),
                ),
          ),
          const SizedBox(height: 20),
          DashboardCard(
            title: l10n.candidatesInPipeline,
            subtitle: data == null
                ? '—'
                : l10n.candidatesDetail(data.totalCandidates, data.deployed),
            icon: Icons.timeline_outlined,
            onTap: () => context.go('${AppRouter.agencyHome}/pipeline'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: l10n.activeAssignments,
            subtitle: data == null
                ? '—'
                : l10n.activeAssignmentsDetail(data.activeAssignments),
            icon: Icons.assignment_ind_outlined,
            onTap: () => context.go('${AppRouter.agencyHome}/assignments'),
          ),
          const SizedBox(height: 12),
          DashboardCard(
            title: l10n.cvPool,
            subtitle: data == null
                ? '—'
                : l10n.cvPoolDetail(data.cvs),
            icon: Icons.folder_shared_outlined,
            onTap: () => context.go('${AppRouter.agencyHome}/pipeline'),
          ),
        ],
      ),
    );
  }
}
