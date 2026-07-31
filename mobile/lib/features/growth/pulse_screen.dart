import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// نبض النمو الأسبوعي كوجهة مستقلة على مستوى مساحة العمل — نظير
/// `views/app/pulse/index.blade.php`. النشرات كلها في مكان واحد بدل أن تكون
/// مدفونة داخل مركز نمو مشروع بعينه.
class PulseScreen extends StatefulWidget {
  const PulseScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<PulseScreen> createState() => _PulseScreenState();
}

class _PulseScreenState extends State<PulseScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.repository.pulse();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('نبض النمو الأسبوعي')),
      body: RefreshIndicator(
        onRefresh: () async => setState(_load),
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) => AsyncView(
            snapshot: snapshot,
            onRetry: () => setState(_load),
            builder: _body,
          ),
        ),
      ),
    );
  }

  Widget _body(List<Map<String, dynamic>> items) {
    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          const Text(
            'نشرة أسبوعية تلخّص ما تحرّك في نشاطك وما يستحق انتباهك.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 12),
          if (items.isEmpty)
            const EmptyState(
              title: 'لا نشرات بعد',
              message: 'تظهر أول نشرة بعد أسبوع من نشاطك على المنصة.',
            )
          else
            for (final item in items) _pulseCard(item),
        ],
      ),
    );
  }

  Widget _pulseCard(Map<String, dynamic> item) {
    final title = item['title']?.toString() ??
        item['week']?.toString() ??
        'نشرة أسبوعية';
    final body = item['summary']?.toString() ?? item['body']?.toString();
    final highlights = (item['highlights'] as List? ?? const [])
        .map((e) => e.toString())
        .toList();

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: BrandColors.navy,
              ),
            ),
            if (body != null && body.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(body, style: const TextStyle(color: BrandColors.ink)),
            ],
            if (highlights.isNotEmpty) ...[
              const SizedBox(height: 8),
              for (final highlight in highlights)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    '• $highlight',
                    style: const TextStyle(color: BrandColors.muted, fontSize: 13),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
