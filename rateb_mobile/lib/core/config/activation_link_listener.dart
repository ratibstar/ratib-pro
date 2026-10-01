import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';
import 'company_activation.dart';

/// Activates the company from `ratebapp://activate?code=…` (activation page / QR),
/// so staff never type the code.
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

class _ActivationLinkListenerState extends State<ActivationLinkListener>
    with WidgetsBindingObserver {
  StreamSubscription<Uri>? _sub;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _sub = AppLinks().uriLinkStream.listen(_handle, onError: (_) {});
    unawaited(_linkFromPending());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sub?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(_linkFromPending());
  }

  /// Fresh install / launcher start: link the company the open-app page remembered for this phone.
  Future<void> _linkFromPending() async {
    if (_busy || CompanyActivation.isActive || CompanyActivation.embeddedCode.isNotEmpty) return;
    _busy = true;
    final error = await CompanyActivation.activateFromPending();
    _busy = false;
    if (error == null) await widget.onCompanyChanged();
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
