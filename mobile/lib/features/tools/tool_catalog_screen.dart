import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// يقابل resources/views/app/tools/index.blade.php
class ToolCatalogScreen extends StatefulWidget {
  const ToolCatalogScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<ToolCatalogScreen> createState() => _ToolCatalogScreenState();
}

class _ToolCatalogScreenState extends State<ToolCatalogScreen> {
  late Future<List<ToolCard>> _future = widget.repository.tools();

  void _reload() => setState(() => _future = widget.repository.tools());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('التشخيصات')),
      body: FutureBuilder<List<ToolCard>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (tools) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const Text(
                'اختر ما تريد تشخيصه الآن',
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              const Text(
                'اختر التحدي الأقرب إلى مشروعك. ستُستخدم معلوماتك المحفوظة لتقليل الأسئلة المتكررة.',
                style: TextStyle(color: BrandColors.muted),
              ),
              const SizedBox(height: 18),

              for (final tool in tools) ...[
                BrandCard(
                  muted: !tool.isRunnable,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Eyebrow(tool.category)),
                          SeverityBadge(
                            label: tool.statusLabel,
                            severity: tool.isRunnable ? 'low' : 'assumption',
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        tool.title,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        tool.description,
                        style: const TextStyle(
                          color: BrandColors.muted,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
