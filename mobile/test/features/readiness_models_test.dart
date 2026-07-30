import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/features/readiness/models.dart';

/// الحمولة منسوخة من `Api\V1\ReadinessController::show`.
///
/// العطل الذي يحرسه هذا الملف: الخادم كان يرسل المعيار القطاعي والسلسلة
/// الزمنية والتعارضات، والتطبيق يقرأ الحمولة خريطةً خامًا فلا يعرض منها
/// شيئًا. الخريطة الخام لا تشتكي من مفتاح لم يُقرأ، فبقيت الفجوة صامتة حتى
/// وإن كان الويب يعرض الثلاثة (§١٥ بند ٨).
void main() {
  const payload = <String, dynamic>{
    'project': {'slug': 'matjar', 'name': 'متجر'},
    'maturity': {
      'maturity_score': 62,
      'axes_active': 2,
      'axes_total': 8,
      'axes': [
        {
          'label': 'الجاهزية للذكاء الاصطناعي',
          'axis_score': 71,
          'axis_coverage': 1.0,
          'active': true,
          'is_assumption': false,
        },
      ],
    },
    'fixes': [
      {'title': 'أضف Schema للمنتجات', 'fix': 'ألصق الوسم', 'effort_label': 'جهد منخفض'},
    ],
    'history': {
      'points': [
        {
          'maturity_score': 48,
          'score_coverage': 0.5,
          'axes_active': 1,
          'evidence_level': 'measured',
          'occurred_at': '2026-07-01T03:00:00+00:00',
        },
        {
          'maturity_score': 62,
          'score_coverage': 1.0,
          'axes_active': 2,
          'evidence_level': 'measured',
          'occurred_at': '2026-07-22T03:00:00+00:00',
        },
      ],
      'plottable': false,
    },
    'benchmark': {
      'available': true,
      'industry': 'التجزئة',
      'sample_size': 7,
      'industry_average': 55,
      'maturity_score': 62,
      'delta': 7,
      'percentile': 71,
    },
    'conflicts': [
      {
        'key': 'monthly_traffic',
        'occurred_at': '2026-07-28T09:00:00+00:00',
        'revisions': 3,
        'sides': [
          {
            'source': 'Intake',
            'value': 5000,
            'evidence_level': 'inferred',
            'observed_at': '2026-07-20T09:00:00+00:00',
          },
          {
            'source': 'AiReadiness',
            'value': 900,
            'evidence_level': 'measured',
            'observed_at': '2026-07-28T09:00:00+00:00',
          },
        ],
      },
    ],
    'impact': [
      {
        'signal': 'maturity_score',
        'intervention': 'حدّثت: geography',
        'intervention_at': '2026-06-20T00:00:00+00:00',
        'signal_before': 50.0,
        'signal_after': 64.0,
        'signal_delta': 14.0,
        'delta_evidence': 'derived',
        'attribution_evidence': 'inferred',
        'attribution_note': 'تزامنٌ زمنيّ لا سبب مثبت: تحرّكت الإشارة بعد إصلاحك، وقد يكون لسبب آخر.',
      },
    ],
  };

  test('العقد يصل بأقسامه الخمسة لا بثلاثة', () {
    final overview = ReadinessOverview.fromJson(payload);

    expect(overview.maturity['maturity_score'], 62);
    expect(overview.fixes, hasLength(1));
    expect(overview.history, hasLength(2));
    expect(overview.benchmark.available, isTrue);
    expect(overview.conflicts, hasLength(1));
    expect(overview.impact, hasLength(1));
  });

  test('بطاقة الأثر تحمل الحركة وملاحظة أنها فرضية لا سبب', () {
    final card = ReadinessOverview.fromJson(payload).impact.single;

    expect(card.intervention, 'حدّثت: geography');
    expect(card.signalDelta, 14.0);
    // النسبة إلى الإصلاح تصل كملاحظة لا كجزم (§٤.١).
    expect(card.attributionNote, contains('لا سبب مثبت'));
  });

  test('التعارض يحمل قولَي المصدرين لا وجودَه وحده', () {
    final conflict = ReadinessOverview.fromJson(payload).conflicts.single;

    expect(conflict.key, 'monthly_traffic');
    expect(conflict.revisions, 3);
    expect(
      conflict.sides.map((side) => side.source),
      containsAll(<String>['Intake', 'AiReadiness']),
    );
    expect(
      conflict.sides.map((side) => side.value),
      containsAll(<String>['5000', '900']),
    );
  });

  test('القائمة تُوصل بفاصلة عربية والفراغ يُقال فراغًا', () {
    final sides = ConflictSide.fromJson(const {
      'source': 'Intake',
      'value': ['الرياض', 'جدة'],
    });

    expect(sides.value, 'الرياض، جدة');
    expect(ConflictSide.fromJson(const {'source': 'X'}).value, '—');
  });

  test('plottable تأتي من الخادم ولا تُحسب في التطبيق', () {
    // نقطتان فقط: الخادم يقول لا تُرسم، والتطبيق لا يعيد تقدير العتبة.
    expect(ReadinessOverview.fromJson(payload).plottable, isFalse);
  });

  test('غياب المقارنة يصل بسببه لا فارغًا', () {
    final benchmark = IndustryBenchmarkView.fromJson(const {
      'available': false,
      'sample_size': 0,
      'reason': 'لم يُقَس في قطاعك عدد كافٍ من الأنشطة بعد.',
    });

    expect(benchmark.available, isFalse);
    expect(benchmark.reason, isNotEmpty);
    expect(benchmark.delta, isNull);
  });

  test('الدرجة غير المقيسة لا تُقارن: لا فرق ولا مئوي', () {
    final benchmark = IndustryBenchmarkView.fromJson(const {
      'available': true,
      'industry': 'التجزئة',
      'sample_size': 7,
      'industry_average': 55,
      'maturity_score': null,
      'delta': null,
      'percentile': null,
    });

    expect(benchmark.delta, isNull);
    expect(benchmark.percentile, isNull);
  });
}
