import 'package:flutter/foundation.dart';

abstract final class AppEnvironment {
  /// عنوان الـAPI: الإنتاج في بناء release، والمحلي في debug — تلقائيًّا.
  ///
  /// المزلق الذي يحرسه هذا: بناء release كان يستخدم عنوان المحاكي المحلي
  /// (`10.0.2.2`) لأنه الافتراض، فلا يتصل التطبيق المنشور بالخادم إطلاقًا.
  /// `kReleaseMode` ثابت زمن الترجمة، فالافتراض يصير إنتاجًا في كل بناء release
  /// بلا الاعتماد على تمرير `--dart-define`. يبقى التجاوز ممكنًا للتطوير.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: kReleaseMode
        ? 'https://khaledsaad.net/api'
        : 'http://10.0.2.2/khaledsaad/public/api',
  );

  /// نفس فترة الاستطلاع المستخدمة في الويب، حتى تتطابق تجربة الانتظار
  /// في السطحين بدل أن يبدو أحدهما أسرع من الآخر بلا سبب.
  static const Duration progressPollInterval = Duration(seconds: 3);

  static const String deviceName = 'khaledsaad-mobile';

  /// رقم بناء هذه النسخة، يُرسل مع كل طلب في ترويسة `X-App-Build`.
  ///
  /// الخادم يقارنه بـ`mobile.min_supported_build` ليردّ رسالة تحديث مفهومة
  /// بدل أن ينكسر العقد صامتًا عند نسخة قديمة. يجب أن يطابق `build` في
  /// `config/mobile.php` و`pubspec.yaml` عند كل إصدار.
  static const int appBuild = int.fromEnvironment('APP_BUILD', defaultValue: 17);
}
