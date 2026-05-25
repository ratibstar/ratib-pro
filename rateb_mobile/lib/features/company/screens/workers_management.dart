import 'package:flutter/material.dart';

import '../../../shared/widgets/empty_state.dart';

class WorkersManagement extends StatelessWidget {
  const WorkersManagement({super.key});

  static const _workers = [
    ('Ahmed Hassan', 'Active · Site A'),
    ('Maria Santos', 'Pending onboarding'),
    ('John Okello', 'Active · Site B'),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
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
              title: Text(worker.$1),
              subtitle: Text(worker.$2),
              trailing: const Icon(Icons.more_vert),
            ),
          ),
        ),
        const EmptyState(
          title: 'Workforce API',
          message:
              'Worker roster will sync from /api/mobile/company/workers.php.',
          icon: Icons.groups_outlined,
        ),
      ],
    );
  }
}
