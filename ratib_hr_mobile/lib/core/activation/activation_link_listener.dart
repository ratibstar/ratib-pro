/// Activates the company from `ratebapp://activate?code=…` (activation page / QR),
/// so staff never type the code.
library;

import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

class ActivationLinkListener extends StatefulWidget {
  const ActivationLinkListener({
    super.key,
    required this.child,
    required this.onCompanyChanged,
  });

  final Widget child;

  /// Called after a different company was linked (sign out of the previous one).
  final Future<void> Function() onCompanyChanged;

  @override
  State<ActivationLinkListener> createState() => _ActivationLinkListenerState();
}

class _ActivationLinkListenerState extends State<ActivationLinkListener> {
  StreamSubscription<Uri>? _sub;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _sub = AppLinks().uriLinkStream.listen(_handle, onError: (_) {});
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  Future<void> _handle(Uri uri) async {
    if (uri.scheme != 'ratebapp' || uri.host != 'activate' || _busy) return;
    final code = CompanyActivation.normalize(uri.queryParameters['code'] ?? '');
    if (code != null && code == CompanyActivation.code) return;
    _busy = true;
    final error = code == null
        ? CompanyActivationError.invalidCode
        : await CompanyActivation.activate(code);
    if (error == null) await widget.onCompanyChanged();
    _busy = false;
    if (!mounted) return;
    final l10n = AppLocalizations.of(context);
    final message = switch (error) {
      null => '${l10n.activationDone} ${CompanyActivation.companyName ?? ''}',
      CompanyActivationError.invalidCode => l10n.activationInvalid,
      CompanyActivationError.appNotEnabled => l10n.activationAppDisabled,
      CompanyActivationError.rateLimited => l10n.activationRateLimited,
      CompanyActivationError.network => l10n.activationNetworkError,
    };
    ScaffoldMessenger.maybeOf(context)
        ?.showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
