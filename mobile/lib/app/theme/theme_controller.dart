import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

/// يدير تفضيل الوضع (فاتح/داكن/تلقائي) وحجم الخط، ويحفظهما محلياً.
///
/// يعالج فجوتين: غياب مبدّل ثيم يدوي، وغياب سقف/تحكم في تكبير الخط.
class ThemeController extends GetxController {
  ThemeController(this._box);

  final GetStorage _box;

  static const _kThemeMode = 'pref_theme_mode';
  static const _kTextScale = 'pref_text_scale';

  /// الحد الأدنى/الأقصى لتكبير الخط — سقف يمنع تراكب العناوين (clamp).
  static const double minScale = 0.85;
  static const double maxScale = 1.30;

  final themeMode = ThemeMode.system.obs;
  final textScale = 1.0.obs;

  @override
  void onInit() {
    super.onInit();
    final storedMode = _box.read<String>(_kThemeMode);
    themeMode.value = _decodeMode(storedMode);
    final storedScale = _box.read<double>(_kTextScale);
    if (storedScale != null) {
      textScale.value = storedScale.clamp(minScale, maxScale);
    }
  }

  void setThemeMode(ThemeMode mode) {
    themeMode.value = mode;
    _box.write(_kThemeMode, _encodeMode(mode));
    Get.changeThemeMode(mode);
  }

  void setTextScale(double scale) {
    final clamped = scale.clamp(minScale, maxScale).toDouble();
    textScale.value = clamped;
    _box.write(_kTextScale, clamped);
  }

  /// سقف آمن حتى لو رفع المستخدم خط النظام لأقصاه.
  TextScaler cappedScaler(TextScaler system) {
    final systemFactor = system.scale(1.0);
    final effective = (systemFactor * textScale.value).clamp(minScale, maxScale);
    return TextScaler.linear(effective);
  }

  static ThemeMode _decodeMode(String? raw) {
    switch (raw) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  static String _encodeMode(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return 'light';
      case ThemeMode.dark:
        return 'dark';
      case ThemeMode.system:
        return 'system';
    }
  }
}
