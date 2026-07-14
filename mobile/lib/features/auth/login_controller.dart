import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/routes/app_routes.dart';
import '../../core/config/env.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/services/session_service.dart';

class LoginController extends GetxController {
  LoginController(this._auth, this._session);

  final AuthRepository _auth;
  final SessionService _session;

  final isLoading = false.obs;
  final obscurePassword = true.obs;

  /// خطأ عام (بيانات دخول خاطئة مثلاً).
  final formError = RxnString();

  /// أخطاء الحقول من الخادم.
  final fieldErrors = <String, String>{}.obs;

  Future<void> login(String email, String password) async {
    if (isLoading.value) return;
    _resetErrors();
    isLoading.value = true;
    try {
      final result = await _auth.login(email: email.trim(), password: password);
      await _session.setToken(result.token);
      Get.offAllNamed(Routes.home);
    } on ApiException catch (e) {
      _applyError(e);
    } finally {
      isLoading.value = false;
    }
  }

  /// تسجيل الدخول الاجتماعي: يفتح صفحة المزوّد في المتصفح، والعودة تُعالَج عبر
  /// DeepLinkService (ksgrowth://auth/social) الذي يحفظ التوكن ويوجّه للرئيسية.
  Future<void> signInWithProvider(String provider) async {
    _resetErrors();
    final uri = Uri.parse('${Env.apiBaseUrl}/auth/social/$provider/redirect');
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok) formError.value = 'تعذّر فتح صفحة تسجيل الدخول.';
    } catch (_) {
      formError.value = 'تعذّر فتح صفحة تسجيل الدخول.';
    }
  }

  void _applyError(ApiException e) {
    if (e.isValidation) {
      fieldErrors.assignAll(
        e.errors.map((key, value) => MapEntry(key, value.first)),
      );
    } else {
      formError.value = e.message;
    }
  }

  void _resetErrors() {
    formError.value = null;
    fieldErrors.clear();
  }
}
