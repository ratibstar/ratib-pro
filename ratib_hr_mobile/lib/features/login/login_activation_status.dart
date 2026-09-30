/// Login screen: which app package is installed and which company is linked.
library;

import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';

class LoginActivationStatus extends StatefulWidget {
  const LoginActivationStatus({super.key});

  @override
  State<LoginActivationStatus> createState() => _LoginActivationStatusState();
}

class _LoginActivationStatusState extends State<LoginActivationStatus> {
  String _package = '';
  String _version = '';

  @override
  void initState() {
    super.initState();
    CompanyActivation.revision.addListener(_onRevision);
    _loadPackage();
  }

  @override
  void dispose() {
    CompanyActivation.revision.removeListener(_onRevision);
    super.dispose();
  }

  void _onRevision() {
    if (mounted) setState(() {});
  }

  Future<void> _loadPackage() async {
    try {
      final info = await PackageInfo.fromPlatform();
      if (!mounted) return;
      setState(() {
        _package = info.packageName;
        _version = '${info.version}+${info.buildNumber}';
      });
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final linked = CompanyActivation.isActive;
    final cid = CompanyActivation.companyId;
    final erp = CompanyActivation.erpBaseUrl ?? '';
    final host = Uri.tryParse(erp)?.host ?? erp;
    final isUnifiedPkg = _package == 'sa.rateb.hr.mobile';
    final isBrandedSibling = _package.contains('.c');
    final wrongTenantOnUnified = isUnifiedPkg &&
        linked &&
        (CompanyActivation.companyId == 51 ||
            (CompanyActivation.companyName ?? '').contains('العرفج'));

    Color? bg;
    if (isBrandedSibling || wrongTenantOnUnified) {
      bg = Theme.of(context).colorScheme.errorContainer;
    } else if (!linked) {
      bg = Theme.of(context).colorScheme.tertiaryContainer;
    } else if (!isUnifiedPkg && _package.isNotEmpty) {
      bg = Theme.of(context).colorScheme.errorContainer;
    } else {
      bg = Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.55);
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: DefaultTextStyle(
        style: Theme.of(context).textTheme.bodySmall!.copyWith(
              fontFamily: 'monospace',
              height: 1.35,
            ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_package.isNotEmpty)
              Text('pkg $_package · $_version', textDirection: TextDirection.ltr),
            if (linked) ...[
              Text(
                'company #${cid ?? '?'} · ${CompanyActivation.companyName ?? ''}',
                textDirection: TextDirection.ltr,
              ),
              if (host.isNotEmpty)
                Text('erp $host', textDirection: TextDirection.ltr),
            ] else
              Text(
                'no company linked — enter activation code before login',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            if (isBrandedSibling)
              Text(
                'wrong app: uninstall Al-Arfaj / branded HR (.c…) and install sa.rateb.hr.mobile',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontFamily: null,
                ),
              ),
            if (wrongTenantOnUnified)
              Text(
                'stale Al-Arfaj link in unified app — tap Change company, clear link, scan QR again (build 224+)',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontFamily: null,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
