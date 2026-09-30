/// Clears stale company activation when the unified APK is freshly installed or upgraded.
library;

import 'package:package_info_plus/package_info_plus.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract final class ActivationInstallGuard {
  static const _prefsKey = 'company_activation.install_tag';
  static const _unifiedPackage = 'sa.rateb.hr.mobile';

  /// Branded builds keep embedded activation; unified builds must not restore Al-Arfaj via backup.
  static Future<void> runBeforeLoad() async {
    if (BrandBuild.activationCode.isNotEmpty) {
      return;
    }
    final info = await PackageInfo.fromPlatform();
    if (info.packageName != _unifiedPackage) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    final cid = prefs.getInt('company_activation.company_id');
    final erp = prefs.getString('company_activation.erp_base_url') ?? '';
    final host = Uri.tryParse(erp)?.host.toLowerCase() ?? '';
    final name = (prefs.getString('company_activation.company_name') ?? '') +
        (prefs.getString('company_activation.company_name_ar') ?? '');
    final staleBrandedOnUnified = cid == 51 ||
        name.contains('العرفج') ||
        host.contains('alarfaj');
    if (staleBrandedOnUnified) {
      await CompanyActivation.clear();
    }
    final tag = '${info.packageName}@${info.buildNumber}';
    final previous = prefs.getString(_prefsKey);
    if (previous == tag) {
      return;
    }
    await CompanyActivation.clear();
    await prefs.setString(_prefsKey, tag);
  }
}
