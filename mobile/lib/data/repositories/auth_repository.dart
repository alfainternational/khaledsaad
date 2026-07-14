import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/user_model.dart';

/// نتيجة مصادقة (توكن + مستخدم + مساحة عمل افتراضية).
class AuthResult {
  const AuthResult({
    required this.token,
    this.user,
    this.defaultWorkspacePublicId,
  });

  final String token;
  final UserModel? user;
  final String? defaultWorkspacePublicId;
}

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  Future<AuthResult> login({
    required String email,
    required String password,
    String deviceName = 'mobile',
    String? workspacePublicId,
  }) async {
    final res = await _api.post(ApiEndpoints.tokens, body: {
      'email': email,
      'password': password,
      'device_name': deviceName,
      'workspace_public_id': ?workspacePublicId,
    });
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return AuthResult(token: data['token']?.toString() ?? '');
  }

  Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? accountName,
    String? workspaceName,
    String deviceName = 'mobile',
  }) async {
    final res = await _api.post(ApiEndpoints.register, body: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'account_name': ?accountName,
      'workspace_name': ?workspaceName,
      'device_name': deviceName,
    });
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return AuthResult(
      token: data['token']?.toString() ?? '',
      user: data['user'] is Map
          ? UserModel.fromJson(Map<String, dynamic>.from(data['user'] as Map))
          : null,
      defaultWorkspacePublicId: data['default_workspace_public_id']?.toString(),
    );
  }

  Future<UserModel> me() async {
    final res = await _api.get(ApiEndpoints.me);
    return UserModel.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<void> logout() => _api.post(ApiEndpoints.logout);

  Future<String> forgotPassword(String email) async {
    final res = await _api.post(ApiEndpoints.passwordForgot, body: {'email': email});
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return data['message']?.toString() ?? 'تم إرسال الطلب.';
  }

  Future<String> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final res = await _api.post(ApiEndpoints.passwordReset, body: {
      'token': token,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return data['message']?.toString() ?? 'تم تحديث كلمة المرور.';
  }
}
