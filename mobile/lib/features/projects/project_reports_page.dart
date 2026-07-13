import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
import '../../core/utils/file_exporter.dart';
import '../../data/repositories/lifecycle_repository.dart';
import '../../data/services/workspace_service.dart';

/// تقارير المشروع: التقرير الشامل + دليل المشروع — عرض داخل التطبيق أو PDF.
class ProjectReportsPage extends StatefulWidget {
  const ProjectReportsPage({super.key});

  @override
  State<ProjectReportsPage> createState() => _ProjectReportsPageState();
}

class _ProjectReportsPageState extends State<ProjectReportsPage> {
  late final String _projectId = Get.arguments as String;
  late final LifecycleRepository _repo = Get.find<LifecycleRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _busy = RxnString(); // report | dossier | report_pdf | dossier_pdf

  Future<void> _viewReport() => _viewDocument(
        key: 'report',
        title: 'التقرير الشامل',
        loader: (ws) => _repo.report(ws, _projectId),
      );

  Future<void> _viewDossier() => _viewDocument(
        key: 'dossier',
        title: 'دليل المشروع',
        loader: (ws) => _repo.dossier(ws, _projectId),
      );

  Future<void> _viewDocument({
    required String key,
    required String title,
    required Future<Map<String, dynamic>> Function(String ws) loader,
  }) async {
    final ws = _workspaces.activeId;
    if (ws == null || _busy.value != null) return;
    _busy.value = key;
    try {
      final doc = await loader(ws);
      Get.to(() => _DocumentView(title: title, document: doc));
    } on ApiException catch (e) {
      Get.snackbar(title, e.message, snackPosition: SnackPosition.BOTTOM);
    } finally {
      _busy.value = null;
    }
  }

  Future<void> _exportPdf({
    required String key,
    required String title,
    required Future<List<int>> Function(String ws) downloader,
    required String filename,
  }) async {
    final ws = _workspaces.activeId;
    if (ws == null || _busy.value != null) return;
    _busy.value = key;
    try {
      final bytes = await downloader(ws);
      await FileExporter.saveAndShare(bytes, filename);
    } on ApiException catch (e) {
      Get.snackbar(
        title,
        e.isEntitlementRequired
            ? 'تصدير PDF غير متاح في باقتك الحالية.'
            : e.message,
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _busy.value = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تقارير المشروع')),
      body: Obx(() => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _ReportCard(
                icon: Icons.analytics_outlined,
                title: 'التقرير الشامل',
                description:
                    'خطة استراتيجية مترابطة مبنية على كل ما أنجزته في الأدوات.',
                viewBusy: _busy.value == 'report',
                pdfBusy: _busy.value == 'report_pdf',
                onView: _viewReport,
                onPdf: () => _exportPdf(
                  key: 'report_pdf',
                  title: 'التقرير الشامل',
                  downloader: (ws) => _repo.reportPdf(ws, _projectId),
                  filename: 'report-$_projectId.pdf',
                ),
              ),
              const SizedBox(height: 10),
              _ReportCard(
                icon: Icons.folder_special_outlined,
                title: 'دليل المشروع',
                description:
                    'كل بياناتك وإجاباتك الخام مجمّعة في وثيقة واحدة قابلة للطباعة.',
                viewBusy: _busy.value == 'dossier',
                pdfBusy: _busy.value == 'dossier_pdf',
                onView: _viewDossier,
                onPdf: () => _exportPdf(
                  key: 'dossier_pdf',
                  title: 'دليل المشروع',
                  downloader: (ws) => _repo.dossierPdf(ws, _projectId),
                  filename: 'dossier-$_projectId.pdf',
                ),
              ),
            ],
          )),
    );
  }
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.viewBusy,
    required this.pdfBusy,
    required this.onView,
    required this.onPdf,
  });

  final IconData icon;
  final String title;
  final String description;
  final bool viewBusy;
  final bool pdfBusy;
  final VoidCallback onView;
  final VoidCallback onPdf;

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
                Icon(icon, color: theme.colorScheme.primary),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(title,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w800)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(description, style: theme.textTheme.bodyMedium),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: FilledButton.tonalIcon(
                    onPressed: viewBusy ? null : onView,
                    icon: viewBusy
                        ? const SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.visibility_outlined, size: 18),
                    label: const Text('عرض'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: pdfBusy ? null : onPdf,
                    icon: pdfBusy
                        ? const SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.picture_as_pdf_outlined, size: 18),
                    label: const Text('PDF'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// كتلة عرض واحدة داخل الوثيقة: نص أو جدول أو مؤشر نسبة.
class _DocBlock {
  const _DocBlock.text(this.title, this.text)
      : tableHeaders = null,
        tableRows = null,
        percent = null;

  const _DocBlock.table(this.title, this.tableHeaders, this.tableRows)
      : text = null,
        percent = null;

  const _DocBlock.metric(this.title, this.percent)
      : text = null,
        tableHeaders = null,
        tableRows = null;

  final String title;
  final String? text;
  final List<String>? tableHeaders;
  final List<List<String>>? tableRows;
  final int? percent;
}

/// عرض وثيقة JSON (تقرير/دليل) بعناوين معرّبة، مؤشرات، جداول،
/// والمحتوى كاملاً دون أي اقتصاص.
class _DocumentView extends StatelessWidget {
  const _DocumentView({required this.title, required this.document});

  final String title;
  final Map<String, dynamic> document;

  /// المفاتيح الرقمية التي تُعرض كمؤشر نسبة مئوية.
  static const _percentKeys = {
    'completion',
    'avg_quality',
    'content_quality',
    'completeness',
    'executive_score',
    'score',
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final blocks = _flatten(document);
    final metrics = blocks.where((b) => b.percent != null).toList();
    final content = blocks.where((b) => b.percent == null).toList();

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: blocks.isEmpty
          ? Center(
              child: Text('لا يوجد محتوى بعد — أكمل بعض الأدوات أولاً.',
                  style: theme.textTheme.bodyMedium),
            )
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (metrics.isNotEmpty) ...[
                  _MetricsGrid(metrics: metrics),
                  const SizedBox(height: 18),
                ],
                for (final block in content)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (block.title.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 4),
                            child: Text(block.title,
                                style: theme.textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                    color: theme.colorScheme.primary)),
                          ),
                        if (block.text != null)
                          SelectableText(block.text!,
                              style: theme.textTheme.bodyMedium
                                  ?.copyWith(height: 1.7)),
                        if (block.tableRows != null)
                          _DocTable(
                            headers: block.tableHeaders!,
                            rows: block.tableRows!,
                          ),
                      ],
                    ),
                  ),
              ],
            ),
    );
  }

  /// يسطّح الوثيقة إلى كتل عرض — يعرّب العناوين ويخفي الحقول التقنية
  /// ويحوّل قوائم العناصر المتجانسة إلى جداول.
  List<_DocBlock> _flatten(Map<String, dynamic> doc, [String prefix = '']) {
    final blocks = <_DocBlock>[];
    doc.forEach((key, value) {
      if (key.startsWith('_') || key == 'meta' || ArLabels.isHidden(key)) {
        return;
      }
      final label = ArLabels.of(key);

      if (value is num && _percentKeys.contains(key)) {
        blocks.add(_DocBlock.metric(label, value.clamp(0, 100).toInt()));
      } else if (value is String && value.trim().isNotEmpty) {
        blocks.add(_DocBlock.text(label, ArLabels.value(value.trim())));
      } else if (value is num) {
        blocks.add(_DocBlock.text(label, value.toString()));
      } else if (value is bool) {
        blocks.add(_DocBlock.text(label, value ? 'نعم' : 'لا'));
      } else if (value is List && value.isNotEmpty) {
        final table = _tryTable(label, value);
        if (table != null) {
          blocks.add(table);
        } else {
          final text = value
              .map((e) => e is Map
                  ? _flatten(Map<String, dynamic>.from(e))
                      .map((b) {
                        final v = b.percent != null ? '${b.percent}%' : b.text;
                        if (v == null || v.trim().isEmpty) return '';
                        return b.title.isEmpty ? v : '${b.title}: $v';
                      })
                      .where((s) => s.trim().isNotEmpty)
                      .join('\n')
                  : '• ${ArLabels.value(e.toString())}')
              .where((s) => s.trim().isNotEmpty)
              .join('\n');
          if (text.isNotEmpty) blocks.add(_DocBlock.text(label, text));
        }
      } else if (value is Map && value.isNotEmpty) {
        blocks.addAll(_flatten(Map<String, dynamic>.from(value), label));
      }
    });
    return blocks;
  }

  /// قائمة من عناصر Map متجانسة المفاتيح وقيمها بسيطة → جدول.
  _DocBlock? _tryTable(String label, List<dynamic> value) {
    if (value.length < 2 || !value.every((e) => e is Map)) return null;

    final maps = value.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    // مفاتيح مشتركة بقيم بسيطة فقط (نص/رقم/منطقي).
    final keys = maps.first.keys
        .where((k) =>
            !k.startsWith('_') &&
            !ArLabels.isHidden(k) &&
            maps.every((m) =>
                m[k] == null || m[k] is String || m[k] is num || m[k] is bool))
        .toList();
    if (keys.length < 2) return null;

    final rows = maps
        .map((m) => keys
            .map((k) => switch (m[k]) {
                  null => '—',
                  final bool b => b ? 'نعم' : 'لا',
                  final v => ArLabels.value(v.toString()),
                })
            .toList())
        .toList();

    return _DocBlock.table(
      label,
      keys.map(ArLabels.of).toList(),
      rows,
    );
  }
}

/// شبكة مؤشرات النسب — رسم تقدم دائري مبسط لكل مؤشر.
class _MetricsGrid extends StatelessWidget {
  const _MetricsGrid({required this.metrics});

  final List<_DocBlock> metrics;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Wrap(
          spacing: 16,
          runSpacing: 14,
          children: [
            for (final m in metrics)
              SizedBox(
                width: 140,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        SizedBox(
                          width: 34,
                          height: 34,
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              CircularProgressIndicator(
                                value: (m.percent ?? 0) / 100,
                                strokeWidth: 4,
                                backgroundColor: theme
                                    .colorScheme.surfaceContainerHighest,
                              ),
                              Text('${m.percent}',
                                  style: theme.textTheme.labelSmall?.copyWith(
                                      fontWeight: FontWeight.w800)),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(m.title,
                              style: theme.textTheme.labelMedium
                                  ?.copyWith(fontWeight: FontWeight.w700)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// جدول وثيقة قابل للتمرير أفقياً — يعرض كل الأعمدة دون اقتصاص.
class _DocTable extends StatelessWidget {
  const _DocTable({required this.headers, required this.rows});

  final List<String> headers;
  final List<List<String>> rows;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        headingRowHeight: 40,
        dataRowMinHeight: 36,
        dataRowMaxHeight: double.infinity,
        headingTextStyle: theme.textTheme.labelMedium?.copyWith(
          fontWeight: FontWeight.w800,
          color: theme.colorScheme.primary,
        ),
        columns: [
          for (final h in headers) DataColumn(label: Text(h)),
        ],
        rows: [
          for (final r in rows)
            DataRow(cells: [
              for (final cell in r)
                DataCell(ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 260),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Text(cell, softWrap: true),
                  ),
                )),
            ]),
        ],
      ),
    );
  }
}
