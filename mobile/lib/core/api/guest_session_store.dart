import 'package:shared_preferences/shared_preferences.dart';

/// يحتفظ برمز تجربة الزائر فقط حتى تنتقل الإجابات إلى الحساب عند التسجيل.
class GuestSessionStore {
  static const String _key = 'khaledsaad.guest_token';

  String? _cached;

  Future<String?> read() async {
    if (_cached != null) return _cached;

    final preferences = await SharedPreferences.getInstance();
    return _cached = preferences.getString(_key);
  }

  Future<void> write(String token) async {
    _cached = token;
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(_key, token);
  }

  Future<void> clear() async {
    _cached = null;
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(_key);
  }
}
