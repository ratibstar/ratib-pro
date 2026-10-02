/// Clears stale company activation when the unified APK is freshly installed or upgraded.
library;

import 'package:package_info_plus/package_info_plus.dart';
import 'package:ratib_hr_mobile/core/activation/company_activation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract final class ActivationInstallGuard {
  static const _prefsKey = 'company_activation.install_tag';
  static const _unifiedPackage = 'sa.rateb.hr.mobile';

  /// Branded builds keep embedded activation.
  static Future<void> runBeforeLoad() async {
    if (BrandBuild.activationCode.isNotEmpty) {
      return;
    }
    final info = await PackageInfo.fromPlatform();
    if (info.packageName != _unifiedPackage) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    final tag = '${info.packageName}@${info.buildNumber}';
    final previous = prefs.getString(_prefsKey);
    if (previous == tag) {
      return;
    }
    await CompanyActivation.clear();
    await prefs.setString(_prefsKey, tag);
  }
}
