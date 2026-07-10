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

    return ApiException(
      message: (json['message'] ?? 'حدث خطأ.').toString(),
      code: (json['code'] ?? 'ERROR').toString(),
      status: status,
      errors: parsed,
    );
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
