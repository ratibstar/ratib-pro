import 'package:flutter/material.dart';

import '../../../shared/widgets/empty_state.dart';

class WorkerTasks extends StatelessWidget {
  const WorkerTasks({super.key});

  static const _sampleTasks = [
    ('Complete onboarding documents', 'Due today', Icons.description_outlined),
    ('Attend safety briefing', 'Tomorrow', Icons.safety_check_outlined),
    ('Update contact information', 'This week', Icons.contact_phone_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Tasks',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
        const SizedBox(height: 12),
        ..._sampleTasks.map(
          (task) => Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              leading: Icon(task.$3),
              title: Text(task.$1),
              subtitle: Text(task.$2),
              trailing: const Icon(Icons.chevron_right),
            ),
          ),
        ),
        const SizedBox(height: 8),
        const EmptyState(
          title: 'Live task sync',
          message:
              'Tasks will load from the workforce API once connected to production endpoints.',
          icon: Icons.sync_outlined,
        ),
      ],
    );
  }
}
