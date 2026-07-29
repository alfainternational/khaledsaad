/// نماذج عقد التشخيص — نظير `Api\V1\ReadinessController::show` حرفيًّا.
///
/// وُجدت لأن الشاشة كانت تقرأ الحمولة خرائطَ خامًا، فمرّت ثلاثة أقسام يرسلها
/// الخادم بلا أن يعرضها أحد: المعيار القطاعي والسلسلة الزمنية والتعارضات.
/// الخريطة الخام لا تشتكي من مفتاح لم يُقرأ — والنموذج يشتكي، ويحرسه اختبار
/// بحمولة منسوخة من العارض (§١٥ بند ٨).
library;

class ReadinessOverview {
  const ReadinessOverview({
    required this.maturity,
    required this.fixes,
    required this.history,
    required this.plottable,
    required this.benchmark,
    required this.conflicts,
  });

  factory ReadinessOverview.fromJson(Map<String, dynamic> json) {
    final history = json['history'];

    return ReadinessOverview(
      maturity: _map(json['maturity']),
      fixes: _list(json['fixes']),
      history: _list(history is Map ? history['points'] : null)
          .map(ScorePoint.fromJson)
          .toList(growable: false),
      plottable: history is Map && history['plottable'] == true,
      benchmark: IndustryBenchmarkView.fromJson(_map(json['benchmark'])),
      conflicts: _list(
        json['conflicts'],
      ).map(BrainConflict.fromJson).toList(growable: false),
    );
  }

  /// درجة النضج والمحاور. تبقى خريطة: الشاشة تعرضها اليوم كما هي، وتنميطها
  /// تغييرٌ مستقل لا يخصّ الفجوة التي فُتح هذا الملف لسدّها.
  final Map<String, dynamic> maturity;

  final List<Map<String, dynamic>> fixes;

  final List<ScorePoint> history;

  /// هل يجوز رسم الاتجاه؟ الخادم يحسمها بأربع نقاط (§١٣)، ولا تُعاد هنا:
  /// عتبةٌ محسوبة في مكانين تتباعد.
  final bool plottable;

  final IndustryBenchmarkView benchmark;

  final List<BrainConflict> conflicts;
}

/// نقطة واحدة في السلسلة الزمنية لدرجة النضج.
class ScorePoint {
  const ScorePoint({
    required this.maturityScore,
    required this.scoreCoverage,
    required this.axesActive,
    required this.occurredAt,
  });

  factory ScorePoint.fromJson(Map<String, dynamic> json) => ScorePoint(
    maturityScore: (json['maturity_score'] as num?)?.toInt() ?? 0,
    scoreCoverage: (json['score_coverage'] as num?)?.toDouble() ?? 0,
    axesActive: (json['axes_active'] as num?)?.toInt() ?? 0,
    occurredAt: DateTime.tryParse(json['occurred_at']?.toString() ?? ''),
  );

  final int maturityScore;
  final double scoreCoverage;
  final int axesActive;
  final DateTime? occurredAt;
}

/// موقع النشاط من متوسط قطاعه، أو سبب غياب المقارنة.
///
/// الغياب حالةٌ من حالات العرض لا فراغ: «لم يُقَس في قطاعك عدد كافٍ» معلومة،
/// و«—» ليست (§٤.٣).
class IndustryBenchmarkView {
  const IndustryBenchmarkView({
    required this.available,
    required this.sampleSize,
    this.industry,
    this.industryAverage,
    this.delta,
    this.percentile,
    this.reason,
  });

  factory IndustryBenchmarkView.fromJson(Map<String, dynamic> json) =>
      IndustryBenchmarkView(
        available: json['available'] == true,
        sampleSize: (json['sample_size'] as num?)?.toInt() ?? 0,
        industry: json['industry']?.toString(),
        industryAverage: (json['industry_average'] as num?)?.toInt(),
        delta: (json['delta'] as num?)?.toInt(),
        percentile: (json['percentile'] as num?)?.toInt(),
        reason: json['reason']?.toString(),
      );

  final bool available;
  final int sampleSize;
  final String? industry;
  final int? industryAverage;

  /// فرق درجتك عن المتوسط، أو null إن لم تُقَس درجتك بعد — المقارنة طرفان.
  final int? delta;
  final int? percentile;
  final String? reason;
}

/// تعارض مفتوح: مصدران قالا شيئين مختلفين عن المعلومة نفسها (§٩).
class BrainConflict {
  const BrainConflict({
    required this.key,
    required this.sides,
    required this.revisions,
    this.occurredAt,
  });

  factory BrainConflict.fromJson(Map<String, dynamic> json) => BrainConflict(
    key: json['key']?.toString() ?? '',
    sides: _list(json['sides']).map(ConflictSide.fromJson).toList(growable: false),
    revisions: (json['revisions'] as num?)?.toInt() ?? 0,
    occurredAt: DateTime.tryParse(json['occurred_at']?.toString() ?? ''),
  );

  final String key;
  final List<ConflictSide> sides;

  /// كم مرة تغيّرت هذه المعلومة قبل التعارض: قيمة استقرّت شهورًا ثم خالفها
  /// قياسٌ واحد ليست كقيمة تتأرجح كل أسبوع.
  final int revisions;
  final DateTime? occurredAt;
}

class ConflictSide {
  const ConflictSide({
    required this.source,
    required this.value,
    this.evidenceLevel,
    this.observedAt,
  });

  factory ConflictSide.fromJson(Map<String, dynamic> json) => ConflictSide(
    source: json['source']?.toString() ?? 'غير معروف',
    value: _readable(json['value']),
    evidenceLevel: json['evidence_level']?.toString(),
    observedAt: DateTime.tryParse(json['observed_at']?.toString() ?? ''),
  );

  final String source;

  /// القيمة كما تُعرض. القائمة تُوصل بفاصلة عربية لأن `[a, b]` ليست عربية.
  final String value;
  final String? evidenceLevel;
  final DateTime? observedAt;
}

String _readable(dynamic value) {
  if (value == null) return '—';
  if (value is List) return value.join('، ');
  if (value is bool) return value ? 'نعم' : 'لا';

  return value.toString();
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _list(dynamic value) => value is List
    ? value
          .whereType<Map>()
          .map(Map<String, dynamic>.from)
          .toList(growable: false)
    : const <Map<String, dynamic>>[];
