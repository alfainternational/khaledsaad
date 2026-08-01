import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';
import 'common.dart';

/// المثال التطبيقي: النص الجاهز الذي ينسخه صاحب النشاط ويستعمله كما هو.
///
/// نظير `resources/views/components/worked-example.blade.php` حرفيًّا — نفس
/// الحقول ونفس الوسوم ونفس التصريح بالمصدر. الاختلاف بين السطحين هنا ليس
/// تفصيلًا تجميليًّا: من قرأ مثالًا على الجوال ولم يجده على الويب يظنّ أنه
/// فقد شيئًا.
class WorkedExampleModel {
  const WorkedExampleModel({
    required this.title,
    required this.body,
    required this.kindLabel,
    this.notes = const [],
    this.source,
  });

  /// يُرجع null بدل الرمي: مثال معطوب لا يُسقط توصية سليمة.
  static WorkedExampleModel? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;

    final body = (json['body'] as String? ?? '').trim();
    if (body.isEmpty) return null;

    return WorkedExampleModel(
      title: json['title'] as String? ?? 'مثال تطبيقي',
      body: body,
      kindLabel: json['kind_label'] as String? ?? 'مثال جاهز',
      notes: (json['notes'] as List? ?? const [])
          .map((e) => e.toString())
          .where((e) => e.trim().isNotEmpty)
          .toList(),
      source: json['source'] as String?,
    );
  }

  final String title;
  final String body;
  final String kindLabel;
  final List<String> notes;
  final String? source;
}

class WorkedExampleCard extends StatelessWidget {
  const WorkedExampleCard({
    super.key,
    required this.example,
    this.initiallyExpanded = false,
    this.inferred = true,
    this.ltr = false,
  });

  final WorkedExampleModel example;
  final bool initiallyExpanded;

  /// هل المتن اجتهاد منهجي؟ افتراضه نعم لأن مثال التوصية كذلك مهما كان مصدره.
  ///
  /// يُطفأ للقصاصة التقنية: JSON-LD معيار ثابت لا ادعاء عن النشاط، ووسمه
  /// «فرضية» يُفقد الوسم معناه حين يظهر على ما ليس بفرضية (§٤.١).
  final bool inferred;

  /// هل يُقرأ المتن من اليسار؟
  ///
  /// عرض الكود بـRTL يقلب الأقواس فيصير غير صالح للصق، وهو كل الغرض منه.
  final bool ltr;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      decoration: BoxDecoration(
        color: BrandColors.surfaceSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: BrandColors.line),
      ),
      child: Theme(
        // فاصل ExpansionTile الافتراضي يقطع البطاقة بخطّ لا معنى له هنا.
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          initiallyExpanded: initiallyExpanded,
          tilePadding: const EdgeInsets.symmetric(horizontal: 12),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          title: Wrap(
            spacing: 8,
            runSpacing: 4,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                example.kindLabel,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: BrandColors.navy,
                ),
              ),
              if (inferred)
                const SeverityBadge(label: 'فرضية', severity: 'assumption'),
            ],
          ),
          subtitle: Text(
            example.title,
            style: const TextStyle(color: BrandColors.muted, fontSize: 12),
          ),
          children: [
            const Text(
              'هذا نصّ جاهز للنسخ. املأ ما بين الأقواس المربعة ببياناتك قبل استعماله.',
              style: TextStyle(color: BrandColors.muted, fontSize: 12),
            ),
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: BrandColors.line),
              ),
              child: _body(),
            ),
            const SizedBox(height: 8),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: OutlinedButton.icon(
                onPressed: () => _copy(context),
                icon: const Icon(Icons.copy_all, size: 18),
                label: const Text('انسخ النص'),
              ),
            ),
            if (example.notes.isNotEmpty) ...[
              const SizedBox(height: 4),
              for (final note in example.notes)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    '• $note',
                    style: const TextStyle(
                      color: BrandColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ),
            ],
            // مصدر المثال يُعلَن: قالب مأمون ليس كصياغة على حالة النشاط.
            if (example.source == 'deterministic')
              const Padding(
                padding: EdgeInsets.only(top: 6),
                child: Text(
                  'مثال مبدئي من قوالب المنصة، لم يُصَغ على حالة نشاطك بعد. '
                  'حوّل التوصية إلى مهمة واطلب تطويرها للحصول على مثال مكتوب ببياناتك.',
                  style: TextStyle(color: BrandColors.muted, fontSize: 12),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// المتن نفسه: نظير `.worked-example__text` وصيغتها `--ltr` في الويب.
  ///
  /// اتجاه النصّ لا يكفي وحده — المحاذاة تتبع الاتجاه المحيط ما لم تُصرَّح،
  /// فيُلصق السطر الأول يمينًا ويبدو الكود مبعثرًا.
  Widget _body() {
    final text = SelectableText(
      example.body,
      textAlign: ltr ? TextAlign.left : TextAlign.start,
      style: TextStyle(
        fontSize: ltr ? 12 : 13,
        height: ltr ? 1.7 : 1.9,
        fontFamily: ltr ? 'monospace' : null,
      ),
    );

    if (!ltr) return text;

    return Directionality(textDirection: TextDirection.ltr, child: text);
  }

  Future<void> _copy(BuildContext context) async {
    await Clipboard.setData(ClipboardData(text: example.body));

    if (!context.mounted) return;

    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('نُسخ النص.')));
  }
}
