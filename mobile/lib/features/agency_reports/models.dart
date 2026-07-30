class AgencyMissingTool {
  const AgencyMissingTool({required this.key, required this.title});

  factory AgencyMissingTool.fromJson(Map<String, dynamic> json) =>
      AgencyMissingTool(
        key: json['key'] as String? ?? '',
        title: json['title'] as String? ?? '',
      );

  final String key;
  final String title;
}

class AgencyReportReadiness {
  const AgencyReportReadiness({
    required this.canGenerate,
    required this.requiredCount,
    required this.completedCount,
    required this.includedCount,
    required this.missingCore,
  });

  factory AgencyReportReadiness.fromJson(Map<String, dynamic> json) =>
      AgencyReportReadiness(
        canGenerate: json['can_generate'] as bool? ?? false,
        requiredCount: json['required_count'] as int? ?? 3,
        completedCount: json['completed_count'] as int? ?? 0,
        includedCount: json['included_count'] as int? ?? 0,
        missingCore: (json['missing_core'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) =>
                  AgencyMissingTool.fromJson(Map<String, dynamic>.from(item)),
            )
            .toList(),
      );

  final bool canGenerate;
  final int requiredCount;
  final int completedCount;
  final int includedCount;
  final List<AgencyMissingTool> missingCore;
}

class AgencyReportCard {
  const AgencyReportCard({
    required this.uuid,
    required this.title,
    required this.version,
    this.freshness = AgencyReportFreshness.fresh,
    this.generatedAt,
  });

  factory AgencyReportCard.fromJson(Map<String, dynamic> json) =>
      AgencyReportCard(
        uuid: json['uuid'] as String,
        title: json['title'] as String? ?? '',
        version: json['version'] as int? ?? 1,
        freshness: AgencyReportFreshness.fromJson(
          Map<String, dynamic>.from(json['freshness'] as Map? ?? const {}),
        ),
        generatedAt: json['generated_at'] as String?,
      );

  final String uuid;
  final String title;
  final int version;
  final AgencyReportFreshness freshness;
  final String? generatedAt;
}

class AgencyReportFreshness {
  const AgencyReportFreshness({
    required this.isStale,
    required this.state,
    required this.label,
    required this.reasons,
  });

  factory AgencyReportFreshness.fromJson(Map<String, dynamic> json) =>
      AgencyReportFreshness(
        isStale: json['is_stale'] == true,
        state: json['state'] as String? ?? 'fresh',
        label: json['label'] as String? ?? 'محدّث',
        reasons: (json['reasons'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
      );

  static const fresh = AgencyReportFreshness(
    isStale: false,
    state: 'fresh',
    label: 'محدّث',
    reasons: [],
  );

  final bool isStale;
  final String state;
  final String label;
  final List<String> reasons;
}

class AgencyReportCardWithShare extends AgencyReportCard {
  const AgencyReportCardWithShare({
    required super.uuid,
    required super.title,
    required super.version,
    required this.share,
    super.freshness,
    super.generatedAt,
  });

  factory AgencyReportCardWithShare.fromJson(Map<String, dynamic> json) =>
      AgencyReportCardWithShare(
        uuid: json['uuid'] as String,
        title: json['title'] as String? ?? '',
        version: json['version'] as int? ?? 1,
        freshness: AgencyReportFreshness.fromJson(
          Map<String, dynamic>.from(json['freshness'] as Map? ?? const {}),
        ),
        generatedAt: json['generated_at'] as String?,
        share: json['share'] == null
            ? AgencyShare.none
            : AgencyShare.fromJson(
                Map<String, dynamic>.from(json['share'] as Map),
              ),
      );

  final AgencyShare share;
}

class AgencyReportIndex {
  const AgencyReportIndex({required this.readiness, required this.reports});

  factory AgencyReportIndex.fromJson(Map<String, dynamic> json) =>
      AgencyReportIndex(
        readiness: AgencyReportReadiness.fromJson(
          Map<String, dynamic>.from(json['readiness'] as Map? ?? const {}),
        ),
        reports: (json['reports'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) =>
                  AgencyReportCard.fromJson(Map<String, dynamic>.from(item)),
            )
            .toList(),
      );

  final AgencyReportReadiness readiness;
  final List<AgencyReportCard> reports;
}

class AgencyPriority {
  const AgencyPriority({
    required this.title,
    required this.description,
    required this.sourceTool,
    this.impact,
    this.effort,
    this.evidence,
  });

  factory AgencyPriority.fromJson(Map<String, dynamic> json) => AgencyPriority(
    title: json['title'] as String? ?? '',
    description: json['description'] as String? ?? '',
    sourceTool: json['source_tool'] as String? ?? '',
    // التسمية العربية أولًا؛ القيمة الخام احتياط لنسخ قديمة من اللقطة.
    impact: json['impact_label'] as String? ?? json['impact'] as String?,
    effort: json['effort_label'] as String? ?? json['effort'] as String?,
    evidence: json['evidence'] as String?,
  );

  final String title;
  final String description;
  final String sourceTool;
  final String? impact;
  final String? effort;
  final String? evidence;
}

class AgencyToolSummary {
  const AgencyToolSummary({
    required this.title,
    required this.scoreBand,
    required this.summary,
    this.score,
    this.scoreNote,
    this.review,
    this.producedAt,
  });

  factory AgencyToolSummary.fromJson(Map<String, dynamic> json) =>
      AgencyToolSummary(
        title: json['title'] as String? ?? '',
        // أداة وصفية بلا درجة تظل مضمّنة؛ الصفر كان يعرضها كأنها رسبت.
        score: json['score'] as int?,
        scoreBand: json['score_band'] as String? ?? '',
        scoreNote: json['score_note'] as String?,
        review: json['review'] as String?,
        producedAt: json['produced_at'] as String?,
        summary: json['summary'] as String? ?? '',
      );

  final String title;
  final int? score;
  final String scoreBand;
  final String? scoreNote;
  final String? review;
  final String? producedAt;
  final String summary;

  String get scoreLabel => score == null
      ? (scoreNote ?? 'بلا درجة رقمية')
      : '$score/100 · $scoreBand';
}

class AgencyExecutiveItem {
  const AgencyExecutiveItem({
    required this.title,
    required this.description,
    required this.sourceTool,
    this.note,
  });

  factory AgencyExecutiveItem.fromJson(Map<String, dynamic> json) =>
      AgencyExecutiveItem(
        title: json['title'] as String? ?? '',
        description: json['description'] as String? ?? '',
        sourceTool: json['source_tool'] as String? ?? '',
        // التسمية العربية أولًا؛ القيمة الخام احتياط لنسخ قديمة من اللقطة.
        note:
            json['basis'] as String? ??
            [
              json['impact_label'] ?? json['impact'],
              json['effort_label'] ?? json['effort'],
            ].whereType<String>().join(' · ').trim(),
      );

  final String title;
  final String description;
  final String sourceTool;
  final String? note;
}

class AgencyExecutive {
  const AgencyExecutive({
    required this.position,
    required this.context,
    required this.readingNote,
    required this.coveragePercent,
    required this.problems,
    required this.opportunities,
  });

  factory AgencyExecutive.fromJson(Map<String, dynamic> json) {
    final coverage = Map<String, dynamic>.from(
      json['knowledge_coverage'] as Map? ?? const {},
    );

    return AgencyExecutive(
      position: json['position'] as String? ?? '',
      context: json['context'] as String? ?? '',
      readingNote: json['reading_note'] as String? ?? '',
      coveragePercent: coverage['percent'] as int? ?? 0,
      problems: (json['problems'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                AgencyExecutiveItem.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      opportunities: (json['opportunities'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                AgencyExecutiveItem.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
    );
  }

  final String position;
  final String context;
  final String readingNote;
  final int coveragePercent;
  final List<AgencyExecutiveItem> problems;
  final List<AgencyExecutiveItem> opportunities;
}

class AgencyLedgerEntry {
  const AgencyLedgerEntry({
    required this.label,
    required this.value,
    this.source,
    this.answeredAt,
  });

  factory AgencyLedgerEntry.fromJson(Map<String, dynamic> json) =>
      AgencyLedgerEntry(
        label: json['label'] as String? ?? '',
        value: json['value'] as String? ?? '',
        source: json['source'] as String?,
        answeredAt: json['answered_at'] as String?,
      );

  final String label;
  final String value;
  final String? source;
  final String? answeredAt;

  String get provenance =>
      [source ?? 'ملف المشروع', answeredAt].whereType<String>().join(' · ');
}

class AgencyLedgerTheme {
  const AgencyLedgerTheme({
    required this.title,
    required this.intent,
    required this.coveragePercent,
    required this.answered,
    required this.unanswered,
  });

  factory AgencyLedgerTheme.fromJson(Map<String, dynamic> json) =>
      AgencyLedgerTheme(
        title: json['title'] as String? ?? '',
        intent: json['intent'] as String? ?? '',
        coveragePercent: json['coverage_percent'] as int? ?? 0,
        answered: (json['answered'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) =>
                  AgencyLedgerEntry.fromJson(Map<String, dynamic>.from(item)),
            )
            .toList(),
        unanswered: (json['unanswered'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => (item['label'] ?? '').toString())
            .where((label) => label.isNotEmpty)
            .toList(),
      );

  final String title;
  final String intent;
  final int coveragePercent;
  final List<AgencyLedgerEntry> answered;
  final List<String> unanswered;
}

class AgencyKpiRow {
  const AgencyKpiRow({
    required this.name,
    this.unit,
    this.baseline,
    this.target,
    this.latest,
  });

  factory AgencyKpiRow.fromJson(Map<String, dynamic> json) => AgencyKpiRow(
    name: json['name'] as String? ?? '',
    unit: json['unit'] as String?,
    baseline: json['baseline']?.toString(),
    target: json['target']?.toString(),
    latest: json['latest']?.toString(),
  );

  final String name;
  final String? unit;
  final String? baseline;
  final String? target;
  final String? latest;
}

/// كتلة تخضع لمفتاح خصوصية: كاملة أو ملخص أو محجوبة.
class AgencyDisclosure {
  const AgencyDisclosure({
    required this.mode,
    required this.count,
    required this.items,
  });

  factory AgencyDisclosure.fromJson(Map<String, dynamic> json) =>
      AgencyDisclosure(
        mode: json['mode'] as String? ?? 'full',
        count: json['count'] as int? ?? 0,
        items: (json['items'] as List? ?? const []).toList(),
      );

  final String mode;
  final int count;
  final List<dynamic> items;

  bool get isFull => mode == 'full';

  String get notice => switch (mode) {
    'private' => 'محجوب بطلب صاحب المشروع.',
    'summary' => '$count بندًا مسجّلًا، معروضة كملخص دون تفاصيلها.',
    _ => '$count بندًا مسجّلًا.',
  };
}

class AgencyNumberRow {
  const AgencyNumberRow({
    required this.label,
    required this.confidenceLabel,
    this.value,
    this.unit,
    this.benchmark,
  });

  factory AgencyNumberRow.fromJson(Map<String, dynamic> json) {
    final benchmark = json['benchmark'];

    return AgencyNumberRow(
      label: json['label'] as String? ?? '',
      value: json['value']?.toString(),
      unit: json['unit'] as String?,
      confidenceLabel: json['confidence_label'] as String? ?? 'غير معروف',
      benchmark: benchmark is Map
          ? '${benchmark['range']} ${benchmark['unit'] ?? ''} · ${benchmark['source'] ?? ''}'
                .trim()
          : null,
    );
  }

  final String label;
  final String? value;
  final String? unit;
  final String confidenceLabel;
  final String? benchmark;

  String get display =>
      value == null ? 'لم يُصرَّح به' : '$value${unit == null ? '' : ' $unit'}';
}

class AgencyAssetRow {
  const AgencyAssetRow({
    required this.label,
    required this.isDeclared,
    required this.detail,
    required this.why,
  });

  factory AgencyAssetRow.fromJson(Map<String, dynamic> json) => AgencyAssetRow(
    label: json['label'] as String? ?? '',
    isDeclared: (json['status'] as String?) == 'declared',
    detail: json['detail'] as String? ?? '',
    why: json['why'] as String? ?? '',
  );

  final String label;
  final bool isDeclared;
  final String detail;
  final String why;

  String get display =>
      isDeclared ? detail : 'غير معروف — يُسأل عنه في أول اجتماع';
}

class AgencyDecisionCard {
  const AgencyDecisionCard({
    required this.project,
    required this.context,
    required this.readiness,
    required this.knowledgePercent,
    required this.assetsPercent,
    required this.numbersKnown,
    required this.numbersTotal,
    this.trend,
    this.successMetric,
    this.money,
    this.opportunity,
    this.risk,
    this.unknown,
  });

  factory AgencyDecisionCard.fromJson(Map<String, dynamic> json) {
    final identity = Map<String, dynamic>.from(
      json['identity'] as Map? ?? const {},
    );
    final readiness = Map<String, dynamic>.from(
      json['readiness'] as Map? ?? const {},
    );
    final coverage = Map<String, dynamic>.from(
      json['coverage'] as Map? ?? const {},
    );
    final signals = Map<String, dynamic>.from(
      json['signals'] as Map? ?? const {},
    );
    final money = Map<String, dynamic>.from(json['money'] as Map? ?? const {});
    final trend = readiness['trend'];

    return AgencyDecisionCard(
      project: identity['project'] as String? ?? '',
      context: [
        identity['industry'],
        identity['geography'],
        identity['business_model'],
        identity['stage'],
      ].whereType<String>().join(' · '),
      readiness: readiness['score'] as String? ?? '',
      trend: trend is Map
          ? '${trend['direction_label']} — من ${trend['from']} إلى ${trend['to']} خلال ${trend['days']} يومًا'
          : null,
      knowledgePercent: coverage['knowledge_percent'] as int? ?? 0,
      assetsPercent: coverage['assets_percent'] as int? ?? 0,
      numbersKnown: coverage['numbers_known'] as int? ?? 0,
      numbersTotal: coverage['numbers_total'] as int? ?? 0,
      successMetric: json['success_metric'] as String?,
      // الحجب قرار، وعدم الحساب نقص بيانات — لا يُخلط بينهما في العرض.
      money: money['mode'] != 'full'
          ? 'غير معروض في هذه النسخة بطلب صاحب المشروع'
          : (money['effective_media'] == null
                ? 'لم يُحسم بعد: الميزانية أو ما إذا كانت تشمل أتعاب الإدارة'
                : '${money['effective_media']} شهريًا بعد الأتعاب'),
      opportunity: signals['opportunity'] as String?,
      risk: signals['risk'] as String?,
      unknown: signals['unknown'] as String?,
    );
  }

  final String project;
  final String context;
  final String readiness;
  final String? trend;
  final int knowledgePercent;
  final int assetsPercent;
  final int numbersKnown;
  final int numbersTotal;
  final String? successMetric;
  final String? money;
  final String? opportunity;
  final String? risk;
  final String? unknown;
}

class AgencyShare {
  const AgencyShare({
    required this.isLive,
    required this.viewsCount,
    required this.uniqueViewers,
    required this.expiryChoices,
    this.url,
    this.expiresAt,
    this.revokedAt,
    this.lastViewedAt,
  });

  factory AgencyShare.fromJson(Map<String, dynamic> json) => AgencyShare(
    isLive: json['is_live'] as bool? ?? false,
    url: json['url'] as String?,
    expiresAt: json['expires_at'] as String?,
    revokedAt: json['revoked_at'] as String?,
    lastViewedAt: json['last_viewed_at'] as String?,
    viewsCount: json['views_count'] as int? ?? 0,
    uniqueViewers: json['unique_viewers'] as int? ?? 0,
    expiryChoices: (json['expiry_choices'] as List? ?? const [7, 30, 90])
        .map((item) => int.tryParse(item.toString()) ?? 30)
        .toList(),
  );

  static const AgencyShare none = AgencyShare(
    isLive: false,
    viewsCount: 0,
    uniqueViewers: 0,
    expiryChoices: [7, 30, 90],
  );

  final bool isLive;
  final String? url;
  final String? expiresAt;
  final String? revokedAt;
  final String? lastViewedAt;
  final int viewsCount;
  final int uniqueViewers;
  final List<int> expiryChoices;
}

class AgencyConsultationEvidence {
  const AgencyConsultationEvidence({
    required this.name,
    required this.extractionStatus,
    this.mimeType,
    this.sha256,
    this.text,
  });

  factory AgencyConsultationEvidence.fromJson(Map<String, dynamic> json) =>
      AgencyConsultationEvidence(
        name: json['name'] as String? ?? '',
        extractionStatus: json['extraction_status'] as String? ?? 'pending',
        mimeType: json['mime_type'] as String?,
        sha256: json['sha256'] as String?,
        text: json['text'] as String?,
      );

  final String name;
  final String extractionStatus;
  final String? mimeType;
  final String? sha256;
  final String? text;

  String get extractionLabel => switch (extractionStatus) {
    'completed' => 'تم استخراج المحتوى',
    'unsupported' => 'نوع الملف غير قابل للاستخراج النصي',
    'failed' => 'تعذر استخراج المحتوى',
    _ => 'بانتظار استخراج المحتوى',
  };
}

class AgencyConsultationInference {
  const AgencyConsultationInference({
    required this.statement,
    required this.status,
    required this.confidence,
  });

  factory AgencyConsultationInference.fromJson(Map<String, dynamic> json) =>
      AgencyConsultationInference(
        statement: json['statement'] as String? ?? '',
        status: json['status'] as String? ?? '',
        confidence: (json['confidence'] as num?)?.toInt() ?? 0,
      );

  final String statement;
  final String status;
  final int confidence;
}

class AgencyConsultationConflict {
  const AgencyConsultationConflict({
    required this.message,
    required this.status,
    this.resolution,
  });

  factory AgencyConsultationConflict.fromJson(Map<String, dynamic> json) {
    final rawResolution = json['resolution'];

    return AgencyConsultationConflict(
      message: json['message'] as String? ?? '',
      status: json['status'] as String? ?? 'open',
      resolution: rawResolution is Map
          ? rawResolution['statement']?.toString()
          : rawResolution?.toString(),
    );
  }

  final String message;
  final String status;
  final String? resolution;
}

class AgencyConsultationContext {
  const AgencyConsultationContext({
    required this.uuid,
    required this.depth,
    required this.inferences,
    required this.conflicts,
    required this.evidence,
  });

  factory AgencyConsultationContext.fromJson(Map<String, dynamic> json) =>
      AgencyConsultationContext(
        uuid: json['uuid'] as String? ?? '',
        depth: json['depth'] as String? ?? '',
        inferences: (json['inferences'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) => AgencyConsultationInference.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList(),
        conflicts: (json['conflicts'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) => AgencyConsultationConflict.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList(),
        evidence: (json['evidence'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) => AgencyConsultationEvidence.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList(),
      );

  final String uuid;
  final String depth;
  final List<AgencyConsultationInference> inferences;
  final List<AgencyConsultationConflict> conflicts;
  final List<AgencyConsultationEvidence> evidence;
}

class AgencyCrossToolFinding {
  const AgencyCrossToolFinding({
    required this.sourceReportId,
    required this.sourceToolKey,
    required this.sourceToolTitle,
    required this.title,
    required this.claimType,
  });

  factory AgencyCrossToolFinding.fromJson(Map<String, dynamic> json) =>
      AgencyCrossToolFinding(
        sourceReportId: (json['source_report_id'] as num?)?.toInt() ?? 0,
        sourceToolKey: json['source_tool_key'] as String? ?? '',
        sourceToolTitle: json['source_tool_title'] as String? ?? '',
        title: json['title'] as String? ?? '',
        claimType: json['claim_type'] as String? ?? 'evidence',
      );

  final int sourceReportId;
  final String sourceToolKey;
  final String sourceToolTitle;
  final String title;
  final String claimType;
}

class AgencyCrossToolGroup {
  const AgencyCrossToolGroup({
    required this.category,
    required this.findings,
    required this.sourceTools,
    this.resolution,
  });

  factory AgencyCrossToolGroup.fromJson(Map<String, dynamic> json) =>
      AgencyCrossToolGroup(
        category: json['category'] as String? ?? '',
        findings: (json['findings'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        sourceTools: (json['source_tools'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        resolution: json['resolution'] as String?,
      );

  final String category;
  final List<String> findings;
  final List<String> sourceTools;
  final String? resolution;
}

class AgencyCrossToolSynthesis {
  const AgencyCrossToolSynthesis({
    required this.findings,
    required this.agreements,
    required this.divergences,
  });

  factory AgencyCrossToolSynthesis.fromJson(
    Map<String, dynamic> json,
  ) => AgencyCrossToolSynthesis(
    findings: (json['findings'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) =>
              AgencyCrossToolFinding.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList(),
    agreements: (json['agreements'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) =>
              AgencyCrossToolGroup.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList(),
    divergences: (json['divergences'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) =>
              AgencyCrossToolGroup.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList(),
  );

  static const empty = AgencyCrossToolSynthesis(
    findings: [],
    agreements: [],
    divergences: [],
  );

  final List<AgencyCrossToolFinding> findings;
  final List<AgencyCrossToolGroup> agreements;
  final List<AgencyCrossToolGroup> divergences;
}

class AgencyReportDocument {
  const AgencyReportDocument({
    required this.label,
    this.pdfUrl,
    this.isReady = true,
    this.missingCount = 0,
    this.message,
    this.missingCritical = const [],
  });

  factory AgencyReportDocument.fromJson(
    Map<String, dynamic> json, {
    required String fallbackLabel,
    bool readyByDefault = true,
  }) => AgencyReportDocument(
    label: json['label'] as String? ?? fallbackLabel,
    pdfUrl: json['pdf_url'] as String?,
    isReady: json['is_ready'] as bool? ?? readyByDefault,
    missingCount: json['missing_count'] as int? ?? 0,
    message: json['message'] as String?,
    // البنود الناقصة بالاسم — يرسلها الـAPI الآن كما يعرضها الويب، فلا يبقى
    // مستخدم التطبيق أمام «ناقص بند» دون معرفة أيّ بند (§٤.٣).
    missingCritical: (json['missing_critical'] as List? ?? const [])
        .map((item) => item.toString())
        .toList(),
  );

  final String label;
  final String? pdfUrl;
  final bool isReady;
  final int missingCount;
  final String? message;
  final List<String> missingCritical;
}

class AgencyReportDetail extends AgencyReportCard {
  const AgencyReportDetail({
    required super.uuid,
    required super.title,
    required super.version,
    required this.projectSlug,
    required this.snapshot,
    required this.share,
    required this.visibility,
    required this.documents,
    super.freshness,
    super.generatedAt,
  });

  factory AgencyReportDetail.fromJson(
    Map<String, dynamic> json,
  ) => AgencyReportDetail(
    uuid: json['uuid'] as String,
    title: json['title'] as String? ?? '',
    version: json['version'] as int? ?? 1,
    projectSlug: json['project_slug'] as String? ?? '',
    generatedAt: json['generated_at'] as String?,
    freshness: AgencyReportFreshness.fromJson(
      Map<String, dynamic>.from(json['freshness'] as Map? ?? const {}),
    ),
    visibility: Map<String, String>.from(
      (json['visibility'] as Map? ?? const {}).map(
        (key, value) => MapEntry(key.toString(), value.toString()),
      ),
    ),
    share: json['share'] == null
        ? AgencyShare.none
        : AgencyShare.fromJson(Map<String, dynamic>.from(json['share'] as Map)),
    snapshot: Map<String, dynamic>.from(json['snapshot'] as Map? ?? const {}),
    documents: Map<String, dynamic>.from(json['documents'] as Map? ?? const {}),
  );

  final String projectSlug;
  final AgencyShare share;
  final Map<String, String> visibility;
  final Map<String, dynamic> snapshot;
  final Map<String, dynamic> documents;

  AgencyReportDocument get ownerDocument => AgencyReportDocument.fromJson(
    Map<String, dynamic>.from(documents['owner'] as Map? ?? const {}),
    fallbackLabel: 'تقريرك الكامل',
  );

  AgencyReportDocument get agencyBriefDocument => AgencyReportDocument.fromJson(
    Map<String, dynamic>.from(documents['agency_brief'] as Map? ?? const {}),
    fallbackLabel: 'موجز التكليف للوكالة',
    readyByDefault: false,
  );

  Map<String, dynamic> get ownerReport =>
      Map<String, dynamic>.from(snapshot['owner_report'] as Map? ?? const {});

  Map<String, dynamic> get agencyBrief =>
      Map<String, dynamic>.from(snapshot['agency_brief'] as Map? ?? const {});

  Map<String, dynamic> get project =>
      Map<String, dynamic>.from(snapshot['project'] as Map? ?? const {});

  Map<String, dynamic> get readiness =>
      Map<String, dynamic>.from(snapshot['readiness'] as Map? ?? const {});

  String get projectName => project['name'] as String? ?? '';
  int? get readinessScore => readiness['score'] as int?;
  String get readinessBand =>
      readiness['band'] as String? ?? 'بلا درجة رقمية بعد';

  AgencyConsultationContext? get consultation {
    final raw = snapshot['consultation'];

    return raw is Map
        ? AgencyConsultationContext.fromJson(Map<String, dynamic>.from(raw))
        : null;
  }

  AgencyCrossToolSynthesis get crossTool {
    final raw = snapshot['cross_tool_synthesis'];

    return raw is Map
        ? AgencyCrossToolSynthesis.fromJson(Map<String, dynamic>.from(raw))
        : AgencyCrossToolSynthesis.empty;
  }

  AgencyExecutive? get executive {
    final raw = snapshot['executive'];

    return raw is Map
        ? AgencyExecutive.fromJson(Map<String, dynamic>.from(raw))
        : null;
  }

  List<AgencyLedgerTheme> get ledgerThemes {
    final ledger = Map<String, dynamic>.from(
      snapshot['ledger'] as Map? ?? const {},
    );

    return (ledger['themes'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) => AgencyLedgerTheme.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
  }

  AgencyDecisionCard? get decisionCard {
    final raw = snapshot['decision_card'];

    return raw is Map
        ? AgencyDecisionCard.fromJson(Map<String, dynamic>.from(raw))
        : null;
  }

  List<AgencyNumberRow> get numbers {
    final block = Map<String, dynamic>.from(
      snapshot['numbers'] as Map? ?? const {},
    );

    return (block['rows'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) => AgencyNumberRow.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
  }

  String? get trackingLabel {
    final block = Map<String, dynamic>.from(
      snapshot['numbers'] as Map? ?? const {},
    );

    return block['tracking_label'] as String?;
  }

  List<AgencyAssetRow> get assets {
    final block = Map<String, dynamic>.from(
      snapshot['assets'] as Map? ?? const {},
    );

    return (block['rows'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => AgencyAssetRow.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  /// سطر واحد يلخّص سجل التنفيذ — التفاصيل في الويب والـPDF.
  String? get behaviourSummary {
    final block = Map<String, dynamic>.from(
      snapshot['behaviour'] as Map? ?? const {},
    );

    if (block.isEmpty) return null;

    final tasks = Map<String, dynamic>.from(block['tasks'] as Map? ?? const {});
    final engagement = Map<String, dynamic>.from(
      block['engagement'] as Map? ?? const {},
    );

    return 'مهام ${tasks['done'] ?? 0} منجزة من ${tasks['total'] ?? 0}'
        ' · ${engagement['tools_completed'] ?? 0} أدوات مكتملة';
  }

  List<AgencyKpiRow> get kpis => (snapshot['kpis'] as List? ?? const [])
      .whereType<Map>()
      .map((item) => AgencyKpiRow.fromJson(Map<String, dynamic>.from(item)))
      .toList();

  AgencyDisclosure get competitors => AgencyDisclosure.fromJson(
    Map<String, dynamic>.from(snapshot['competitors'] as Map? ?? const {}),
  );

  AgencyDisclosure get evidence => AgencyDisclosure.fromJson(
    Map<String, dynamic>.from(snapshot['evidence'] as Map? ?? const {}),
  );

  List<String> get methodologyLimits {
    final methodology = Map<String, dynamic>.from(
      snapshot['methodology'] as Map? ?? const {},
    );

    return (methodology['limits'] as List? ?? const [])
        .map((item) => item.toString())
        .toList();
  }

  List<AgencyToolSummary> get tools => (snapshot['tools'] as List? ?? const [])
      .whereType<Map>()
      .map(
        (item) => AgencyToolSummary.fromJson(Map<String, dynamic>.from(item)),
      )
      .toList();

  List<AgencyPriority> get priorities =>
      (snapshot['priorities'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) => AgencyPriority.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList();

  List<AgencyPriority> plan(String key) {
    final plan = Map<String, dynamic>.from(
      snapshot['plan'] as Map? ?? const {},
    );

    return (plan[key] as List? ?? const [])
        .whereType<Map>()
        .map((item) => AgencyPriority.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Map<String, dynamic> get scope =>
      Map<String, dynamic>.from(snapshot['scope'] as Map? ?? const {});

  List<String> get agencyQuestions =>
      (snapshot['agency_questions'] as List? ?? const [])
          .map((item) => item.toString())
          .toList();

  List<String> get assumptions => (snapshot['assumptions'] as List? ?? const [])
      .map((item) => item.toString())
      .toList();

  List<String> get dataGaps => (snapshot['data_gaps'] as List? ?? const [])
      .map((item) => item.toString())
      .toList();
}
