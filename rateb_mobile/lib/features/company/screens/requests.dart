import 'package:flutter/material.dart';

import '../../../shared/widgets/empty_state.dart';

class Requests extends StatelessWidget {
  const Requests({super.key});

  static const _requests = [
    ('New hire — Electrician', 'Submitted · 2 days ago'),
    ('Visa renewal batch', 'In review'),
    ('Replacement request', 'Draft'),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Requests',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ),
            FilledButton.tonalIcon(
              onPressed: () {},
              icon: const Icon(Icons.add),
              label: const Text('New'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ..._requests.map(
          (request) => Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              leading: const Icon(Icons.description_outlined),
              title: Text(request.$1),
              subtitle: Text(request.$2),
              trailing: const Icon(Icons.chevron_right),
            ),
          ),
        ),
        const EmptyState(
          title: 'Request pipeline',
          message:
              'Recruitment requests will load from the company mobile API.',
          icon: Icons.inbox_outlined,
        ),
      ],
    );
  }
}
