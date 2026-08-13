import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class SharedReportScreen extends StatefulWidget {
  const SharedReportScreen({
    super.key,
    required this.repository,
    required this.token,
  });
  final PlatformRepository repository;
  final String token;

  @override
  State<SharedReportScreen> createState() => _SharedReportScreenState();
}

class _SharedReportScreenState extends State<SharedReportScreen> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .publicSharedReport(widget.token);
  bool _downloading = false;

  Future<void> _downloadPdf() async {
    setState(() => _downloading = true);
    try {
      final bytes = await widget.repository.publicSharedReportPdf(widget.token);
      final directory = await getTemporaryDirectory();
      final file = File('${directory.path}/khaled-saad-shared-report.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AdaptiveScaffold(
    family: AdaptivePageFamily.reading,
    appBar: AppBar(title: const Text('تقرير مشترك')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) => AsyncView(
        snapshot: snapshot,
        onRetry: () => setState(
          () => _future = widget.repository.publicSharedReport(widget.token),
        ),
        builder: (data) {
          final document = Map<String, dynamic>.from(
            data['document'] as Map? ?? data,
          );
          final entries = document.entries.where(
            (entry) => entry.value != null,
          );
          return ListView(
            padding: EdgeInsets.zero,
            children: [
              Text(
                document['title']?.toString() ?? 'التقرير',
                style: const TextStyle(
                  fontSize: 23,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _downloading ? null : _downloadPdf,
                icon: const Icon(Icons.picture_as_pdf_outlined),
                label: Text(_downloading ? 'جارٍ التنزيل…' : 'تنزيل PDF'),
              ),
              const SizedBox(height: 16),
              for (final entry in entries)
                if (entry.key != 'title') ...[
                  BrandCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _label(entry.key),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 7),
                        Text(
                          _render(entry.value),
                          style: const TextStyle(color: BrandColors.muted),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
            ],
          );
        },
      ),
    ),
  );

  String _render(dynamic value) {
    if (value is List) return value.map(_render).join('\n\n');
    if (value is Map) {
      return value.entries
          .map(
            (entry) =>
                '${_label(entry.key.toString())}: ${_render(entry.value)}',
          )
          .join('\n');
    }
    return value.toString();
  }

  String _label(String key) =>
      const {
        'executive_summary': 'الخلاصة التنفيذية',
        'tools': 'نتائج التشخيصات',
        'priorities': 'الأولويات',
        'roadmap': 'خطة التنفيذ',
        'readiness': 'الجاهزية',
        'decision_card': 'بطاقة القرار',
        'snapshot': 'البيانات',
      }[key] ??
      key.replaceAll('_', ' ');
}
