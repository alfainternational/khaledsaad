import 'dart:convert';

/// يعكس عقد الأخطاء الموحّد القادم من الـ Backend:
/// { "message": "...", "code": "SNAKE_CODE", "errors"?: { field: [..] } }
class ApiException implements Exception {
  ApiException({
    required this.message,
    required this.code,
    this.status,
    this.errors = const {},
  });

  final String message;
  final String code;
  final int? status;

  /// أخطاء التحقق لكل حقل: { field: [رسائل] }.
  final Map<String, List<String>> errors;

  bool get isUnauthenticated => code == 'UNAUTHENTICATED' || status == 401;
  bool get isForbidden => code == 'FORBIDDEN' || status == 403;
  bool get isEntitlementRequired => code == 'ENTITLEMENT_REQUIRED';
  bool get isCreditsExhausted => code == 'AI_CREDITS_EXHAUSTED' || status == 402;
  bool get isValidation => code == 'VALIDATION_ERROR' || status == 422;
  bool get isNotFound => code == 'NOT_FOUND' || status == 404;
  bool get isRateLimited => code == 'RATE_LIMITED' || status == 429;
  bool get isNetwork => code == 'NETWORK_ERROR';
  bool get isServer => code == 'SERVER_ERROR' || (status != null && status! >= 500);

  /// أول رسالة تحقق لحقل معيّن (إن وُجدت).
  String? fieldError(String field) {
    final list = errors[field];
    if (list == null || list.isEmpty) return null;
    return list.first;
  }

  /// بناء من جسم رد JSON.
  ///
  /// يدعم العقد الموحّد `{message, code, errors?}` بالإضافة إلى الأشكال غير
  /// القياسية التي تُرجعها بعض الـ endpoints: `{error: "..."}` أو
  /// `{success:false, error:"..."}` — فلا تضيع الرسالة الإرشادية للمستخدم.
  factory ApiException.fromJson(Map<String, dynamic> json, {int? status}) {
    final rawErrors = json['errors'];
    final parsed = <String, List<String>>{};
    if (rawErrors is Map) {
      rawErrors.forEach((key, value) {
        if (value is List) {
          parsed[key.toString()] = value.map((e) => e.toString()).toList();
        } else if (value != null) {
          parsed[key.toString()] = [value.toString()];
        }
      });
    }

    final message = json['message'] ?? json['error'];

    return ApiException(
      message: (message ?? _defaultMessageForStatus(status)).toString(),
      code: (json['code'] ?? _codeForStatus(status)).toString(),
      status: status,
      errors: parsed,
    );
  }

  /// رسالة افتراضية ودّية حين لا يرسل الخادم `message` — خصوصاً 429 (تجاوز الحد).
  static String _defaultMessageForStatus(int? status) {
    switch (status) {
      case 429:
        return 'الطلبات كثيرة الآن. انتظر لحظات ثم أعد المحاولة.';
      case 503:
        return 'الخدمة مشغولة مؤقتاً. حاول بعد قليل.';
      default:
        if (status != null && status >= 500) {
          return 'خطأ في الخادم. حاول لاحقاً.';
        }
        return 'حدث خطأ.';
    }
  }

  /// بناء من جسم خطأ وصل كبايتات (حالة تنزيل الملفات مثل PDF): نحاول فكّه
  /// كـ JSON أولاً حتى نحافظ على الرسالة والرمز (ENTITLEMENT_REQUIRED...)،
  /// وإلا نُرجع خطأ HTTP عاماً.
  factory ApiException.fromBytes(List<int> bytes, {int? status}) {
    if (bytes.isNotEmpty) {
      try {
        final decoded = jsonDecode(utf8.decode(bytes));
        if (decoded is Map<String, dynamic>) {
          return ApiException.fromJson(decoded, status: status);
        }
      } catch (_) {
        // ليست JSON صالحة — نتابع للخطأ العام.
      }
    }
    return ApiException(
      message: 'حدث خطأ${status != null ? ' ($status)' : ''}.',
      code: _codeForStatus(status),
      status: status,
    );
  }

  /// رمز افتراضي مشتقّ من حالة HTTP حين لا يرسل الخادم `code` صريحاً.
  static String _codeForStatus(int? status) {
    switch (status) {
      case 401:
        return 'UNAUTHENTICATED';
      case 402:
        return 'PAYMENT_REQUIRED';
      case 403:
        return 'FORBIDDEN';
      case 404:
        return 'NOT_FOUND';
      case 422:
        return 'VALIDATION_ERROR';
      case 429:
        return 'RATE_LIMITED';
      default:
        if (status != null && status >= 500) return 'SERVER_ERROR';
        return 'ERROR';
    }
  }

  /// خطأ شبكة محلي (لا يوجد اتصال / مهلة).
  factory ApiException.network([String? message]) => ApiException(
        message: message ?? 'تعذّر الاتصال بالخادم. تحقّق من اتصالك بالإنترنت.',
        code: 'NETWORK_ERROR',
      );

  /// خطأ غير متوقع.
  factory ApiException.unknown([String? message]) => ApiException(
        message: message ?? 'حدث خطأ غير متوقع.',
        code: 'UNKNOWN',
      );

  @override
  String toString() => 'ApiException($code, $status): $message';
}
