import 'package:flutter/material.dart';

import '../../../shared/widgets/empty_state.dart';

class RecruitmentPipeline extends StatelessWidget {
  const RecruitmentPipeline({super.key});

  static const _stages = [
    ('Sourcing', 12, Colors.blue),
    ('Screening', 8, Colors.indigo),
    ('Interview', 6, Colors.deepPurple),
    ('Deployment', 4, Colors.teal),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Recruitment pipeline',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
        const SizedBox(height: 16),
        ..._stages.map(
          (stage) => Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: (stage.$3 as Color).withValues(alpha: 0.15),
                child: Text(
                  '${stage.$2}',
                  style: TextStyle(
                    color: stage.$3 as Color,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              title: Text(stage.$1 as String),
              subtitle: Text('${stage.$2} candidates'),
              trailing: const Icon(Icons.chevron_right),
            ),
          ),
        ),
        const EmptyState(
          title: 'Live pipeline',
          message:
              'Pipeline stages will sync from /api/mobile/agency/pipeline.php.',
          icon: Icons.timeline_outlined,
        ),
      ],
    );
  }
}
