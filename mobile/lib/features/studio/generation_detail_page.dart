import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import 'generation_detail_controller.dart';

class GenerationDetailPage extends StatelessWidget {
  const GenerationDetailPage({super.key});

  @override
  Widget build(BuildContext context) {
    final publicId = Get.arguments as String;
    final c = Get.put(
      GenerationDetailController(
        Get.find<StudioRepository>(),
        Get.find<WorkspaceService>(),
        publicId,
      ),
      tag: publicId,
    );
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('المخرج'),
        actions: [
          Obx(() => PopupMenuButton<String>(
                enabled: !c.isExporting.value && c.generation.value != null,
                icon: c.isExporting.value
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2.2))
                    : const Icon(Icons.ios_share),
                onSelected: (format) => _export(context, c, format),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'md', child: Text('تصدير Markdown')),
                  PopupMenuItem(value: 'html', child: Text('تصدير HTML')),
                  PopupMenuItem(value: 'pdf', child: Text('تصدير PDF')),
                ],
              )),
        ],
      ),
      body: Obx(() {
        if (c.isLoading.value && c.generation.value == null) {
          return AppStateView.loading();
        }
        if (c.error.value != null && c.generation.value == null) {
          return AppStateView.error(message: c.error.value, onRetry: c.load);
        }
        final g = c.generation.value;
        if (g == null) {
          return AppStateView.empty(
              icon: Icons.description_outlined, title: 'المخرج غير متاح');
        }
        if (g.isFailed) {
          return AppStateView.error(
            title: 'فشل التوليد',
            message: g.error ?? 'حدث خطأ أثناء التوليد.',
            onRetry: c.load,
          );
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (g.templateName != null)
              Text(g.templateName!,
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            SelectableText(
              g.output ?? 'لا يوجد محتوى.',
              style: theme.textTheme.bodyLarge?.copyWith(height: 1.7),
            ),
          ],
        );
      }),
    );
  }

  Future<void> _export(
      BuildContext context, GenerationDetailController c, String format) async {
    final err = await c.export(format);
    if (err != null) {
      Get.snackbar('التصدير', err,
          snackPosition: SnackPosition.BOTTOM);
    }
  }
}
