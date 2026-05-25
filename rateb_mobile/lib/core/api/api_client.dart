import 'dart:convert';

import 'package:dio/dio.dart';

import '../config/app_config.dart';
import 'api_exception.dart';

typedef TokenProvider = Future<String?> Function();

class ApiClient {
  ApiClient({TokenProvider? tokenProvider})
      : _tokenProvider = tokenProvider,
        _dio = Dio(
          BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            connectTimeout: AppConfig.connectTimeout,
            receiveTimeout: AppConfig.receiveTimeout,
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            responseType: ResponseType.json,
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          if (!_isPublicPath(options.path)) {
            final token = await _tokenProvider?.call();
            if (token != null && token.isNotEmpty) {
              options.headers['Authorization'] = 'Bearer $token';
            }
          }
          handler.next(options);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenProvider? _tokenProvider;

  static bool _isPublicPath(String path) {
    return path.contains('login.php') ||
        path.contains('health.php') ||
        path.contains('logout.php');
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.get<dynamic>(
        path,
        queryParameters: queryParameters,
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        path,
        data: body,
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  Map<String, dynamic> _unwrap(dynamic data) {
    final map = _asJsonMap(data);
    if (map == null) {
      throw ApiException('Empty response from server');
    }
    if (map['success'] == false) {
      throw ApiException(
        map['message'] as String? ?? 'Request failed',
        code: map['code'] as String?,
      );
    }
    return map;
  }

  Map<String, dynamic>? _asJsonMap(dynamic data) {
    if (data == null) return null;
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    if (data is String && data.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(data);
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } catch (_) {
        return null;
      }
    }
    return null;
  }

  ApiException _mapDioError(DioException error) {
    final response = error.response;
    final fromBody = _asJsonMap(response?.data);
    if (fromBody != null) {
      return ApiException(
        fromBody['message'] as String? ?? error.message ?? 'Network error',
        statusCode: response?.statusCode,
        code: fromBody['code'] as String?,
      );
    }
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException('Connection timed out', statusCode: 408);
      case DioExceptionType.connectionError:
        return ApiException('Unable to reach server', statusCode: 0);
      default:
        return ApiException(
          error.message ?? 'Unexpected network error',
          statusCode: response?.statusCode,
        );
    }
  }
}
