/// Phase 1 Login — ERP email/password via AuthPort only.
library;

import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/config/app_config.dart';
import 'package:ratib_hr_mobile/core/di/app_locator.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/routing/app_router.dart';
import 'package:ratib_hr_mobile/core/theme/tokens/tokens.dart';
import 'package:ratib_hr_mobile/features/login/auth_session.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:ratib_hr_mobile/shared/design_system/design_system.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({
    super.key,
    required this.session,
    required this.onLocaleChanged,
    required this.onSignedIn,
  });

  final AuthSession session;
  final LocaleChanged onLocaleChanged;
  final VoidCallback onSignedIn;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  bool _biometricAvailable = false;

  @override
  void initState() {
    super.initState();
    _refreshBiometric();
  }

  Future<void> _refreshBiometric() async {
    final ok = await widget.session.biometricUnlockAvailable();
    if (!mounted) return;
    setState(() => _biometricAvailable = ok);
  }

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  String _messageFor(AppFailure? failure, AppLocalizations l10n) {
    if (failure == null) return l10n.loginFailed;
    final erp = failure.message ?? '';
    if (erp.contains('Super admin API tokens disabled') ||
        erp.contains('Platform super-admin API tokens disabled') ||
        failure.code == 'platform_sa_token_disabled') {
      return l10n.loginPlatformSuperAdmin;
    }
    if (failure.code == 'no_company' || erp.contains('No company linked')) {
      return l10n.loginNoCompany;
    }
    if (erp.contains('Company access denied')) {
      return l10n.loginCompanyAccessDenied;
    }
    switch (failure.code) {
      case 'config':
        return l10n.loginConfigMissing;
      case 'network':
      case 'timeout':
        return l10n.loginNetworkError;
      case 'unauthorized':
        return erp.isNotEmpty ? erp : l10n.loginInvalidCredentials;
      case 'mobile_disabled':
        return erp.isNotEmpty ? erp : l10n.loginMobileDisabled;
      case 'forbidden':
        return erp.isNotEmpty ? erp : l10n.loginForbidden;
      case 'employee_unbound':
        return l10n.loginEmployeeUnbound;
      case 'employee_ambiguous':
        return l10n.loginEmployeeAmbiguous;
      case 'rate_limited':
        return l10n.loginRateLimited;
      default:
        return failure.message?.isNotEmpty == true
            ? failure.message!
            : l10n.loginFailed;
    }
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context);
    if (!AppLocator.environment.apisEnabled) {
      DsSnackbar.show(
        context,
        message: l10n.loginConfigMissing,
        kind: DsSnackbarKind.error,
      );
      return;
    }
    final id = _identifier.text.trim();
    final secret = _password.text;
    if (id.isEmpty || secret.isEmpty) {
      DsSnackbar.show(
        context,
        message: l10n.loginFieldsRequired,
        kind: DsSnackbarKind.error,
      );
      return;
    }

    setState(() => _busy = true);
    final ok = await widget.session.signIn(identifier: id, secret: secret);
    if (!mounted) return;
    setState(() => _busy = false);

    if (ok) {
      widget.onSignedIn();
      return;
    }
    DsSnackbar.show(
      context,
      message: _messageFor(widget.session.lastError, l10n),
      kind: DsSnackbarKind.error,
    );
  }

  Future<void> _unlockBiometric() async {
    final l10n = AppLocalizations.of(context);
    setState(() => _busy = true);
    final ok = await widget.session.unlockWithBiometric();
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      widget.onSignedIn();
      return;
    }
    DsSnackbar.show(
      context,
      message: l10n.loginBiometricFailed,
      kind: DsSnackbarKind.error,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isAr = Localizations.localeOf(context).languageCode == 'ar';
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return DsPageBackdrop(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(24, 12, 24, 32),
            children: [
              Align(
                alignment: AlignmentDirectional.topEnd,
                child: TextButton(
                  onPressed: () {
                    widget.onLocaleChanged(
                      isAr ? const Locale('en') : AppConfig.defaultLocale,
                    );
                  },
                  child: Text(isAr ? l10n.english : l10n.arabic),
                ),
              ),
              const SizedBox(height: 28),
              Container(
                width: double.infinity,
                margin: const EdgeInsets.only(bottom: 16),
                padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
                color: const Color(0xFFFF0000),
                child: const Text(
                  AppConfig.buildStamp,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 22,
                    letterSpacing: 1,
                  ),
                ),
              ),
              Center(
                child: Container(
                  width: 88,
                  height: 88,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(28),
                    color: const Color(0xFFFF0000),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFFFF0000).withValues(alpha: 0.4),
                        blurRadius: 24,
                        offset: const Offset(0, 12),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.warning_amber_rounded,
                    color: Colors.white,
                    size: 42,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'رتب جديد 0.1.20',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Color(0xFFFF0000),
                  fontWeight: FontWeight.w900,
                  fontSize: 28,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                l10n.loginSubtitle,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
              ),
              const SizedBox(height: 36),
              DsGlassTile(
                padding: const EdgeInsets.fromLTRB(18, 22, 18, 22),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    DsTextField(
                      controller: _identifier,
                      label: l10n.loginEmailLabel,
                      hint: l10n.loginEmailHint,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      enabled: !_busy,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    DsTextField(
                      controller: _password,
                      label: l10n.loginPasswordLabel,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.done,
                      enabled: !_busy,
                      suffixIcon: IconButton(
                        onPressed: () => setState(() => _obscure = !_obscure),
                        icon: Icon(
                          _obscure
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    if (_busy)
                      const DsLoadingState()
                    else ...[
                      DsPrimaryButton(
                        label: l10n.loginSubmit,
                        onPressed: _submit,
                        icon: Icons.login_rounded,
                      ),
                      if (_biometricAvailable) ...[
                        const SizedBox(height: AppSpacing.md),
                        OutlinedButton.icon(
                          onPressed: _unlockBiometric,
                          icon: const Icon(Icons.fingerprint_rounded),
                          label: Text(l10n.loginBiometric),
                        ),
                      ],
                    ],
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                l10n.loginErpOnlyHint,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: isDark
                          ? AppColors.textSecondaryDark
                          : AppColors.textSecondary,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
