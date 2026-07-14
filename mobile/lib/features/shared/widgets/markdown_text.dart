import 'package:flutter/material.dart';

/// عارض Markdown خفيف بلا تبعيات خارجية — يغطي ما تُنتجه مخرجات الـ AI:
/// العناوين (#..###)، الغامق (**)، القوائم (- / *) والمرقّمة، الاقتباس (>)،
/// والفقرات. يعرض النص قابلاً للتحديد.
class MarkdownText extends StatelessWidget {
  const MarkdownText(this.data, {super.key, this.selectable = true});

  final String data;
  final bool selectable;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final blocks = <Widget>[];
    final lines = data.replaceAll('\r\n', '\n').split('\n');

    for (var raw in lines) {
      final line = raw.trimRight();
      if (line.trim().isEmpty) {
        blocks.add(const SizedBox(height: 8));
        continue;
      }

      // العناوين
      final heading = RegExp(r'^(#{1,3})\s+(.*)$').firstMatch(line.trim());
      if (heading != null) {
        final level = heading.group(1)!.length;
        final text = heading.group(2)!;
        final style = level == 1
            ? theme.textTheme.titleLarge
            : level == 2
                ? theme.textTheme.titleMedium
                : theme.textTheme.titleSmall;
        blocks.add(Padding(
          padding: const EdgeInsets.only(top: 12, bottom: 4),
          child: _rich(context, text,
              base: style?.copyWith(fontWeight: FontWeight.w800)),
        ));
        continue;
      }

      // الاقتباس
      if (line.trim().startsWith('> ')) {
        blocks.add(Container(
          margin: const EdgeInsets.symmetric(vertical: 4),
          padding: const EdgeInsetsDirectional.only(start: 12),
          decoration: BoxDecoration(
            border: BorderDirectional(
              start: BorderSide(color: theme.colorScheme.primary, width: 3),
            ),
          ),
          child: _rich(context, line.trim().substring(2),
              base: theme.textTheme.bodyMedium
                  ?.copyWith(fontStyle: FontStyle.italic)),
        ));
        continue;
      }

      // قوائم غير مرقّمة
      final bullet = RegExp(r'^\s*[-*]\s+(.*)$').firstMatch(line);
      if (bullet != null) {
        blocks.add(_listItem(context, '•', bullet.group(1)!));
        continue;
      }

      // قوائم مرقّمة
      final ordered = RegExp(r'^\s*(\d+)[.)]\s+(.*)$').firstMatch(line);
      if (ordered != null) {
        blocks.add(_listItem(context, '${ordered.group(1)}.', ordered.group(2)!));
        continue;
      }

      // فقرة عادية
      blocks.add(Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: _rich(context, line, base: theme.textTheme.bodyMedium),
      ));
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: blocks,
    );
  }

  Widget _listItem(BuildContext context, String marker, String text) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.only(end: 8, top: 1),
            child: Text(marker,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.primary)),
          ),
          Expanded(child: _rich(context, text, base: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }

  /// يحوّل الغامق **..** إلى TextSpan.
  Widget _rich(BuildContext context, String text, {TextStyle? base}) {
    final spans = <TextSpan>[];
    final re = RegExp(r'\*\*(.+?)\*\*');
    var index = 0;
    for (final m in re.allMatches(text)) {
      if (m.start > index) {
        spans.add(TextSpan(text: text.substring(index, m.start)));
      }
      spans.add(TextSpan(
        text: m.group(1),
        style: const TextStyle(fontWeight: FontWeight.w800),
      ));
      index = m.end;
    }
    if (index < text.length) {
      spans.add(TextSpan(text: text.substring(index)));
    }
    final span = TextSpan(style: base, children: spans);
    return selectable
        ? SelectableText.rich(span)
        : Text.rich(span);
  }
}
