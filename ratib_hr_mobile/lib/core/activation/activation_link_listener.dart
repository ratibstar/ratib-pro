/// Activates the company from activation links (QR / browser / `ratebapp://`).
library;

import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:ratib_hr_mobile/core/activation/activation_bootstrap.dart';
import 'package:ratib_hr_mobile/core/activation/activation_intent_bridge.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
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

class _ActivationLinkListenerState extends State<ActivationLinkListener>
    with WidgetsBindingObserver {
  StreamSubscription<Uri>? _sub;
  bool _busy = false;
  final AppLinks _appLinks = AppLinks();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _sub = _appLinks.uriLinkStream.listen(_handle, onError: (_) {});
    unawaited(_handleInitial());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_consumeNativePending());
    }
  }

  Future<void> _consumeNativePending() async {
    final pending = await ActivationIntentBridge.consumePendingUri();
    if (pending != null) {
      final uri = Uri.tryParse(pending);
      if (uri != null) {
        await _handle(uri);
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sub?.cancel();
    super.dispose();
  }

  Future<void> _handleInitial() async {
    await _consumeNativePending();
    try {
      final uri = await _appLinks.getInitialLink();
      if (uri != null) {
        await _handle(uri);
      }
    } catch (_) {}
  }

  Future<void> _handle(Uri uri) async {
    if (_busy) return;
    final code = ActivationBootstrap.codeFromUri(uri);
    if (code == null) return;
    final embedded = CompanyActivation.normalize(BrandBuild.activationCode);
    if (embedded != null && embedded != code) {
      if (!mounted) return;
      final l10n = AppLocalizations.of(context);
      ScaffoldMessenger.maybeOf(context)?.showSnackBar(
        SnackBar(content: Text(l10n.activationBrandedWrongCompany)),
      );
      return;
    }
    final current = CompanyActivation.normalize(CompanyActivation.code ?? '');
    if (current != null && current == code) return;

    _busy = true;
    if (current != null && current != code) {
      await CompanyActivation.clear();
    }
    final error = await CompanyActivation.activate(code);
    if (error == null) {
      await widget.onCompanyChanged();
    }
    _busy = false;
    if (!mounted) return;
    final l10n = AppLocalizations.of(context);
    final message = switch (error) {
      null => '${l10n.activationDone} ${CompanyActivation.companyName ?? ''}',
      CompanyActivationError.invalidCode => l10n.activationInvalid,
      CompanyActivationError.wrongAndroidPackage => l10n.activationWrongAndroidPackage,
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
