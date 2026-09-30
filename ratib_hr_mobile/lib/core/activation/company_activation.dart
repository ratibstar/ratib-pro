/// Company activation for the shared HR build.
///
/// The company code (issued by RATEB Super Admin) resolves once to the
/// company's ERP server. Stores routing data only — never credentials.
library;

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/brand/brand_build.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum CompanyActivationError {
  invalidCode,
  appNotEnabled,
  wrongAndroidPackage,
  rateLimited,
  network,
}

final class CompanyActivation {
  CompanyActivation._();

  static const _kBaseUrl = 'company_activation.erp_base_url';
  static const _kName = 'company_activation.company_name';
  static const _kNameAr = 'company_activation.company_name_ar';
  static const _kNameEn = 'company_activation.company_name_en';
  static const _kLogo = 'company_activation.logo_url';
  static const _kCode = 'company_activation.code';
  static const _kCompanyId = 'company_activation.company_id';
  static const _kUnifiedHr = 'company_activation.unified_hr';
  static const _alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

  static String? _erpBaseUrl;
  static String? _companyName;
  static String _nameAr = '';
  static String _nameEn = '';
  static String? _logoUrl;
  static String? _code;

  /// Company logo (https) set by Super Admin, or null.
  static String? get logoUrl => isActive ? _logoUrl : null;

  static String? get erpBaseUrl => _erpBaseUrl;
  static String? get companyName => _companyName;

  /// Company name in the app language ("شركة العرفج" / "Al Arfaj"), or "" when not linked.
  static String localizedName({required bool arabic}) {
    if (!isActive) return '';
    final name = arabic ? _nameAr : _nameEn;
    return name.isNotEmpty ? name : (_companyName ?? '').trim();
  }

  static String? get code => _code;
  static int? _companyId;
  static bool? _unifiedHr;

  /// RATEB company id from the last successful activation API response.
  static int? get companyId => _companyId;

  static bool get isActive => _erpBaseUrl != null;

  /// Bumped whenever the linked company changes, so open screens can refresh.
  static final ValueNotifier<int> revision = ValueNotifier<int>(0);

  /// When true, skips background refresh so a cold-start activation link is not overwritten
  /// (e.g. branded embedded code racing platform QR).
  /// Unified APK must not keep Al-Arfaj (#51) after platform QR / fresh install.
  static Future<void> purgeWrongTenantForUnifiedPackage() async {
    if (embeddedCode.isNotEmpty) {
      return;
    }
    String package = '';
    try {
      package = (await PackageInfo.fromPlatform()).packageName;
    } catch (_) {}
    if (package != 'sa.rateb.hr.mobile') {
      return;
    }
    final cid = _companyId;
    final name = (_companyName ?? '') + _nameAr + _nameEn;
    final host = Uri.tryParse(_erpBaseUrl ?? '')?.host.toLowerCase() ?? '';
    if (cid == 51 ||
        name.contains('العرفج') ||
        name.toLowerCase().contains('arfaj') ||
        host.contains('alarfaj') ||
        _unifiedHr == false) {
      await clear();
    }
  }

  static Future<void> load({bool deferBackgroundRefresh = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final base = prefs.getString(_kBaseUrl);
    _erpBaseUrl = _validBase(base);
    _companyName = _erpBaseUrl == null ? null : prefs.getString(_kName);
    _nameAr = _erpBaseUrl == null ? '' : (prefs.getString(_kNameAr) ?? '');
    _nameEn = _erpBaseUrl == null ? '' : (prefs.getString(_kNameEn) ?? '');
    _logoUrl = _validBase(prefs.getString(_kLogo));
    _code = _erpBaseUrl == null ? null : prefs.getString(_kCode);
    final idRaw = prefs.getInt(_kCompanyId);
    _companyId = _erpBaseUrl == null ? null : (idRaw != null && idRaw > 0 ? idRaw : null);
    _unifiedHr = _erpBaseUrl == null ? null : prefs.getBool(_kUnifiedHr);
    // Do not auto-refresh saved codes on startup — stale backup (e.g. Al-Arfaj #51) overwrote QR activation.
  }

  /// Code baked into a company-branded build: links the app to its company on first launch.
  static const String embeddedCode = BrandBuild.activationCode;

  /// "ABCD-2345", "abcd2345" or an activation link → "ABCD2345", or null.
  static String? normalize(String input) {
    var raw = input.trim();
    final path = RegExp(r'(?:app-activate|m/activate)/([A-Za-z0-9\-]+)').firstMatch(raw);
    final query = RegExp(r'[?&]code=([A-Za-z0-9\-]+)').firstMatch(raw);
    if (path != null) {
      raw = path.group(1)!;
    } else if (query != null) {
      raw = query.group(1)!;
    }
    final code = raw.replaceAll(RegExp(r'[^A-Za-z0-9]'), '').toUpperCase();
    if (code.length != 8 || code.split('').any((c) => !_alphabet.contains(c))) {
      return null;
    }
    return code;
  }

  static String format(String code) =>
      code.length == 8 ? '${code.substring(0, 4)}-${code.substring(4)}' : code;

  /// Resolves [input] with the RATEB platform and saves the company server.
  static Future<CompanyActivationError?> activate(String input) async {
    final code = normalize(input);
    if (code == null) return CompanyActivationError.invalidCode;
    final prefs = await SharedPreferences.getInstance();
    final previous = normalize(prefs.getString(_kCode) ?? _code ?? '');
    if (previous != null && previous != code) {
      await clear();
    }
    String androidPackage = '';
    try {
      androidPackage = (await PackageInfo.fromPlatform()).packageName;
    } catch (_) {}
    final dio = Dio(
      BaseOptions(
        baseUrl: DartDefineAppEnvironment.productionErpBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
        headers: {'Accept': 'application/json'},
        validateStatus: (_) => true,
      ),
    );
    final Response<dynamic> response;
    try {
      response = await dio.get<dynamic>(
        '/api/v1/mobile/activation',
        queryParameters: {
          'code': code,
          'app': 'hr',
          if (androidPackage.isNotEmpty) 'android_package': androidPackage,
        },
      );
    } on DioException {
      return CompanyActivationError.network;
    }
    final data = response.data;
    final body = data is Map ? data : const {};
    if (response.statusCode == 429) return CompanyActivationError.rateLimited;
    if (response.statusCode == 403) {
      final apiCode = body['code']?.toString() ?? '';
      if (apiCode == 'wrong_android_package') {
        return CompanyActivationError.wrongAndroidPackage;
      }
      return CompanyActivationError.appNotEnabled;
    }
    if (response.statusCode != 200 || body['success'] != true) {
      return response.statusCode == 404
          ? CompanyActivationError.invalidCode
          : CompanyActivationError.network;
    }
    final expectedPkg = body['expected_android_package']?.toString().trim() ?? '';
    if (expectedPkg.isNotEmpty &&
        androidPackage.isNotEmpty &&
        expectedPkg != androidPackage) {
      return CompanyActivationError.wrongAndroidPackage;
    }
    final unifiedHr = body['unified_hr'] == true;
    if (androidPackage == 'sa.rateb.hr.mobile' && !unifiedHr) {
      return CompanyActivationError.wrongAndroidPackage;
    }
    final base = _validBase(body['erp_base_url']?.toString());
    if (base == null) return CompanyActivationError.network;
    final company = body['company'];
    final name = company is Map ? (company['name']?.toString() ?? '') : '';
    final nameAr =
        company is Map ? (company['name_ar']?.toString() ?? '').trim() : '';
    final nameEn =
        company is Map ? (company['name_en']?.toString() ?? '').trim() : '';
    final logo =
        company is Map ? _validBase(company['logo_url']?.toString()) : null;
    final companyId = company is Map ? int.tryParse('${company['id']}') : null;

    await prefs.setString(_kBaseUrl, base);
    await prefs.setString(_kName, name);
    await prefs.setString(_kNameAr, nameAr);
    await prefs.setString(_kNameEn, nameEn);
    await prefs.setString(_kLogo, logo ?? '');
    await prefs.setString(_kCode, code);
    await prefs.setBool(_kUnifiedHr, unifiedHr);
    _unifiedHr = unifiedHr;
    if (companyId != null && companyId > 0) {
      await prefs.setInt(_kCompanyId, companyId);
      _companyId = companyId;
    } else {
      await prefs.remove(_kCompanyId);
      _companyId = null;
    }
    _erpBaseUrl = base;
    _companyName = name;
    _nameAr = nameAr;
    _nameEn = nameEn;
    _logoUrl = logo;
    _code = code;
    revision.value++;
    return null;
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kBaseUrl);
    await prefs.remove(_kName);
    await prefs.remove(_kNameAr);
    await prefs.remove(_kNameEn);
    await prefs.remove(_kLogo);
    await prefs.remove(_kCode);
    await prefs.remove(_kCompanyId);
    await prefs.remove(_kUnifiedHr);
    _erpBaseUrl = null;
    _companyId = null;
    _unifiedHr = null;
    _companyName = null;
    _nameAr = '';
    _nameEn = '';
    _logoUrl = null;
    _code = null;
    revision.value++;
  }

  static String? _validBase(String? value) {
    final base = (value ?? '').trim().replaceAll(RegExp(r'/+$'), '');
    final uri = Uri.tryParse(base);
    if (uri == null || uri.scheme != 'https' || uri.host.isEmpty) return null;
    return base;
  }
}
