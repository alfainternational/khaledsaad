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
      _tools.assignAll(await _repo.listTools(ws));
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  void _openTool(ToolListItem tool) {
    Get.toNamed(Routes.toolRunner, arguments: {
      'project_public_id': _projectId,
      'tool_code': tool.code,
      'tool_name': tool.name,
    });
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
              icon: Icons.build_outlined, title: 'لا توجد أدوات متاحة');
        }

        // تجميع حسب المرحلة مع الحفاظ على الترتيب.
        final byStage = <int, List<ToolListItem>>{};
        for (final tool in _tools) {
          byStage.putIfAbsent(tool.stage ?? 0, () => []).add(tool);
        }
        final stages = byStage.keys.toList()..sort();

        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              for (final stage in stages) ...[
                Padding(
                  padding: const EdgeInsets.only(bottom: 8, top: 8),
                  child: Text(
                    _stageLabels[stage] ?? 'أدوات أخرى',
                    style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: theme.colorScheme.primary),
                  ),
                ),
                ...byStage[stage]!.map((tool) => Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text(tool.name),
                        subtitle: tool.estimatedMinutes != null
                            ? Text('نحو ${tool.estimatedMinutes} دقيقة')
                            : null,
                        trailing: const Icon(Icons.chevron_left),
                        onTap: () => _openTool(tool),
                      ),
                    )),
              ],
            ],
          ),
        );
      }),
    );
  }
}
