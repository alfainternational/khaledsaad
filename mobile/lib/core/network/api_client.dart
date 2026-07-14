import 'dart:convert';

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

  /// [idempotencyKey]: مفتاح ثابت للعملية عبر إعادة المحاولات؛ حين يُمرَّر
  /// لا يولّد الـ interceptor مفتاحاً عشوائياً جديداً، فتُمنَع النسخ المكررة.
  Future<Map<String, dynamic>> post(
    String path, {
    Object? body,
    String? idempotencyKey,
  }) =>
      _request(() => _dio.post(
            path,
            data: body,
            options: _idempotencyOptions(idempotencyKey),
          ));

  /// نداء POST لعمليات التوليد الطويلة (استوديو/مستشار/تحليل): مهلة استقبال
  /// موسّعة حتى لا يقطع التطبيق الاتصال قبل اكتمال الرد من مزوّد أبطأ.
  Future<Map<String, dynamic>> postGenerative(
    String path, {
    Object? body,
    Duration receiveTimeout = const Duration(seconds: 150),
    String? idempotencyKey,
  }) =>
      _request(() => _dio.post(
            path,
            data: body,
            options: Options(
              receiveTimeout: receiveTimeout,
              sendTimeout: const Duration(seconds: 30),
              headers: idempotencyKey == null
                  ? null
                  : {'Idempotency-Key': idempotencyKey},
            ),
          ));

  Options? _idempotencyOptions(String? key) => key == null
      ? null
      : Options(headers: {'Idempotency-Key': key});

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

  /// رفع ملف (multipart/form-data) — لمستندات المعرفة. الحقل المتوقّع: file.
  Future<Map<String, dynamic>> upload(
    String path, {
    required String filePath,
    required String filename,
  }) =>
      _request(() async {
        final form = FormData.fromMap({
          'file': await MultipartFile.fromFile(filePath, filename: filename),
        });
        return _dio.post(
          path,
          data: form,
          options: Options(
            sendTimeout: const Duration(seconds: 120),
            receiveTimeout: const Duration(seconds: 120),
          ),
        );
      });

  /// رفع ملف صوتي (multipart) مع حقول إضافية اختيارية — لإدخال الصوت.
  /// اسم الحقل الافتراضي `audio` مطابق لما يتوقّعه الخادم.
  Future<Map<String, dynamic>> uploadAudio(
    String path, {
    required String filePath,
    required String filename,
    Map<String, String> fields = const {},
    String fieldName = 'audio',
  }) =>
      _request(() async {
        final form = FormData.fromMap({
          ...fields,
          fieldName: await MultipartFile.fromFile(filePath, filename: filename),
        });
        return _dio.post(
          path,
          data: form,
          options: Options(
            sendTimeout: const Duration(seconds: 60),
            receiveTimeout: const Duration(seconds: 90),
          ),
        );
      });

  /// بثّ خادم-مُرسَل (SSE): يفكّ أسطر `data:` ويُطلق نص كل delta لحظياً.
  /// يرمي ApiException عند حدث خطأ من الخادم، وينتهي عند `[DONE]`.
  Stream<String> stream(String path, {Object? body}) async* {
    late final Response<ResponseBody> response;
    try {
      response = await _dio.post<ResponseBody>(
        path,
        data: body,
        options: Options(
          responseType: ResponseType.stream,
          headers: {'Accept': 'text/event-stream'},
          receiveTimeout: const Duration(seconds: 150),
        ),
      );
    } on DioException catch (e) {
      final err = e.error;
      if (err is ApiException) throw err;
      throw ApiException.network(e.message);
    }

    var buffer = '';
    await for (final chunk in response.data!.stream) {
      buffer += utf8.decode(chunk, allowMalformed: true);
      while (true) {
        final nl = buffer.indexOf('\n');
        if (nl < 0) break;
        final line = buffer.substring(0, nl).trim();
        buffer = buffer.substring(nl + 1);
        if (line.isEmpty || !line.startsWith('data:')) continue;
        final data = line.substring(5).trim();
        if (data == '[DONE]') return;
        Map<String, dynamic>? event;
        try {
          final decoded = jsonDecode(data);
          if (decoded is Map) event = Map<String, dynamic>.from(decoded);
        } catch (_) {
          continue; // سطر SSE غير مكتمل — نتجاهله.
        }
        if (event == null) continue;
        final error = event['error'];
        if (error != null) throw ApiException.network(error.toString());
        final delta = event['delta'];
        if (delta != null) yield delta.toString();
      }
    }
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
