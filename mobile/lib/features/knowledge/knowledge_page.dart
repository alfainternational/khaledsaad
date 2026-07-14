import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/knowledge_models.dart';
import '../../data/repositories/knowledge_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// مصادر المعرفة: ارفع مستندات المشروع (عروض، أبحاث، ملفات) ليقرأها التحليل والذكاء.
class KnowledgePage extends StatefulWidget {
  const KnowledgePage({super.key});

  @override
  State<KnowledgePage> createState() => _KnowledgePageState();
}

class _KnowledgePageState extends State<KnowledgePage> {
  late final String _projectId = Get.arguments as String;
  late final KnowledgeRepository _repo = Get.find<KnowledgeRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  static const _allowedExtensions = [
    'txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm',
    'pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff',
  ];

  final _uploads = <KnowledgeUpload>[].obs;
  final _loading = true.obs;
  final _uploading = false.obs;
  final _error = RxnString();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      _uploads.assignAll(await _repo.list(ws, _projectId));
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _pickAndUpload() async {
    final ws = _workspaces.activeId;
    if (ws == null || _uploading.value) return;

    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: _allowedExtensions,
      withData: false,
    );
    final path = result?.files.single.path;
    if (path == null) return;

    _uploading.value = true;
    try {
      final uploaded = await _repo.upload(
        ws,
        _projectId,
        filePath: path,
        filename: result!.files.single.name,
      );
      _uploads.insert(0, uploaded);
      UiFeedback.success('رُفع الملف. سيُقرأ في التحليل القادم.',
          title: 'مصادر المعرفة');
    } on ApiException catch (e) {
      final fieldError =
          e.errors.isNotEmpty ? e.errors.values.first.first : null;
      UiFeedback.error(fieldError ?? e.message, title: 'مصادر المعرفة');
    } finally {
      _uploading.value = false;
    }
  }

  Future<void> _retry(KnowledgeUpload item) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      final updated = await _repo.retry(ws, _projectId, item.publicId);
      final i = _uploads.indexWhere((u) => u.publicId == item.publicId);
      if (i >= 0) _uploads[i] = updated;
      UiFeedback.success('أُعيدت المعالجة.', title: 'مصادر المعرفة');
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'مصادر المعرفة');
    }
  }

  Future<void> _delete(KnowledgeUpload item) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('حذف المستند'),
        content: Text('هل تريد حذف «${item.originalName}»؟'),
        actions: [
          TextButton(
              onPressed: () => Get.back(result: false),
              child: const Text('إلغاء')),
          FilledButton(
              onPressed: () => Get.back(result: true), child: const Text('حذف')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _repo.remove(ws, _projectId, item.publicId);
      _uploads.removeWhere((u) => u.publicId == item.publicId);
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'مصادر المعرفة');
    }
  }

  Color _statusColor(KnowledgeUpload u, ThemeData theme) {
    if (u.isFailed) return theme.colorScheme.error;
    if (u.isReady) return theme.colorScheme.primary;
    return theme.colorScheme.tertiary;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('مصادر المعرفة')),
      floatingActionButton: Obx(() => FloatingActionButton.extended(
            onPressed: _uploading.value ? null : _pickAndUpload,
            icon: _uploading.value
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2))
                : const Icon(Icons.upload_file_outlined),
            label: Text(_uploading.value ? 'جارٍ الرفع...' : 'ارفع مستنداً'),
          )),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        if (_uploads.isEmpty) {
          return AppStateView.empty(
            icon: Icons.folder_open_outlined,
            title: 'لا مستندات بعد',
            message:
                'ارفع عروضك وأبحاثك وملفاتك ليقرأها التحليل ويضمّنها في تقاريرك.',
          );
        }
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: _uploads.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (_, i) {
              final u = _uploads[i];
              return Card(
                child: ListTile(
                  leading: Icon(Icons.description_outlined,
                      color: _statusColor(u, theme)),
                  title: Text(u.originalName,
                      maxLines: 1, overflow: TextOverflow.ellipsis),
                  subtitle: Text([
                    u.statusLabel,
                    if (u.sizeLabel.isNotEmpty) u.sizeLabel,
                    if (u.isFailed && u.errorCode != null) u.errorCode!,
                  ].join(' · ')),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (u.isProcessing)
                        const SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(strokeWidth: 2)),
                      if (u.isFailed)
                        IconButton(
                          tooltip: 'إعادة المعالجة',
                          icon: const Icon(Icons.refresh),
                          onPressed: () => _retry(u),
                        ),
                      IconButton(
                        tooltip: 'حذف',
                        icon: const Icon(Icons.delete_outline),
                        onPressed: () => _delete(u),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      }),
    );
  }
}
