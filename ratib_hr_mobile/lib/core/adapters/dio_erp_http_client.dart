/// Dio transport to RATIB ERP. No domain rules.
library;

import 'package:dio/dio.dart';
import 'package:ratib_hr_mobile/core/contracts/erp_http_client.dart';
import 'package:ratib_hr_mobile/core/contracts/secure_token_store.dart';
import 'package:ratib_hr_mobile/core/env/app_environment.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class DioErpHttpClient implements ErpHttpClient {
  DioErpHttpClient({
    required AppEnvironment environment,
    required SecureTokenStore tokenStore,
    Dio? dio,
  })  : _environment = environment,
        _tokenStore = tokenStore,
        _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = environment.erpBaseUrl
      ..connectTimeout = const Duration(seconds: 20)
      ..receiveTimeout = const Duration(seconds: 30)
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json';

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStore.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final AppEnvironment _environment;
  final SecureTokenStore _tokenStore;
  final Dio _dio;

  void _ensureEnabled() {
    if (!_environment.apisEnabled || _environment.erpBaseUrl.isEmpty) {
      throw const AppFailure(
        code: 'config',
        message: 'ERP_BASE_URL is not configured',
      );
    }
  }

  Map<String, Object?> _asMap(dynamic data) {
    if (data is Map<String, Object?>) return data;
    if (data is Map) {
      return data.map((k, v) => MapEntry(k.toString(), v));
    }
    return <String, Object?>{'data': data};
  }

  @override
  Future<Map<String, Object?>> get(
    String path, {
    Map<String, String>? query,
  }) async {
    _ensureEnabled();
    final response = await _dio.get<dynamic>(
      path,
      queryParameters: query,
    );
    return _asMap(response.data);
  }

  @override
  Future<Map<String, Object?>> post(
    String path, {
    Map<String, Object?>? body,
  }) async {
    _ensureEnabled();
    final response = await _dio.post<dynamic>(path, data: body);
    return _asMap(response.data);
  }

  @override
  Future<Map<String, Object?>> put(
    String path, {
    Map<String, Object?>? body,
  }) async {
    _ensureEnabled();
    final response = await _dio.put<dynamic>(path, data: body);
    return _asMap(response.data);
  }
}
