import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';

import '../../core/api/platform_repository.dart';
import '../../core/config/app_environment.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'public_content_models.dart';

class PublicContentScreen extends StatefulWidget {
  const PublicContentScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<PublicContentScreen> createState() => _PublicContentScreenState();
}

class _PublicContentScreenState extends State<PublicContentScreen> {
  late Future<List<PublicContentSummary>> _future = _load();

  Future<List<PublicContentSummary>> _load() async {
    final response = await widget.repository.publicContent();
    return (response['data'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) => PublicContentSummary.fromJson(
            Map<String, dynamic>.from(item),
            siteBaseUrl: _siteBaseUrl,
          ),
        )
        .toList();
  }

  Future<void> _refresh() async {
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) => AdaptivePage(
    family: AdaptivePageFamily.reading,
    padding: EdgeInsets.zero,
    child: FutureBuilder<List<PublicContentSummary>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Padding(
            padding: const EdgeInsets.all(16),
            child: ErrorNotice(
              message: 'تعذر تحميل مكتبة المحتوى الآن.',
              onRetry: _refresh,
            ),
          );
        }
        final items = snapshot.data ?? const [];
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            key: const PageStorageKey('public-content'),
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 100),
            children: [
              const Eyebrow('المكتبة المعرفية'),
              const SizedBox(height: 6),
              const Text(
                'مقالات ودروس تطبقها خطوة بخطوة',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              const Text(
                'نفس المحتوى المنشور في الويب، داخل تجربة قراءة أصلية في التطبيق.',
                style: TextStyle(color: BrandColors.muted),
              ),
              const SizedBox(height: 18),
              if (items.isEmpty)
                const EmptyState(
                  title: 'لا توجد مواد منشورة الآن',
                  message: 'ستظهر المقالات والدروس هنا فور نشرها.',
                )
              else
                for (final item in items) ...[
                  PublicContentCard(
                    item: item,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => PublicContentDetailScreen(
                          repository: widget.repository,
                          summary: item,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
            ],
          ),
        );
      },
    ),
  );
}

class PublicContentCard extends StatelessWidget {
  const PublicContentCard({super.key, required this.item, required this.onTap});

  final PublicContentSummary item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (item.coverImageUrl != null)
            AspectRatio(
              aspectRatio: 16 / 9,
              child: Image.network(
                item.coverImageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const ColoredBox(
                  color: BrandColors.navy,
                  child: Icon(Icons.menu_book, color: Colors.white, size: 44),
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (item.categoryName != null)
                  Text(
                    item.categoryName!,
                    style: const TextStyle(
                      color: BrandColors.blue,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                const SizedBox(height: 5),
                Text(
                  item.title,
                  style: const TextStyle(
                    color: BrandColors.navy,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (item.excerpt.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    item.excerpt,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: BrandColors.muted),
                  ),
                ],
                const SizedBox(height: 10),
                Text(
                  '${item.typeLabel}${item.durationMinutes == null ? '' : ' · ${item.durationMinutes} دقيقة'}',
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class PublicContentDetailScreen extends StatefulWidget {
  const PublicContentDetailScreen({
    super.key,
    required this.repository,
    required this.summary,
  });

  final PlatformRepository repository;
  final PublicContentSummary summary;

  @override
  State<PublicContentDetailScreen> createState() =>
      _PublicContentDetailScreenState();
}

class _PublicContentDetailScreenState extends State<PublicContentDetailScreen> {
  late Future<PublicContentDetail> _future = _load();

  Future<PublicContentDetail> _load() async {
    final json = await widget.repository.publicContentDetail(
      widget.summary.slug,
    );
    return PublicContentDetail.fromJson(json, siteBaseUrl: _siteBaseUrl);
  }

  @override
  Widget build(BuildContext context) => AdaptiveScaffold(
    family: AdaptivePageFamily.reading,
    appBar: AppBar(title: const Text('قراءة المحتوى')),
    body: FutureBuilder<PublicContentDetail>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return ErrorNotice(
            message: 'تعذر فتح المادة الآن.',
            onRetry: () => setState(() => _future = _load()),
          );
        }
        final detail = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.only(bottom: 40),
          children: [
            if (detail.summary.coverImageUrl != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: AspectRatio(
                  aspectRatio: 16 / 9,
                  child: Image.network(
                    detail.summary.coverImageUrl!,
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            const SizedBox(height: 18),
            Text(
              detail.summary.title,
              style: const TextStyle(
                color: BrandColors.navy,
                fontSize: 28,
                fontWeight: FontWeight.w800,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '${detail.summary.typeLabel}${detail.summary.durationMinutes == null ? '' : ' · ${detail.summary.durationMinutes} دقيقة'}',
              style: const TextStyle(color: BrandColors.muted),
            ),
            const SizedBox(height: 20),
            if (detail.locked || detail.bodyHtml == null)
              const EmptyState(
                title: 'هذه المادة للمشتركين',
                message: 'افتحها من حسابك المشترك للوصول إلى النص الكامل.',
              )
            else
              Html(
                data: detail.bodyHtml!,
                style: {
                  'body': Style(
                    margin: Margins.zero,
                    color: BrandColors.ink,
                    fontSize: FontSize(17),
                    lineHeight: const LineHeight(1.85),
                    direction: TextDirection.rtl,
                  ),
                  'h2': Style(
                    color: BrandColors.navy,
                    fontSize: FontSize(23),
                    fontWeight: FontWeight.w800,
                  ),
                  'h3': Style(
                    color: BrandColors.navy,
                    fontSize: FontSize(20),
                    fontWeight: FontWeight.w700,
                  ),
                  'img': Style(width: Width(100, Unit.percent)),
                },
              ),
          ],
        );
      },
    ),
  );
}

String get _siteBaseUrl {
  final api = Uri.parse(AppEnvironment.apiBaseUrl);
  final cleanPath = api.path.replaceFirst(RegExp(r'/api/?$'), '');
  return api.replace(path: cleanPath, query: '', fragment: '').toString();
}
