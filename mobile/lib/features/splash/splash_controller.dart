import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

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
      Get.offAllNamed(Routes.home);
    } else if (GetStorage().read('welcome_seen') == true) {
      Get.offAllNamed(Routes.login);
    } else {
      Get.offAllNamed(Routes.welcome);
    }
  }
}
