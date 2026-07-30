import 'package:flutter/foundation.dart';

/// بوابة «حدّث التطبيق» — الطرف المستقبِل للرمز ٤٢٦.
///
/// الخادم يحرس عقد `api/v1` بـ`min_supported_build`، ويردّ على النسخة الأقدم
/// من الحدّ برسالة عربية واضحة ورابط تنزيل. **والتطبيق لم يكن يعرف هذا الرمز
/// إطلاقًا**، فيعرضه خطأً عامًّا: «تعذر إكمال الطلب» على شاشة وسط عمل. رسالةٌ
/// محسوبة في الخادم ولا تصل من قُصدت له.
///
/// ولماذا بوابة عامة لا معالجة في كل شاشة: أي نداء قد يعيد ٤٢٦، وعددها
/// عشرات. معالجتها في مواضع الاستدعاء تعني أن أي نداء يُضاف لاحقًا يفوته
/// الحارس — وهو نفس نمط «القدرة بلا مستدعٍ» مقلوبًا.
///
/// وهي **لا تُرفع إلا صعودًا**: أول ٤٢٦ يقفل الواجهة، ونداءٌ تالٍ ينجح لا
/// يفتحها. الحدّ لا يُخفَّض في منتصف جلسة، ومحاولة إخفاء الشاشة بعد ظهورها
/// تُنتج تطبيقًا نصفه معطّل ونصفه يعمل.
class AppUpdateGate {
  AppUpdateGate._();

  static final AppUpdateGate instance = AppUpdateGate._();

  final ValueNotifier<AppUpdateRequirement?> requirement =
      ValueNotifier<AppUpdateRequirement?>(null);

  bool get isBlocked => requirement.value != null;

  /// تسجيل أن الخادم رفض هذه النسخة. تجاهل التكرار: الشاشة واحدة.
  void raise(AppUpdateRequirement value) {
    if (requirement.value != null) return;

    requirement.value = value;
  }

  /// للاختبارات وحدها: إعادة الحالة النظيفة بين الحالات.
  @visibleForTesting
  void reset() => requirement.value = null;
}

/// ما يقوله الخادم عن سبب المنع — بنصّه لا بإعادة صياغته في التطبيق.
class AppUpdateRequirement {
  const AppUpdateRequirement({
    required this.message,
    this.downloadUrl,
    this.minimumBuild,
    this.currentBuild,
  });

  /// يقرأ حمولة `EnsureSupportedAppVersion`. غياب `meta` لا يُسقط البوابة:
  /// المنع واقع، وأسوأ ما يحدث أن يُعرض بلا رقم بناء.
  factory AppUpdateRequirement.fromJson(Map<dynamic, dynamic> json) {
    final meta = json['meta'];
    final message = json['message'];

    return AppUpdateRequirement(
      message: message is String && message.isNotEmpty
          ? message
          : 'هذا الإصدار من التطبيق لم يعد مدعومًا. حدّثه للمتابعة.',
      downloadUrl: meta is Map ? meta['download_url']?.toString() : null,
      minimumBuild: meta is Map
          ? (meta['min_supported_build'] as num?)?.toInt()
          : null,
      currentBuild: meta is Map ? (meta['your_build'] as num?)?.toInt() : null,
    );
  }

  final String message;
  final String? downloadUrl;
  final int? minimumBuild;
  final int? currentBuild;

  /// الرقم مع أساسه (§١٣): «نسختك ٥ والمطلوب ٦» تُقرأ، و«حدّث» وحدها لا.
  String? get basis {
    if (minimumBuild == null || currentBuild == null) return null;

    return 'نسختك رقم $currentBuild، وأقل نسخة مدعومة $minimumBuild.';
  }
}
