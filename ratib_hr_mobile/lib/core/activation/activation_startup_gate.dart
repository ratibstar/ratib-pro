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
  String _statusLine = '';
  String _packageLine = '';
  bool _wrongBrandedApp = false;

  @override
  void initState() {
    super.initState();
    CompanyActivation.revision.addListener(_onCompanyChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  @override
  void dispose() {
    CompanyActivation.revision.removeListener(_onCompanyChanged);
    super.dispose();
  }

  void _onCompanyChanged() {
    if (!mounted || !_done) return;
    setState(() {
      _statusLine = CompanyActivation.isActive
          ? 'ok #${CompanyActivation.companyId} ${CompanyActivation.companyName ?? ''}'
          : 'no_link';
    });
  }

  Future<void> _boot() async {
    try {
      final info = await PackageInfo.fromPlatform();
      _packageLine = '${info.packageName} · ${info.version}+${info.buildNumber}';
      _wrongBrandedApp = info.packageName.contains('.c');
      if (_wrongBrandedApp) {
        _statusLine = 'branded_apk';
        if (mounted) setState(() => _done = true);
        return;
      }

      await CompanyActivation.load(deferBackgroundRefresh: true);
      await CompanyActivation.purgeWrongTenantForUnifiedPackage();

      final uri = await ActivationBootstrap.resolveLaunchUri();
      CompanyActivationError? err;
      if (uri != null) {
        err = await ActivationBootstrap.applyLaunchUri(uri);
        if (err == null) {
          await ActivationBootstrap.clearPendingAfterSuccess();
        }
        await CompanyActivation.purgeWrongTenantForUnifiedPackage();
      } else if (BrandBuild.activationCode.isNotEmpty) {
        err = await CompanyActivation.activate(BrandBuild.activationCode);
      }
      if (!CompanyActivation.isActive && BrandBuild.activationCode.isEmpty) {
        if (await CompanyActivation.activateFromPending() == null) {
          err = null;
        }
      }

      if (err == null && CompanyActivation.isActive) {
        _statusLine =
            'ok #${CompanyActivation.companyId} ${CompanyActivation.companyName ?? ''}';
      } else if (err != null) {
        _statusLine = 'err $err';
      } else if (!CompanyActivation.isActive) {
        _statusLine = 'no_link';
      } else {
        _statusLine = 'ok #${CompanyActivation.companyId}';
      }
    } catch (e) {
      _statusLine = 'boot $e';
    }
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
                const SizedBox(height: 8),
                Text(
                  _packageLine,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontFamily: 'monospace'),
                ),
                const Spacer(),
              ],
            ),
          ),
        ),
      );
    }

    final showBanner = _statusLine.startsWith('ok #49') ||
        _statusLine.contains('تجربة');
    final isAr = Localizations.localeOf(context).languageCode == 'ar';

    return Column(
      children: [
        if (_packageLine.isNotEmpty || _statusLine.isNotEmpty)
          Material(
            color: showBanner
                ? Colors.green.shade900
                : (_statusLine.startsWith('ok')
                    ? Colors.orange.shade900
                    : Colors.red.shade900),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Text(
                isAr
                    ? '$_packageLine\nربط: $_statusLine'
                    : '$_packageLine\nlink: $_statusLine',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontFamily: 'monospace',
                  height: 1.35,
                ),
              ),
            ),
          ),
        Expanded(child: widget.child),
      ],
    );
  }
}
