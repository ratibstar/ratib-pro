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

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  String _messageFor(AppFailure? failure, AppLocalizations l10n) {
    if (failure == null) return l10n.loginFailed;
    switch (failure.code) {
      case 'config':
        return l10n.loginConfigMissing;
      case 'network':
      case 'timeout':
        return l10n.loginNetworkError;
      case 'unauthorized':
        return failure.message?.isNotEmpty == true
            ? failure.message!
            : l10n.loginInvalidCredentials;
      case 'mobile_disabled':
        return failure.message?.isNotEmpty == true
            ? failure.message!
            : l10n.loginMobileDisabled;
      case 'forbidden':
        return failure.message?.isNotEmpty == true
            ? failure.message!
            : l10n.loginForbidden;
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

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final isAr = Localizations.localeOf(context).languageCode == 'ar';

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.xl),
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
            const SizedBox(height: AppSpacing.xxl),
            Text(
              l10n.appTitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              l10n.loginSubtitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.xxl),
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
                  _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            if (_busy)
              const DsLoadingState()
            else
              DsPrimaryButton(
                label: l10n.loginSubmit,
                onPressed: _submit,
              ),
            const SizedBox(height: AppSpacing.md),
            Text(
              l10n.loginErpOnlyHint,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}
