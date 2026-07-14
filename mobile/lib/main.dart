import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import 'app/app.dart';
import 'app/routes/app_routes.dart';
import 'app/theme/theme_controller.dart';
import 'core/network/dio_client.dart';
import 'core/observability/crash_reporter.dart';
import 'core/storage/secure_token_store.dart';
import 'data/services/session_service.dart';

Future<void> main() async {
  // التقاط كل الأعطال غير المُمسكة (Flutter framework + أي منطقة async) وتمريرها
  // لمُبلّغ مركزي — نقطة امتداد لربط مزوّد خارجي (Sentry/Crashlytics) لاحقاً.
  runZonedGuarded<Future<void>>(() async {
    WidgetsFlutterBinding.ensureInitialized();

    FlutterError.onError = (details) {
      FlutterError.presentError(details);
      CrashReporter.instance.record(details.exception, details.stack);
    };
    // أخطاء المنصّة (native) خارج منطقة Flutter.
    PlatformDispatcher.instance.onError = (error, stack) {
      CrashReporter.instance.record(error, stack);
      return true;
    };

    await GetStorage.init();

    // مُتحكّم الثيم (دائم) — تفضيل الوضع وحجم الخط محفوظان محلياً.
    Get.put<ThemeController>(ThemeController(GetStorage()), permanent: true);

    // خدمة الجلسة (دائمة) — تُحمّل التوكن ومساحة العمل من التخزين الآمن.
    final session = await SessionService(SecureTokenStore()).init();
    Get.put<SessionService>(session, permanent: true);

    // عميل Dio — عند انتهاء الجلسة (401) نمسحها ونعود لتسجيل الدخول مع تنبيه واضح.
    final dio = DioClient.build(
      session: session,
      onUnauthenticated: () async {
        final wasAuthenticated = session.isAuthenticated.value;
        await session.clear();
        if (Get.currentRoute != Routes.login) {
          Get.offAllNamed(Routes.login);
          if (wasAuthenticated) {
            Get.snackbar(
              'انتهت الجلسة',
              'انتهت صلاحية جلستك. يرجى تسجيل الدخول من جديد.',
              snackPosition: SnackPosition.BOTTOM,
            );
          }
        }
      },
    );
    Get.put(dio, permanent: true);

    runApp(const KsGrowthApp());
  }, (error, stack) {
    CrashReporter.instance.record(error, stack);
  });
}
