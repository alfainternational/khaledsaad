import 'package:get/get.dart';

import '../../core/storage/secure_token_store.dart';

/// خدمة جلسة دائمة (permanent) تحمل التوكن ومساحة العمل النشطة في الذاكرة
/// للوصول المتزامن من الـ interceptors، مع مزامنة مع التخزين الآمن.
class SessionService extends GetxService {
  SessionService(this._store);

  final SecureTokenStore _store;

  /// التوكن الحالي (متاح متزامناً للـ interceptors).
  String? _token;
  String? get token => _token;

  /// public_id لمساحة العمل النشطة.
  final RxnString activeWorkspaceId = RxnString();

  /// هل المستخدم مصادَق؟
  final RxBool isAuthenticated = false.obs;

  /// تحميل الحالة من التخزين الآمن عند الإقلاع.
  Future<SessionService> init() async {
    _token = await _store.readToken();
    activeWorkspaceId.value = await _store.readActiveWorkspace();
    isAuthenticated.value = _token != null && _token!.isNotEmpty;
    return this;
  }

  Future<void> setToken(String token) async {
    _token = token;
    isAuthenticated.value = true;
    await _store.saveToken(token);
  }

  Future<void> setActiveWorkspace(String publicId) async {
    activeWorkspaceId.value = publicId;
    await _store.saveActiveWorkspace(publicId);
  }

  /// مسح الجلسة كاملة (تسجيل خروج / انتهاء التوكن).
  Future<void> clear() async {
    _token = null;
    activeWorkspaceId.value = null;
    isAuthenticated.value = false;
    await _store.clearAll();
  }
}
