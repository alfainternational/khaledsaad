abstract final class AppEnvironment {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2/khaledsaad/public/api',
  );

  /// نفس فترة الاستطلاع المستخدمة في الويب، حتى تتطابق تجربة الانتظار
  /// في السطحين بدل أن يبدو أحدهما أسرع من الآخر بلا سبب.
  static const Duration progressPollInterval = Duration(seconds: 3);

  static const String deviceName = 'khaledsaad-mobile';

  /// رقم بناء هذه النسخة، يُرسل مع كل طلب في ترويسة `X-App-Build`.
  ///
  /// الخادم يقارنه بـ`mobile.min_supported_build` ليردّ رسالة تحديث مفهومة
  /// بدل أن ينكسر العقد صامتًا عند نسخة قديمة. يجب أن يطابق `build` في
  /// `config/mobile.php` عند كل إصدار.
  static const int appBuild = int.fromEnvironment('APP_BUILD', defaultValue: 5);
}
