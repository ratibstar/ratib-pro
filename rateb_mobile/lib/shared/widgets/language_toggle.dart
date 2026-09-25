import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_localizations.dart';
import '../../l10n/locale_controller.dart';

/// Switches between Arabic and English. [compact] is for app bars.
class LanguageToggle extends StatelessWidget {
  const LanguageToggle({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final controller = context.read<LocaleController>();

    if (compact) {
      return Tooltip(
        message: l10n.changeLanguage,
        child: TextButton(
          onPressed: controller.toggle,
          child: Text(
            l10n.otherLanguageShort,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      );
    }

    return TextButton.icon(
      onPressed: controller.toggle,
      icon: const Icon(Icons.language_rounded, size: 20),
      label: Text(l10n.otherLanguage),
    );
  }
}
