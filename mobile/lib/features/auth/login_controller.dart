import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
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
      Get.offAllNamed(Routes.dashboard);
    } on ApiException catch (e) {
      _applyError(e);
    } finally {
      isLoading.value = false;
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
