import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// محفظة الوكالة: كل أنشطة مساحة العمل بدرجاتها واتجاهها في شاشة واحدة.
///
/// نظير `resources/views/app/portfolio/index.blade.php`. الوكالة تفتحها صباح كل
/// اثنين لتقرر على أي عميل تصرف ساعاتها. لا حساب هنا — تُعرض أرقام الخادم كما هي.
class PortfolioScreen extends StatefulWidget {
  const PortfolioScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<PortfolioScreen> createState() => _PortfolioScreenState();
}

class _PortfolioScreenState extends State<PortfolioScreen> {
  late Future<Map<String, dynamic>> _portfolio;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _portfolio = widget.repository.portfolio();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('محفظة العملاء')),
      body: RefreshIndicator(
        onRefresh: () async => setState(_load),
        child: FutureBuilder<Map<String, dynamic>>(
          future: _portfolio,
          builder: (context, snapshot) => AsyncView(
            snapshot: snapshot,
            onRetry: () => setState(_load),
            builder: _body,
          ),
        ),
      ),
    );
  }

  Widget _body(Map<String, dynamic> data) {
    final projects = (data['projects'] as List? ?? const [])
        .cast<Map<String, dynamic>>();
    final summary = Map<String, dynamic>.from(data['summary'] as Map? ?? {});

    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          Eyebrow('${data['workspace']?['name'] ?? 'مساحة العمل'} · محفظة'),
          const SizedBox(height: 6),
          const Text(
            'كل نشاط بدرجته واتجاهه. الاتجاه لا يُعرض حين يكون سببه اتّساع القياس '
            'لا تغيّر النشاط.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 12),
          if (summary.isNotEmpty) _summaryCard(summary),
          const SizedBox(height: 12),
          if (projects.isEmpty)
            const EmptyState(
              title: 'لا أنشطة بعد',
              message: 'أضف نشاطًا أو شخّصه ليظهر في المحفظة.',
            )
          else
            for (final project in projects) _projectRow(project),
        ],
      ),
    );
  }

  Widget _summaryCard(Map<String, dynamic> summary) {
    final measured = summary['measured_count'] ?? summary['measured'] ?? 0;
    final total = summary['total'] ?? summary['count'] ?? 0;
    final avg = summary['average_score'] ?? summary['avg'];

    return BrandCard(
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _stat('$total', 'نشاط'),
          _stat('$measured', 'مقيس'),
          _stat(avg == null ? '—' : '$avg', 'متوسط الدرجة'),
        ],
      ),
    );
  }

  Widget _stat(String value, String label) => Column(
    children: [
      Text(
        value,
        style: const TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w800,
          color: BrandColors.navy,
        ),
      ),
      Text(label, style: const TextStyle(color: BrandColors.muted, fontSize: 12)),
    ],
  );

  Widget _projectRow(Map<String, dynamic> project) {
    final info = Map<String, dynamic>.from(project['project'] as Map? ?? {});
    final measured = project['measured'] == true;
    final score = project['maturity_score'];
    final coverage = project['score_coverage'];
    final trend = Map<String, dynamic>.from(project['trend'] as Map? ?? {});

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BrandCard(
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    info['name']?.toString() ?? '—',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: BrandColors.navy,
                    ),
                  ),
                  if (project['industry'] != null)
                    Text(
                      project['industry'].toString(),
                      style: const TextStyle(
                        color: BrandColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  const SizedBox(height: 4),
                  _trendLine(trend),
                ],
              ),
            ),
            const SizedBox(width: 12),
            // المحسوب من صفر محاور لا يُعرض رقمًا: «لم يُقَس» لا «فشل» (§٤.٣).
            if (measured && score != null)
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '$score',
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: BrandColors.blue,
                    ),
                  ),
                  if (coverage != null)
                    Text(
                      'تغطية ${(( (coverage as num) ) * 100).round()}٪',
                      style: const TextStyle(
                        color: BrandColors.muted,
                        fontSize: 11,
                      ),
                    ),
                ],
              )
            else
              const Text(
                'لم يُقَس بعد',
                style: TextStyle(color: BrandColors.muted, fontSize: 12),
              ),
          ],
        ),
      ),
    );
  }

  Widget _trendLine(Map<String, dynamic> trend) {
    final direction = trend['direction']?.toString() ?? 'unknown';
    final delta = trend['delta'];

    final (icon, color, text) = switch (direction) {
      'up' => (Icons.trending_up, BrandColors.success, 'صعود${delta != null ? ' +$delta' : ''}'),
      'down' => (Icons.trending_down, BrandColors.red, 'هبوط${delta != null ? ' $delta' : ''}'),
      'flat' => (Icons.trending_flat, BrandColors.muted, 'ثابت'),
      _ => (Icons.help_outline, BrandColors.muted, trend['reason']?.toString() ?? 'قياس واحد فقط'),
    };

    return Row(
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            text,
            style: TextStyle(color: color, fontSize: 12),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
