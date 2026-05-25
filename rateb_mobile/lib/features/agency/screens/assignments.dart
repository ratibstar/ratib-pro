import 'package:flutter/material.dart';

import '../../../shared/widgets/empty_state.dart';

class Assignments extends StatelessWidget {
  const Assignments({super.key});

  static const _assignments = [
    ('Gulf Construction Co.', '5 workers deployed'),
    ('Metro Facilities', '2 workers · visa pending'),
    ('Al Noor Trading', '1 worker · onboarding'),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
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
              title: Text(item.$1),
              subtitle: Text(item.$2),
              trailing: const Icon(Icons.chevron_right),
            ),
          ),
        ),
        const EmptyState(
          title: 'Assignment sync',
          message:
              'Client assignments will load from the agency mobile API.',
          icon: Icons.assignment_ind_outlined,
        ),
      ],
    );
  }
}
