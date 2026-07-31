import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'admin_consultation_version_screen.dart';

/// محرّر مخططات الاستشارة للآدمن — نظير `views/admin/consultations/index.blade.php`.
///
/// المسودة تُحرَّر والمنشور مقفل. من هنا: استعراض المخططات وإصداراتها، وإنشاء
/// مسودة مستقلة، والدخول لتحرير أسئلة إصدار.
class AdminConsultationsScreen extends StatefulWidget {
  const AdminConsultationsScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<AdminConsultationsScreen> createState() =>
      _AdminConsultationsScreenState();
}

class _AdminConsultationsScreenState extends State<AdminConsultationsScreen> {
  late Future<List<dynamic>> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.repository.adminConsultations();
  }

  Future<void> _createDraft(int blueprintId) async {
    setState(() => _busy = true);
    try {
      final draft = await widget.repository.adminCreateConsultationDraft(
        blueprintId,
      );
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AdminConsultationVersionScreen(
            repository: widget.repository,
            versionId: draft['id'] as int,
          ),
        ),
      );
      setState(_load);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مخططات الاستشارة')),
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

  Widget _body(List<Map<String, dynamic>> blueprints) {
    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          const Text(
            'المسودة تُحرَّر والإصدار المنشور مقفل ضد التعديل حتى لا تتغيّر أسئلة '
            'تحت من يجيب عليها.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 12),
          if (blueprints.isEmpty)
            const EmptyState(
              title: 'لا مخططات',
              message: 'لا توجد مخططات استشارة بعد.',
            )
          else
            for (final blueprint in blueprints) _blueprintCard(blueprint),
        ],
      ),
    );
  }

  Widget _blueprintCard(Map<String, dynamic> blueprint) {
    final versions = (blueprint['versions'] as List? ?? const [])
        .cast<Map<String, dynamic>>();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    blueprint['name']?.toString() ?? '—',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: BrandColors.navy,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _createDraft(blueprint['id'] as int),
                  icon: const Icon(Icons.add, size: 18),
                  label: const Text('مسودة'),
                ),
              ],
            ),
            const SizedBox(height: 4),
            for (final version in versions) _versionRow(version),
          ],
        ),
      ),
    );
  }

  Widget _versionRow(Map<String, dynamic> version) {
    final isDraft = version['status'] == 'draft';

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text('الإصدار ${version['version']}'),
      subtitle: Text(
        isDraft ? 'مسودة قابلة للتحرير' : 'منشور مقفل',
        style: TextStyle(
          color: isDraft ? BrandColors.orange : BrandColors.success,
          fontSize: 12,
        ),
      ),
      trailing: Icon(
        version['is_current'] == true ? Icons.star : Icons.chevron_left,
        color: version['is_current'] == true
            ? BrandColors.orange
            : BrandColors.muted,
        size: 20,
      ),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AdminConsultationVersionScreen(
            repository: widget.repository,
            versionId: version['id'] as int,
          ),
        ),
      ),
    );
  }
}
