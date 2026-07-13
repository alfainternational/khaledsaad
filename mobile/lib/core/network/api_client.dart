import 'package:dio/dio.dart';

import '../error/api_exception.dart';

/// غلاف رقيق حول Dio: ينفّذ الطلبات ويحوّل أي خطأ إلى ApiException.
/// يتوقّع عقد النجاح: { "data": ... } — ويعيد جسم الرد كاملاً.
class ApiClient {
  ApiClient(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) =>
      _request(() => _dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(
    String path, {
    Object? body,
  }) =>
      _request(() => _dio.post(path, data: body));

  /// نداء POST لعمليات التوليد الطويلة (استوديو/مستشار/تحليل): مهلة استقبال
  /// موسّعة حتى لا يقطع التطبيق الاتصال قبل اكتمال الرد من مزوّد أبطأ.
  Future<Map<String, dynamic>> postGenerative(
    String path, {
    Object? body,
    Duration receiveTimeout = const Duration(seconds: 150),
  }) =>
      _request(() => _dio.post(
            path,
            data: body,
            options: Options(
              receiveTimeout: receiveTimeout,
              sendTimeout: const Duration(seconds: 30),
            ),
          ));

  Future<Map<String, dynamic>> put(
    String path, {
    Object? body,
  }) =>
      _request(() => _dio.put(path, data: body));

  Future<Map<String, dynamic>> patch(
    String path, {
    Object? body,
  }) =>
      _request(() => _dio.patch(path, data: body));

  Future<void> delete(String path) async {
    await _guard(() => _dio.delete(path));
  }

  /// تنزيل بايتات (لملفات PDF مثلاً) مع مصادقة Bearer.
  Future<List<int>> download(String path) async {
    final response = await _guard(
      () => _dio.get<List<int>>(
        path,
        options: Options(responseType: ResponseType.bytes),
      ),
    );
    return (response.data as List<int>?) ?? const [];
  }

  Future<Map<String, dynamic>> _request(
    Future<Response> Function() run,
  ) async {
    final response = await _guard(run);
    final data = response.data;
    if (data is Map<String, dynamic>) return data;
    return {'data': data};
  }

  /// يشغّل الطلب ويحوّل DioException إلى ApiException المرفق داخله.
  Future<Response> _guard(Future<Response> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      final err = e.error;
      if (err is ApiException) throw err;
      throw ApiException.network(e.message);
    }
  }
}
