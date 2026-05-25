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
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenProvider?.call();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenProvider? _tokenProvider;

  Dio get dio => _dio;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
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
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: body,
      );
      return _unwrap(response.data);
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? data) {
    if (data == null) {
      throw ApiException('Empty response from server');
    }
    if (data['success'] == false) {
      throw ApiException(
        data['message'] as String? ?? 'Request failed',
        code: data['code'] as String?,
      );
    }
    return data;
  }

  ApiException _mapDioError(DioException error) {
    final response = error.response;
    if (response?.data is Map<String, dynamic>) {
      final data = response!.data as Map<String, dynamic>;
      return ApiException(
        data['message'] as String? ?? error.message ?? 'Network error',
        statusCode: response.statusCode,
        code: data['code'] as String?,
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
