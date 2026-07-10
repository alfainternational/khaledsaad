import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

/// يولّد رأس Idempotency-Key لطلبات POST الحسّاسة (المشاريع/تشغيل الأداة/الاستوديو).
/// الباك يدعم هذا الرأس عبر middleware `idempotency`.
class IdempotencyInterceptor extends Interceptor {
  IdempotencyInterceptor([Uuid? uuid]) : _uuid = uuid ?? const Uuid();

  final Uuid _uuid;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final method = options.method.toUpperCase();
    if (method == 'POST' && _needsKey(options.path)) {
      options.headers.putIfAbsent('Idempotency-Key', () => _uuid.v4());
    }
    handler.next(options);
  }

  bool _needsKey(String path) {
    // تشغيل الأداة ينتهي بـ /run؛ إنشاء المشروع ينتهي بـ /projects؛ الاستوديو ينتهي بـ /generations.
    return path.endsWith('/projects') ||
        path.endsWith('/run') ||
        path.endsWith('/studio/generations');
  }
}
