import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import 'app/app.dart';
import 'app/routes/app_routes.dart';
import 'core/network/dio_client.dart';
import 'core/storage/secure_token_store.dart';
import 'data/services/session_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await GetStorage.init();

  // خدمة الجلسة (دائمة) — تُحمّل التوكن ومساحة العمل من التخزين الآمن.
  final session = await SessionService(SecureTokenStore()).init();
  Get.put<SessionService>(session, permanent: true);

  // عميل Dio — عند انتهاء الجلسة (401) نمسحها ونعود لتسجيل الدخول.
  final dio = DioClient.build(
    session: session,
    onUnauthenticated: () async {
      await session.clear();
      if (Get.currentRoute != Routes.login) {
        Get.offAllNamed(Routes.login);
      }
    },
  );
  Get.put(dio, permanent: true);

  runApp(const KsGrowthApp());
}
