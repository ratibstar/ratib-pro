import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum CompanyActivationError { invalidCode, appNotEnabled, rateLimited, network }

/// Company activation for the shared customer build.
///
/// The company code (issued by RATEB Super Admin) resolves once to the
/// company's servers and agency. Stores routing data only — never credentials.
class CompanyActivation {
  CompanyActivation._();

  static const String platformErpBaseUrl = 'https://rateb.sa/rateb-erp/public';

  static const _kApiBaseUrl = 'company_activation.api_base_url';
  static const _kErpBaseUrl = 'company_activation.erp_base_url';
  static const _kSlug = 'company_activation.slug';
  static const _kAgencyId = 'company_activation.agency_id';
  static const _kName = 'company_activation.company_name';
  static const _kCode = 'company_activation.code';
  static const _alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

  static String? _apiBaseUrl;
  static String? _erpBaseUrl;
  static String _slug = '';
  static int _agencyId = 0;
  static String? _companyName;
  static String? _code;

  static String? get apiBaseUrl => _apiBaseUrl;
  static String? get erpBaseUrl => _erpBaseUrl;
  static String get slug => _slug;
  static int get agencyId => _agencyId;
  static String? get companyName => _companyName;
  static String? get code => _code;
  static bool get isActive => _apiBaseUrl != null;

  /// Bumped whenever the linked company changes, so open screens can refresh.
  static final ValueNotifier<int> revision = ValueNotifier<int>(0);

  static Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final api = _validBase(prefs.getString(_kApiBaseUrl));
    if (api == null) return;
    _apiBaseUrl = api;
    _erpBaseUrl = _validBase(prefs.getString(_kErpBaseUrl));
    _slug = prefs.getString(_kSlug) ?? '';
    _agencyId = prefs.getInt(_kAgencyId) ?? 0;
    _companyName = prefs.getString(_kName);
    _code = prefs.getString(_kCode);
  }

  /// "ABCD-2345", "abcd2345" or an activation link / QR → "ABCD2345", or null.
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

  /// Resolves [input] with the RATEB platform and saves the company servers.
  static Future<CompanyActivationError?> activate(String input) async {
    final code = normalize(input);
    if (code == null) return CompanyActivationError.invalidCode;
    final dio = Dio(
      BaseOptions(
        baseUrl: platformErpBaseUrl,
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
        queryParameters: {'code': code, 'app': 'customer'},
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
    final api = _validBase(body['api_base_url']?.toString());
    if (api == null) return CompanyActivationError.network;
    final erp = _validBase(body['erp_base_url']?.toString());
    final company = body['company'];
    final name = company is Map ? (company['name']?.toString() ?? '') : '';
    final slug = company is Map ? (company['slug']?.toString() ?? '') : '';
    final agencyId = int.tryParse('${body['agency_id'] ?? 0}') ?? 0;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kApiBaseUrl, api);
    if (erp != null) {
      await prefs.setString(_kErpBaseUrl, erp);
    } else {
      await prefs.remove(_kErpBaseUrl);
    }
    await prefs.setString(_kSlug, slug);
    await prefs.setInt(_kAgencyId, agencyId);
    await prefs.setString(_kName, name);
    await prefs.setString(_kCode, code);
    _apiBaseUrl = api;
    _erpBaseUrl = erp;
    _slug = slug;
    _agencyId = agencyId;
    _companyName = name;
    _code = code;
    revision.value++;
    return null;
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    for (final key in [_kApiBaseUrl, _kErpBaseUrl, _kSlug, _kAgencyId, _kName, _kCode]) {
      await prefs.remove(key);
    }
    _apiBaseUrl = null;
    _erpBaseUrl = null;
    _slug = '';
    _agencyId = 0;
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
