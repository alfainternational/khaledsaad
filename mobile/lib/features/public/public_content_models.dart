class PublicContentSummary {
  const PublicContentSummary({
    required this.id,
    required this.slug,
    required this.type,
    required this.title,
    required this.excerpt,
    required this.locked,
    this.coverImageUrl,
    this.durationMinutes,
    this.categoryName,
    this.publishedAt,
  });

  final int id;
  final String slug;
  final String type;
  final String title;
  final String excerpt;
  final bool locked;
  final String? coverImageUrl;
  final int? durationMinutes;
  final String? categoryName;
  final DateTime? publishedAt;

  String get typeLabel => switch (type) {
    'lesson' => 'درس',
    'lecture' => 'محاضرة',
    'course' => 'دورة',
    _ => 'مقال',
  };

  factory PublicContentSummary.fromJson(
    Map<String, dynamic> json, {
    String? siteBaseUrl,
  }) {
    final category = json['category'] is Map
        ? Map<String, dynamic>.from(json['category'] as Map)
        : const <String, dynamic>{};

    return PublicContentSummary(
      id: (json['id'] as num?)?.toInt() ?? 0,
      slug: json['slug']?.toString() ?? '',
      type: json['type']?.toString() ?? 'article',
      title: json['title']?.toString() ?? '',
      excerpt: json['excerpt']?.toString() ?? '',
      locked: json['locked'] == true,
      coverImageUrl: resolvePublicAssetUrl(
        json['cover_image_url']?.toString(),
        siteBaseUrl: siteBaseUrl,
      ),
      durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
      categoryName: category['name']?.toString(),
      publishedAt: DateTime.tryParse(json['published_at']?.toString() ?? ''),
    );
  }
}

class PublicContentDetail {
  const PublicContentDetail({
    required this.summary,
    required this.locked,
    this.bodyHtml,
    this.videoUrl,
  });

  final PublicContentSummary summary;
  final bool locked;
  final String? bodyHtml;
  final String? videoUrl;

  factory PublicContentDetail.fromJson(
    Map<String, dynamic> json, {
    String? siteBaseUrl,
  }) => PublicContentDetail(
    summary: PublicContentSummary.fromJson(json, siteBaseUrl: siteBaseUrl),
    locked: json['locked'] == true,
    bodyHtml: json['body_html']?.toString(),
    videoUrl: json['video_url']?.toString(),
  );
}

String? resolvePublicAssetUrl(String? value, {String? siteBaseUrl}) {
  if (value == null || value.trim().isEmpty) return null;
  final raw = value.trim();
  final parsed = Uri.tryParse(raw);
  if (parsed != null && parsed.hasScheme) return raw;
  if (siteBaseUrl == null || siteBaseUrl.isEmpty) return raw;

  final base = Uri.parse(
    siteBaseUrl.endsWith('/') ? siteBaseUrl : '$siteBaseUrl/',
  );
  return base.resolve(raw.startsWith('/') ? raw.substring(1) : raw).toString();
}
