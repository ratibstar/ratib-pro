/// Resolves activation deep links from every Android delivery path (app_links + native intent).
library;

import 'package:app_links/app_links.dart';
import 'package:ratib_hr_mobile/core/activation/activation_intent_bridge.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';

abstract final class ActivationBootstrap {
  static Future<Uri?> resolveLaunchUri() async {
    final pending = await ActivationIntentBridge.consumePendingUri();
    if (pending != null && pending.isNotEmpty) {
      return Uri.tryParse(pending);
    }
    final links = AppLinks();
    try {
      return await links.getInitialLink();
    } catch (_) {
      return null;
    }
  }

  static String? codeFromUri(Uri? uri) {
    if (uri == null) {
      return null;
    }
    if (uri.scheme == 'ratebhr' && uri.host == 'activate') {
      return CompanyActivation.normalize(uri.queryParameters['code'] ?? '');
    }
    if (uri.scheme == 'ratebapp' && uri.host == 'activate') {
      return CompanyActivation.normalize(uri.queryParameters['code'] ?? '');
    }
    if (uri.scheme == 'https' || uri.scheme == 'http') {
      return CompanyActivation.normalize(uri.toString());
    }
    return CompanyActivation.normalize(uri.toString());
  }

  static Future<void> applyLaunchUri(Uri? uri) async {
    final code = codeFromUri(uri);
    if (code == null) {
      return;
    }
    final embedded = CompanyActivation.normalize(BrandBuild.activationCode);
    if (embedded != null && embedded != code) {
      return;
    }
    await CompanyActivation.activate(code);
  }
}
