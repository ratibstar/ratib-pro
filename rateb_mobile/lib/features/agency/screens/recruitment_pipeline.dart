import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/agency_models.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';

class RecruitmentPipeline extends StatefulWidget {
  const RecruitmentPipeline({super.key});

  @override
  State<RecruitmentPipeline> createState() => _RecruitmentPipelineState();
}

class _RecruitmentPipelineState extends State<RecruitmentPipeline> {
  bool _isLoading = true;
  String? _error;
  List<PipelineStage> _stages = [];

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
      final data = await RatebApiService.instance.getAgencyPipeline();
      if (!mounted) return;
      setState(() {
        _stages = data.stages;
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

  Color _colorFor(String statusKey) {
    switch (statusKey) {
      case 'cvs':
        return Colors.blue;
      case 'processing':
        return Colors.indigo;
      case 'deployed':
        return Colors.teal;
      case 'issue':
        return Colors.orange;
      case 'returned':
        return Colors.brown;
      default:
        return Colors.deepPurple;
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasOnlyEmptyStages =
        _stages.isNotEmpty && _stages.every((s) => s.count == 0);

    return DataStateView(
      isLoading: _isLoading,
      errorMessage: _error,
      onRetry: _load,
      isEmpty: _stages.isEmpty || hasOnlyEmptyStages,
      emptyTitle: 'Pipeline empty',
      emptyMessage: 'No candidates in the recruitment pipeline yet.',
      emptyIcon: Icons.timeline_outlined,
      loadingMessage: 'Loading pipeline…',
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Recruitment pipeline',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 16),
          ..._stages.where((s) => s.count > 0).map(
                (stage) {
                  final color = _colorFor(stage.statusKey);
                  return Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundColor: color.withValues(alpha: 0.15),
                        child: Text(
                          '${stage.count}',
                          style: TextStyle(
                            color: color,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      title: Text(stage.name),
                      subtitle: Text('${stage.count} candidates'),
                      trailing: const Icon(Icons.chevron_right),
                    ),
                  );
                },
              ),
        ],
      ),
    );
  }
}
