/// موديلات typed لملف المشروع (brief + assessment) وتقارير المشروع
/// (التقرير الشامل + دليل المشروع). تعكس مخرجات:
///   - ProjectBriefController::show/update  (brief + assessment)
///   - ProjectReportController::report      (BuildProjectReportAction)
///   - ProjectReportController::dossier     (ProjectDossierBuilder)
///
/// التقرير والدليل يُعرضان بمحرّك عام يسطّح JSON؛ لذا يحتفظ كل منهما بالخريطة
/// الخام (`raw`) لتغذية العرض دون تغيير سلوكه، مع حقول typed للاستهلاك المباشر.
library;

// ─────────────────────────── أدوات تحويل مشتركة ───────────────────────────

String _str(dynamic v) => v?.toString() ?? '';

String? _strN(dynamic v) => v?.toString();

int _int(dynamic v) =>
    v is num ? v.toInt() : (v is String ? (int.tryParse(v) ?? 0) : 0);

int? _intN(dynamic v) =>
    v is num ? v.toInt() : (v is String ? int.tryParse(v) : null);

bool _bool(dynamic v) => v == true || v == 'true' || v == 1;

List<String> _listStr(dynamic v) => v is List
    ? v.map((e) => e.toString()).where((s) => s.isNotEmpty).toList()
    : const <String>[];

List<Map<String, dynamic>> _listMap(dynamic v) => v is List
    ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const <Map<String, dynamic>>[];

Map<String, dynamic>? _mapN(dynamic v) =>
    v is Map ? Map<String, dynamic>.from(v) : null;

/// وثيقة قابلة للعرض في محرّك التسطيح العام (`_DocumentView`).
abstract class RenderableDocument {
  /// الخريطة الخام كما وردت من الـ API — مصدر العرض العام.
  Map<String, dynamic> get raw;
}

// ══════════════════════════ ملف المشروع (brief) ══════════════════════════

/// قسم «نشاطك وعرضك».
class BriefBusiness {
  const BriefBusiness({this.summary = '', this.offer = '', this.market = ''});

  final String summary;
  final String offer;
  final String market;

  factory BriefBusiness.fromJson(Map<String, dynamic> j) => BriefBusiness(
        summary: _str(j['summary']),
        offer: _str(j['offer']),
        market: _str(j['market']),
      );

  String? value(String field) => switch (field) {
        'summary' => summary,
        'offer' => offer,
        'market' => market,
        _ => null,
      };
}

/// قسم «جمهورك».
class BriefAudience {
  const BriefAudience({
    this.idealCustomer = '',
    this.painPoints = '',
    this.buyingTrigger = '',
  });

  final String idealCustomer;
  final String painPoints;
  final String buyingTrigger;

  factory BriefAudience.fromJson(Map<String, dynamic> j) => BriefAudience(
        idealCustomer: _str(j['ideal_customer']),
        painPoints: _str(j['pain_points']),
        buyingTrigger: _str(j['buying_trigger']),
      );

  String? value(String field) => switch (field) {
        'ideal_customer' => idealCustomer,
        'pain_points' => painPoints,
        'buying_trigger' => buyingTrigger,
        _ => null,
      };
}

/// قسم «أهدافك».
class BriefGoals {
  const BriefGoals({
    this.primaryGoal = '',
    this.successMetric = '',
    this.timeframe = '',
  });

  final String primaryGoal;
  final String successMetric;
  final String timeframe;

  factory BriefGoals.fromJson(Map<String, dynamic> j) => BriefGoals(
        primaryGoal: _str(j['primary_goal']),
        successMetric: _str(j['success_metric']),
        timeframe: _str(j['timeframe']),
      );

  String? value(String field) => switch (field) {
        'primary_goal' => primaryGoal,
        'success_metric' => successMetric,
        'timeframe' => timeframe,
        _ => null,
      };
}

/// قسم «تسويقك الحالي».
class BriefCurrentMarketing {
  const BriefCurrentMarketing({
    this.channels = '',
    this.currentState = '',
    this.assets = '',
  });

  final String channels;
  final String currentState;
  final String assets;

  factory BriefCurrentMarketing.fromJson(Map<String, dynamic> j) =>
      BriefCurrentMarketing(
        channels: _str(j['channels']),
        currentState: _str(j['current_state']),
        assets: _str(j['assets']),
      );

  String? value(String field) => switch (field) {
        'channels' => channels,
        'current_state' => currentState,
        'assets' => assets,
        _ => null,
      };
}

/// قسم «هويتك».
class BriefBrand {
  const BriefBrand({this.voice = '', this.toneRules = ''});

  final String voice;
  final String toneRules;

  factory BriefBrand.fromJson(Map<String, dynamic> j) => BriefBrand(
        voice: _str(j['voice']),
        toneRules: _str(j['tone_rules']),
      );

  String? value(String field) => switch (field) {
        'voice' => voice,
        'tone_rules' => toneRules,
        _ => null,
      };
}

/// قسم «تموضعك».
class BriefPositioning {
  const BriefPositioning({this.edge = '', this.promise = ''});

  final String edge;
  final String promise;

  factory BriefPositioning.fromJson(Map<String, dynamic> j) => BriefPositioning(
        edge: _str(j['edge']),
        promise: _str(j['promise']),
      );

  String? value(String field) => switch (field) {
        'edge' => edge,
        'promise' => promise,
        _ => null,
      };
}

/// قسم «منافسوك».
class BriefCompetition {
  const BriefCompetition({this.competitors = '', this.gap = ''});

  final String competitors;
  final String gap;

  factory BriefCompetition.fromJson(Map<String, dynamic> j) => BriefCompetition(
        competitors: _str(j['competitors']),
        gap: _str(j['gap']),
      );

  String? value(String field) => switch (field) {
        'competitors' => competitors,
        'gap' => gap,
        _ => null,
      };
}

/// قسم «التنفيذ».
class BriefExecution {
  const BriefExecution({
    this.priority = '',
    this.nextAsset = '',
    this.deliveryNotes = '',
  });

  final String priority;
  final String nextAsset;
  final String deliveryNotes;

  factory BriefExecution.fromJson(Map<String, dynamic> j) => BriefExecution(
        priority: _str(j['priority']),
        nextAsset: _str(j['next_asset']),
        deliveryNotes: _str(j['delivery_notes']),
      );

  String? value(String field) => switch (field) {
        'priority' => priority,
        'next_asset' => nextAsset,
        'delivery_notes' => deliveryNotes,
        _ => null,
      };
}

/// قسم «الجانب التجاري».
class BriefCommercial {
  const BriefCommercial({this.budgetRange = '', this.decisionMaker = ''});

  final String budgetRange;
  final String decisionMaker;

  factory BriefCommercial.fromJson(Map<String, dynamic> j) => BriefCommercial(
        budgetRange: _str(j['budget_range']),
        decisionMaker: _str(j['decision_maker']),
      );

  String? value(String field) => switch (field) {
        'budget_range' => budgetRange,
        'decision_maker' => decisionMaker,
        _ => null,
      };
}

/// ملف المشروع التسويقي كاملاً — أقسام typed، تعكس ProjectMarketingBriefStore::normalize.
class BriefData {
  const BriefData({
    this.business = const BriefBusiness(),
    this.audience = const BriefAudience(),
    this.goals = const BriefGoals(),
    this.currentMarketing = const BriefCurrentMarketing(),
    this.brand = const BriefBrand(),
    this.positioning = const BriefPositioning(),
    this.competition = const BriefCompetition(),
    this.execution = const BriefExecution(),
    this.commercial = const BriefCommercial(),
  });

  final BriefBusiness business;
  final BriefAudience audience;
  final BriefGoals goals;
  final BriefCurrentMarketing currentMarketing;
  final BriefBrand brand;
  final BriefPositioning positioning;
  final BriefCompetition competition;
  final BriefExecution execution;
  final BriefCommercial commercial;

  factory BriefData.fromJson(Map<String, dynamic> j) => BriefData(
        business: BriefBusiness.fromJson(_mapN(j['business']) ?? const {}),
        audience: BriefAudience.fromJson(_mapN(j['audience']) ?? const {}),
        goals: BriefGoals.fromJson(_mapN(j['goals']) ?? const {}),
        currentMarketing: BriefCurrentMarketing.fromJson(
            _mapN(j['current_marketing']) ?? const {}),
        brand: BriefBrand.fromJson(_mapN(j['brand']) ?? const {}),
        positioning:
            BriefPositioning.fromJson(_mapN(j['positioning']) ?? const {}),
        competition:
            BriefCompetition.fromJson(_mapN(j['competition']) ?? const {}),
        execution: BriefExecution.fromJson(_mapN(j['execution']) ?? const {}),
        commercial: BriefCommercial.fromJson(_mapN(j['commercial']) ?? const {}),
      );

  /// يقرأ قيمة حقل بصيغة `group.field` (مثل `business.summary`) — يعيد ''
  /// للمسار المجهول. يُستخدم لتعبئة حقول الواجهة بمفاتيحها الموحّدة.
  String value(String path) {
    final parts = path.split('.');
    if (parts.length != 2) return '';
    final field = parts[1];
    final section = switch (parts[0]) {
      'business' => business.value(field),
      'audience' => audience.value(field),
      'goals' => goals.value(field),
      'current_marketing' => currentMarketing.value(field),
      'brand' => brand.value(field),
      'positioning' => positioning.value(field),
      'competition' => competition.value(field),
      'execution' => execution.value(field),
      'commercial' => commercial.value(field),
      _ => null,
    };
    return section ?? '';
  }
}

/// تقارير التقييم المشتقّة داخل الـ assessment (قوائم نصية).
class BriefAssessmentReports {
  const BriefAssessmentReports({
    this.executiveBrief = const [],
    this.audienceSnapshot = const [],
    this.offerPositioning = const [],
    this.channelDirection = const [],
    this.decisionSummary = const [],
  });

  final List<String> executiveBrief;
  final List<String> audienceSnapshot;
  final List<String> offerPositioning;
  final List<String> channelDirection;
  final List<String> decisionSummary;

  factory BriefAssessmentReports.fromJson(Map<String, dynamic> j) =>
      BriefAssessmentReports(
        executiveBrief: _listStr(j['executive_brief']),
        audienceSnapshot: _listStr(j['audience_snapshot']),
        offerPositioning: _listStr(j['offer_positioning']),
        channelDirection: _listStr(j['channel_direction']),
        decisionSummary: _listStr(j['decision_summary']),
      );
}

/// تقييم اكتمال الملف — يعكس ProjectMarketingBriefStore::assess.
class BriefAssessment {
  const BriefAssessment({
    this.completenessScore = 0,
    this.knownFields = 0,
    this.totalFields = 0,
    this.missingFields = const [],
    this.missingLabels = const [],
    this.reports = const BriefAssessmentReports(),
    this.nextActions = const [],
    this.score,
    this.verdict,
    this.summary,
  });

  final int completenessScore;
  final int knownFields;
  final int totalFields;
  final List<String> missingFields;
  final List<String> missingLabels;
  final BriefAssessmentReports reports;
  final List<String> nextActions;

  /// حقول تستهلكها بطاقة الملخص في الواجهة (قد تغيب عن الاستجابة الحالية).
  final num? score;
  final String? verdict;
  final String? summary;

  factory BriefAssessment.fromJson(Map<String, dynamic> j) => BriefAssessment(
        completenessScore: _int(j['completeness_score']),
        knownFields: _int(j['known_fields']),
        totalFields: _int(j['total_fields']),
        missingFields: _listStr(j['missing_fields']),
        missingLabels: _listStr(j['missing_labels']),
        reports:
            BriefAssessmentReports.fromJson(_mapN(j['reports']) ?? const {}),
        nextActions: _listStr(j['next_actions']),
        score: j['score'] is num ? j['score'] as num : null,
        verdict: _strN(j['verdict']),
        summary: _strN(j['summary']),
      );
}

// ══════════════════════════ التقرير الشامل ══════════════════════════

/// عنصر أداة داخل مرحلة في التقرير.
class ReportStageItem {
  const ReportStageItem({
    this.tool,
    this.toolName,
    this.headline,
    this.points = const [],
    this.score = 0,
  });

  final String? tool;
  final String? toolName;
  final String? headline;
  final List<String> points;
  final int score;

  factory ReportStageItem.fromJson(Map<String, dynamic> j) => ReportStageItem(
        tool: _strN(j['tool']),
        toolName: _strN(j['tool_name']),
        headline: _strN(j['headline']),
        points: _listStr(j['points']),
        score: _int(j['score']),
      );
}

/// مرحلة في التقرير الشامل.
class ReportStage {
  const ReportStage({
    this.label,
    this.items = const [],
    this.missing = const [],
  });

  final String? label;
  final List<ReportStageItem> items;
  final List<String> missing;

  factory ReportStage.fromJson(Map<String, dynamic> j) => ReportStage(
        label: _strN(j['label']),
        items:
            _listMap(j['items']).map(ReportStageItem.fromJson).toList(),
        missing: _listStr(j['missing']),
      );
}

/// لقطة التدقيق الذكي داخل التقرير.
class ReportAudit {
  const ReportAudit({
    this.executiveScore,
    this.siteUnreachable = false,
    this.topProblems = const [],
    this.quickWins7 = const [],
    this.improvements30 = const [],
    this.strategic90 = const [],
    this.completedAt,
  });

  final int? executiveScore;
  final bool siteUnreachable;
  final List<String> topProblems;
  final List<String> quickWins7;
  final List<String> improvements30;
  final List<String> strategic90;
  final String? completedAt;

  factory ReportAudit.fromJson(Map<String, dynamic> j) => ReportAudit(
        executiveScore: _intN(j['executive_score']),
        siteUnreachable: _bool(j['site_unreachable']),
        topProblems: _listStr(j['top_problems']),
        quickWins7: _listStr(j['quick_wins_7']),
        improvements30: _listStr(j['improvements_30']),
        strategic90: _listStr(j['strategic_90']),
        completedAt: _strN(j['completed_at']),
      );
}

/// خطة زمنية موحّدة (7/30/90).
class ReportPlan {
  const ReportPlan({
    this.quickWins7 = const [],
    this.improvements30 = const [],
    this.strategic90 = const [],
  });

  final List<String> quickWins7;
  final List<String> improvements30;
  final List<String> strategic90;

  factory ReportPlan.fromJson(Map<String, dynamic> j) => ReportPlan(
        quickWins7: _listStr(j['quick_wins_7']),
        improvements30: _listStr(j['improvements_30']),
        strategic90: _listStr(j['strategic_90']),
      );
}

/// التقرير الشامل للمشروع — يعكس BuildProjectReportAction::handle.
class ProjectReport implements RenderableDocument {
  const ProjectReport({
    required this.raw,
    this.project,
    this.client,
    this.completion = 0,
    this.avgQuality = 0,
    this.contentQuality,
    this.toolsCompleted = 0,
    this.stages = const [],
    this.gaps = const [],
    this.audit,
    this.diagnosis,
    this.domainPlans,
    this.executiveSummary,
    this.priorities = const [],
    this.plan = const ReportPlan(),
    this.synthesisSource,
  });

  @override
  final Map<String, dynamic> raw;

  final String? project;
  final String? client;
  final int completion;
  final int avgQuality;
  final int? contentQuality;
  final int toolsCompleted;
  final List<ReportStage> stages;
  final List<String> gaps;
  final ReportAudit? audit;

  /// التشخيص الاستراتيجي وخطط المجالات بنيتهما متغيّرة — تُحفظ كخرائط خام.
  final Map<String, dynamic>? diagnosis;
  final Map<String, dynamic>? domainPlans;

  final String? executiveSummary;
  final List<String> priorities;
  final ReportPlan plan;
  final String? synthesisSource;

  factory ProjectReport.fromJson(Map<String, dynamic> j) => ProjectReport(
        raw: Map<String, dynamic>.from(j),
        project: _strN(j['project']),
        client: _strN(j['client']),
        completion: _int(j['completion']),
        avgQuality: _int(j['avg_quality']),
        contentQuality: _intN(j['content_quality']),
        toolsCompleted: _int(j['tools_completed']),
        stages: _listMap(j['stages']).map(ReportStage.fromJson).toList(),
        gaps: _listStr(j['gaps']),
        audit: j['audit'] is Map
            ? ReportAudit.fromJson(Map<String, dynamic>.from(j['audit'] as Map))
            : null,
        diagnosis: _mapN(j['diagnosis']),
        domainPlans: _mapN(j['domain_plans']),
        executiveSummary: _strN(j['executive_summary']),
        priorities: _listStr(j['priorities']),
        plan: ReportPlan.fromJson(_mapN(j['plan']) ?? const {}),
        synthesisSource: _strN(j['synthesis_source']),
      );
}

// ══════════════════════════ دليل المشروع (dossier) ══════════════════════════

/// إجابة خام واحدة (سؤال ← جواب) داخل أداة في الدليل.
class DossierAnswer {
  const DossierAnswer({this.label = '', this.value = ''});

  final String label;
  final String value;

  factory DossierAnswer.fromJson(Map<String, dynamic> j) => DossierAnswer(
        label: _str(j['label']),
        value: _str(j['value']),
      );
}

/// أداة منجَزة داخل مرحلة في الدليل.
class DossierTool {
  const DossierTool({
    this.code,
    this.name,
    this.completeness = 0,
    this.answeredAt,
    this.headline,
    this.bullets = const [],
    this.answers = const [],
  });

  final String? code;
  final String? name;
  final int completeness;
  final String? answeredAt;
  final String? headline;
  final List<String> bullets;
  final List<DossierAnswer> answers;

  factory DossierTool.fromJson(Map<String, dynamic> j) => DossierTool(
        code: _strN(j['code']),
        name: _strN(j['name']),
        completeness: _int(j['completeness']),
        answeredAt: _strN(j['answered_at']),
        headline: _strN(j['headline']),
        bullets: _listStr(j['bullets']),
        answers: _listMap(j['answers']).map(DossierAnswer.fromJson).toList(),
      );
}

/// مرحلة في دليل المشروع.
class DossierStage {
  const DossierStage({
    this.num,
    this.label,
    this.description,
    this.completion = 0,
    this.tools = const [],
    this.missing = const [],
  });

  final int? num;
  final String? label;
  final String? description;
  final int completion;
  final List<DossierTool> tools;
  final List<String> missing;

  factory DossierStage.fromJson(Map<String, dynamic> j) => DossierStage(
        num: _intN(j['num']),
        label: _strN(j['label']),
        description: _strN(j['description']),
        completion: _int(j['completion']),
        tools: _listMap(j['tools']).map(DossierTool.fromJson).toList(),
        missing: _listStr(j['missing']),
      );
}

/// بيانات المشروع العامة في الدليل.
class DossierMeta {
  const DossierMeta({
    this.name,
    this.client,
    this.sector,
    this.marketCountry,
    this.primaryDomain,
    this.stageLabel,
    this.toolsCompleted = 0,
    this.completion = 0,
  });

  final String? name;
  final String? client;
  final String? sector;
  final String? marketCountry;
  final String? primaryDomain;
  final String? stageLabel;
  final int toolsCompleted;
  final int completion;

  factory DossierMeta.fromJson(Map<String, dynamic> j) => DossierMeta(
        name: _strN(j['name']),
        client: _strN(j['client']),
        sector: _strN(j['sector']),
        marketCountry: _strN(j['market_country']),
        primaryDomain: _strN(j['primary_domain']),
        stageLabel: _strN(j['stage_label']),
        toolsCompleted: _int(j['tools_completed']),
        completion: _int(j['completion']),
      );
}

/// دليل المشروع كاملاً — يعكس ProjectDossierBuilder::build.
class ProjectDossier implements RenderableDocument {
  const ProjectDossier({
    required this.raw,
    this.meta,
    this.stages = const [],
    this.markdown,
    this.hasAnswers = false,
  });

  @override
  final Map<String, dynamic> raw;

  final DossierMeta? meta;
  final List<DossierStage> stages;
  final String? markdown;
  final bool hasAnswers;

  factory ProjectDossier.fromJson(Map<String, dynamic> j) => ProjectDossier(
        raw: Map<String, dynamic>.from(j),
        meta: j['meta'] is Map
            ? DossierMeta.fromJson(Map<String, dynamic>.from(j['meta'] as Map))
            : null,
        stages: _listMap(j['stages']).map(DossierStage.fromJson).toList(),
        markdown: _strN(j['markdown']),
        hasAnswers: _bool(j['has_answers']),
      );
}
