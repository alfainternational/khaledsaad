import 'dart:async';

import 'package:flutter/material.dart';

import '../../features/tools/models.dart' show AnswerFitnessResult, AssistDraftModel;
import '../api/platform_repository.dart';
import '../theme/app_theme.dart';

/// دليل ومقترحات لسؤال واحد، وقياس كفاية ما كُتب فيه — ويدجت واحدة لكل الأسطح.
///
/// **لماذا مشتركة:** القاعدة تسري على كل سؤال في كل استمارة بلا استثناء، ونسخةٌ
/// لكل شاشة تعني منطق تكلفة وحراسة يتباعد مع أول تعديل. الشاشة تمرّر ما يعرفه
/// عن سؤالها، والويدجت تتكفّل بالبقية.
///
/// حدّان لا يُخترقان:
///   - **التوليد بنقر صريح.** يستهلك من سقف المساحة، فلا يُطلق لأن المستخدم فتح
///     شاشة. أما القياس فحتميّ محليّ بلا تكلفة، فيجري أثناء الكتابة.
///   - **الفشل لا يمنع الإجابة.** لا سقف ولا عطل مزوّد يحجب خانة الكتابة:
///     المساعدة معونة على السؤال لا شرط له.
class QuestionAssistPanel extends StatefulWidget {
  const QuestionAssistPanel({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.surface,
    required this.questionKey,
    required this.fieldKey,
    required this.answerType,
    required this.currentValue,
    required this.onApply,
    this.runUuid,
    this.sessionUuid,
  });

  final PlatformRepository repository;
  final String projectSlug;

  /// consultation · tool · agency · profile
  final String surface;
  final String questionKey;

  /// مفتاح الحقيقة في الدماغ — به تُقاس الكفاية، وقد يخالف مفتاح السؤال.
  final String fieldKey;
  final String answerType;
  final String currentValue;

  /// اعتماد مقترح: يملأ الخانة ولا يرسل — المراجعة شرط لا تحسين.
  final ValueChanged<String> onApply;

  final String? runUuid;
  final String? sessionUuid;

  @override
  State<QuestionAssistPanel> createState() => QuestionAssistPanelState();
}

class QuestionAssistPanelState extends State<QuestionAssistPanel> {
  /// أنواع تُقاس كفايتها — نظير `AnswerFitnessScorer::MEASURABLE_TYPES` حرفيًّا.
  static const _measurable = {'text', 'textarea', 'long_text', 'repeater'};

  AssistDraftModel? _draft;
  AnswerFitnessResult? _fitness;
  bool _busy = false;
  Timer? _debounce;

  bool get _measures => _measurable.contains(widget.answerType);

  @override
  void initState() {
    super.initState();

    // قياس أوّليّ لإجابة محفوظة: من يعود إلى سؤال أجاب عنه يرى كفاية إجابته
    // القديمة لا شاشة صامتة.
    if (widget.currentValue.trim().isNotEmpty) {
      scheduleMeasure(widget.currentValue);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  /// قياس بعد سكون الكتابة — تُستدعى من الشاشة عند كل تغيير في الخانة.
  void scheduleMeasure(String value) {
    if (!_measures) return;

    _debounce?.cancel();

    if (value.trim().isEmpty) {
      if (mounted) setState(() => _fitness = null);
      return;
    }

    _debounce = Timer(const Duration(milliseconds: 600), () => _measure(value));
  }

  Future<void> _measure(String value) async {
    try {
      final result = await widget.repository.answerFitness(
        widget.projectSlug,
        fieldKey: widget.fieldKey,
        type: widget.answerType,
        value: value,
      );

      if (mounted) setState(() => _fitness = result);
    } catch (_) {
      // القياس إرشاد لا حكم يمنع الحفظ؛ تعذّره يُخفي البطاقة ولا يوقف شيئًا.
      if (mounted) setState(() => _fitness = null);
    }
  }

  Future<void> _request() async {
    if (_busy) return;

    setState(() => _busy = true);

    try {
      final draft = await widget.repository.assist(
        widget.projectSlug,
        surface: widget.surface,
        questionKey: widget.questionKey,
        runUuid: widget.runUuid,
        sessionUuid: widget.sessionUuid,
      );

      if (mounted) setState(() => _draft = draft);
    } catch (_) {
      // لا شيء: الفراغ يُعلن بالزرّ الباقي مكانه، ولا يُلفَّق بمقترح عام.
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_fitness != null) ...[
          _fitnessCard(_fitness!),
          const SizedBox(height: 8),
        ],
        _assistArea(),
      ],
    );
  }

  Widget _assistArea() {
    final draft = _draft;

    if (draft == null) {
      return Align(
        alignment: AlignmentDirectional.centerStart,
        child: TextButton.icon(
          onPressed: _busy ? null : _request,
          icon: _busy
              ? const SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.auto_awesome, size: 16),
          label: Text(_busy ? 'نجهّز اقتراحًا…' : 'اقترح لي إجابة تناسب نشاطي'),
        ),
      );
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: BrandColors.surfaceSoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: BrandColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (draft.guide.isNotEmpty)
            Text(
              draft.guide,
              style: const TextStyle(fontSize: 12, color: BrandColors.ink),
            ),
          if (draft.recommendationReason != null &&
              draft.recommendationReason!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              'الأقرب لوصفك: ${draft.recommendationReason}',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: BrandColors.ink,
              ),
            ),
          ],
          if (draft.suggestions.isNotEmpty) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final suggestion in draft.suggestions)
                  ActionChip(
                    label: Text(
                      suggestion.label.isEmpty
                          ? suggestion.value
                          : suggestion.label,
                    ),
                    onPressed: () {
                      widget.onApply(suggestion.value);
                      scheduleMeasure(suggestion.value);
                    },
                  ),
              ],
            ),
          ],
          const SizedBox(height: 8),
          /*
           * الوسم شرط لا تجميل (§٤.١، §١٣): المقترح كلامُ نموذج عن نشاط لم يره،
           * والخطر ليس أن يكون فرضية بل أن يُقرأ حقيقة.
           */
          Text(
            draft.assumptionLabel ?? 'فرضية — راجعها وعدّلها قبل اعتمادها.',
            style: const TextStyle(color: BrandColors.muted, fontSize: 11),
          ),
        ],
      ),
    );
  }

  Widget _fitnessCard(AnswerFitnessResult fitness) {
    final ok = fitness.isSufficient;
    final color = ok ? BrandColors.success : BrandColors.orange;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: ok ? BrandColors.surfaceSoft : BrandColors.surfaceWarm,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: BrandColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                ok ? Icons.check_circle_outline : Icons.lightbulb_outline,
                size: 16,
                color: color,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  fitness.label,
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              const Text(
                'فرضية',
                style: TextStyle(color: BrandColors.muted, fontSize: 10),
              ),
            ],
          ),
          if (!ok && fitness.gaps.isNotEmpty) ...[
            const SizedBox(height: 6),
            for (final gap in fitness.gaps)
              Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Text(
                  '· $gap',
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 11,
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}
