/// إعدادات البيئة. تُمرَّر عبر --dart-define أو تُترك للقيم الافتراضية.
///
/// أمثلة:
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2/api/v1
///   (10.0.2.2 هو مضيف الجهاز من محاكي أندرويد)
class Env {
  const Env._();

  /// عنوان الـ API الأساسي (حتى /api/v1 دون شرطة ختامية).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://khaledsaad.net/api/v1',
  );

  /// مخطط الروابط العميقة (Deep link) لعودة الدفع.
  static const String deepLinkScheme = 'ksgrowth';

  /// مهلة الاتصال والاستقبال (بالمللي ثانية).
  static const int connectTimeoutMs = 20000;
  static const int receiveTimeoutMs = 30000;

  /// وضع التصحيح لتسجيل الشبكة.
  static const bool enableNetworkLogs = bool.fromEnvironment(
    'NETWORK_LOGS',
    defaultValue: true,
  );
}
