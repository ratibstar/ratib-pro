import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/company_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';

class WorkersManagement extends StatefulWidget {
  const WorkersManagement({super.key});

  @override
  State<WorkersManagement> createState() => _WorkersManagementState();
}

class _WorkersManagementState extends State<WorkersManagement> {
  bool _isLoading = true;
  String? _error;
  List<CompanyWorker> _workers = [];

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
      final data = await RatebApiService.instance.getCompanyWorkers();
      if (!mounted) return;
      setState(() {
        _workers = data.workers;
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
      isEmpty: _workers.isEmpty,
      emptyTitle: 'No workers',
      emptyMessage: 'No workers found in your roster.',
      emptyIcon: Icons.groups_outlined,
      loadingMessage: 'Loading workers…',
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
          ..._workers.map(
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
