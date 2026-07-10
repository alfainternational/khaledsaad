import 'package:dio/dio.dart';

import '../../../data/services/session_service.dart';

/// يحقن رأس المصادقة Bearer من الجلسة في كل طلب.
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._session);

  final SessionService _session;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final token = _session.token;
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    options.headers['Accept'] = 'application/json';
    handler.next(options);
  }
}
