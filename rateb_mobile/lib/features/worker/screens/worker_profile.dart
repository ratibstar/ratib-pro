import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../shared/widgets/empty_state.dart';
import '../../auth/providers/auth_provider.dart';

class WorkerProfile extends StatelessWidget {
  const WorkerProfile({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor:
                          theme.colorScheme.primary.withValues(alpha: 0.12),
                      child: Icon(
                        Icons.person,
                        color: theme.colorScheme.primary,
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            auth.username ?? 'Worker',
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            'Worker account',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: theme.colorScheme.onSurface
                                  .withValues(alpha: 0.65),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const Divider(height: 32),
                _ProfileRow(label: 'Role', value: 'Worker'),
                _ProfileRow(label: 'Status', value: 'Active'),
                _ProfileRow(label: 'Portal', value: 'Mobile workforce'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        const EmptyState(
          title: 'Extended profile',
          message:
              'Connect to /api/mobile/auth/profile.php for full worker details from RATEB.',
          icon: Icons.info_outline,
        ),
      ],
    );
  }
}

class _ProfileRow extends StatelessWidget {
  const _ProfileRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .onSurface
                        .withValues(alpha: 0.65),
                  ),
            ),
          ),
          Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }
}
