import 'package:flutter/material.dart';

import 'workforce_badge_card.dart';
import 'workforce_badge_data.dart';

/// Preview printable workforce identity badge (demo / UI only).
class QrBadgePreviewScreen extends StatelessWidget {
  const QrBadgePreviewScreen({super.key, this.data = WorkforceBadgeData.demo});

  final WorkforceBadgeData data;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Workforce badge preview'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Sample identity card',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                'Preview only — live badges are issued from RATEB System Settings.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.65),
                    ),
              ),
              const SizedBox(height: 24),
              Center(child: WorkforceBadgeCard(data: data)),
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.check),
                label: const Text('Done'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
