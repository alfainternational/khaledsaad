import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/tool_list_item.dart';
import '../../data/repositories/tool_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

/// أدوات المشروع مجمّعة حسب المرحلة — قائمة هادئة بأقسام واضحة.
class ProjectToolsPage extends StatefulWidget {
  const ProjectToolsPage({super.key});

  @override
  State<ProjectToolsPage> createState() => _ProjectToolsPageState();
}

class _ProjectToolsPageState extends State<ProjectToolsPage> {
  late final String _projectId = Get.arguments as String;
  late final ToolRepository _repo = Get.find<ToolRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _tools = <ToolListItem>[].obs;
  final _loading = true.obs;
  final _error = RxnString();

  static const _stageLabels = <int, String>{
    1: 'اكتشف مشروعك',
    2: 'ابنِ أساسك التسويقي',
    3: 'ابنِ عرضك',
    4: 'اجذب وحوّل',
    5: 'قِس ووسّع',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    _loading.value = true;
    _error.value = null;
    try {
      _tools.assignAll(await _repo.listTools(ws, projectPublicId: _projectId));
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  void _openTool(ToolListItem tool) {
    Get.toNamed(
      Routes.toolRunner,
      arguments: {
        'project_public_id': _projectId,
        'tool_code': tool.code,
        'tool_name': tool.name,
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الأدوات')),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        if (_tools.isEmpty) {
          return AppStateView.empty(
            icon: Icons.build_outlined,
            title: 'لا توجد أدوات متاحة',
          );
        }

        final recommended = _tools.where((tool) => tool.recommendedNow).toList()
          ..sort(_compareTools);

        // تجميع حسب المرحلة مع الحفاظ على الترتيب.
        final byStage = <int, List<ToolListItem>>{};
        for (final tool in _tools) {
          byStage.putIfAbsent(tool.stage ?? 0, () => []).add(tool);
        }
        for (final stageTools in byStage.values) {
          stageTools.sort(_compareTools);
        }
        final stages = byStage.keys.toList()..sort();

        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (recommended.isNotEmpty) ...[
                Text(
                  'ابدأ بهذه الآن',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                ...recommended.map(
                  (tool) => _ToolTile(tool: tool, onTap: () => _openTool(tool)),
                ),
                const SizedBox(height: 12),
              ],
              for (final stage in stages) ...[
                Padding(
                  padding: const EdgeInsets.only(bottom: 8, top: 8),
                  child: Text(
                    _stageLabels[stage] ?? 'أدوات أخرى',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: theme.colorScheme.primary,
                    ),
                  ),
                ),
                ...byStage[stage]!.map(
                  (tool) => _ToolTile(tool: tool, onTap: () => _openTool(tool)),
                ),
              ],
            ],
          ),
        );
      }),
    );
  }

  int _compareTools(ToolListItem a, ToolListItem b) {
    final recommended = (b.recommendedNow ? 1 : 0) - (a.recommendedNow ? 1 : 0);
    if (recommended != 0) return recommended;

    final completed =
        (a.completedInCurrentProject ? 1 : 0) -
        (b.completedInCurrentProject ? 1 : 0);
    if (completed != 0) return completed;

    final stage = (a.stage ?? 0).compareTo(b.stage ?? 0);
    if (stage != 0) return stage;

    return (a.sortOrder ?? 0).compareTo(b.sortOrder ?? 0);
  }
}

class _ToolTile extends StatelessWidget {
  const _ToolTile({required this.tool, required this.onTap});

  final ToolListItem tool;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final subtitleParts = <String>[
      if (tool.recommendedNow) 'الخطوة التالية',
      if (tool.completedInCurrentProject) 'مكتملة',
      if (!tool.completedInCurrentProject && tool.currentProjectRuns > 0)
        'قيد العمل',
      if (tool.estimatedMinutes != null) 'نحو ${tool.estimatedMinutes} دقيقة',
    ];

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: tool.recommendedNow
              ? theme.colorScheme.primary.withValues(alpha: 0.12)
              : theme.colorScheme.surfaceContainerHighest,
          child: Icon(
            tool.completedInCurrentProject
                ? Icons.check_circle_outline
                : tool.recommendedNow
                ? Icons.play_arrow_rounded
                : Icons.build_outlined,
            color: tool.recommendedNow
                ? theme.colorScheme.primary
                : theme.colorScheme.onSurfaceVariant,
          ),
        ),
        title: Text(tool.name),
        subtitle: subtitleParts.isNotEmpty
            ? Text(subtitleParts.join(' · '))
            : null,
        trailing: const Icon(Icons.chevron_left),
        enabled: tool.unlocked,
        onTap: tool.unlocked ? onTap : null,
      ),
    );
  }
}
