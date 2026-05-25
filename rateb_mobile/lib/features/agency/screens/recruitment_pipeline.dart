import 'package:flutter/material.dart';

import '../../../core/models/agency_models.dart';
import '../../../core/services/resilient_loader.dart';
import '../../../core/services/screen_cache.dart';
import '../../../core/services/rateb_api_service.dart';
import '../../../shared/widgets/data_state_view.dart';
import '../../../shared/widgets/skeleton_loader.dart';

class RecruitmentPipeline extends StatefulWidget {
  const RecruitmentPipeline({super.key});

  @override
  State<RecruitmentPipeline> createState() => _RecruitmentPipelineState();
}

class _RecruitmentPipelineState extends State<RecruitmentPipeline> {
  ScreenLoadResult<AgencyPipelineData>? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool manualRetry = false}) async {
    if (manualRetry || _result == null) {
      setState(() {
        _result = (_result ?? const ScreenLoadResult<AgencyPipelineData>())
            .copyWith(isLoading: true, clearError: true);
      });
    }

    final next = await ResilientLoader.execute(
      cacheKey: CacheKeys.agencyPipeline,
      fetch: RatebApiService.instance.getAgencyPipeline,
      manualRetry: manualRetry,
    );
    if (!mounted) return;
    setState(() => _result = next);
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
    final result = _result;
    final stages = result?.data?.stages ?? const <PipelineStage>[];
    final visibleStages = stages.where((s) => s.count > 0).toList();
    final isEmpty = visibleStages.isEmpty && result?.isLoading != true;

    return DataStateView(
      isLoading: result?.isLoading ?? true,
      isFromCache: result?.isFromCache ?? false,
      errorMessage: result?.showError == true ? result!.error : null,
      staleMessage: result?.showStaleData == true ? result!.error : null,
      onRetry: () => _load(manualRetry: true),
      isEmpty: isEmpty,
      emptyTitle: EmptyStateCopy.agencyPipelineTitle,
      emptyMessage: EmptyStateCopy.agencyPipelineMessage,
      emptyIcon: Icons.timeline_outlined,
      skeletonType: SkeletonType.cards,
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
          ...visibleStages.map(
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
