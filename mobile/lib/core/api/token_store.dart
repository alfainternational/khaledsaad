import 'package:shared_preferences/shared_preferences.dart';

/// تخزين رمز الجلسة فقط. لا مفاتيح مزودين ولا أسرار خادم على الجهاز.
class TokenStore {
  static const String _key = 'khaledsaad.api_token';

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
