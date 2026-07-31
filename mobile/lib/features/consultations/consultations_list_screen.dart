import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'consultation_screen.dart';

/// قائمة الاستشارات: كل مشاريع المستخدم بأحدث جلسة لكلٍّ — نظير
/// `views/app/consultations/index.blade.php`. مدخل واحد للتشخيص الذكي بدل
/// الدخول من كل مشروع على حدة.
class ConsultationsListScreen extends StatefulWidget {
  const ConsultationsListScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<ConsultationsListScreen> createState() =>
      _ConsultationsListScreenState();
}

class _ConsultationsListScreenState extends State<ConsultationsListScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.repository.consultationsList();
  }

  static const _statusLabels = {
    'in_progress': 'قيد الإجابة',
    'awaiting_review': 'بانتظار مراجعتك',
    'analyzing': 'يُحلّل الآن',
    'completed': 'مكتملة',
    'failed': 'تعذّرت',
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الاستشارات')),
      body: RefreshIndicator(
        onRefresh: () async => setState(_load),
        child: FutureBuilder<List<dynamic>>(
          future: _future,
          builder: (context, snapshot) => AsyncView(
            snapshot: snapshot,
            onRetry: () => setState(_load),
            builder: (data) => _body(data.cast<Map<String, dynamic>>()),
          ),
        ),
      ),
    );
  }

  Widget _body(List<Map<String, dynamic>> projects) {
    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          const Text(
            'التشخيص الذكي الشامل: جلسة أسئلة تتكيّف مع نشاطك ثم تبني تقريرًا موحّدًا.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 12),
          if (projects.isEmpty)
            const EmptyState(
              title: 'لا مشاريع بعد',
              message: 'أضف مشروعًا لتبدأ استشارته.',
            )
          else
            for (final project in projects) _projectRow(project),
        ],
      ),
    );
  }

  Widget _projectRow(Map<String, dynamic> project) {
    final consultation = project['consultation'] as Map<String, dynamic>?;
    final status = consultation?['status']?.toString();
    final label = status == null
        ? 'لم تبدأ بعد'
        : (_statusLabels[status] ?? status);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BrandCard(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ConsultationScreen(
              repository: widget.repository,
              projectSlug: project['slug'].toString(),
            ),
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    project['name']?.toString() ?? '—',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: BrandColors.navy,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    label,
                    style: const TextStyle(
                      color: BrandColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              status == 'completed'
                  ? Icons.check_circle_outline
                  : Icons.chevron_left,
              color: status == 'completed'
                  ? BrandColors.success
                  : BrandColors.muted,
            ),
          ],
        ),
      ),
    );
  }
}
