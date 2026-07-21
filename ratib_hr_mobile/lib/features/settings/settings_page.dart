/// Application settings — local prefs + ERP change-password.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class SettingsPage extends StatefulWidget {
  const SettingsPage({
    super.key,
    required this.onLocaleChanged,
  });

  final LocaleChanged onLocaleChanged;

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  bool _loading = true;
  bool _biometric = false;
  bool _notifications = true;
  ThemeMode _theme = ThemeMode.system;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final settings = AppLocator.settings;
    final bio = await settings.biometricEnabled();
    final notif = await settings.notificationsEnabled();
    if (!mounted) return;
    setState(() {
      _biometric = bio;
      _notifications = notif;
      _theme = AppLocator.appearance.themeMode;
      _loading = false;
    });
  }

  void _showLegal(String title, String body) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(title, style: Theme.of(ctx).textTheme.titleLarge),
              const SizedBox(height: AppSpacing.md),
              Text(body),
              const SizedBox(height: AppSpacing.lg),
              DsPrimaryButton(
                label: AppLocalizations.of(ctx).settingsClose,
                onPressed: () => Navigator.pop(ctx),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _changePassword() async {
    final l10n = AppLocalizations.of(context);
    final current = TextEditingController();
    final next = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.settingsChangePassword),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: current,
              obscureText: true,
              decoration:
                  InputDecoration(labelText: l10n.settingsCurrentPassword),
            ),
            TextField(
              controller: next,
              obscureText: true,
              decoration: InputDecoration(labelText: l10n.settingsNewPassword),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.settingsCancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l10n.settingsSave),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) {
      current.dispose();
      next.dispose();
      return;
    }
    try {
      await AppLocator.settings.changePassword(
        currentPassword: current.text,
        newPassword: next.text,
      );
      if (!mounted) return;
      DsSnackbar.show(
        context,
        message: l10n.settingsPasswordChanged,
        kind: DsSnackbarKind.success,
      );
    } catch (e) {
      if (!mounted) return;
      final f = e is AppFailure ? e : AppLocator.errors.map(e);
      DsSnackbar.show(
        context,
        message: f.message ?? f.code,
        kind: DsSnackbarKind.error,
      );
    } finally {
      current.dispose();
      next.dispose();
    }
  }

  Widget _switchTile({
    required String title,
    required bool value,
    required ValueChanged<bool> onChanged,
    required IconData icon,
    required Color accent,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: 6,
      ),
      child: DsGlassTile(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: SwitchListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 8),
          secondary: DsIconBadge(icon: icon, color: accent),
          title: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          value: value,
          onChanged: onChanged,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final cfg = AppLocator.mobileConfiguration.current;
    final isAr = l10n.isArabic;
    final privacy = (cfg?.extensions['privacy_policy'] ?? l10n.settingsPrivacyBody)
        .toString();
    final terms = (cfg?.extensions['terms_of_service'] ?? l10n.settingsTermsBody)
        .toString();

    if (_loading) {
      return DsPageScaffold(
        title: l10n.navSettings,
        body: DsLoadingState(message: l10n.genericLoading),
      );
    }

    return DsPageScaffold(
      title: l10n.navSettings,
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          DsSectionHeader(title: l10n.settingsPreferences),
          DsListItem(
            title: l10n.language,
            subtitle: isAr ? l10n.arabic : l10n.english,
            leading: const DsIconBadge(
              icon: Icons.language,
              color: AppColors.auroraCyan,
            ),
            onTap: () {
              widget.onLocaleChanged(
                isAr ? const Locale('en') : AppConfig.defaultLocale,
              );
            },
          ),
          DsListItem(
            title: l10n.settingsTheme,
            subtitle: _themeLabel(l10n, _theme),
            leading: const DsIconBadge(
              icon: Icons.brightness_6_outlined,
              color: AppColors.auroraAmber,
            ),
            onTap: () async {
              final next = switch (_theme) {
                ThemeMode.system => ThemeMode.light,
                ThemeMode.light => ThemeMode.dark,
                ThemeMode.dark => ThemeMode.system,
              };
              await AppLocator.appearance.setThemeMode(next);
              setState(() => _theme = next);
            },
          ),
          _switchTile(
            title: l10n.settingsNotifications,
            value: _notifications,
            icon: Icons.notifications_outlined,
            accent: AppColors.auroraTeal,
            onChanged: (v) async {
              await AppLocator.settings.setNotificationsEnabled(v);
              setState(() => _notifications = v);
            },
          ),
          _switchTile(
            title: l10n.settingsBiometric,
            value: _biometric,
            icon: Icons.fingerprint_rounded,
            accent: AppColors.auroraRose,
            onChanged: (v) async {
              await AppLocator.settings.setBiometricEnabled(v);
              setState(() => _biometric = v);
            },
          ),
          DsSectionHeader(title: l10n.settingsAccount),
          DsListItem(
            title: l10n.settingsChangePassword,
            leading: const DsIconBadge(
              icon: Icons.lock_outline,
              color: AppColors.auroraTeal,
            ),
            onTap: _changePassword,
          ),
          DsSectionHeader(title: l10n.settingsAboutSection),
          DsListItem(
            title: l10n.settingsAbout,
            subtitle: [
              cfg?.displayName ?? AppConfig.appName,
              'v${AppConfig.versionLabel}',
              'Phase ${AppConfig.phase}',
            ].join(' · '),
            leading: const DsIconBadge(
              icon: Icons.info_outline,
              color: AppColors.badgeNeutral,
            ),
            trailing: const SizedBox.shrink(),
          ),
          DsListItem(
            title: l10n.settingsPrivacy,
            leading: const DsIconBadge(
              icon: Icons.privacy_tip_outlined,
              color: AppColors.auroraCyan,
            ),
            onTap: () => _showLegal(l10n.settingsPrivacy, privacy),
          ),
          DsListItem(
            title: l10n.settingsTerms,
            leading: const DsIconBadge(
              icon: Icons.description_outlined,
              color: AppColors.auroraAmber,
            ),
            onTap: () => _showLegal(l10n.settingsTerms, terms),
          ),
          const SizedBox(height: AppSpacing.md),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            child: DsOutlineButton(
              label: l10n.signOut,
              icon: Icons.logout,
              onPressed: () => AppLocator.signOut(),
            ),
          ),
          const SizedBox(height: AppSpacing.xxl),
        ],
      ),
    );
  }

  String _themeLabel(AppLocalizations l10n, ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return l10n.settingsThemeLight;
      case ThemeMode.dark:
        return l10n.settingsThemeDark;
      case ThemeMode.system:
        return l10n.settingsThemeSystem;
    }
  }
}
