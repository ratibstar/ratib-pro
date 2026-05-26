import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import 'workforce_badge_card.dart';
import 'workforce_badge_data.dart';

/// Preview printable workforce identity badge (demo / UI only).
///
/// TODO: Wire live QR from `/mobile/qr-generate.php` via authenticated session.
class QrBadgePreviewScreen extends StatelessWidget {
  const QrBadgePreviewScreen({super.key, this.data = WorkforceBadgeData.demo});

  final WorkforceBadgeData data;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.darkBackground,
      appBar: AppBar(
        title: const Text('Workforce badge'),
        backgroundColor: AppColors.darkSurface,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Workforce identity preview',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.darkText,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                'Live badges are issued from RATEB System Settings on out.ratib.sa.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.darkMuted,
                    ),
              ),
              const SizedBox(height: 28),
              Center(
                child: WorkforceBadgeCard(data: data, darkTheme: true),
              ),
              const SizedBox(height: 28),
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
