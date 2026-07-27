import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/widgets/common.dart';

class LegalScreen extends StatefulWidget {
  const LegalScreen({
    super.key,
    required this.repository,
    required this.page,
    required this.fallbackTitle,
  });

  final PlatformRepository repository;
  final String page;
  final String fallbackTitle;

  @override
  State<LegalScreen> createState() => _LegalScreenState();
}

class _LegalScreenState extends State<LegalScreen> {
  late Future<Map<String, dynamic>> _future = widget.repository.legalPage(
    widget.page,
  );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.fallbackTitle)),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: () => setState(
            () => _future = widget.repository.legalPage(widget.page),
          ),
          builder: (data) {
            final sections = (data['sections'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item))
                .toList();

            return ListView(
              padding: const EdgeInsets.all(18),
              children: [
                Text(
                  data['title']?.toString() ?? widget.fallbackTitle,
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (data['updated_at'] != null) ...[
                  const SizedBox(height: 6),
                  Text('آخر تحديث: ${data['updated_at']}'),
                ],
                const SizedBox(height: 18),
                for (final section in sections) ...[
                  BrandCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          section['title']?.toString() ?? '',
                          style: const TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(section['body']?.toString() ?? ''),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}
