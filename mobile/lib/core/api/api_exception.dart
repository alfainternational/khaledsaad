class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.errors = const {}});

  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;

  /// أول رسالة تحقق لحقل معين، لعرضها بجانب الحقل نفسه لا في تنبيه عام.
  String? fieldError(String key) => errors[key]?.firstOrNull;

  @override
  String toString() => message;
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
