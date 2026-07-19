/// Avatar — initials or icon. No network image loading in Phase 0.7.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';

class DsAvatar extends StatelessWidget {
  const DsAvatar({
    super.key,
    this.initials,
    this.icon = AppIcons.profile,
    this.size = 40,
  });

  final String? initials;
  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) {
    final bg = Theme.of(context).colorScheme.secondaryContainer;
    final fg = Theme.of(context).colorScheme.onSecondaryContainer;
    return CircleAvatar(
      radius: size / 2,
      backgroundColor: bg,
      child: initials != null && initials!.trim().isNotEmpty
          ? Text(
              _twoChars(initials!.trim()),
              style: Theme.of(context).textTheme.labelLarge?.copyWith(color: fg),
            )
          : Icon(icon, color: fg, size: size * 0.5),
    );
  }

  static String _twoChars(String value) {
    final runes = value.runes.toList();
    if (runes.isEmpty) return '';
    if (runes.length == 1) {
      return String.fromCharCode(runes.first).toUpperCase();
    }
    return String.fromCharCodes(runes.take(2)).toUpperCase();
  }
}
