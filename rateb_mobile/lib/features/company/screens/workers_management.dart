import 'package:flutter/material.dart';

import '../../../core/models/company_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class WorkersManagement extends StatefulWidget {
  const WorkersManagement({super.key});

  @override
  State<WorkersManagement> createState() => _WorkersManagementState();
}

class _WorkersManagementState extends State<WorkersManagement> {
  ScreenLoadResult<CompanyWorkersData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<CompanyWorkersData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.companyWorkers,
      fetch: RatebApiService.instance.getCompanyWorkers,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final workers = result?.data?.workers ?? const <CompanyWorker>[];

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: workers.isEmpty && result?.isLoading != true,
      emptyTitle: EmptyStateCopy.companyWorkersTitle,
      emptyMessage: EmptyStateCopy.companyWorkersMessage,
      emptyIcon: Icons.groups_outlined,
      skeletonType: SkeletonType.list,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Workers',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 12),
          ...workers.map(
            (worker) => Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text(worker.name),
                subtitle: Text(worker.subtitle),
                trailing: const Icon(Icons.more_vert),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
