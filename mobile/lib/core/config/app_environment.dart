abstract final class AppEnvironment {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2/khaledsaad/public/api',
  );

  /// نفس فترة الاستطلاع المستخدمة في الويب، حتى تتطابق تجربة الانتظار
  /// في السطحين بدل أن يبدو أحدهما أسرع من الآخر بلا سبب.
  static const Duration progressPollInterval = Duration(seconds: 3);

  static const String deviceName = 'khaledsaad-mobile';
}
