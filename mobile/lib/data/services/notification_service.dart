import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../core/observability/crash_reporter.dart';
import '../repositories/billing_repository.dart';
import 'session_service.dart';

/// معالج رسائل الخلفية (يجب أن يكون دالة عليا top-level و vm:entry-point).
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {
  // لا حاجة لعمل شيء هنا الآن؛ النظام يعرض إشعار الـ notification تلقائياً.
}

/// خدمة الإشعارات (FCM): تهيّئ Firebase، تطلب الإذن، وتسجّل توكن الجهاز لدى
/// الخادم عبر `POST /devices` عند المصادقة — فتصل إشعارات الخادم (اكتمال تحليل،
/// موافقات...) فعلياً للتطبيق. مقيّدة بالمنصّات التي لديها إعداد Firebase
/// (أندرويد عبر google-services.json) وتفشل بهدوء على غيرها.
class NotificationService extends GetxService {
  bool _ready = false;
  String? _token;
  Worker? _authWorker;

  Future<NotificationService> init() async {
    // أندرويد فقط لديه ملف إعداد (google-services.json). نتفادى iOS/غيره حتى
    // لا تنهار التهيئة بدون GoogleService-Info.plist.
    if (!(!kIsWeb && Platform.isAndroid)) return this;

    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();

      // رسائل المقدّمة (التطبيق مفتوح) — تنبيه خفيف.
      FirebaseMessaging.onMessage.listen((message) {
        final n = message.notification;
        if (n == null) return;
        Get.snackbar(
          n.title ?? 'إشعار',
          n.body ?? '',
          snackPosition: SnackPosition.TOP,
        );
      });

      messaging.onTokenRefresh.listen((token) {
        _token = token;
        _registerIfAuthenticated();
      });

      _token = await messaging.getToken();
      _ready = true;

      // سجّل الآن إن كان مصادَقاً، وراقب تغيّر المصادقة للتسجيل عند الدخول لاحقاً.
      _registerIfAuthenticated();
      if (Get.isRegistered<SessionService>()) {
        _authWorker = ever<bool>(
          Get.find<SessionService>().isAuthenticated,
          (_) => _registerIfAuthenticated(),
        );
      }
    } catch (e, s) {
      CrashReporter.instance.record(e, s);
    }
    return this;
  }

  Future<void> _registerIfAuthenticated() async {
    if (!_ready || _token == null) return;
    if (!Get.isRegistered<SessionService>()) return;
    if (!Get.find<SessionService>().isAuthenticated.value) return;
    if (!Get.isRegistered<BillingRepository>()) return;
    try {
      await Get.find<BillingRepository>().registerDevice(
        token: _token!,
        platform: 'android',
      );
    } on ApiException catch (_) {
      // تسجيل التوكن ليس حرجاً لتجربة المستخدم — نتجاهل الفشل العابر.
    }
  }

  @override
  void onClose() {
    _authWorker?.dispose();
    super.onClose();
  }
}
