import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../tools/models.dart';

class PublicToolScreen extends StatefulWidget {
  const PublicToolScreen({
    super.key,
    required this.repository,
    required this.toolKey,
    required this.onStart,
    required this.onLogin,
  });

  final PlatformRepository repository;
  final String toolKey;
  final ValueChanged<ToolCard> onStart;
  final VoidCallback onLogin;

  @override
  State<PublicToolScreen> createState() => _PublicToolScreenState();
}

class _PublicToolScreenState extends State<PublicToolScreen> {
  late Future<ToolDetail> _future = widget.repository.tool(widget.toolKey);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('تفاصيل التشخيص')),
    body: FutureBuilder<ToolDetail>(
      future: _future,
      builder: (context, snapshot) => AsyncView(
        snapshot: snapshot,
        onRetry: () =>
            setState(() => _future = widget.repository.tool(widget.toolKey)),
        builder: (tool) => ListView(
          padding: const EdgeInsets.all(18),
          children: [
            Eyebrow(tool.card.category),
            const SizedBox(height: 6),
            Text(
              tool.card.title,
              style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            if (tool.card.pain != null)
              Text('«${tool.card.pain}»', style: const TextStyle(fontSize: 17)),
            const SizedBox(height: 12),
            Text(
              tool.card.headline,
              style: const TextStyle(color: BrandColors.muted),
            ),
            const SizedBox(height: 18),
            BrandCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'ما الذي ستجيب عنه؟',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  for (final item in tool.inputs) Text('• $item'),
                  const SizedBox(height: 16),
                  const Text(
                    'ما الذي ستحصل عليه؟',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  for (final item in tool.outputs) Text('• $item'),
                ],
              ),
            ),
            const SizedBox(height: 18),
            if (tool.card.isRunnable)
              FilledButton(
                onPressed: () {
                  Navigator.pop(context);
                  widget.onStart(tool.card);
                },
                child: const Text('جرّبها بلا حساب'),
              )
            else
              const ErrorNotice(
                message:
                    'هذا التشخيص غير متاح حاليًا. اختر تشخيصًا متاحًا للبدء.',
              ),
            TextButton(
              onPressed: widget.onLogin,
              child: const Text('لديك حساب؟ سجّل الدخول'),
            ),
          ],
        ),
      ),
    ),
  );
}
