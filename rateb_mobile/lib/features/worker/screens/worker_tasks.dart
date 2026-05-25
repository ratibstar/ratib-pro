import 'package:flutter/material.dart';

import '../../../core/models/worker_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class WorkerTasks extends StatefulWidget {
  const WorkerTasks({super.key});

  @override
  State<WorkerTasks> createState() => _WorkerTasksState();
}

class _WorkerTasksState extends State<WorkerTasks> {
  ScreenLoadResult<List<WorkerTask>>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<List<WorkerTask>>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.workerTasks,
      fetch: RatebApiService.instance.getWorkerTasks,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
  }

  IconData _iconFor(WorkerTask task) {
    switch (task.category) {
      case 'document':
        return Icons.description_outlined;
      case 'deployment':
        return Icons.flight_takeoff_outlined;
      case 'profile':
        return Icons.contact_phone_outlined;
      default:
        return Icons.task_alt_outlined;
    }
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final tasks = result?.data ?? const <WorkerTask>[];

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: tasks.isEmpty && result?.isLoading != true,
      emptyTitle: EmptyStateCopy.workerTasksTitle,
      emptyMessage: EmptyStateCopy.workerTasksMessage,
      emptyIcon: Icons.task_alt_outlined,
      skeletonType: SkeletonType.list,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Tasks',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 12),
          ...tasks.map(
            (task) => Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: Icon(_iconFor(task)),
                title: Text(task.title),
                subtitle: Text('${task.subtitle} · ${task.dueLabel}'),
                trailing: const Icon(Icons.chevron_right),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
