import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// يقابل resources/views/app/reports/gaps.blade.php
///
/// **سبب وجودها:** التقرير كان يعرض «معلومات تحتاج إلى تأكيد منك» قائمةَ
/// نقاطٍ صمّاء — في التطبيق كما في الويب. يقرأ صاحب النشاط أن تشخيصه ناقص،
/// ويعرف أن النقص منه، ولا يجد أين يكتب. §٤.٣ توجب إعلان الفجوة، وإعلانٌ
/// بلا باب ليس التزامًا بها بل اعتذارٌ عنها.
///
/// الحدود هنا يفرضها الخادم لا الشاشة: مفتاح لم يعلنه التقرير يُرفض. الشاشة
/// تعرض ما أرسله الخادم وتعيد إليه ما كُتب، ولا تخترع مفتاحًا.
class ReportGapsScreen extends StatefulWidget {
  const ReportGapsScreen({
    super.key,
    required this.repository,
    required this.reportId,
    required this.projectName,
  });

  final PlatformRepository repository;
  final int reportId;
  final String projectName;

  @override
  State<ReportGapsScreen> createState() => _ReportGapsScreenState();
}

class _ReportGapsScreenState extends State<ReportGapsScreen> {
  late Future<List<ReportGap>> _future;
  final Map<String, TextEditingController> _text = {};
  final Map<String, String> _choice = {};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.reportGaps(widget.reportId);
  }

  @override
  void dispose() {
    for (final controller in _text.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController _controllerFor(String key) =>
      _text.putIfAbsent(key, TextEditingController.new);

  Map<String, String> _answers() {
    final answers = <String, String>{};

    _text.forEach((key, controller) {
      final value = controller.text.trim();
      if (value.isNotEmpty) answers[key] = value;
    });
    _choice.forEach((key, value) {
      if (value.isNotEmpty) answers[key] = value;
    });

    return answers;
  }

  Future<void> _save() async {
    final answers = _answers();

    if (answers.isEmpty) {
      _say('اكتب إجابة واحدة على الأقل قبل الحفظ.');

      return;
    }

    setState(() => _saving = true);

    try {
      final remaining = await widget.repository.saveReportGaps(
        widget.reportId,
        answers,
      );

      if (!mounted) return;

      // ما حُفظ يُنظَّف من الحقول حتى لا يُرسَل مرتين، والباقي يبقى معروضًا.
      for (final key in answers.keys) {
        _text[key]?.clear();
        _choice.remove(key);
      }

      setState(() {
        _saving = false;
        _future = Future.value(remaining);
      });

      _say(
        remaining.isEmpty
            ? 'حُفظت إجاباتك. لم يبقَ نقص في هذا التقرير.'
            : 'حُفظت إجاباتك. التشخيص القادم يبدأ منها.',
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      _say('تعذّر الحفظ الآن. تحقّق من اتصالك وأعد المحاولة.');
    }
  }

  void _say(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    // شاشة نموذج: أسئلة تُملأ وتُحفظ، لا نصّ يُقرأ ولا لوحة تُراقَب.
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: const Text('أكمل ما ينقص تشخيصك')),
      body: FutureBuilder<List<ReportGap>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('تعذّر جلب المعلومات الناقصة الآن.'),
              ),
            );
          }

          final gaps = snapshot.data ?? const <ReportGap>[];

          if (gaps.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('لا توجد معلومات ناقصة في هذا التقرير.'),
              ),
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                widget.projectName,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'هذه هي المعلومات التي لم نجدها عنك، وكل واحدة منها غيّرت شيئًا '
                'في تقريرك. اكتب ما تعرفه واترك ما لا تعرفه — الفراغ المعلن '
                'أصدق من تخمين.',
              ),
              const SizedBox(height: 16),
              for (final gap in gaps) _field(gap),

              /*
               * الحفظ لا يعيد حساب تقرير صدر: التقرير مستند مؤرَّخ، وتعديله
               * بأثر رجعيّ يجعل نسخته المطبوعة تخالف نسخته على الشاشة.
               */
              const SizedBox(height: 8),
              const Text(
                'ما تكتبه يُحفظ في ملف نشاطك فورًا ولن نسألك عنه مرة أخرى. '
                'تقريرك الحالي يبقى كما صدر، والتشخيص القادم يبدأ من هنا.',
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('احفظ ما كتبته'),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _field(ReportGap gap) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              gap.label,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (gap.help != null && gap.help!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(gap.help!),
            ],
            if (gap.why != null && gap.why!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text('لماذا يهم: ${gap.why!}'),
            ],
            const SizedBox(height: 10),
            if (gap.type == 'select' && gap.options.isNotEmpty)
              DropdownButtonFormField<String>(
                initialValue: _choice[gap.key],
                // بلا التوسّع تأخذ القائمة عرض أطول خيار فيها، وخيارات الملف
                // جُمل عربية كاملة («يحقق مبيعات ويستعد للتوسع») — فتفيض عن
                // شاشة الجوال بدل أن تُقصّ داخلها.
                isExpanded: true,
                items: [
                  for (final option in gap.options)
                    DropdownMenuItem(
                      value: option.value,
                      child: Text(option.label),
                    ),
                ],
                onChanged: (value) =>
                    setState(() => _choice[gap.key] = value ?? ''),
                decoration: const InputDecoration(hintText: 'اختر…'),
              )
            else
              TextField(
                controller: _controllerFor(gap.key),
                maxLines: gap.type == 'textarea' ? 3 : 1,
                keyboardType: gap.type == 'number'
                    ? TextInputType.number
                    : TextInputType.text,
              ),
          ],
        ),
      ),
    );
  }
}
