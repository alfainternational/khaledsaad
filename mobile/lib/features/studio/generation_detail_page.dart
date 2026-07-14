import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/markdown_text.dart';
import '../shared/widgets/ui_feedback.dart';
import 'generation_detail_controller.dart';

class GenerationDetailPage extends StatelessWidget {
  const GenerationDetailPage({super.key});

  @override
  Widget build(BuildContext context) {
    final publicId = Get.arguments as String;
    final c = Get.put(
      GenerationDetailController(
        Get.find<StudioRepository>(),
        Get.find<WorkspaceService>(),
        publicId,
      ),
      tag: publicId,
    );
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('المخرج'),
        actions: [
          Obx(() {
            final output = c.generation.value?.output;
            if (output == null || output.trim().isEmpty) {
              return const SizedBox.shrink();
            }
            return CopyIconButton(text: output, tooltip: 'نسخ المخرج');
          }),
          Obx(() => PopupMenuButton<String>(
                enabled: !c.isExporting.value && c.generation.value != null,
                icon: c.isExporting.value
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2.2))
                    : const Icon(Icons.ios_share),
                onSelected: (format) => _export(context, c, format),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'md', child: Text('تصدير Markdown')),
                  PopupMenuItem(value: 'html', child: Text('تصدير HTML')),
                  PopupMenuItem(value: 'pdf', child: Text('تصدير PDF')),
                ],
              )),
        ],
      ),
      body: Obx(() {
        if (c.isLoading.value && c.generation.value == null) {
          return AppStateView.loading();
        }
        if (c.error.value != null && c.generation.value == null) {
          return AppStateView.error(message: c.error.value, onRetry: c.load);
        }
        final g = c.generation.value;
        if (g == null) {
          return AppStateView.empty(
              icon: Icons.description_outlined, title: 'المخرج غير متاح');
        }
        if (g.isFailed) {
          return AppStateView.error(
            title: 'فشل التوليد',
            message: g.error ?? 'حدث خطأ أثناء التوليد.',
            onRetry: c.load,
          );
        }
        // قيد التوليد على الخادم — نعرض انتظاراً بدل «لا يوجد محتوى».
        if (g.isProcessing) {
          return AppStateView.loading(
            message: 'جارٍ توليد المخرج، سيظهر تلقائياً خلال لحظات...',
          );
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (g.templateName != null)
              Text(g.templateName!,
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            _TypewriterMarkdown(
              g.output ?? '',
              style: theme.textTheme.bodyLarge?.copyWith(height: 1.7),
            ),
          ],
        );
      }),
    );
  }

  Future<void> _export(
      BuildContext context, GenerationDetailController c, String format) async {
    final err = await c.export(format);
    if (err != null) {
      UiFeedback.error(err, title: 'التصدير');
    }
  }
}

/// يعرض المخرج تدريجياً (typewriter) لإحساس أسرع، ثم يستبدله بعرض Markdown
/// كامل عند الاكتمال. المس النص لتخطّي التأثير فوراً.
class _TypewriterMarkdown extends StatefulWidget {
  const _TypewriterMarkdown(this.text, {this.style});

  final String text;
  final TextStyle? style;

  @override
  State<_TypewriterMarkdown> createState() => _TypewriterMarkdownState();
}

class _TypewriterMarkdownState extends State<_TypewriterMarkdown> {
  static const _charsPerTick = 4;
  Timer? _timer;
  int _count = 0;
  bool _done = false;

  @override
  void initState() {
    super.initState();
    _start();
  }

  @override
  void didUpdateWidget(covariant _TypewriterMarkdown oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.text != widget.text) _start();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _start() {
    _timer?.cancel();
    _count = 0;
    _done = widget.text.isEmpty;
    if (_done) return;
    HapticFeedback.selectionClick();
    _timer = Timer.periodic(const Duration(milliseconds: 16), (_) {
      _count += _charsPerTick;
      if (_count >= widget.text.length) {
        _finish();
      } else if (mounted) {
        setState(() {});
      }
    });
  }

  void _finish() {
    _timer?.cancel();
    if (!mounted) return;
    setState(() {
      _count = widget.text.length;
      _done = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_done) return MarkdownText(widget.text);
    return GestureDetector(
      onTap: _finish,
      child: Text(
        widget.text.substring(0, _count.clamp(0, widget.text.length)),
        style: widget.style,
      ),
    );
  }
}
