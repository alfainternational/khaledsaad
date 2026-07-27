class ConsultationOption {
  const ConsultationOption({required this.value, required this.label});
  final String value;
  final String label;

  factory ConsultationOption.fromJson(Map<String, dynamic> json) =>
      ConsultationOption(
        value: json['value'].toString(),
        label: json['label'].toString(),
      );
}

class ConsultationQuestion {
  const ConsultationQuestion({
    required this.key,
    required this.text,
    required this.type,
    required this.options,
    required this.required,
    required this.allowUnknown,
    required this.allowSkip,
    required this.sensitive,
    this.help,
    this.why,
    this.validation = const {},
  });
  final String key;
  final String text;
  final String type;
  final List<ConsultationOption> options;
  final bool required;
  final bool allowUnknown;
  final bool allowSkip;
  final bool sensitive;
  final String? help;
  final String? why;
  final Map<String, dynamic> validation;

  bool get isSingleChoice =>
      type == 'select' ||
      type == 'radio' ||
      type == 'boolean' ||
      type == 'confirmation';
  bool get isMultipleChoice => type == 'multiselect';
  bool get isNumber => type == 'number';

  factory ConsultationQuestion.fromJson(Map<String, dynamic> json) =>
      ConsultationQuestion(
        key: json['key'].toString(),
        text: json['text'].toString(),
        type: json['type'].toString(),
        options: (json['options'] as List? ?? const [])
            .map(
              (item) => ConsultationOption.fromJson(
                Map<String, dynamic>.from(item as Map),
              ),
            )
            .toList(),
        required: json['required'] == true,
        allowUnknown: json['allow_unknown'] == true,
        allowSkip: json['allow_skip'] == true,
        sensitive: json['sensitive'] == true,
        help: json['help']?.toString(),
        why: json['why']?.toString(),
        validation: Map<String, dynamic>.from(
          (json['validation'] as Map?) ?? const {},
        ),
      );
}

class ConsultationProgress {
  const ConsultationProgress({
    required this.answered,
    required this.limit,
    required this.percent,
    required this.label,
  });
  final int answered;
  final int limit;
  final int percent;
  final String label;

  factory ConsultationProgress.fromJson(Map<String, dynamic> json) =>
      ConsultationProgress(
        answered: (json['answered'] as num?)?.toInt() ?? 0,
        limit: (json['limit'] as num?)?.toInt() ?? 0,
        percent: (json['percent'] as num?)?.toInt() ?? 0,
        label: json['label']?.toString() ?? '',
      );
}

class ConsultationScope {
  const ConsultationScope({
    required this.key,
    required this.name,
    required this.state,
    required this.reason,
    required this.confidence,
  });
  final String key;
  final String name;
  final String state;
  final String reason;
  final int confidence;
  factory ConsultationScope.fromJson(Map<String, dynamic> json) =>
      ConsultationScope(
        key: json['key'].toString(),
        name: json['name'].toString(),
        state: json['state'].toString(),
        reason: json['reason']?.toString() ?? '',
        confidence: (json['confidence'] as num?)?.toInt() ?? 0,
      );
}

class ConsultationConflict {
  const ConsultationConflict({
    required this.id,
    required this.key,
    required this.message,
    required this.severity,
  });
  final int id;
  final String key;
  final String message;
  final String severity;
  factory ConsultationConflict.fromJson(Map<String, dynamic> json) =>
      ConsultationConflict(
        id: (json['id'] as num).toInt(),
        key: json['key'].toString(),
        message: json['message'].toString(),
        severity: json['severity'].toString(),
      );
}

class ConsultationEvidence {
  const ConsultationEvidence({
    required this.id,
    required this.name,
    required this.type,
    required this.confidence,
    this.size,
    required this.reviewRequired,
  });
  final int id;
  final String name;
  final String type;
  final String confidence;
  final int? size;
  final bool reviewRequired;

  factory ConsultationEvidence.fromJson(Map<String, dynamic> json) =>
      ConsultationEvidence(
        id: (json['id'] as num).toInt(),
        name: json['name'].toString(),
        type: json['type'].toString(),
        confidence: json['confidence'].toString(),
        size: (json['size'] as num?)?.toInt(),
        reviewRequired: json['review_required'] == true,
      );
}

class ConsultationReviewItem {
  const ConsultationReviewItem({
    required this.label,
    this.value,
    this.confidence,
    this.questionKey,
    this.type,
    this.options = const [],
    this.validation = const {},
  });
  final String label;
  final dynamic value;
  final String? confidence;
  final String? questionKey;
  final String? type;
  final List<ConsultationOption> options;
  final Map<String, dynamic> validation;
  String get displayValue =>
      value is List ? (value as List).join('، ') : value?.toString() ?? '';
  factory ConsultationReviewItem.fromJson(
    Map<String, dynamic> json,
  ) => ConsultationReviewItem(
    label: (json['label'] ?? json['statement'] ?? json['key'] ?? '').toString(),
    value: json['value'],
    confidence: json['confidence']?.toString(),
    questionKey: json['question_key']?.toString(),
    type: json['type']?.toString(),
    options: (json['options'] as List? ?? const [])
        .map(
          (item) => ConsultationOption.fromJson(
            Map<String, dynamic>.from(item as Map),
          ),
        )
        .toList(),
    validation: Map<String, dynamic>.from(
      (json['validation'] as Map?) ?? const {},
    ),
  );
}

class ConsultationReview {
  const ConsultationReview({
    required this.facts,
    required this.estimates,
    required this.unknowns,
    required this.assumptions,
    required this.conflicts,
  });
  final List<ConsultationReviewItem> facts;
  final List<ConsultationReviewItem> estimates;
  final List<ConsultationReviewItem> unknowns;
  final List<ConsultationReviewItem> assumptions;
  final List<ConsultationConflict> conflicts;
  factory ConsultationReview.fromJson(Map<String, dynamic> json) =>
      ConsultationReview(
        facts: _items(json['facts']),
        estimates: _items(json['estimates']),
        unknowns: _items(json['unknowns']),
        assumptions: _items(json['assumptions']),
        conflicts: (json['conflicts'] as List? ?? const [])
            .map(
              (e) => ConsultationConflict.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
      );
  static List<ConsultationReviewItem> _items(dynamic raw) =>
      (raw as List? ?? const [])
          .map(
            (e) => ConsultationReviewItem.fromJson(
              Map<String, dynamic>.from(e as Map),
            ),
          )
          .toList();
}

class ConsultationSessionModel {
  const ConsultationSessionModel({
    required this.uuid,
    required this.status,
    required this.depth,
    required this.projectName,
    required this.progress,
    required this.scope,
    required this.conflicts,
    required this.review,
    required this.evidence,
    required this.statusMessage,
    required this.canConfirm,
    this.question,
    this.reportUuid,
  });
  final String uuid;
  final String status;
  final String depth;
  final String projectName;
  final ConsultationProgress progress;
  final ConsultationQuestion? question;
  final List<ConsultationScope> scope;
  final List<ConsultationConflict> conflicts;
  final ConsultationReview review;
  final List<ConsultationEvidence> evidence;
  final String statusMessage;
  final bool canConfirm;
  final String? reportUuid;

  bool get isReview => status == 'review';
  bool get isQueued => status == 'analysis_queued';
  bool get isCompleted => status == 'completed';
  bool get isFailed => status == 'failed';

  factory ConsultationSessionModel.fromJson(Map<String, dynamic> json) =>
      ConsultationSessionModel(
        uuid: json['uuid'].toString(),
        status: json['status'].toString(),
        depth: json['depth'].toString(),
        projectName: (json['project'] as Map)['name'].toString(),
        progress: ConsultationProgress.fromJson(
          Map<String, dynamic>.from(json['progress'] as Map),
        ),
        question: json['question'] == null
            ? null
            : ConsultationQuestion.fromJson(
                Map<String, dynamic>.from(json['question'] as Map),
              ),
        scope: (json['scope'] as List? ?? const [])
            .map(
              (e) => ConsultationScope.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
        conflicts: (json['conflicts'] as List? ?? const [])
            .map(
              (e) => ConsultationConflict.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
        review: ConsultationReview.fromJson(
          Map<String, dynamic>.from((json['review'] as Map?) ?? const {}),
        ),
        evidence: (json['evidence'] as List? ?? const [])
            .map(
              (e) => ConsultationEvidence.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
        statusMessage: json['status_message']?.toString() ?? '',
        canConfirm: json['can_confirm'] == true,
        reportUuid: json['report_uuid']?.toString(),
      );
}
