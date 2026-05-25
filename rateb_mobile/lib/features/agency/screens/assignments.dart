import 'package:flutter/material.dart';

import '../../../core/models/agency_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class Assignments extends StatefulWidget {
  const Assignments({super.key});

  @override
  State<Assignments> createState() => _AssignmentsState();
}

class _AssignmentsState extends State<Assignments> {
  ScreenLoadResult<AgencyAssignmentsData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<AgencyAssignmentsData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.agencyAssignments,
      fetch: RatebApiService.instance.getAgencyAssignments,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final assignments =
        result?.data?.assignments ?? const <AgencyAssignment>[];

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: assignments.isEmpty && result?.isLoading != true,
      emptyTitle: EmptyStateCopy.agencyAssignmentsTitle,
      emptyMessage: EmptyStateCopy.agencyAssignmentsMessage,
      emptyIcon: Icons.assignment_ind_outlined,
      skeletonType: SkeletonType.list,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Assignments',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 12),
          ...assignments.map(
            (item) => Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const Icon(Icons.business_outlined),
                title: Text(item.clientName),
                subtitle: Text(item.subtitle),
                trailing: const Icon(Icons.chevron_right),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
