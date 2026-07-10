import 'package:flutter/material.dart';

import '../../../data/models/tool_form_model.dart';
import '../tool_runner_controller.dart';

/// يعرض حقلاً ديناميكياً واحداً بحسب نوعه (text/textarea/select)،
/// مع تلميح السياق، زر الاقتراح، وتحذير الجودة اللين.
class DynamicFormField extends StatefulWidget {
  const DynamicFormField({
    super.key,
    required this.field,
    required this.controller,
  });

  final ToolField field;
  final ToolRunnerController controller;

  @override
  State<DynamicFormField> createState() => _DynamicFormFieldState();
}

class _DynamicFormFieldState extends State<DynamicFormField> {
  late final TextEditingController _text;

  ToolField get field => widget.field;
  ToolRunnerController get c => widget.controller;

  @override
  void initState() {
    super.initState();
    _text = TextEditingController(text: c.values[field.key] ?? '');
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _onChanged(String v) {
    c.setValue(field.key, v);
    setState(() {}); // لتحديث تحذير الجودة
  }

  void _useSuggestion() {
    final suggestion = field.suggestedValue;
    if (suggestion == null || suggestion.isEmpty) return;
    _text.text = suggestion;
    _onChanged(suggestion);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final warning = c.qualityWarning(field);

    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Flexible(
                child: Text(
                  field.label,
                  style: theme.textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
              if (field.isCritical) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    field.priorityLabel ?? 'مهم',
                    style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ],
          ),
          if (field.contextHint != null && field.contextHint!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(field.contextHint!,
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: theme.colorScheme.outline)),
          ],
          const SizedBox(height: 8),
          if (field.isSelect) _buildSelect(theme) else _buildText(),
          if (field.suggestedValue != null &&
              field.suggestedValue!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed: _useSuggestion,
                icon: const Icon(Icons.auto_awesome, size: 18),
                label: Text(field.suggestionLabel ?? 'استخدم الاقتراح'),
              ),
            ),
          ],
          if (warning != null) ...[
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.info_outline,
                    size: 14, color: theme.colorScheme.tertiary),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(warning,
                      style: theme.textTheme.bodySmall
                          ?.copyWith(color: theme.colorScheme.tertiary)),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildText() {
    return TextField(
      controller: _text,
      onChanged: _onChanged,
      minLines: field.isTextarea ? 3 : 1,
      maxLines: field.isTextarea ? 8 : 1,
      decoration: InputDecoration(
        hintText: field.smartPlaceholder ?? field.placeholder,
      ),
    );
  }

  Widget _buildSelect(ThemeData theme) {
    final current = c.values[field.key];
    final validValue =
        field.options.any((o) => o.value == current) ? current : null;
    return DropdownButtonFormField<String>(
      initialValue: validValue,
      isExpanded: true,
      hint: Text(field.placeholder.isEmpty ? 'اختر' : field.placeholder),
      items: field.options
          .map((o) => DropdownMenuItem(value: o.value, child: Text(o.label)))
          .toList(),
      onChanged: (v) {
        if (v != null) _onChanged(v);
      },
    );
  }
}
