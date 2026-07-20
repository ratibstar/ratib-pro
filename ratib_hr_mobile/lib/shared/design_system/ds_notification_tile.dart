/// Notification list tile — presentation only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_list_item.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_surfaces.dart';

class DsNotificationTile extends StatelessWidget {
  const DsNotificationTile({
    super.key,
    required this.title,
    this.body,
    this.timeLabel,
    this.unread = false,
    this.onTap,
  });

  final String title;
  final String? body;
  final String? timeLabel;
  final bool unread;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final accent =
        unread ? AppColors.auroraCyan : AppColors.badgeNeutral;
    return DsListItemRaw(
      onTap: onTap,
      leading: DsIconBadge(
        icon: AppIcons.notifications,
        color: accent,
      ),
      title: Text(
        title,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
            ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (body != null && body!.isNotEmpty) Text(body!),
          if (timeLabel != null && timeLabel!.isNotEmpty)
            Text(
              timeLabel!,
              style: Theme.of(context).textTheme.bodySmall,
            ),
        ],
      ),
      trailing: unread
          ? Container(
              width: 10,
              height: 10,
              decoration: const BoxDecoration(
                color: AppColors.auroraCyan,
                shape: BoxShape.circle,
              ),
            )
          : null,
    );
  }
}
