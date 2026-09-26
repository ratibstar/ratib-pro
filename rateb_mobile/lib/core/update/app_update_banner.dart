import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_localizations.dart';
import '../config/app_config.dart';

/// Shows a banner when a newer APK of this app is published on rateb.sa.
class AppUpdateBanner extends StatefulWidget {
  const AppUpdateBanner({super.key, required this.child});

  static const String _downloads = 'https://rateb.sa/rateb-erp/public/downloads/';

  final Widget child;

  @override
  State<AppUpdateBanner> createState() => _AppUpdateBannerState();
}

class _AppUpdateBannerState extends State<AppUpdateBanner> {
  String? _downloadUrl;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    try {
      final response = await Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
        ),
      ).get<dynamic>('${AppUpdateBanner._downloads}mobile-apps-latest.json');
      final data = response.data;
      final app = data is Map ? data['customer'] : null;
      if (app is! Map) return;
      final latest = int.tryParse('${app['version_code'] ?? 0}') ?? 0;
      final file = '${app['file'] ?? ''}';
      if (latest <= AppConfig.buildNumber ||
          !RegExp(r'^[a-z0-9][a-z0-9.\-]*\.apk$').hasMatch(file)) {
        return;
      }
      if (!mounted) return;
      setState(() => _downloadUrl = '${AppUpdateBanner._downloads}$file');
    } catch (_) {
      // Offline or manifest unavailable: no prompt.
    }
  }

  @override
  Widget build(BuildContext context) {
    final url = _downloadUrl;
    if (url == null) return widget.child;
    final l10n = AppLocalizations.of(context);
    return Stack(
      children: [
        widget.child,
        Positioned(
          left: 12,
          right: 12,
          bottom: 12,
          child: SafeArea(
            child: Material(
              color: const Color(0xFF0F766E),
              borderRadius: BorderRadius.circular(12),
              elevation: 8,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
                child: Row(
                  children: [
                    const Icon(Icons.system_update_rounded, color: Colors.white),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l10n.updateAvailable,
                        style: const TextStyle(color: Colors.white),
                      ),
                    ),
                    TextButton(
                      onPressed: () => setState(() => _downloadUrl = null),
                      child: Text(
                        l10n.updateLater,
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ),
                    FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: const Color(0xFF0F766E),
                      ),
                      onPressed: () => launchUrl(
                        Uri.parse(url),
                        mode: LaunchMode.externalApplication,
                      ),
                      child: Text(l10n.updateNow),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
