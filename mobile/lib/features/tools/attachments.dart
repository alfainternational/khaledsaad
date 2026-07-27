/// نماذج المرفقات والمنافسين — تقرأ حمولات الخادم حرفيًا.
library;

class RunFile {
  const RunFile({
    required this.id,
    required this.name,
    required this.sizeKb,
    required this.status,
    required this.statusLabel,
  });

  factory RunFile.fromJson(Map<String, dynamic> json) => RunFile(
    id: json['id'] as int,
    name: json['name'] as String,
    sizeKb: json['size_kb'] as int? ?? 0,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String? ?? '',
  );

  final int id;
  final String name;
  final int sizeKb;
  final String status;
  final String statusLabel;
}

class Competitor {
  const Competitor({
    required this.id,
    required this.name,
    required this.tier,
    required this.tierLabel,
    required this.source,
    this.url,
  });

  factory Competitor.fromJson(Map<String, dynamic> json) => Competitor(
    id: json['id'] as int,
    name: json['name'] as String,
    tier: json['tier'] as String? ?? 'global',
    tierLabel: json['tier_label'] as String? ?? '',
    source: json['source'] as String? ?? '',
    url: json['url'] as String?,
  );

  final int id;
  final String name;
  final String tier;
  final String tierLabel;
  final String source;
  final String? url;
}

class CompetitorView {
  const CompetitorView({
    required this.confirmed,
    required this.candidates,
    required this.hasLocal,
  });

  factory CompetitorView.fromJson(Map<String, dynamic> json) => CompetitorView(
    confirmed: (json['confirmed'] as List? ?? const [])
        .map((e) => Competitor.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
    candidates: (json['candidates'] as List? ?? const [])
        .map((e) => Competitor.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
    hasLocal: json['has_local'] as bool? ?? false,
  );

  final List<Competitor> confirmed;
  final List<Competitor> candidates;
  final bool hasLocal;
}
