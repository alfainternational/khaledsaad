import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:local_auth/local_auth.dart';

/// قفل بيومتري اختياري عند فتح التطبيق أو العودة إليه — يحمي بيانات المشاريع
/// والملف التسويقي على جهاز مفقود. يُفعَّل من إعدادات الحساب ويُحفظ محلياً.
class AppLockService extends GetxService with WidgetsBindingObserver {
  AppLockService(this._box);

  final GetStorage _box;
  final _auth = LocalAuthentication();

  static const _kEnabled = 'app_lock_enabled';

  /// هل القفل مفعّل من الإعدادات؟
  final enabled = false.obs;

  /// هل التطبيق مقفول الآن (يحتاج مصادقة)؟
  final locked = false.obs;

  Future<AppLockService> init() async {
    enabled.value = _box.read<bool>(_kEnabled) ?? false;
    locked.value = enabled.value; // اطلب المصادقة عند الإقلاع إن كان مفعّلاً.
    WidgetsBinding.instance.addObserver(this);
    return this;
  }

  /// هل الجهاز يدعم البصمة/الوجه أصلاً؟
  Future<bool> canAuthenticate() async {
    try {
      return await _auth.isDeviceSupported() &&
          await _auth.canCheckBiometrics;
    } catch (_) {
      return false;
    }
  }

  Future<void> setEnabled(bool value) async {
    enabled.value = value;
    await _box.write(_kEnabled, value);
    locked.value = false; // لا نقفل فور التبديل داخل التطبيق.
  }

  /// يطلب المصادقة البيومترية ويفتح القفل عند النجاح.
  Future<bool> unlock() async {
    try {
      final ok = await _auth.authenticate(
        localizedReason: 'أكّد هويتك لفتح التطبيق',
        options: const AuthenticationOptions(stickyAuth: true),
      );
      if (ok) locked.value = false;
      return ok;
    } catch (_) {
      return false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // عند العودة من الخلفية أعد القفل إن كان مفعّلاً.
    if (state == AppLifecycleState.resumed && enabled.value) {
      locked.value = true;
    }
  }

  @override
  void onClose() {
    WidgetsBinding.instance.removeObserver(this);
    super.onClose();
  }
}
