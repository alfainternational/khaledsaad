import 'package:flutter/foundation.dart';

/// مُبلّغ أعطال مركزي بلا تبعيات خارجية.
///
/// يجمع كل الأخطاء غير المُمسكة في مكان واحد. في وضع التطوير يطبعها؛ وفي
/// الإنتاج يمكن ربط مزوّد حقيقي (Sentry/Crashlytics) عبر [handler] دون تعديل
/// نقاط الالتقاط في `main.dart`.
///
/// مثال الربط لاحقاً (بعد إضافة الحزمة ومفتاح المشروع):
/// ```dart
/// CrashReporter.instance.handler = (e, s) => Sentry.captureException(e, stackTrace: s);
/// ```
class CrashReporter {
  CrashReporter._();

  static final CrashReporter instance = CrashReporter._();

  /// خطّاف اختياري لمزوّد خارجي. اتركه فارغاً حتى يُهيَّأ المزوّد.
  void Function(Object error, StackTrace? stack)? handler;

  void record(Object error, StackTrace? stack) {
    if (kDebugMode) {
      debugPrint('[CrashReporter] $error');
      if (stack != null) debugPrint(stack.toString());
    }
    try {
      handler?.call(error, stack);
    } catch (_) {
      // لا نُسقط التطبيق بسبب فشل التبليغ نفسه.
    }
  }
}
