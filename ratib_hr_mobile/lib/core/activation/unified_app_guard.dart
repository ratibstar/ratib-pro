/// Blocks company-branded HR packages from masquerading as the unified multi-tenant app.
library;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:ratib_hr_mobile/core/env/app_flavor.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';
import 'package:ratib_hr_mobile/l10n/app_localizations.dart';
import 'package:url_launcher/url_launcher.dart';

const _unifiedPackage = 'sa.rateb.hr.mobile';
const _unifiedApkUrl =
    'https://rateb.sa/rateb-erp/public/downloads/rateb-hr-mobile-latest.apk';

/// Production unified build only — branded APKs (`.c<id>`) use [BrandBuild.key].
class UnifiedAppGuard extends StatefulWidget {
  const UnifiedAppGuard({super.key, required this.child});

  final Widget child;

  @override
  State<UnifiedAppGuard> createState() => _UnifiedAppGuardState();
}

class _UnifiedAppGuardState extends State<UnifiedAppGuard> {
  String? _package;
  bool _checked = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final info = await PackageInfo.fromPlatform();
      _package = info.packageName;
    } catch (_) {
      _package = '';
    }
    if (mounted) setState(() => _checked = true);
  }

  bool _mustBlock() {
    if (!_checked || kDebugMode) return false;
    if (BrandBuild.key.isNotEmpty) return false;
    const env = DartDefineAppEnvironment();
    if (env.flavor != AppFlavor.production) return false;
    final pkg = (_package ?? '').trim();
    return pkg.isNotEmpty && pkg != _unifiedPackage;
  }

  @override
  Widget build(BuildContext context) {
    if (!_mustBlock()) return widget.child;
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Spacer(),
              Icon(Icons.warning_amber_rounded,
                  size: 64, color: Theme.of(context).colorScheme.error),
              const SizedBox(height: 16),
              Text(
                l10n.wrongHrAppTitle,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 12),
              Text(
                l10n.wrongHrAppBody(_package ?? ''),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: () => launchUrl(
                  Uri.parse(_unifiedApkUrl),
                  mode: LaunchMode.externalApplication,
                ),
                icon: const Icon(Icons.download),
                label: Text(l10n.wrongHrAppDownload),
              ),
              const Spacer(),
            ],
          ),
        ),
      ),
    );
  }
}
