import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_localizations.dart';
import '../config/app_config.dart';
import '../config/brand_build.dart';

/// Shows a banner when a newer APK of this app is published on rateb.sa.
class AppUpdateBanner extends StatefulWidget {
  const AppUpdateBanner({super.key, required this.child});

  static const String _downloads = 'https://rateb.sa/rateb-erp/public/downloads/';

  /// Company-branded builds (scripts/build-branded-app.ps1) follow their own update channel.
  static const String _brandKey = BrandBuild.key;

  final Widget child;

  @override
  State<AppUpdateBanner> createState() => _AppUpdateBannerState();
}

class _AppUpdateBannerState extends State<AppUpdateBanner>
    with WidgetsBindingObserver {
  static const _dismissKey = 'app_update.dismissed_version_code';

  String? _downloadUrl;
  int _latestCode = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _check();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _check();
    }
  }

  Future<int> _installedBuild() async {
    final raw = (await PackageInfo.fromPlatform()).buildNumber;
    return int.tryParse(raw) ?? AppConfig.buildNumber;
  }

  Future<int> _dismissedCode() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_dismissKey) ?? 0;
  }

  Future<void> _setDismissed(int code) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_dismissKey, code);
  }

  Future<void> _check() async {
    const branded = AppUpdateBanner._brandKey != '';
    const dir = branded
        ? '${AppUpdateBanner._downloads}company/'
        : AppUpdateBanner._downloads;
    try {
      final manifestUrl = branded
          ? '$dir${AppUpdateBanner._brandKey}.json'
          : '${dir}mobile-apps-latest.json';
      final response = await Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
          headers: {'Cache-Control': 'no-cache'},
        ),
      ).get<dynamic>(
        '$manifestUrl?_=${DateTime.now().millisecondsSinceEpoch}',
      );
      final data = response.data;
      final app = data is Map ? (branded ? data : data['customer']) : null;
      if (app is! Map) {
        if (mounted) setState(() => _downloadUrl = null);
        return;
      }
      final latest = int.tryParse('${app['version_code'] ?? 0}') ?? 0;
      final file = '${app['file'] ?? ''}';
      final installed = await _installedBuild();
      if (latest <= installed ||
          !RegExp(r'^[a-z0-9][a-z0-9.\-]*\.apk$').hasMatch(file)) {
        if (mounted) {
          setState(() {
            _downloadUrl = null;
            _latestCode = latest;
          });
        }
        if (latest > 0 && installed >= latest) {
          await _setDismissed(0);
        }
        return;
      }
      final dismissed = await _dismissedCode();
      if (dismissed >= latest) {
        if (mounted) setState(() => _downloadUrl = null);
        return;
      }
      if (!mounted) return;
      setState(() {
        _latestCode = latest;
        _downloadUrl = '$dir$file';
      });
    } catch (_) {
      // Offline or manifest unavailable: no prompt.
    }
  }

  Future<void> _onLater() async {
    if (_latestCode > 0) {
      await _setDismissed(_latestCode);
    }
    if (mounted) setState(() => _downloadUrl = null);
  }

  Future<void> _onUpdate(String url) async {
    if (mounted) setState(() => _downloadUrl = null);
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
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
                      onPressed: _onLater,
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
                      onPressed: () => _onUpdate(url),
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
