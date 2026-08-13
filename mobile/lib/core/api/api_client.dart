import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_environment.dart';
import 'api_exception.dart';
import 'app_update_gate.dart';
import 'token_store.dart';

/// عميل واحد لكل نداء نحو الخادم.
///
/// مفتاح DeepSeek لا يوجد هنا ولا في أي مكان داخل التطبيق؛ التطبيق يحمل
/// رمز مستخدم فقط، والمزود يُستدعى من Laravel حصريًا.
class ApiClient {
  ApiClient({http.Client? client, TokenStore? tokenStore})
    : _client = client ?? http.Client(),
      _tokenStore = tokenStore ?? TokenStore();

  final http.Client _client;
  final TokenStore _tokenStore;
  String _locale = 'ar';

  TokenStore get tokens => _tokenStore;

  set locale(String value) {
    _locale = const {'ar', 'en', 'fr'}.contains(value) ? value : 'ar';
  }

  Uri _uri(String path, [Map<String, String>? query]) => Uri.parse(
    '${AppEnvironment.apiBaseUrl}/v1$path',
  ).replace(queryParameters: {'lang': _locale, ...?query});

  Future<Map<String, String>> _headers([
    Map<String, String> extra = const {},
  ]) async {
    final token = await _tokenStore.read();

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Accept-Language': _locale,
      // يعرّف الخادم بنسخة التطبيق ليردّ رسالة تحديث مفهومة بدل عقد مكسور.
      'X-App-Build': '${AppEnvironment.appBuild}',
      if (token != null) 'Authorization': 'Bearer $token',
      ...extra,
    };
  }

  Future<dynamic> get(String path, [Map<String, String>? query]) => _send(
    () async => _client.get(_uri(path, query), headers: await _headers()),
  );

  Future<dynamic> getWithHeaders(
    String path,
    Map<String, String> headers, [
    Map<String, String>? query,
  ]) => _send(
    () async =>
        _client.get(_uri(path, query), headers: await _headers(headers)),
  );

  /// تنزيل ملف ثنائي مُصدَّق (مثل PDF التقرير). يعيد البايتات لا JSON.
  Future<List<int>> downloadBytes(String path) async {
    late final http.Response response;

    try {
      response = await _client.get(_uri(path), headers: await _headers());
    } catch (_) {
      throw const ApiException('تعذر الوصول إلى الخادم.');
    }

    if (response.statusCode == 401) {
      await _tokenStore.clear();
      throw const ApiException(
        'انتهت جلستك. سجّل الدخول مرة أخرى.',
        statusCode: 401,
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      // التنزيل يمرّ بالبوابة نفسها: العقد واحد، وردّه ٤٢٦ هنا أيضًا.
      _raiseUpdateGate(
        response.statusCode,
        response.body.isEmpty ? null : jsonDecode(response.body),
      );

      throw ApiException('تعذر تنزيل الملف.', statusCode: response.statusCode);
    }

    return response.bodyBytes;
  }

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send(
    () async => _client.post(
      _uri(path),
      headers: await _headers(),
      body: jsonEncode(body ?? const {}),
    ),
  );

  Future<dynamic> postWithHeaders(
    String path,
    Map<String, String> headers, [
    Map<String, dynamic>? body,
  ]) => _send(
    () async => _client.post(
      _uri(path),
      headers: await _headers(headers),
      body: jsonEncode(body ?? const {}),
    ),
  );

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) => _send(
    () async => _client.put(
      _uri(path),
      headers: await _headers(),
      body: jsonEncode(body ?? const {}),
    ),
  );

  Future<dynamic> putWithHeaders(
    String path,
    Map<String, String> headers, [
    Map<String, dynamic>? body,
  ]) => _send(
    () async => _client.put(
      _uri(path),
      headers: await _headers(headers),
      body: jsonEncode(body ?? const {}),
    ),
  );

  /// رفع ملف عبر multipart. المفتاح لا يغادر الخادم — نرسل رمز المستخدم فقط.
  Future<dynamic> upload(
    String path,
    String filePath, {
    String field = 'file',
  }) async {
    final token = await _tokenStore.read();
    final request = http.MultipartRequest('POST', _uri(path))
      ..headers['Accept'] = 'application/json'
      ..headers['Accept-Language'] = _locale
      ..files.add(await http.MultipartFile.fromPath(field, filePath));

    if (token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    return _send(() async => http.Response.fromStream(await request.send()));
  }

  /// رفع تسجيل صوتي للنسخ.
  ///
  /// حقل منفصل عن [upload]: عقد الصوت يتوقّع `audio` و`seconds` لا `file`،
  /// والمدة ليست تزيينًا — الخادم يحجز بها من سقف التكلفة **قبل** الاستدعاء،
  /// فإرسالها ناقصةً يعني حجزًا لا يطابق ما استُهلك.
  Future<dynamic> uploadAudio(String path, String filePath, int seconds) async {
    final token = await _tokenStore.read();
    final request = http.MultipartRequest('POST', _uri(path))
      ..headers['Accept'] = 'application/json'
      ..headers['Accept-Language'] = _locale
      ..fields['seconds'] = '$seconds'
      ..files.add(await http.MultipartFile.fromPath('audio', filePath));

    if (token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    return _send(() async => http.Response.fromStream(await request.send()));
  }

  Future<dynamic> patch(String path, [Map<String, dynamic>? body]) => _send(
    () async => _client.patch(
      _uri(path),
      headers: await _headers(),
      body: jsonEncode(body ?? const {}),
    ),
  );

  Future<dynamic> delete(String path, [Map<String, dynamic>? body]) => _send(
    () async => _client.delete(
      _uri(path),
      headers: await _headers(),
      body: body == null ? null : jsonEncode(body),
    ),
  );

  Future<dynamic> _send(Future<http.Response> Function() request) async {
    late final http.Response response;

    try {
      response = await request();
    } catch (_) {
      throw const ApiException(
        'تعذر الوصول إلى الخادم. تحقق من اتصالك ثم أعد المحاولة.',
      );
    }

    final body = response.body.isEmpty ? null : jsonDecode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }

    if (response.statusCode == 401) {
      await _tokenStore.clear();
      throw const ApiException(
        'انتهت جلستك. سجّل الدخول مرة أخرى.',
        statusCode: 401,
      );
    }

    _raiseUpdateGate(response.statusCode, body);

    throw ApiException(
      _message(body, response.statusCode),
      statusCode: response.statusCode,
      errors: _errors(body),
      code: _errorValue(body, 'code'),
      action: _errorValue(body, 'action'),
    );
  }

  /// ٤٢٦ = العقد تجاوز هذه النسخة.
  ///
  /// ترفع البوابة **ويُرمى الاستثناء بعدها كما هو**: الشاشة المستدعية تتوقف
  /// كما تتوقف عند أي خطأ، والبوابة تغطّي التطبيق فوقها. لو استُبدل الاستثناء
  /// لكان على كل مستدعٍ أن يعرف الحالة الجديدة.
  void _raiseUpdateGate(int statusCode, dynamic body) {
    if (statusCode != 426) return;

    AppUpdateGate.instance.raise(
      AppUpdateRequirement.fromJson(body is Map ? body : const {}),
    );
  }

  String _message(dynamic body, int statusCode) {
    if (body is Map &&
        body['message'] is String &&
        (body['message'] as String).isNotEmpty) {
      return body['message'] as String;
    }

    final nested = _errorValue(body, 'message');
    if (nested != null && nested.isNotEmpty) return nested;

    return switch (statusCode) {
      404 => 'العنصر المطلوب غير موجود.',
      422 => 'راجع البيانات المدخلة.',
      >= 500 => 'الخادم واجه مشكلة. إجاباتك محفوظة، أعد المحاولة بعد قليل.',
      _ => 'تعذر إكمال الطلب.',
    };
  }

  Map<String, List<String>> _errors(dynamic body) {
    if (body is! Map || body['errors'] is! Map) return const {};

    return (body['errors'] as Map).map(
      (key, value) => MapEntry(
        key.toString(),
        (value as List).map((item) => item.toString()).toList(),
      ),
    );
  }

  String? _errorValue(dynamic body, String key) {
    if (body is! Map) return null;
    final error = body['error'];
    final value = error is Map ? error[key] : body[key];
    return value is String ? value : null;
  }
}
