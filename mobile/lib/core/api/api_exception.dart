class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statusCode,
    this.errors = const {},
    this.code,
    this.action,
  });

  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;
  final String? code;
  final String? action;

  bool get needsExperienceActivation => code == 'experience_not_enabled';

  bool get needsPlanUpgrade => code == 'feature_not_available';

  /// أول رسالة تحقق لحقل معين، لعرضها بجانب الحقل نفسه لا في تنبيه عام.
  String? fieldError(String key) => errors[key]?.firstOrNull;

  @override
  String toString() => message;
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
