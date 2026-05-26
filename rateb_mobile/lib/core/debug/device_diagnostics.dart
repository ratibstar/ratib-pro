import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../api/api_client.dart';
import '../services/network_monitor.dart';
import 'api_telemetry.dart';
import 'diagnostics_config.dart';
import 'qr_scanner_telemetry.dart';
import '../../features/qr/qr_platform.dart';

/// Snapshot of device + app health for pilot validation.
class DeviceDiagnosticsSnapshot {
  DeviceDiagnosticsSnapshot({
    required this.platform,
    required this.isWeb,
    required this.appVersion,
    required this.apiBaseUrl,
    required this.apiReachable,
    required this.apiHealthLatencyMs,
    required this.networkOnline,
    required this.simulateOffline,
    required this.cameraMode,
    required this.cameraAvailable,
    required this.cameraPermission,
    required this.qrScannerState,
    required this.qrScanAttempts,
    required this.lastApiLatencyMs,
    required this.lastApiEndpoint,
    required this.hasStoredToken,
    required this.collectedAt,
  });

  final String platform;
  final bool isWeb;
  final String appVersion;
  final String apiBaseUrl;
  final bool? apiReachable;
  final int? apiHealthLatencyMs;
  final bool networkOnline;
  final bool simulateOffline;
  final String cameraMode;
  final bool cameraAvailable;
  final String cameraPermission;
  final String qrScannerState;
  final int qrScanAttempts;
  final int? lastApiLatencyMs;
  final String? lastApiEndpoint;
  final bool hasStoredToken;
  final DateTime collectedAt;

  Map<String, dynamic> toJson() => {
        'platform': platform,
        'is_web': isWeb,
        'app_version': appVersion,
        'api_base_url': apiBaseUrl,
        'api_reachable': apiReachable,
        'api_health_latency_ms': apiHealthLatencyMs,
        'network_online': networkOnline,
        'simulate_offline': simulateOffline,
        'camera_mode': cameraMode,
        'camera_available': cameraAvailable,
        'camera_permission': cameraPermission,
        'qr_scanner_state': qrScannerState,
        'qr_scan_attempts': qrScanAttempts,
        'last_api_latency_ms': lastApiLatencyMs,
        'last_api_endpoint': lastApiEndpoint,
        'has_stored_token': hasStoredToken,
        'collected_at': collectedAt.toIso8601String(),
      };
}

abstract final class DeviceDiagnostics {
  static Future<DeviceDiagnosticsSnapshot> collect({
    required Future<bool> hasToken,
    Future<bool> Function()? probeHealth,
  }) async {
    final cameraMode = qrUsesNativeCamera
        ? 'native_camera'
        : (kIsWeb ? 'web_paste_fallback' : 'desktop_paste_fallback');

    bool? reachable;
    int? healthMs;
    if (probeHealth != null) {
      final sw = Stopwatch()..start();
      try {
        reachable = await probeHealth();
        healthMs = sw.elapsedMilliseconds;
      } catch (_) {
        reachable = false;
        healthMs = sw.elapsedMilliseconds;
      }
    }

    return DeviceDiagnosticsSnapshot(
      platform: defaultTargetPlatform.name,
      isWeb: kIsWeb,
      appVersion: AppConfig.appVersion,
      apiBaseUrl: AppConfig.apiBaseUrl,
      apiReachable: reachable,
      apiHealthLatencyMs: healthMs,
      networkOnline: NetworkMonitor.instance.isEffectivelyOnline,
      simulateOffline: NetworkMonitor.instance.simulateOffline,
      cameraMode: cameraMode,
      cameraAvailable: qrUsesNativeCamera && QrScannerTelemetry.cameraAvailable,
      cameraPermission: qrUsesNativeCamera
          ? QrScannerTelemetry.cameraPermission
          : 'not_applicable',
      qrScannerState: QrScannerTelemetry.state,
      qrScanAttempts: QrScannerTelemetry.scanAttempts,
      lastApiLatencyMs: ApiTelemetry.lastLatencyMs,
      lastApiEndpoint: ApiTelemetry.lastEndpoint,
      hasStoredToken: await hasToken,
      collectedAt: DateTime.now(),
    );
  }

  /// Decode JWT payload claims for pilot tools — never logs the raw token.
  static Map<String, dynamic>? decodeJwtPayloadClaims(String token) {
    if (!DiagnosticsConfig.enabled) return null;
    final parts = token.split('.');
    if (parts.length != 3) return null;
    try {
      var payload = parts[1];
      final pad = payload.length % 4;
      if (pad > 0) payload += '=' * (4 - pad);
      final normalized = payload.replaceAll('-', '+').replaceAll('_', '/');
      final decoded = utf8.decode(base64.decode(normalized));
      final map = jsonDecode(decoded);
      if (map is Map<String, dynamic>) return map;
      if (map is Map) return Map<String, dynamic>.from(map);
    } catch (_) {
      return null;
    }
    return null;
  }

  static Future<bool> probeHealthApi() async {
    final client = ApiClient();
    final sw = Stopwatch()..start();
    try {
      await client.get('/mobile/health.php');
      ApiTelemetry.recordSuccess('/mobile/health.php', sw.elapsedMilliseconds);
      return true;
    } catch (_) {
      ApiTelemetry.recordFailure('/mobile/health.php', sw.elapsedMilliseconds);
      return false;
    }
  }
}
