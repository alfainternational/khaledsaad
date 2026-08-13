import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../tools/attachments.dart';

/// إدارة المنافسين من داخل التقرير — نظير قسم المنافسين في تقرير الويب.
/// يؤكّد مرشّحًا، يستبعده، أو يضيف منافسًا محليًا سمّاه بنفسه.
class CompetitorsCard extends StatefulWidget {
  const CompetitorsCard({
    super.key,
    required this.repository,
    required this.projectSlug,
  });

  final PlatformRepository repository;
  final String projectSlug;

  @override
  State<CompetitorsCard> createState() => _CompetitorsCardState();
}

class _CompetitorsCardState extends State<CompetitorsCard> {
  late Future<CompetitorView> _future = widget.repository.competitors(
    widget.projectSlug,
  );
  final _namesController = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _namesController.dispose();
    super.dispose();
  }

  Future<void> _run(Future<CompetitorView> Function() action) async {
    setState(() => _busy = true);

    try {
      final view = await action();
      setState(() => _future = Future.value(view));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<CompetitorView>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const SizedBox.shrink();
        }

        final view = snapshot.data!;

        return BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'منافسوك',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),

              if (view.confirmed.isNotEmpty) ...[
                const Eyebrow('مؤكّدون'),
                for (final competitor in view.confirmed)
                  _row(competitor, confirmed: true),
                const SizedBox(height: 8),
              ],

              if (view.candidates.isNotEmpty) ...[
                const Eyebrow('مرشّحون — أكّد من يخصّك'),
                for (final competitor in view.candidates)
                  _row(competitor, confirmed: false),
                const SizedBox(height: 8),
              ],

              if (!view.hasLocal)
                const Text(
                  'أضف منافسيك المحليين الذين تعرفهم — هم الأقرب أثرًا عليك.',
                  style: TextStyle(color: BrandColors.muted, fontSize: 13),
                ),

              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _namesController,
                      decoration: const InputDecoration(
                        hintText: 'اسم منافس أو أكثر، مفصولة بفاصلة',
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: _busy
                        ? null
                        : () {
                            final names = _namesController.text.trim();
                            if (names.isEmpty) return;
                            _namesController.clear();
                            _run(
                              () => widget.repository.addCompetitors(
                                widget.projectSlug,
                                names,
                              ),
                            );
                          },
                    child: const Text('أضف'),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _row(Competitor competitor, {required bool confirmed}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SeverityBadge(label: competitor.tierLabel, severity: 'low'),
          const SizedBox(width: 8),
          Expanded(child: Text(competitor.name)),
          if (confirmed)
            IconButton(
              tooltip: 'استبعاد',
              icon: const Icon(Icons.close, size: 18),
              onPressed: _busy
                  ? null
                  : () => _run(
                      () => widget.repository.dismissCompetitor(competitor.id),
                    ),
            )
          else ...[
            IconButton(
              tooltip: 'تأكيد',
              icon: const Icon(
                Icons.check,
                size: 18,
                color: BrandColors.success,
              ),
              onPressed: _busy
                  ? null
                  : () => _run(
                      () => widget.repository.confirmCompetitor(competitor.id),
                    ),
            ),
            IconButton(
              tooltip: 'استبعاد',
              icon: const Icon(Icons.close, size: 18),
              onPressed: _busy
                  ? null
                  : () => _run(
                      () => widget.repository.dismissCompetitor(competitor.id),
                    ),
            ),
          ],
        ],
      ),
    );
  }
}
