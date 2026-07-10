import 'package:dio/dio.dart';

import '../../error/api_exception.dart';

/// يحوّل كل أخطاء Dio إلى ApiException موحّد بالاعتماد على عقد الأخطاء من الباك.
/// عند 401 يستدعي onUnauthenticated (لمسح الجلسة والتوجيه لتسجيل الدخول).
class ErrorInterceptor extends Interceptor {
  ErrorInterceptor({required this.onUnauthenticated});

  final Future<void> Function() onUnauthenticated;

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final apiException = _map(err);

    if (apiException.isUnauthenticated) {
      await onUnauthenticated();
    }

    // نمرّر ApiException كـ error داخل DioException ليصله repository.
    handler.reject(
      DioException(
        requestOptions: err.requestOptions,
        error: apiException,
        response: err.response,
        type: err.type,
      ),
    );
  }

  ApiException _map(DioException err) {
    switch (err.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.transformTimeout:
        return ApiException.network('انتهت مهلة الاتصال بالخادم.');
      case DioExceptionType.connectionError:
        return ApiException.network();
      case DioExceptionType.cancel:
        return ApiException(message: 'أُلغي الطلب.', code: 'CANCELLED');
      case DioExceptionType.badCertificate:
        return ApiException(message: 'شهادة أمان غير صالحة.', code: 'BAD_CERTIFICATE');
      case DioExceptionType.badResponse:
      case DioExceptionType.unknown:
        final response = err.response;
        final data = response?.data;
        if (data is Map<String, dynamic>) {
          return ApiException.fromJson(data, status: response?.statusCode);
        }
        if (response != null) {
          return ApiException(
            message: 'حدث خطأ (${response.statusCode}).',
            code: 'HTTP_${response.statusCode}',
            status: response.statusCode,
          );
        }
        return ApiException.network();
    }
  }
}
