import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/services/session_service.dart';

class SplashController extends GetxController {
  SplashController(this._session);

  final SessionService _session;

  @override
  void onReady() {
    super.onReady();
    _decide();
  }

  void _decide() {
    // نقطة توجيه أولية: مصادَق → الداشبورد، غير ذلك → تسجيل الدخول.
    if (_session.isAuthenticated.value) {
      Get.offAllNamed(Routes.dashboard);
    } else {
      Get.offAllNamed(Routes.login);
    }
  }
}
