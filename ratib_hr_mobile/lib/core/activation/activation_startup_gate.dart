/// Cold start: apply QR / green-button link before login (engine + MethodChannel ready).
library;

import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:ratib_hr_mobile/core/activation/activation_bootstrap.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';

class ActivationStartupGate extends StatefulWidget {
  const ActivationStartupGate({super.key, required this.child});

  final Widget child;

  @override
  State<ActivationStartupGate> createState() => _ActivationStartupGateState();
}

class _ActivationStartupGateState extends State<ActivationStartupGate> {
  bool _done = false;
  String _packageLine = '';
  bool _wrongBrandedApp = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  Future<void> _boot() async {
    try {
      final info = await PackageInfo.fromPlatform();
      _packageLine = '${info.packageName} · ${info.version}+${info.buildNumber}';
      _wrongBrandedApp = info.packageName.contains('.c');
      if (_wrongBrandedApp) {
        if (mounted) setState(() => _done = true);
        return;
      }

      await CompanyActivation.load(deferBackgroundRefresh: true);
      await CompanyActivation.purgeWrongTenantForUnifiedPackage();

      final uri = await ActivationBootstrap.resolveLaunchUri();
      if (uri != null) {
        final err = await ActivationBootstrap.applyLaunchUri(uri);
        if (err == null) {
          await ActivationBootstrap.clearPendingAfterSuccess();
        }
        await CompanyActivation.purgeWrongTenantForUnifiedPackage();
      } else if (BrandBuild.activationCode.isNotEmpty) {
        await CompanyActivation.activate(BrandBuild.activationCode);
      }
      if (!CompanyActivation.isActive && BrandBuild.activationCode.isEmpty) {
        await CompanyActivation.activateFromPending();
      }
    } catch (_) {}
    if (mounted) setState(() => _done = true);
  }

  @override
  Widget build(BuildContext context) {
    if (!_done) {
      return const ColoredBox(
        color: Color(0xFF0F172A),
        child: Center(
          child: CircularProgressIndicator(color: Color(0xFF14B8A6)),
        ),
      );
    }

    if (_wrongBrandedApp) {
      final l10n = AppLocalizations.of(context);
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Spacer(),
                Text(
                  l10n.wrongHrAppTitle,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 12),
                Text(
                  l10n.wrongHrAppBody(_packageLine),
                  textAlign: TextAlign.center,
                ),
                const Spacer(),
              ],
            ),
          ),
        ),
      );
    }

    return widget.child;
  }
}
