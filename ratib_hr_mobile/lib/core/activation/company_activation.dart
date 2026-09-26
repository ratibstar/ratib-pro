/// Company activation for the shared HR build.
///
/// The company code (issued by RATEB Super Admin) resolves once to the
/// company's ERP server. Stores routing data only — never credentials.
library;

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:ratib_hr_mobile/core/env/dart_define_app_environment.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum CompanyActivationError { invalidCode, appNotEnabled, rateLimited, network }

final class CompanyActivation {
  CompanyActivation._();

  static const _kBaseUrl = 'company_activation.erp_base_url';
  static const _kName = 'company_activation.company_name';
  static const _kCode = 'company_activation.code';
  static const _alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

  static String? _erpBaseUrl;
  static String? _companyName;
  static String? _code;

  static String? get erpBaseUrl => _erpBaseUrl;
  static String? get companyName => _companyName;
  static String? get code => _code;
  static bool get isActive => _erpBaseUrl != null;

  /// Bumped whenever the linked company changes, so open screens can refresh.
  static final ValueNotifier<int> revision = ValueNotifier<int>(0);

  static Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final base = prefs.getString(_kBaseUrl);
    _erpBaseUrl = _validBase(base);
    _companyName = _erpBaseUrl == null ? null : prefs.getString(_kName);
    _code = _erpBaseUrl == null ? null : prefs.getString(_kCode);
  }

  /// "ABCD-2345", "abcd2345" or an activation link → "ABCD2345", or null.
  static String? normalize(String input) {
    var raw = input.trim();
    final path = RegExp(r'app-activate/([A-Za-z0-9\-]+)').firstMatch(raw);
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
        queryParameters: {'code': code, 'app': 'hr'},
      );
    } on DioException {
      return CompanyActivationError.network;
    }
    final data = response.data;
    final body = data is Map ? data : const {};
    if (response.statusCode == 429) return CompanyActivationError.rateLimited;
    if (response.statusCode == 403) return CompanyActivationError.appNotEnabled;
    if (response.statusCode != 200 || body['success'] != true) {
      return response.statusCode == 404
          ? CompanyActivationError.invalidCode
          : CompanyActivationError.network;
    }
    final base = _validBase(body['erp_base_url']?.toString());
    if (base == null) return CompanyActivationError.network;
    final company = body['company'];
    final name = company is Map ? (company['name']?.toString() ?? '') : '';

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBaseUrl, base);
    await prefs.setString(_kName, name);
    await prefs.setString(_kCode, code);
    _erpBaseUrl = base;
    _companyName = name;
    _code = code;
    revision.value++;
    return null;
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kBaseUrl);
    await prefs.remove(_kName);
    await prefs.remove(_kCode);
    _erpBaseUrl = null;
    _companyName = null;
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
