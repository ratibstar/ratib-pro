import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/agency_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';

class Assignments extends StatefulWidget {
  const Assignments({super.key});

  @override
  State<Assignments> createState() => _AssignmentsState();
}

class _AssignmentsState extends State<Assignments> {
  bool _isLoading = true;
  String? _error;
  List<AgencyAssignment> _assignments = [];

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
      final data = await RatebApiService.instance.getAgencyAssignments();
      if (!mounted) return;
      setState(() {
        _assignments = data.assignments;
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
      isEmpty: _assignments.isEmpty,
      emptyTitle: 'No assignments',
      emptyMessage: 'No client assignments found yet.',
      emptyIcon: Icons.assignment_ind_outlined,
      loadingMessage: 'Loading assignments…',
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
          ..._assignments.map(
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
