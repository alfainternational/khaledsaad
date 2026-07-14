import 'package:flutter/material.dart';

import '../../../app/theme/app_semantic_colors.dart';
import '../../../core/l10n/ar_labels.dart';
import '../../../data/models/tool_run_model.dart';
import '../../shared/widgets/markdown_text.dart';
import '../../shared/widgets/ui_feedback.dart';

/// يعرض نتيجة تشغيل الأداة: نسبة الاكتمال، الملخّص، المخرجات، والخطوات التالية.
class ToolResultView extends StatelessWidget {
  const ToolResultView({
    super.key,
    required this.result,
    this.briefing,
    this.onNextAction,
  });

  final ToolRunResult result;
  final ToolBriefing? briefing;
  final ValueChanged<ToolNextAction>? onNextAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.check_circle, color: theme.colorScheme.primary),
                const SizedBox(width: 8),
                Text(
                  'النتيجة',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Spacer(),
                if (result.completenessScore != null)
                  _CompletenessBadge(score: result.completenessScore!),
                if (_copyText().isNotEmpty)
                  CopyIconButton(text: _copyText(), tooltip: 'نسخ النتيجة'),
              ],
            ),
            if (result.aiGenerated) ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  Icon(
                    Icons.auto_awesome,
                    size: 14,
                    color: theme.colorScheme.tertiary,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    'نُقّح بالذكاء الاصطناعي',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                ],
              ),
            ],
            const Divider(height: 24),
            ..._renderBody(theme),
          ],
        ),
      ),
    );
  }

  List<Widget> _renderBody(ThemeData theme) {
    final body = <Widget>[
      ..._renderSummary(theme),
      ..._renderOutput(theme),
      ..._renderBriefingAction(theme),
      ..._renderNextActions(theme),
    ];
    if (body.isEmpty) {
      return [
        Row(
          children: [
            Icon(Icons.inbox_outlined,
                size: 20, color: theme.colorScheme.outline),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'اكتملت الأداة دون مخرجات نصية. جرّب إضافة تفاصيل أكثر ثم أعد التشغيل.',
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.outline),
              ),
            ),
          ],
        ),
      ];
    }
    return body;
  }

  /// نص موحّد قابل للنسخ من الملخّص والمخرجات.
  String _copyText() {
    final buffer = StringBuffer();
    final headline = result.summary['headline']?.toString();
    final text = result.summary['text']?.toString();
    if (headline != null && headline.trim().isNotEmpty) {
      buffer.writeln(headline.trim());
    }
    if (text != null && text.trim().isNotEmpty) {
      buffer.writeln(text.trim());
    }
    result.output.forEach((key, value) {
      final rendered = _stringifyValue(value);
      if (rendered.trim().isEmpty) return;
      buffer.writeln('${_humanizeKey(key)}: $rendered');
    });
    return buffer.toString().trim();
  }

  List<Widget> _renderSummary(ThemeData theme) {
    final summary = result.summary;
    final widgets = <Widget>[];
    final headline = summary['headline']?.toString();
    final text = summary['text']?.toString();
    if (headline != null && headline.isNotEmpty) {
      widgets.add(
        Text(
          headline,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
      );
      widgets.add(const SizedBox(height: 4));
    }
    if (text != null && text.isNotEmpty) {
      widgets.add(Text(text, style: theme.textTheme.bodyMedium));
      widgets.add(const SizedBox(height: 12));
    }
    return widgets;
  }

  List<Widget> _renderOutput(ThemeData theme) {
    final output = result.output;
    if (output.isEmpty) return const [];
    final widgets = <Widget>[];
    output.forEach((key, value) {
      final rendered = _stringifyValue(value);
      if (rendered.trim().isEmpty) return;
      widgets.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _humanizeKey(key),
                style: theme.textTheme.labelLarge?.copyWith(
                  color: theme.colorScheme.primary,
                ),
              ),
              const SizedBox(height: 2),
              MarkdownText(rendered),
            ],
          ),
        ),
      );
    });
    return widgets;
  }

  List<Widget> _renderBriefingAction(ThemeData theme) {
    final action = briefing?.nextAction;
    if (action == null || !action.hasCta) return const [];

    return [
      const Divider(height: 24),
      FilledButton.icon(
        onPressed: onNextAction == null
            ? null
            : () => onNextAction!.call(action),
        icon: const Icon(Icons.open_in_new_rounded),
        label: Text(action.displayLabel),
      ),
    ];
  }

  List<Widget> _renderNextActions(ThemeData theme) {
    final actions = result.nextActions;
    if (actions.isEmpty) return const [];
    return [
      const Divider(height: 24),
      Text(
        'الخطوات التالية',
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 8),
      ...actions.map((a) {
        final label = a is Map
            ? (a['label'] ?? a['title'] ?? a['text'] ?? '').toString()
            : a.toString();
        if (label.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.arrow_left,
                size: 18,
                color: theme.colorScheme.primary,
              ),
              const SizedBox(width: 4),
              Expanded(child: Text(label, style: theme.textTheme.bodyMedium)),
            ],
          ),
        );
      }),
    ];
  }

  String _stringifyValue(dynamic value) {
    if (value == null) return '';
    if (value is String) return value;
    if (value is num || value is bool) return value.toString();
    if (value is List) {
      return value.map((e) => '• ${_stringifyValue(e)}').join('\n');
    }
    if (value is Map) {
      return value.entries
          .map(
            (e) =>
                '${_humanizeKey(e.key.toString())}: ${_stringifyValue(e.value)}',
          )
          .join('\n');
    }
    return value.toString();
  }

  String _humanizeKey(String key) => ArLabels.of(key);
}

class _CompletenessBadge extends StatelessWidget {
  const _CompletenessBadge({required this.score});

  final int score;

  @override
  Widget build(BuildContext context) {
    final sem = AppSemanticColors.of(context);
    final color = score >= 70
        ? sem.success
        : score >= 40
            ? sem.warning
            : sem.danger;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        'الاكتمال $score%',
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}
