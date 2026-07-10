import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
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

/// عرض هادئ لوثيقة JSON (تقرير/دليل): يعرض الأقسام النصية فقط دون زحمة تقنية.
class _DocumentView extends StatelessWidget {
  const _DocumentView({required this.title, required this.document});

  final String title;
  final Map<String, dynamic> document;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final blocks = _flatten(document);
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: blocks.isEmpty
          ? Center(
              child: Text('لا يوجد محتوى بعد — أكمل بعض الأدوات أولاً.',
                  style: theme.textTheme.bodyMedium),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: blocks.length,
              itemBuilder: (_, i) {
                final block = blocks[i];
                return Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (block.$1.isNotEmpty)
                        Text(block.$1,
                            style: theme.textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                                color: theme.colorScheme.primary)),
                      const SizedBox(height: 4),
                      SelectableText(block.$2,
                          style: theme.textTheme.bodyMedium
                              ?.copyWith(height: 1.7)),
                    ],
                  ),
                );
              },
            ),
    );
  }

  /// يسطّح الوثيقة إلى أزواج (عنوان، نص) — يتجاهل الحقول التقنية والفارغة.
  List<(String, String)> _flatten(Map<String, dynamic> doc,
      [String prefix = '']) {
    final blocks = <(String, String)>[];
    doc.forEach((key, value) {
      if (key.startsWith('_') || key == 'meta') return;
      final label = _humanize(key);
      if (value is String && value.trim().isNotEmpty) {
        blocks.add((label, value.trim()));
      } else if (value is num || value is bool) {
        blocks.add((label, value.toString()));
      } else if (value is List && value.isNotEmpty) {
        final text = value
            .map((e) => e is Map
                ? _flatten(Map<String, dynamic>.from(e))
                    .map((b) => b.$1.isEmpty ? b.$2 : '${b.$1}: ${b.$2}')
                    .join('\n')
                : '• $e')
            .where((s) => s.trim().isNotEmpty)
            .join('\n');
        if (text.isNotEmpty) blocks.add((label, text));
      } else if (value is Map && value.isNotEmpty) {
        blocks.addAll(_flatten(Map<String, dynamic>.from(value), label));
      }
    });
    return blocks;
  }

  String _humanize(String key) => key.replaceAll('_', ' ');
}
