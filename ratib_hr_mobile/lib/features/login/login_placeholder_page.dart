/// Login route placeholder — Phase 0 shell only (no ERP auth yet).
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

class LoginPlaceholderPage extends StatelessWidget {
  const LoginPlaceholderPage({
    super.key,
    required this.onContinue,
    required this.onLocaleChanged,
    required this.currentLocale,
  });

  final VoidCallback onContinue;
  final LocaleChanged onLocaleChanged;
  final Locale currentLocale;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isAr = currentLocale.languageCode == 'ar';

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Align(
                alignment: AlignmentDirectional.topEnd,
                child: TextButton(
                  onPressed: () {
                    onLocaleChanged(
                      isAr ? const Locale('en') : AppConfig.defaultLocale,
                    );
                  },
                  child: Text(isAr ? l10n.english : l10n.arabic),
                ),
              ),
              const Spacer(),
              Text(
                l10n.appTitle,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 12),
              Text(
                l10n.phase0Subtitle,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 8),
              Text(
                l10n.phase0Placeholder,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
              ),
              const Spacer(),
              FilledButton(
                onPressed: onContinue,
                child: Text(l10n.continueDemo),
              ),
              const SizedBox(height: 12),
              Text(
                l10n.navLogin,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
