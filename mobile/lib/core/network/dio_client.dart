import 'package:dio/dio.dart';
import 'package:dio/io.dart';

import '../../data/services/session_service.dart';
import '../config/env.dart';
import 'certificate_pinning.dart';
import 'interceptors/auth_interceptor.dart';
import 'interceptors/error_interceptor.dart';
import 'interceptors/idempotency_interceptor.dart';

/// يبني نسخة Dio مهيّأة بكل الـ interceptors.
///
/// ملاحظة: نطاق مساحة العمل (workspace scope) يُمرَّر ضمن المسار نفسه
/// (`/workspaces/{public_id}/...`) لذا لا يلزم interceptor منفصل له؛
/// التوكن النطاقي (ability `workspace:{id}`) يفرض العزل من جهة الخادم.
class DioClient {
  static Dio build({
    required SessionService session,
    required Future<void> Function() onUnauthenticated,
  }) {
    final dio = Dio(
      BaseOptions(
        baseUrl: Env.apiBaseUrl,
        connectTimeout: const Duration(milliseconds: Env.connectTimeoutMs),
        receiveTimeout: const Duration(milliseconds: Env.receiveTimeoutMs),
        headers: {'Accept': 'application/json'},
        // نتعامل مع أكواد الحالة يدوياً عبر ErrorInterceptor.
        validateStatus: (status) => status != null && status < 400,
      ),
    );

    // تثبيت الشهادة (معطّل افتراضياً — يُفعَّل عبر --dart-define=CERT_PINS).
    if (certPinningEnabled()) {
      dio.httpClientAdapter = IOHttpClientAdapter(
        validateCertificate: validatePinnedCertificate,
      );
    }

    dio.interceptors.addAll([
      AuthInterceptor(session),
      IdempotencyInterceptor(),
      ErrorInterceptor(onUnauthenticated: onUnauthenticated),
      if (Env.enableNetworkLogs)
        LogInterceptor(
          requestBody: true,
          responseBody: true,
          requestHeader: false,
          responseHeader: false,
        ),
    ]);

    return dio;
  }
}
