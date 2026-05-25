import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/worker_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';

class WorkerTasks extends StatefulWidget {
  const WorkerTasks({super.key});

  @override
  State<WorkerTasks> createState() => _WorkerTasksState();
}

class _WorkerTasksState extends State<WorkerTasks> {
  bool _isLoading = true;
  String? _error;
  List<WorkerTask> _tasks = [];

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
      final tasks = await RatebApiService.instance.getWorkerTasks();
      if (!mounted) return;
      setState(() {
        _tasks = tasks;
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
    return DataStateView(
      isLoading: _isLoading,
      errorMessage: _error,
      onRetry: _load,
      isEmpty: _tasks.isEmpty,
      emptyTitle: 'No tasks',
      emptyMessage: 'You have no pending tasks right now.',
      emptyIcon: Icons.task_alt_outlined,
      loadingMessage: 'Loading tasks…',
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
          ..._tasks.map(
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
