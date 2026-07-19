/// Notification list tile — presentation only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/shared/design_system/ds_list_item.dart';

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
    return DsListItemRaw(
      onTap: onTap,
      leading: Icon(
        AppIcons.notifications,
        color: unread
            ? Theme.of(context).colorScheme.secondary
            : Theme.of(context).colorScheme.onSurfaceVariant,
      ),
      title: Text(
        title,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: unread ? FontWeight.w700 : FontWeight.w600,
            ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (body != null) Text(body!),
          if (timeLabel != null)
            Text(
              timeLabel!,
              style: Theme.of(context).textTheme.bodySmall,
            ),
        ],
      ),
    );
  }
}
