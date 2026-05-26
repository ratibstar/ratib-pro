import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_endpoints.dart';
import '../../core/auth/token_storage.dart';
import '../../core/config/app_config.dart';
import '../../core/debug/api_telemetry.dart';
import '../../core/debug/device_diagnostics.dart';
import '../../core/debug/diagnostics_config.dart';
import '../../core/services/network_monitor.dart';
import '../../core/services/screen_cache.dart';
import '../auth/providers/auth_provider.dart';
import '../qr/qr_scanner_screen.dart';

/// Internal pilot tools — debug builds only.
class PilotToolsScreen extends StatefulWidget {
  const PilotToolsScreen({super.key});

  static bool get isAvailable => kDebugMode;

  @override
  State<PilotToolsScreen> createState() => _PilotToolsScreenState();
}

class _PilotToolsScreenState extends State<PilotToolsScreen> {
  final _tokenStorage = TokenStorage();
  DeviceDiagnosticsSnapshot? _snapshot;
  Map<String, dynamic>? _jwtClaims;
  String? _profileResult;
  String? _statusMessage;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (PilotToolsScreen.isAvailable) {
      _refreshDiagnostics();
    }
  }

  Future<void> _refreshDiagnostics() async {
    setState(() => _busy = true);
    final token = await _tokenStorage.readToken();
    final snapshot = await DeviceDiagnostics.collect(
      hasToken: Future.value(token != null && token.isNotEmpty),
      probeHealth: DeviceDiagnostics.probeHealthApi,
    );
    Map<String, dynamic>? claims;
    if (token != null && token.isNotEmpty) {
      claims = DeviceDiagnostics.decodeJwtPayloadClaims(token);
    }
    if (mounted) {
      setState(() {
        _snapshot = snapshot;
        _jwtClaims = claims;
        _busy = false;
      });
    }
  }

  Future<void> _clearToken() async {
    await _tokenStorage.clear();
    if (!mounted) return;
    await context.read<AuthProvider>().handleUnauthorized();
    setState(() => _statusMessage = 'Token cleared.');
    await _refreshDiagnostics();
  }

  void _clearCache() {
    ScreenCache.instance.clear();
    ApiTelemetry.reset();
    setState(() => _statusMessage = 'In-memory cache cleared.');
  }

  Future<void> _copyApiBase() async {
    await Clipboard.setData(ClipboardData(text: AppConfig.apiBaseUrl));
    setState(() => _statusMessage = 'API base URL copied.');
  }

  Future<void> _testProfile() async {
    setState(() {
      _busy = true;
      _profileResult = null;
    });
    final client = ApiClient(tokenProvider: _tokenStorage.readToken);
    try {
      final payload = await client.get(ApiEndpoints.authProfile);
      final data = payload['data'] ?? payload;
      _profileResult = const JsonEncoder.withIndent('  ').convert(data);
      _statusMessage = 'Profile endpoint OK (${ApiTelemetry.lastLatencyMs} ms).';
    } catch (e) {
      _profileResult = e.toString();
      _statusMessage = 'Profile test failed.';
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _fakeQrPayload() {
    final stamp = DateTime.now().millisecondsSinceEpoch;
    return 'RATEBMOBQR:PILOT-UI-TEST:$stamp';
  }

  Future<void> _copyFakeQr() async {
    final payload = _fakeQrPayload();
    await Clipboard.setData(ClipboardData(text: payload));
    setState(() => _statusMessage = 'Fake QR payload copied (UI test only).');
  }

  void _openScannerWithFakeQr() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => const QrScannerScreen(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!PilotToolsScreen.isAvailable) {
      return const Scaffold(
        body: Center(child: Text('Pilot tools are not available in release.')),
      );
    }

    final simulateOffline = NetworkMonitor.instance.simulateOffline;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pilot tools'),
        actions: [
          IconButton(
            tooltip: 'Refresh diagnostics',
            onPressed: _busy ? null : _refreshDiagnostics,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_statusMessage != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Material(
                color: Theme.of(context).colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(8),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(_statusMessage!),
                ),
              ),
            ),
          _Section(
            title: 'Session',
            children: [
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Clear token'),
                subtitle: const Text('Removes stored JWT and signs out locally'),
                onTap: _busy ? null : _clearToken,
              ),
              ListTile(
                leading: const Icon(Icons.cleaning_services_outlined),
                title: const Text('Clear cache'),
                subtitle: const Text('Screen cache + API telemetry counters'),
                onTap: _clearCache,
              ),
            ],
          ),
          _Section(
            title: 'Network',
            children: [
              SwitchListTile(
                secondary: const Icon(Icons.cloud_off_outlined),
                title: const Text('Simulate offline'),
                subtitle: const Text('Blocks API requests (debug only)'),
                value: simulateOffline,
                onChanged: (value) {
                  NetworkMonitor.instance.setSimulateOffline(value);
                  setState(() {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.link),
                title: const Text('Copy API base URL'),
                subtitle: Text(AppConfig.apiBaseUrl),
                onTap: _copyApiBase,
              ),
              ListTile(
                leading: const Icon(Icons.person_search_outlined),
                title: const Text('Test profile endpoint'),
                subtitle: const Text('GET /mobile/profile.php with stored token'),
                onTap: _busy ? null : _testProfile,
              ),
            ],
          ),
          if (_jwtClaims != null)
            _Section(
              title: 'JWT payload (claims only)',
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: SelectableText(
                    jsonEncode(_jwtClaims),
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
                  ),
                ),
              ],
            ),
          if (_profileResult != null)
            _Section(
              title: 'Profile test result',
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: SelectableText(
                    _profileResult!,
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
                  ),
                ),
              ],
            ),
          _Section(
            title: 'QR UI testing',
            children: [
              ListTile(
                leading: const Icon(Icons.qr_code_2),
                title: const Text('Copy fake QR payload'),
                subtitle: Text(_fakeQrPayload()),
                onTap: _copyFakeQr,
              ),
              ListTile(
                leading: const Icon(Icons.qr_code_scanner),
                title: const Text('Open scanner'),
                subtitle: const Text('Paste fake payload on web; scan on device'),
                onTap: _openScannerWithFakeQr,
              ),
            ],
          ),
          if (DiagnosticsConfig.enabled && _snapshot != null)
            _Section(
              title: 'Device diagnostics',
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: SelectableText(
                    const JsonEncoder.withIndent('  ').convert(_snapshot!.toJson()),
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
        ),
        Card(
          margin: EdgeInsets.zero,
          child: Column(children: children),
        ),
      ],
    );
  }
}
