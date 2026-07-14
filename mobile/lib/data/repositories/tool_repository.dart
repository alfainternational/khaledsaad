import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/idempotency.dart';
import '../models/tool_form_model.dart';
import '../models/tool_list_item.dart';
import '../models/tool_run_model.dart';

/// نتيجة تحميل أداة: مخطط النموذج + آخر تشغيل (إن وُجد).
class ToolLoadResult {
  const ToolLoadResult({required this.form, this.lastRun, this.briefing});

  final ToolForm form;
  final ToolRunResult? lastRun;
  final ToolBriefing? briefing;
}

class ToolRepository {
  ToolRepository(this._api);

  final ApiClient _api;

  Future<List<ToolListItem>> listTools(
    String ws, {
    String? projectPublicId,
  }) async {
    final res = await _api.get(
      ApiEndpoints.tools(ws),
      query: {
        if (projectPublicId != null && projectPublicId.isNotEmpty)
          'project_public_id': projectPublicId,
      },
    );
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) => ToolListItem.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ToolLoadResult> load(String ws, String project, String tcode) async {
    final res = await _api.get(ApiEndpoints.toolLoad(ws, project, tcode));

    final form = res['form'] is Map
        ? ToolForm.fromJson(Map<String, dynamic>.from(res['form'] as Map))
        : const ToolForm(modes: []);

    final data = res['data'];
    final lastRun = data is Map
        ? ToolRunResult.fromJson(Map<String, dynamic>.from(data))
        : null;

    final briefing = res['tool_briefing'] is Map
        ? ToolBriefing.fromJson(
            Map<String, dynamic>.from(res['tool_briefing'] as Map),
          )
        : null;

    return ToolLoadResult(form: form, lastRun: lastRun, briefing: briefing);
  }

  /// يفرّغ ملفاً صوتياً ويوزّعه على حقول الأداة عبر الخادم.
  /// يعيد (transcript, fields) حيث fields خريطة مفتاح الحقل → القيمة المقترحة.
  Future<({String transcript, Map<String, String> fields})> transcribe(
    String ws,
    String project,
    String tcode, {
    required String filePath,
    required String mode,
  }) async {
    final res = await _api.uploadAudio(
      ApiEndpoints.toolTranscribe(ws, project, tcode),
      filePath: filePath,
      filename: 'voice.m4a',
      fields: {'mode': mode},
    );
    final data = res['data'] is Map
        ? Map<String, dynamic>.from(res['data'] as Map)
        : const <String, dynamic>{};
    final rawFields = data['fields'];
    final fields = <String, String>{};
    if (rawFields is Map) {
      rawFields.forEach((k, v) => fields[k.toString()] = v?.toString() ?? '');
    }
    return (
      transcript: data['transcript']?.toString() ?? '',
      fields: fields,
    );
  }

  Future<ToolRunResult> run(
    String ws,
    String project,
    String tcode, {
    required String mode,
    required Map<String, dynamic> inputs,
    String? brief,
  }) async {
    final res = await _api.post(
      ApiEndpoints.toolRun(ws, project, tcode),
      // مفتاح ثابت لنفس (المشروع/الأداة/الوضع/المدخلات) كي لا تُنشئ إعادة
      // المحاولة بعد انقطاع مؤقّت سجلّ تشغيل مكرّراً.
      idempotencyKey: stableIdempotencyKey('toolrun', [
        project,
        tcode,
        mode,
        inputs,
      ]),
      body: {
        'mode': mode,
        'inputs': inputs,
        if (brief != null && brief.isNotEmpty) 'brief': brief,
      },
    );

    // رد التشغيل قد يكون تحت data أو في الجذر.
    final data = res['data'] is Map
        ? Map<String, dynamic>.from(res['data'] as Map)
        : res;
    return ToolRunResult.fromJson(data);
  }
}
