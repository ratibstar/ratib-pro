/// Maps transport failures to [AppFailure]. No HR business interpretation.
library;

import 'package:dio/dio.dart';
import 'package:ratib_hr_mobile/core/contracts/error_mapper.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';

final class DefaultErrorMapper implements ErrorMapper {
  const DefaultErrorMapper();

  @override
  AppFailure map(Object error, [StackTrace? stackTrace]) {
    if (error is AppFailure) return error;

    if (error is DioException) {
      final status = error.response?.statusCode;
      final data = error.response?.data;
      String? erpMessage;
      if (data is Map) {
        final msg = data['message'];
        if (msg != null) erpMessage = msg.toString();
      }

      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout ||
          error.type == DioExceptionType.sendTimeout) {
        return AppFailure(code: 'timeout', message: erpMessage);
      }
      if (error.type == DioExceptionType.connectionError) {
        return AppFailure(code: 'network', message: erpMessage);
      }
      if (status == 401) {
        return AppFailure(code: 'unauthorized', message: erpMessage);
      }
      if (status == 403) {
        return AppFailure(code: 'forbidden', message: erpMessage);
      }
      if (status == 429) {
        return AppFailure(code: 'rate_limited', message: erpMessage);
      }
      if (status != null && status >= 500) {
        return AppFailure(code: 'erp', message: erpMessage);
      }
      return AppFailure(
        code: 'erp',
        message: erpMessage ?? error.message,
      );
    }

    return AppFailure(code: 'unknown', message: error.toString());
  }
}
