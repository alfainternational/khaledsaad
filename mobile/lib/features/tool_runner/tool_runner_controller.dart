import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/tool_form_model.dart';
import '../../data/models/tool_run_model.dart';
import '../../data/repositories/ai_assist_repository.dart';
import '../../data/repositories/tool_repository.dart';
import '../../data/services/workspace_service.dart';

class ToolRunnerController extends GetxController {
  ToolRunnerController(
    this._tools,
    this._aiAssist,
    this._workspaces, {
    required this.projectPublicId,
    required this.toolCode,
    required this.toolName,
  });

  final ToolRepository _tools;
  final AiAssistRepository _aiAssist;
  final WorkspaceService _workspaces;
  final String projectPublicId;
  final String toolCode;
  final String toolName;

  final Rxn<ToolForm> form = Rxn<ToolForm>();
  final selectedMode = RxnString();
  final values = <String, String>{}.obs;

  final isLoading = false.obs;
  final isRunning = false.obs;
  final isAnalyzing = false.obs;
  final error = RxnString();
  final Rxn<ToolRunResult> result = Rxn<ToolRunResult>();
  final Rxn<ToolBriefing> briefing = Rxn<ToolBriefing>();

  /// نتيجة تحليل جودة المدخلات (verdict/strategic_note/...).
  final Rxn<Map<String, dynamic>> analysis = Rxn<Map<String, dynamic>>();

  /// يزداد عند تعبئة قيم خارجية (اقتراحات) لإجبار حقول النص على إعادة البناء.
  final formEpoch = 0.obs;

  ToolMode? get currentMode => selectedMode.value == null
      ? null
      : form.value?.modeByKey(selectedMode.value!);

  @override
  void onReady() {
    super.onReady();
    load();
  }

  Future<void> load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    isLoading.value = true;
    error.value = null;
    try {
      final res = await _tools.load(ws, projectPublicId, toolCode);
      form.value = res.form;
      selectedMode.value =
          res.form.defaultMode ??
          (res.form.modes.isNotEmpty ? res.form.modes.first.key : null);
      result.value = (res.lastRun != null && !res.lastRun!.isEmpty)
          ? res.lastRun
          : null;
      briefing.value = res.briefing;
      _seedValuesFromLastRun(res.lastRun);
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  void _seedValuesFromLastRun(ToolRunResult? lastRun) {
    if (lastRun == null) return;
    lastRun.inputs.forEach((key, value) {
      if (value != null) values[key] = value.toString();
    });
  }

  void selectMode(String mode) {
    selectedMode.value = mode;
  }

  void setValue(String key, String value) {
    values[key] = value;
  }

  void applySuggestion(ToolField field) {
    if (field.suggestedValue != null && field.suggestedValue!.isNotEmpty) {
      values[field.key] = field.suggestedValue!;
      update();
    }
  }

  /// تحذير جودة ليّن لحقل (لا يمنع التشغيل).
  String? qualityWarning(ToolField field) {
    final value = values[field.key]?.trim() ?? '';
    if (value.isEmpty) return null;
    if (field.quality.minLength > 0 && value.length < field.quality.minLength) {
      return 'أضف تفاصيل أكثر (${field.quality.minLength} حرف على الأقل).';
    }
    for (final term in field.quality.genericTerms) {
      if (value == term) {
        return 'حاول أن تكون أكثر تحديداً بدل عبارة عامة.';
      }
    }
    return null;
  }

  /// اقتراح قيم للحقول الفارغة عبر الذكاء الاصطناعي.
  final isSuggesting = false.obs;

  Future<void> suggestFields() async {
    final ws = _workspaces.activeId;
    final mode = selectedMode.value;
    if (ws == null || mode == null || isSuggesting.value) return;

    isSuggesting.value = true;
    error.value = null;
    try {
      final inputs = <String, dynamic>{};
      for (final field in currentMode?.fields ?? <ToolField>[]) {
        inputs[field.key] = values[field.key] ?? '';
      }
      final res = await _aiAssist.suggestFields(
        ws,
        toolCode: toolCode,
        toolName: toolName,
        inputs: inputs,
        mode: mode,
        projectPublicId: projectPublicId,
      );
      // الرد يحمل suggestions: {key: value} — نملأ الفارغ فقط.
      final suggestions = res['suggestions'];
      if (suggestions is Map) {
        suggestions.forEach((key, value) {
          final k = key.toString();
          final v = value?.toString() ?? '';
          if (v.isNotEmpty && (values[k] ?? '').trim().isEmpty) {
            values[k] = v;
          }
        });
        values.refresh();
        formEpoch.value++; // إعادة بناء الحقول لالتقاط القيم الجديدة
      }
    } on ApiException catch (e) {
      error.value = e.isCreditsExhausted
          ? 'انتهى رصيد الذكاء الاصطناعي.'
          : e.message;
    } finally {
      isSuggesting.value = false;
    }
  }

  /// تحليل جودة المدخلات الحالية قبل التشغيل (تقييم محلي + إثراء LLM).
  Future<void> analyzeInputs() async {
    final ws = _workspaces.activeId;
    final mode = selectedMode.value;
    if (ws == null || mode == null || isAnalyzing.value) return;

    isAnalyzing.value = true;
    error.value = null;
    try {
      final inputs = <String, dynamic>{};
      for (final field in currentMode?.fields ?? <ToolField>[]) {
        inputs[field.key] = values[field.key] ?? '';
      }
      final res = await _aiAssist.analyzeInputs(
        ws,
        toolCode: toolCode,
        toolName: toolName,
        inputs: inputs,
        mode: mode,
        projectPublicId: projectPublicId,
      );
      analysis.value = res;
    } on ApiException catch (e) {
      error.value = e.isCreditsExhausted
          ? 'انتهى رصيد الذكاء الاصطناعي.'
          : e.message;
    } finally {
      isAnalyzing.value = false;
    }
  }

  Future<void> run() async {
    final ws = _workspaces.activeId;
    final mode = selectedMode.value;
    if (ws == null || mode == null) return;
    if (isRunning.value) return;

    isRunning.value = true;
    error.value = null;
    try {
      final inputs = <String, dynamic>{};
      for (final field in currentMode?.fields ?? <ToolField>[]) {
        inputs[field.key] = values[field.key] ?? '';
      }
      final res = await _tools.run(
        ws,
        projectPublicId,
        toolCode,
        mode: mode,
        inputs: inputs,
      );
      result.value = res;
    } on ApiException catch (e) {
      // 422 قد يعني أن الوضع غير متاح بعد (ToolModePolicy).
      error.value = e.message;
    } finally {
      isRunning.value = false;
    }
  }
}
