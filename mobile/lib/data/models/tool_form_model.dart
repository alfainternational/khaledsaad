/// خيار في حقل select.
class ToolFieldOption {
  const ToolFieldOption({required this.value, required this.label});

  final String value;
  final String label;

  factory ToolFieldOption.fromJson(Map<String, dynamic> json) => ToolFieldOption(
        value: json['value']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
      );
}

/// قواعد جودة الإجابة (تحقّق عميل ليّن).
class ToolFieldQuality {
  const ToolFieldQuality({this.minLength = 0, this.genericTerms = const []});

  final int minLength;
  final List<String> genericTerms;

  factory ToolFieldQuality.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const ToolFieldQuality();
    return ToolFieldQuality(
      minLength: (json['min_length'] is num) ? (json['min_length'] as num).toInt() : 0,
      genericTerms: (json['generic_terms'] as List?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }
}

/// تعريف حقل ديناميكي مدموج (blueprint + experience).
class ToolField {
  const ToolField({
    required this.key,
    required this.label,
    required this.type,
    this.placeholder = '',
    this.answerTip = '',
    this.options = const [],
    this.priority = 'normal',
    this.priorityLabel,
    this.contextHint,
    this.smartPlaceholder,
    this.suggestedValue,
    this.suggestionLabel,
    this.quality = const ToolFieldQuality(),
    this.mandatory = false,
  });

  final String key;
  final String label;

  /// text | textarea | select
  final String type;
  final String placeholder;
  final String answerTip;
  final List<ToolFieldOption> options;

  /// critical | high | normal | low
  final String priority;
  final String? priorityLabel;
  final String? contextHint;
  final String? smartPlaceholder;
  final String? suggestedValue;
  final String? suggestionLabel;
  final ToolFieldQuality quality;

  /// حقل إلزامي صريح (من الخادم) — يُمنع التشغيل بدونه.
  final bool mandatory;

  bool get isCritical => priority == 'critical';
  bool get isSelect => type == 'select';
  bool get isTextarea => type == 'textarea';

  /// إلزامي فعلياً: مُعلَّم إلزامياً صراحةً أو حرج الأولوية.
  bool get isRequired => mandatory || isCritical;

  factory ToolField.fromJson(Map<String, dynamic> json) {
    return ToolField(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      type: json['type']?.toString() ?? 'text',
      placeholder: json['placeholder']?.toString() ?? '',
      answerTip: json['answer_tip']?.toString() ?? '',
      options: (json['options'] as List?)
              ?.map((e) => ToolFieldOption.fromJson(
                  Map<String, dynamic>.from(e as Map)))
              .toList() ??
          const [],
      priority: json['priority']?.toString() ?? 'normal',
      priorityLabel: json['priority_label']?.toString(),
      contextHint: json['context_hint']?.toString(),
      smartPlaceholder: json['smart_placeholder']?.toString(),
      suggestedValue: json['suggested_value']?.toString(),
      suggestionLabel: json['suggestion_label']?.toString(),
      quality: ToolFieldQuality.fromJson(
        json['quality'] is Map
            ? Map<String, dynamic>.from(json['quality'] as Map)
            : null,
      ),
      mandatory: json['required'] == true ||
          json['is_required'] == true ||
          json['priority']?.toString() == 'critical',
    );
  }
}

/// وضع (quick/advanced/guided...) بحقوله المرتّبة.
class ToolMode {
  const ToolMode({
    required this.key,
    required this.label,
    required this.description,
    required this.fields,
  });

  final String key;
  final String label;
  final String description;
  final List<ToolField> fields;

  factory ToolMode.fromJson(Map<String, dynamic> json) {
    return ToolMode(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      fields: (json['fields'] as List?)
              ?.map((e) => ToolField.fromJson(Map<String, dynamic>.from(e as Map)))
              .toList() ??
          const [],
    );
  }
}

/// مخطط نموذج الأداة كاملاً (كل الأوضاع).
class ToolForm {
  const ToolForm({
    required this.modes,
    this.defaultMode,
    this.voiceEnabled = false,
  });

  final List<ToolMode> modes;
  final String? defaultMode;

  /// هل خدمة الصوت مفعّلة (تُظهر زر "تكلّم بدل الكتابة").
  final bool voiceEnabled;

  ToolMode? modeByKey(String key) =>
      modes.where((m) => m.key == key).cast<ToolMode?>().firstOrNull;

  factory ToolForm.fromJson(Map<String, dynamic> json) {
    return ToolForm(
      modes: (json['modes'] as List?)
              ?.map((e) => ToolMode.fromJson(Map<String, dynamic>.from(e as Map)))
              .toList() ??
          const [],
      defaultMode: json['default_mode']?.toString(),
      voiceEnabled: json['voice_enabled'] == true,
    );
  }
}

extension _FirstOrNull<E> on Iterable<E> {
  E? get firstOrNull {
    final it = iterator;
    return it.moveNext() ? it.current : null;
  }
}
