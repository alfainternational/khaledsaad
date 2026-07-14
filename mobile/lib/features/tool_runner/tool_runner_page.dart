import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/models/tool_form_model.dart';
import '../../data/models/tool_run_model.dart';
import '../../data/repositories/ai_assist_repository.dart';
import '../../data/repositories/tool_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import 'tool_runner_controller.dart';
import 'widgets/ai_chat_sheet.dart';
import 'widgets/dynamic_form_field.dart';
import 'widgets/tool_result_view.dart';

class ToolRunnerPage extends StatefulWidget {
  const ToolRunnerPage({super.key});

  @override
  State<ToolRunnerPage> createState() => _ToolRunnerPageState();
}

class _ToolRunnerPageState extends State<ToolRunnerPage> {
  final _scrollController = ScrollController();
  final _resultKey = GlobalKey();

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  /// يشغّل الأداة ثم يمرّر تلقائياً إلى بطاقة النتيجة عند النجاح.
  Future<void> _runAndScroll(ToolRunnerController controller) async {
    await controller.run();
    if (controller.result.value == null || controller.error.value != null) {
      return;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctx = _resultKey.currentContext;
      if (ctx != null) {
        Scrollable.ensureVisible(
          ctx,
          duration: const Duration(milliseconds: 420),
          curve: Curves.easeOutCubic,
          alignment: 0.1,
        );
      }
    });
  }

  Future<void> _handleNextAction(
    ToolRunnerController controller,
    ToolNextAction action,
  ) async {
    if (action.actionType == 'current_tool' || action.ctaUrl == '#tool-form') {
      await _scrollController.animateTo(
        0,
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOutCubic,
      );
      return;
    }

    if (action.actionType == 'brief') {
      Get.toNamed(Routes.projectBrief, arguments: controller.projectPublicId);
      return;
    }

    if (action.actionType == 'tool') {
      final code = action.recommendedToolCode;
      if (code == null || code.isEmpty) {
        Get.toNamed(Routes.projectBrief, arguments: controller.projectPublicId);
        return;
      }

      if (code == controller.toolCode) {
        await _scrollController.animateTo(
          0,
          duration: const Duration(milliseconds: 320),
          curve: Curves.easeOutCubic,
        );
        return;
      }

      Get.toNamed(
        Routes.toolRunner,
        arguments: {
          'project_public_id': controller.projectPublicId,
          'tool_code': code,
          'tool_name': action.recommendedToolLabel ?? action.displayLabel,
        },
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final args = Get.arguments as Map<String, dynamic>;
    final toolCode = args['tool_code'] as String;
    final toolName = (args['tool_name'] as String?) ?? 'الأداة';

    final c = Get.put(
      ToolRunnerController(
        Get.find<ToolRepository>(),
        Get.find<AiAssistRepository>(),
        Get.find<WorkspaceService>(),
        projectPublicId: args['project_public_id'] as String,
        toolCode: toolCode,
        toolName: toolName,
      ),
      tag: toolCode,
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(toolName),
        actions: [
          IconButton(
            tooltip: 'المستشار الذكي',
            icon: const Icon(Icons.support_agent),
            onPressed: () => showModalBottomSheet<void>(
              context: context,
              isScrollControlled: true,
              builder: (_) => AiChatSheet(
                toolKey: toolCode,
                projectPublicId: c.projectPublicId,
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Obx(() {
          if (c.isLoading.value && c.form.value == null) {
            return AppStateView.loading();
          }
          if (c.error.value != null && c.form.value == null) {
            return AppStateView.error(message: c.error.value, onRetry: c.load);
          }
          final form = c.form.value;
          if (form == null || form.modes.isEmpty) {
            return AppStateView.empty(
              icon: Icons.build_outlined,
              title: 'لا يوجد نموذج لهذه الأداة',
            );
          }
          final bottomPadding = 56 + MediaQuery.paddingOf(context).bottom;
          return ListView(
            controller: _scrollController,
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: EdgeInsets.fromLTRB(16, 16, 16, bottomPadding),
            children: [
              if (form.modes.length > 1) _ModeSelector(controller: c),
              const SizedBox(height: 8),
              _FormBody(controller: c),
              const SizedBox(height: 8),
              Obx(() {
                final err = c.error.value;
                if (err == null) return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    err,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                );
              }),
              Row(
                children: [
                  Expanded(
                    child: Obx(
                      () => OutlinedButton.icon(
                        onPressed: c.isAnalyzing.value ? null : c.analyzeInputs,
                        icon: c.isAnalyzing.value
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                ),
                              )
                            : const Icon(Icons.insights_outlined),
                        label: Text(
                          c.isAnalyzing.value
                              ? 'جارٍ التحليل...'
                              : 'حلّل إجاباتي',
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Obx(
                      () => OutlinedButton.icon(
                        onPressed: c.isSuggesting.value
                            ? null
                            : c.suggestFields,
                        icon: c.isSuggesting.value
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                ),
                              )
                            : const Icon(Icons.tips_and_updates_outlined),
                        label: Text(
                          c.isSuggesting.value
                              ? 'جارٍ الاقتراح...'
                              : 'اقترح لي',
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              Obx(() {
                final a = c.analysis.value;
                if (a == null) return const SizedBox.shrink();
                return _AnalysisCard(analysis: a);
              }),
              const SizedBox(height: 8),
              Obx(
                () => FilledButton.icon(
                  onPressed: c.isRunning.value ? null : () => _runAndScroll(c),
                  icon: c.isRunning.value
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Icon(Icons.play_arrow),
                  label: Text(
                    c.isRunning.value ? 'جارٍ التشغيل...' : 'تشغيل الأداة',
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Obx(() {
                final result = c.result.value;
                if (result == null) return const SizedBox.shrink();
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ToolResultView(
                      key: _resultKey,
                      result: result,
                      briefing: c.briefing.value,
                      onNextAction: (action) => _handleNextAction(c, action),
                    ),
                    if (result.runPublicId != null) ...[
                      const SizedBox(height: 12),
                      Obx(() => OutlinedButton.icon(
                            onPressed: c.isRequestingApproval.value
                                ? null
                                : c.requestApproval,
                            icon: c.isRequestingApproval.value
                                ? const SizedBox(
                                    height: 18,
                                    width: 18,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2.2))
                                : const Icon(Icons.verified_outlined),
                            label: const Text('طلب مراجعة وموافقة'),
                          )),
                    ],
                  ],
                );
              }),
            ],
          );
        }),
      ),
    );
  }
}

class _ModeSelector extends StatelessWidget {
  const _ModeSelector({required this.controller});

  final ToolRunnerController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final modes = controller.form.value?.modes ?? <ToolMode>[];
      final selected = controller.selectedMode.value;
      return Wrap(
        spacing: 8,
        children: modes.map((m) {
          return ChoiceChip(
            label: Text(m.label),
            selected: selected == m.key,
            onSelected: (_) => controller.selectMode(m.key),
          );
        }).toList(),
      );
    });
  }
}

class _AnalysisCard extends StatelessWidget {
  const _AnalysisCard({required this.analysis});

  final Map<String, dynamic> analysis;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final verdict = analysis['verdict']?.toString();
    final note = analysis['strategic_note']?.toString();
    final score = analysis['score'];
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 12),
      color: theme.colorScheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  Icons.insights,
                  color: theme.colorScheme.primary,
                  size: 20,
                ),
                const SizedBox(width: 6),
                Text(
                  'تحليل إجاباتك',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const Spacer(),
                if (score is num)
                  Text(
                    '${score.toInt()}%',
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: theme.colorScheme.primary,
                    ),
                  ),
              ],
            ),
            if (verdict != null && verdict.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                verdict,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            if (note != null && note.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(note, style: theme.textTheme.bodyMedium),
            ],
          ],
        ),
      ),
    );
  }
}

class _FormBody extends StatelessWidget {
  const _FormBody({required this.controller});

  final ToolRunnerController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final mode = controller.currentMode;
      if (mode == null) return const SizedBox.shrink();
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (mode.description.isNotEmpty) ...[
            Text(
              mode.description,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
          ],
          ...mode.fields.map(
            (f) => DynamicFormField(
              key: ValueKey(
                '${mode.key}:${f.key}:${controller.formEpoch.value}',
              ),
              field: f,
              controller: controller,
            ),
          ),
        ],
      );
    });
  }
}
