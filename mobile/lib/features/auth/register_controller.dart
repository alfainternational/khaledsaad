import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/services/session_service.dart';

class RegisterController extends GetxController {
  RegisterController(this._auth, this._session);

  final AuthRepository _auth;
  final SessionService _session;

  final isLoading = false.obs;
  final obscurePassword = true.obs;
  final formError = RxnString();
  final fieldErrors = <String, String>{}.obs;

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (isLoading.value) return;
    formError.value = null;
    fieldErrors.clear();
    isLoading.value = true;
    try {
      final result = await _auth.register(
        name: name.trim(),
        email: email.trim(),
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      await _session.setToken(result.token);
      if (result.defaultWorkspacePublicId != null) {
        await _session.setActiveWorkspace(result.defaultWorkspacePublicId!);
      }
      Get.offAllNamed(Routes.dashboard);
    } on ApiException catch (e) {
      if (e.isValidation) {
        fieldErrors.assignAll(
          e.errors.map((key, value) => MapEntry(key, value.first)),
        );
      } else {
        formError.value = e.message;
      }
    } finally {
      isLoading.value = false;
    }
  }
}
